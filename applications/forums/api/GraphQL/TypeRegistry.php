<?php
/**
 * @brief		GraphQL: Types registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Forum type registry. GraphQL requires exactly one instance of each type,
 * so we'll generate singletons here.
 * @todo automate this somehow?
 */
class _TypeRegistry
{
	protected static $forum;
	protected static $post;
	protected static $topic;
	protected static $vote;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	 * @return ForumType
	 */
	public static function forum()
	{
		return self::$forum ?: (self::$forum = new \IPS\forums\api\GraphQL\Types\ForumType());
	}
	
	/**
	 * @return PostType
	 */
	public static function post()
	{
		return self::$post ?: (self::$post = new \IPS\forums\api\GraphQL\Types\PostType());
	}

	/**
	 * @return TopicType
	 */
	public static function topic()
	{
		return self::$topic ?: (self::$topic = new \IPS\forums\api\GraphQL\Types\TopicType());
	}

	/**
	 * @return VoteType
	 */
	public static function vote()
	{
		return self::$vote ?: (self::$vote = new \IPS\forums\api\GraphQL\Types\VoteType());
	}
}