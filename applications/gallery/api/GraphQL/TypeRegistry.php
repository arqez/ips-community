<?php
/**
 * @brief		GraphQL: Types registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		23 Feb 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gallery type registry. GraphQL requires exactly one instance of each type,
 * so we'll generate singletons here.
 * @todo automate this somehow?
 */
class _TypeRegistry
{
	protected static $album;
	protected static $albumComment;
	protected static $albumItem;
	protected static $image;
	protected static $imageComment;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	 * @return ImageType
	 */
	public static function album()
	{
		return self::$album ?: (self::$album = new \IPS\gallery\api\GraphQL\Types\AlbumType());
	}

	/**
	 * @return AlbumCommentType
	 */
	public static function albumComment()
	{
		return self::$albumComment ?: (self::$albumComment = new \IPS\gallery\api\GraphQL\Types\AlbumCommentType());
	}

	/**
	 * @return AlbumCommentType
	 */
	public static function albumItem()
	{
		return self::$albumItem ?: (self::$albumItem = new \IPS\gallery\api\GraphQL\Types\AlbumItemType());
	}

	/**
	 * @return ImageType
	 */
	public static function image()
	{
		return self::$image ?: (self::$image = new \IPS\gallery\api\GraphQL\Types\ImageType());
	}

	/**
	 * @return ImageCommentType
	 */
	public static function imageComment()
	{
		return self::$imageComment ?: (self::$imageComment = new \IPS\gallery\api\GraphQL\Types\ImageCommentType());
	}
}