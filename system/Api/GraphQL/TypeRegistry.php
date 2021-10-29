<?php
/**
 * @brief		Type registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Dec 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Type registry
 */
class _TypeRegistry
{
	protected static $query;
	protected static $mutation;
	protected static $itemState;
	protected static $image;
	protected static $reputation;
	protected static $richText;
	protected static $url;
	protected static $follow;
	protected static $moduleAccess;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	* @return QueryType
	*/
	public static function query()
	{
		return self::$query ?: (self::$query = new \IPS\Api\GraphQL\Types\QueryType());
	}

	/**
	* @return MutationType
	*/
	public static function mutation()
	{
		return self::$mutation ?: (self::$mutation = new \IPS\Api\GraphQL\Types\MutationType());
	}

	/**
	 * @return ItemStateType
	 */
	public static function itemState()
	{
		return self::$itemState ?: (self::$itemState = new \IPS\Api\GraphQL\Types\ItemStateType());
	}
	
	/**
	 * @return ImageType
	 */
	public static function image()
	{
		return self::$image ?: (self::$image = new \IPS\Api\GraphQL\Types\ImageType());
	}
	
	/**
	 * @return ReputationType
	 */
	public static function reputation()
	{
		return self::$reputation ?: (self::$reputation = new \IPS\Api\GraphQL\Types\ReputationType());
	}
	
	/**
	 * @return RichTextType
	 */
	public static function richText()
	{
		return self::$richText ?: (self::$richText = new \IPS\Api\GraphQL\Types\RichTextType());
	}

	/**
	 * @return URLType
	 */
	public static function url()
	{
		return self::$url ?: (self::$url = new \IPS\Api\GraphQL\Types\UrlType());
	}

	/**
	 * @return FollowType
	 */
	public static function follow()
	{
		return self::$follow ?: (self::$follow = new \IPS\Api\GraphQL\Types\FollowType());
	}

	/**
	 * @return ModuleAccessType
	 */
	public static function moduleAccess()
	{
		return self::$moduleAccess ?: (self::$moduleAccess = new \IPS\Api\GraphQL\Types\ModuleAccessType());
	}

	/**
	* @return \GraphQL\Type\Definition\IDType
	*/
	public static function id()
	{
		return Type::id();
	}

	/**
	* @return \GraphQL\Type\Definition\StringType
	*/
	public static function string()
	{
		return Type::string();
	}

	/**
	* @return \GraphQL\Type\Definition\IntType
	*/
	public static function int()
	{
		return Type::int();
	}

	/**
	* @return \GraphQL\Type\Definition\FloatType
	*/
	public static function float()
	{
		return Type::float();
	}

	/**
	* @return \GraphQL\Type\Definition\BooleanType
	*/
	public static function boolean()
	{
		return Type::boolean();
	}

	/**
	* @return \GraphQL\Type\Definition\ListOfType
	*/
	public static function listOf($type)
	{
		return new ListOfType($type);
	}

	/**
	* @return \GraphQL\Type\Definition\EnumType
	*/
	public static function eNum($config)
	{
		return new EnumType($config);
	}

	public static function inputObjectType($config)
	{
		return new InputObjectType($config);
	}

	/**
	* @param Type $type
	* @return NonNull
	*/
	public static function nonNull($type)
	{
		return new NonNull($type);
	}
}
