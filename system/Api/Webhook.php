<?php
/**
 * @brief		Webhook
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		5 Feb 2020
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Webhook
 */
class _Webhook extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_api_webhooks';
	
	/**
	 * @brief	cache for get_url()
	 */
	protected $_url;
	
	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array('webhooks');
	
	/**
	 * Fire all webhooks for given event
	 *
	 * @param	string	$event		The event key
	 * @param	mixed	$data		Data
	 * @param	array	$filters		Filters
	 * @return	void
	 */
	public static function fire( $event, $data = NULL, $filters = array() )
	{		
		/* Normalise data */
		if ( \is_object( $data ) and method_exists( $data, 'apiOutput' ) )
		{
			$data = $data->apiOutput();
		}

		/* We need to replace and langstring hashes ( like node names) */
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $data );

		$data = $data ? json_encode( $data ) : NULL;
						
		/* Get our webhooks from cache */
		if ( !isset( \IPS\Data\Store::i()->webhooks ) )
		{
			$webhooks = array();
			foreach ( \IPS\Db::i()->select( '*', 'core_api_webhooks', array('enabled=1') ) as $row )
			{
				foreach ( explode( ',', $row['events'] ) as $e )
				{
					if ( !isset( $webhooks[ $e ] ) )
					{
						$webhooks[ $e ] = array();
					}
					
					$webhooks[ $e ][ $row['id'] ] = array(
						'key'		=> $row['api_key'],
						'url'		=> $row['url'],
						'filters'	=> json_decode( $row['filters'], TRUE )
					);
				}
			}
			\IPS\Data\Store::i()->webhooks = $webhooks;
		}
		
		/* If we have webhooks for this event... */
		$enable = FALSE;
		if ( isset( \IPS\Data\Store::i()->webhooks[ $event ] ) )
		{
			/* Loop through each one... */
			foreach ( \IPS\Data\Store::i()->webhooks[ $event ] as $id => $webhook )
			{					
				/* Skip over it if the filters don't match */
				if ( isset( $webhook['filters'][ $event ] ) )
				{
					foreach ( $webhook['filters'][ $event ] as $k => $v )
					{
						if ( isset( $filters[ $k ] ) )
						{
							if ( \is_array( $v ) and !\is_array( $filters[ $k ] ) )
							{
								if ( !\in_array( $filters[ $k ], $v ) )
								{
									continue 2;
								}
							}
							else
							{
								if ( $filters[ $k ] != $v )
								{
									continue 2;
								}
							}
						}
					}
				}
														
				/* Insert it */
				$enable = TRUE;
				\IPS\Db::i()->insert( 'core_api_webhook_fires', [
					'webhook'	=> $id,
					'event'		=> $event,
					'data'		=> $data,
					'time'		=> time(),
					'status'		=> 'pending'
				] );
			}
		}
		
		/* Make sure the task is enabled */
		if ( $enable )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'webhooks' ) );
		}
	}
	
	/**
	 * Get API Key
	 *
	 * @return	\IPS\Api\Key
	 */
	public function get_api_key()
	{
		return \IPS\Api\Key::load( $this->_data['api_key'] );
	}
	
	/**
	 * Set API Key
	 *
	 * @param	\IPS\Api\Key		$key		The API Key
	 * @return	void
	 */
	public function set_api_key( \IPS\Api\Key $key )
	{
		$this->_data['api_key'] = $key->id;
	}
	
	/**
	 * Get Events
	 *
	 * @return	array
	 */
	public function get_events()
	{
		return explode( ',', $this->_data['events'] );
	}
	
	/**
	 * Set Events
	 *
	 * @param	array	$events	List of events
	 * @return	void
	 */
	public function set_events( array $events )
	{
		$this->_data['events'] = implode( ',', $events );
	}
	
	/**
	 * Get URL
	 *
	 * @return	array
	 */
	public function get_url()
	{
		if ( $this->_url === NULL )
		{
			$this->_url = new \IPS\Http\Url( $this->_data['url'] );
		}
		
		return $this->_url;
	}
	
	/**
	 * Set Events
	 *
	 * @param	\IPS\Http\Url	$url		The URL
	 * @return	void
	 */
	public function set_url( \IPS\Http\Url $url )
	{
		$this->_url = $url;
		$this->_data['url'] = (string) $this->_url;
	}
	
	/**
	 * Get Filters
	 *
	 * @return	array
	 */
	public function get_filters()
	{
		return $this->_data['filters'] ? json_decode( $this->_data['filters'], TRUE ) : array();
	}
	
	/**
	 * Set Filters
	 *
	 * @param	array	$filters		Filter
	 * @return	void
	 */
	public function set_filters( array $filters )
	{
		$this->_data['filters'] = json_encode( $filters );
	}
	
	/**
	 * Get output for API
	 *
	 * @return	array
	 * @apiresponse		int		id		ID number
	 * @apiresponse		array	events	List of events to subscribe to
	 * @apiresponse		string	url		URL to send webhook to
	 */
	public function apiOutput()
	{
		return array(
			'id'		=> $this->id,
			'events'	=> $this->events,
			'url'		=> (string) $this->url
		);
	}
}