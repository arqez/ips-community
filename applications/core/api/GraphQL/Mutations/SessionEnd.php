<?php
/**
 * @brief		GraphQL: End a session for the user
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		02 Apr 2019
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
class _SessionEnd
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "End a user session";

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
		
		\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'member_id=? AND access_token=?', \IPS\Member::loggedIn()->member_id, \IPS\Dispatcher::i()->accessToken['access_token'] ) );
		if ( isset( \IPS\Dispatcher::i()->accessToken['device_key'] ) )
		{
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'member_id=? AND device_key=?', \IPS\Member::loggedIn()->member_id, \IPS\Dispatcher::i()->accessToken['device_key'] ) );
		}
		
		if ( isset( $args['token'] ) and $args['token'] )
		{
			try
			{
				$response = \IPS\Http\Url::ips('mobile/signout')->request()->login( \IPS\Settings::i()->mobile_app_id, \IPS\Settings::i()->mobile_app_secret )->post( array(
					'member'	=> \IPS\Member::loggedIn()->member_id,
					'token'	=> $args['token']
				) )->decodeJson();
							
				if ( $response->httpResponseCode != 200 )
				{
					throw new \DomainException( (string) $response );
				}
				
				if ( !$response['remainingDevices'] )
				{
					\IPS\Member::loggedIn()->members_bitoptions['mobile_notifications'] = FALSE;
					\IPS\Member::loggedIn()->save();
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
