<?php
/**
 * @brief		GraphQL: Mobile menu query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		12 June 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mobile menu query for GraphQL API
 */
class _MobileMenu
{

	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns details of the mobile app menu";

	/*
	 * Query arguments
	 */
	public function args()
	{
		return array();
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::mobileMenu() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	array
	 */
	public function resolve($val, $args, $context)
	{
		$return = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_mobileapp_menu', NULL, 'position' ) as $item )
		{
			if ( \IPS\Application::appIsEnabled( $item['app'] ) )
			{
				$class = 'IPS\\' . $item['app'] . '\extensions\core\MobileNavigation\\' . $item['extension'];
				if ( class_exists( $class ) )
				{
					$object = new $class( json_decode( $item['config'], TRUE ), $item['id'], $item['permissions'] );
					if ( $object->canView() )
					{
						$return[ $item['id'] ] = $object;
					}
				}
				
			}
		}
		
		return $return;
	}
}
