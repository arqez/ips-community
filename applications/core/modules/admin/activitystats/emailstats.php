<?php
/**
 * @brief		Email statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Oct 2018
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Email statistics
 */
class _emailstats extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emailstats_manage' );

		/* We can only view the stats if we have logging enabled */
		if( \IPS\Settings::i()->prune_log_emailstats == 0 )
		{
			\IPS\Output::i()->error( 'emaillogs_not_enabled', '1C395/1', 403, '' );
		}

		parent::execute();
	}

	/**
	 * Show the charts
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$activeTab = $this->_getActiveTab();

		/* Determine minimum date */
		$minimumDate = NULL;

		if( \IPS\Settings::i()->prune_log_emailstats > 0 )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->prune_log_emailstats . 'D' ) );
		}

		/* We can't retrieve any stats prior to the new tracking being implemented */
		try
		{
			$oldestLog = \IPS\Db::i()->select( 'MIN(time)', 'core_statistics', array( 'type=?', 'emails_sent' ) )->first();

			if( !$minimumDate OR $oldestLog < $minimumDate->getTimestamp() )
			{
				$minimumDate = \IPS\DateTime::ts( $oldestLog );
			}
		}
		catch( \UnderflowException $e )
		{
			/* We have nothing tracked, set minimum date to today */
			$minimumDate = \IPS\DateTime::create();
		}

		$startDate = \IPS\DateTime::ts( time() - ( 60 * 60 * 24 * 30 ) );
		
		/* If our start date is older than our minimum date, use that as the start date instead */
		if ( $startDate->getTimestamp() < $minimumDate->getTimestamp() )
		{
			$startDate = $minimumDate;
		}

		$chart = new \IPS\Helpers\Chart\Callback( 
			\IPS\Http\Url::internal( 'app=core&module=activitystats&controller=emailstats&tab=' . $activeTab ), 
			array( $this, 'getResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'height'			=> 450
			), 
			'LineChart', 
			'daily',
			array( 'start' => $startDate, 'end' => \IPS\DateTime::create() ),
			'',
			$minimumDate
		);

		$chart->availableTypes	= array( 'LineChart', 'ColumnChart', 'BarChart' );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_emailstats_' . $activeTab);

		/* Force the notice that the chart is displayed in the server time zone */
		$chart->timezoneError = TRUE;
		$chart->hideTimezoneLink = TRUE;

		foreach( $this->_getEmailTypes() as $series )
		{
			$chart->addSeries( $series, 'number' );
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_emailstats');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $this->_getAvailableTabs(), $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=core&module=activitystats&controller=emailstats" ), 'tab', '', 'ipsPad' );
		}
	}

	/**
	 * Fetch the results
	 *
	 * @param	\IPS\Helpers\Chart\Callback	$chart	Chart object
	 * @return	array
	 */
	public function getResults( $chart )
	{
		$activeTab = $this->_getActiveTab();

		$where = array( array( "time>?", 0 ) );

		switch( $activeTab )
		{
			case 'emails':
				$where[] = array( 'type=?', 'emails_sent' );
			break;

			case 'clicks':
				$where[] = array( 'type=?', 'email_clicks' );
			break;
		}

		if ( $chart->start )
		{
			$where[] = array( "value_4>=?", $chart->start->format('Y-m-d') );
		}
		if ( $chart->end )
		{
			$where[] = array( "value_4<=?", $chart->end->format('Y-m-d') );
		}

		$results = array();

		foreach( \IPS\Db::i()->select( '*', 'core_statistics', $where, 'value_4 ASC' ) as $row )
		{
			/* We need to use month/days NOT prefixed with '0' - i.e. 2019-2-12 instead of 2019-02-12 - to match the chart helper */
			$_date = new \IPS\DateTime( $row['value_4'] );

			switch( $chart->timescale )
			{
				case 'daily':
					$_date = $_date->format( 'Y-n-j' );
				break;

				case 'weekly':
					$_date = $_date->format( 'o-W' );
				break;

				case 'monthly':
					$_date = $_date->format( 'Y-n' );
				break;
			}
			

			if( !isset( $results[ $_date ] ) )
			{
				$results[ $_date ] = array( 'time' => $_date );

				foreach( $this->_getEmailTypes() as $series )
				{
					$results[ $_date ][ $series ] = 0;
				}
			}

			$lang = $row['extra_data'];

			try
			{
				$lang = \IPS\Member::loggedIn()->language()->get( 'emailstats__' . $row['extra_data'] );
			}
			catch( \UnderflowException $e ){}

			$results[ $_date ][ $lang ] += (int) $row['value_1'];
		}

		return $results;
	}

	/**
	 * Get the active tab
	 *
	 * @return string
	 */
	protected function _getActiveTab()
	{
		return ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->_getAvailableTabs() ) ) ? \IPS\Request::i()->tab : 'emails';
	}

	/**
	 * Get the possible tabs
	 *
	 * @return array
	 */
	protected function _getAvailableTabs()
	{
		return array(
			'emails'	=> 'stats_emailstats_emails',
			'clicks'	=> 'stats_emailstats_clicks',
		);
	}

	/**
	 * @brief	Cached email types
	 */
	protected $_emailTypes = NULL;

	/**
	 * Get all possible email types logged
	 *
	 * @return array
	 */
	protected function _getEmailTypes()
	{
		if( $this->_emailTypes === NULL )
		{
			$this->_emailTypes = array();

			foreach( \IPS\Db::i()->select( 'extra_data', 'core_statistics', array( 'type=?', 'emails_sent' ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_DISTINCT ) as $series )
			{
				$lang = $series;

				try
				{
					$lang = \IPS\Member::loggedIn()->language()->get( 'emailstats__' . $series );
				}
				catch( \UnderflowException $e ){}

				$this->_emailTypes[] = $lang;
			}
		}

		return array_unique( $this->_emailTypes );
	}
}