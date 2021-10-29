<?php
/**
 * @brief		GraphQL API Dispatcher
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Dec 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/* Register our GraphQL library */
\IPS\IPS::$PSR0Namespaces['GraphQL'] = \IPS\ROOT_PATH . "/system/3rd_party/graphql-php";

/**
 * @brief	API Dispatcher
 */
class _GraphQL extends Api
{
	/**
	 * @brief Path
	 */
	public $path = '/graphql';
	
	/**
	 * Init
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function init()
	{		
		try
		{
			/* Check our IP address isn't banned */
			$this->_checkIpAddressIsAllowed();
			
			/* If this is a pre-flight request, acknowldge it */
			// @todo review this approach
			if( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' )
			{
				$this->_respond( '', 200 );
			}
			
			/* Authenticate */
			$client = NULL;
			$this->_setRawCredentials();
			try
			{
				if ( $this->rawAccessToken )
				{
					$this->_setAccessToken();
					$client = \IPS\Api\OAuthClient::load( $this->accessToken['client_id'] );
					\IPS\Member::$loggedInMember = \IPS\Member::load( $this->accessToken['member_id'] );
				}
				else
				{
					$client = \IPS\Api\OAuthClient::load( $this->rawApiKey );
					\IPS\Member::$loggedInMember = new \IPS\Member;
				}
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'INVALID_API_KEY', '3S290/7', 401 );
			}
			
			/* Check that the OAuth client has access to the GraphQL API */
			if ( !$client->graphql )
			{
				throw new \IPS\Api\Exception( 'NO_GRAPHQL_ACCESS', '2S291/3', 403 );
			}
		}
		catch ( \IPS\Api\Exception $e )
		{
			/* Build resonse */
			$response = json_encode( array( 'errors' => array( array( 'message' => $e->getMessage(), 'id' => $e->exceptionCode ) ) ), JSON_PRETTY_PRINT );
			
			/* Do we need to log this? */
			if ( \in_array( $e->exceptionCode, array( '2S290/8', '2S290/B', '3S290/7', '3S290/9' ) ) )
			{
				$this->_log( $response, $e->getCode(), \in_array( $e->exceptionCode, array( '3S290/7', '3S290/9', '3S290/B' ) ) );
			}
			
			/* Output */
			$this->_respond( $response, $e->getCode(), $e->oauthError );
		}
	}
	
	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		try
		{
			/* Work out the query (can either use a JSON-encoded or form-encoded) body */
			$query = NULL;
			$variables = NULL;
			if( \IPS\Request::i()->query )
			{
				$query = \IPS\Request::i()->query;
				$variables = ( isset( \IPS\Request::i()->variables ) AND \is_array( \IPS\Request::i()->variables ) ) ? \IPS\Request::i()->variables : [];
			}
			elseif ( $json = json_decode( file_get_contents('php://input'), TRUE ) )
			{
				$query = $json['query'];
				if( isset( $json['variables'] ) )
				{
					$variables = $json['variables'];
				}
			}
						
			/* Execute! */
			$result = \GraphQL\GraphQL::executeQuery(
				new \GraphQL\Type\Schema([
					'query' => \IPS\Api\GraphQL\TypeRegistry::query(),
					'mutation' => \IPS\Api\GraphQL\TypeRegistry::mutation()
				]),
				$query,
				NULL, // $rootValue
				[
					'member'	=> \IPS\Member::loggedIn()
				], // $context
				$variables
			);
			
			/* Convert result into JSON and send */
			$output = $result->toArray( ( \IPS\IN_DEV OR \IPS\DEBUG_GRAPHQL ) ? \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE | \GraphQL\Error\DebugFlag::INCLUDE_TRACE : false );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );
			$this->_respond( json_encode( $output, JSON_PRETTY_PRINT ), 200 );
			
		}
		catch ( \Exception $e )
		{
			$response = json_encode( array( 'errors' => array( array( 'message' => ( \IPS\IN_DEV OR \IPS\DEBUG_GRAPHQL ) ? $e->getMessage() : 'UNKNOWN_ERROR', 'id' => $e->getCode() ) ) ), JSON_PRETTY_PRINT );
			$this->_respond( $response, 500 );
		}
	}
}