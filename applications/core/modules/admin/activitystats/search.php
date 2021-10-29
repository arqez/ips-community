<?php
/**
 * @brief		Search Statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Dec 2019
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search Statistics
 */
class _search extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Default limit to number of graphed results
	 */
	protected $defaultLimit = 25;

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
		\IPS\Dispatcher::i()->checkAcpPermission( 'search_stats_manage' );
		parent::execute();
	}

	/**
	 * View search statistics
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Show button to adjust settings */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'primary'	=> TRUE,
			'title'		=> 'manage_searchstats',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search&do=settings' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
		);

		/* Determine minimum date */
		$minimumDate = NULL;

		if( \IPS\Settings::i()->stats_search_prune )
		{
			$minimumDate = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->stats_search_prune . 'D' ) );
		}

		$chart = new \IPS\Helpers\Chart\Database( 
			\IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search' ), 
			'core_statistics', 
			'time', 
			'', 
			array( 
				'isStacked' => FALSE,
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'limitSearch'		=> 'stats_search_term_menu',
			), 
			'LineChart', 
			'daily', 
			array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P90D' ) ), 'end' => \IPS\DateTime::ts( time() ) ),
			array(),
			'',
			$minimumDate
		);
		$chart->where	= array( array( 'type=?', 'search' ) );
		$chart->groupBy	= 'value_4';

		$terms = [];
		foreach( $this->getTerms( $chart->searchTerm ) as $v )
		{
			$terms[] = $v;
			$chart->addSeries( $v, 'number', 'COUNT(*)', FALSE );
		}

		if ( \count( $terms ) )
		{
			$chart->where[] = [ \IPS\Db::i()->in( 'value_4', $terms ) ];
		}

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('search_stats_chart');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );

		\IPS\Output::i()->output	= (string) $chart;

		if( \IPS\Request::i()->noheader AND \IPS\Request::i()->isAjax() )
		{
			return;
		}

		/* The table filters won't without this */
		\IPS\Output::i()->bypassCsrfKeyCheck = true;

		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_statistics', \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search' ), array( array( 'type=?', 'search' ) ) );
		$table->langPrefix = 'searchstats_';
		$table->quickSearch = 'value_4';

		/* Columns we need */
		$table->include = array( 'value_4', 'value_2', 'time' );
		$table->mainColumn = 'value_4';

		$table->sortBy = $table->sortBy ?: 'time';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		/* Custom parsers */
		$table->parsers = array(
			'time'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			}
		);

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_search');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', (string) $table, TRUE, 'ipsPad ipsSpacer_top' );
	}

	/**
	 * @brief	Cached top search terms
	 */
	protected $_topSearchTerms = array();

	/**
	 * Get the top search terms
	 *
	 * @param	string|null		$term	Term we searched for
	 * @return	array
	 */
	public function getTerms( $term=NULL )
	{
		if( !isset( $this->_topSearchTerms[ $term ] ) )
		{
			$this->_topSearchTerms[ $term ] = array();

			$where = array( array( 'type=?', 'search' ) );

			if( $term !== NULL )
			{
				$where[] = \IPS\Db::i()->like( 'value_4', $term, TRUE, TRUE, TRUE );
			}

			foreach( \IPS\Db::i()->select( 'SQL_BIG_RESULT value_4, COUNT(*) as total', 'core_statistics', $where, 'total DESC', $this->defaultLimit, 'value_4' ) as $searchedValue )
			{
				$this->_topSearchTerms[ $term ][] = $searchedValue['value_4'];
			}
		}

		return $this->_topSearchTerms[ $term ];
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'stats_search_prune', \IPS\Settings::i()->stats_search_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__statssearch_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'settings', $form, FALSE );
	}
}