<?php
/**
 * @brief		GraphQL: Start a session for a user
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		20 Mar 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Register a push token for a specific user
 */
class _SessionStart
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Start a session for an authenticated user";

	/*
	 * Mutation arguments
	 */
	public function args()
	{
		return [
			'token' => TypeRegistry::string()
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\core\Api\GraphQL\TypeRegistry::member();
	}

	/**
	 * Resolves this mutation
	 * @todo this is basically copied and pasted from notifications.php which isn't ideal, so we 
	 * might want to consider refactoring to abstract this functionality.
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\forums\Forum
	 */
	public function resolve($val, $args)
	{
		if( !\IPS\Member::loggedIn()->member_id )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NOT_LOGGED_IN', 'GQL/0001/6', 403 );
		}
		
		if ( isset( $args['token'] ) and $args['token'] )
		{
			try
			{
				/* Register the token with the backend */
				$response = \IPS\Http\Url::ips('mobile/signin')->request()->login( \IPS\Settings::i()->mobile_app_id, \IPS\Settings::i()->mobile_app_secret )->post( array(
					'member'	=> \IPS\Member::loggedIn()->member_id,
					'token'		=> $args['token']
				) );
				if ( !\in_array( $response->httpResponseCode, array( 200, 201 ) ) )
				{
					throw new \DomainException( (string) $response );
				}
				
				/* If we didn't already have notifications enabled, enable them and set the defaults */
				if ( !\IPS\Member::loggedIn()->members_bitoptions['mobile_notifications'] )
				{
					\IPS\Member::loggedIn()->members_bitoptions['mobile_notifications'] = TRUE;
					\IPS\Member::loggedIn()->save();
					
					$notificationPreferences = \IPS\Db::i()->select( '*', 'core_notification_preferences', array( 'member_id=?', \IPS\Member::loggedIn()->member_id ) );
					if ( \count( $notificationPreferences ) )
					{
						$keysToAddPushTo = array();
						
						foreach ( $notificationPreferences as $row )
						{
							if ( \in_array( 'inline', explode( ',', $row['preference'] ) ) )
							{
								$keysToAddPushTo[] = $row['notification_key'];
							}
						}
						
						if ( \count( $keysToAddPushTo ) )
						{
							\IPS\Db::i()->update( 'core_notification_preferences', "`preference` = IF( `preference` = '', 'push', CONCAT_WS( ',', `preference`, 'push' ) )", array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'notification_key', $keysToAddPushTo ) ) ) );
						}
					}
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'mobile-push-notifications' );
			}
		}

		return \IPS\Member::loggedIn();
	}
}
