<?php
/**
 * @brief		GraphQL: Mobile Menu Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		12 June 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mobile Menu Type for GraphQL API
 */
class _MobileMenuType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_MobileMenu',
			'description' => 'Mobile menu item type',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Item ID",
						'resolve' => function ( $item ) {
							return $item->id;
						}
					],
					'class' => [
						'type' => TypeRegistry::string(),
						'description' => "Class which handles this item",
						'resolve' => function ( $item ) {
							return \get_class( $item );
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Menu item title",
						'resolve' => function ($item) {
							return $item->title();
						}
					],
					'icon' => [
						'type' => TypeRegistry::string(),
						'description' => "Icon key for this item",
						'resolve' => function ($item) {
							return $item->icon();
						}
					],
					'url' => [
						'type' => TypeRegistry::url(),
						'description' => "URL to content",
						'resolve' => function ( $item ) {
							return $item->link();
						}
					]
				];
			}
		];

		parent::__construct($config);  
	}
}
