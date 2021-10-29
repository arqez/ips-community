<?php
/**
 * @brief		GraphQL: Core type registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core type registry. GraphQL requires exactly one instance of each type,
 * so we'll generate singletons here.
 * @todo automate this somehow?
 */
class _TypeRegistry
{
	protected static $activeUsers;
	protected static $activeUser;
	protected static $attachment;
	protected static $attachmentPermissions;
	protected static $clubNode;
	protected static $club;
	protected static $contentReaction;
	protected static $contentSearchResult;
	protected static $group;
	protected static $ignoreOption;
	protected static $member;
	protected static $mobileMenu;
	protected static $language;
	protected static $login;
	protected static $messengerConversation;
	protected static $messengerFolder;
	protected static $messengerParticipant;
	protected static $messengerReply;
	protected static $notification;
	protected static $notificationMethod;
	protected static $notificationType;
	protected static $poll;
	protected static $pollQuestion;
	protected static $popularContributor;
	protected static $profileFieldGroup;
	protected static $profileField;
	protected static $promotedItem;
	protected static $report;
	protected static $reportReason;
	protected static $search;
	protected static $searchResult;
	protected static $stats;
	protected static $stream;
	protected static $tag;
	protected static $settings;
	protected static $uploadProgress;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	 * @return ActiveUsersType
	 */
	public static function activeUsers()
	{
		return self::$activeUsers ?: (self::$activeUsers = new \IPS\core\api\GraphQL\Types\ActiveUsersType());
	}

	/**
	 * @return ActiveUsersType
	 */
	public static function activeUser()
	{
		return self::$activeUser ?: (self::$activeUser = new \IPS\core\api\GraphQL\Types\ActiveUserType());
	}
	
	/**
	 * @return AttachmentType
	 */
	public static function attachment()
	{
		return self::$attachment ?: (self::$attachment = new \IPS\core\api\GraphQL\Types\AttachmentType());
	}
	
	/**
	 * @return AttachmentPermissionsType
	 */
	public static function attachmentPermissions()
	{
		return self::$attachmentPermissions ?: (self::$attachmentPermissions = new \IPS\core\api\GraphQL\Types\AttachmentPermissionsType());
	}

	/**
	 * @return ClubNodeType
	 */
	public static function clubNode()
	{
		return self::$clubNode ?: (self::$clubNode = new \IPS\core\api\GraphQL\Types\ClubNodeType());
	}

	/**
	 * @return ClubNodeType
	 */
	public static function club()
	{
		return self::$club ?: (self::$club = new \IPS\core\api\GraphQL\Types\ClubType());
	}

	/**
	 * @return ContentReactionType
	 */
	public static function contentReaction()
	{
		return self::$contentReaction ?: (self::$contentReaction = new \IPS\core\api\GraphQL\Types\ContentReactionType());
	}

	/**
	 * @return GroupType
	 */
	public static function group()
	{
		return self::$group ?: (self::$group = new \IPS\core\api\GraphQL\Types\GroupType());
	}

	/**
	 * @return IgnoreOptionType
	 */
	public static function ignoreOption()
	{
		return self::$ignoreOption ?: (self::$ignoreOption = new \IPS\core\api\GraphQL\Types\IgnoreOptionType());
	}

	/**
	 * @return MemberType
	 */
	public static function member()
	{
		return self::$member ?: (self::$member = new \IPS\core\api\GraphQL\Types\MemberType());
	}
	
	/**
	 * @return MobileMenuType
	 */
	public static function mobileMenu()
	{
		return self::$mobileMenu ?: (self::$mobileMenu = new \IPS\core\api\GraphQL\Types\MobileMenuType());
	}

	/**
	 * @return LanguageType
	 */
	public static function language()
	{
		return self::$language ?: (self::$language = new \IPS\core\api\GraphQL\Types\LanguageType());
	}

	/**
	 * @return LoginType
	 */
	public static function login()
	{
		return self::$login ?: (self::$login = new \IPS\core\api\GraphQL\Types\LoginType());
	}

	/**
	 * @return MessengerConversationType
	 */
	public static function messengerConversation()
	{
		return self::$messengerConversation ?: (self::$messengerConversation = new \IPS\core\api\GraphQL\Types\MessengerConversationType());
	}

	/**
	 * @return MessengerFolderType
	 */
	public static function messengerFolder()
	{
		return self::$messengerFolder ?: (self::$messengerFolder = new \IPS\core\api\GraphQL\Types\MessengerFolderType());
	}

	/**
	 * @return MessengerParticipantType
	 */
	public static function messengerParticipant()
	{
		return self::$messengerParticipant ?: (self::$messengerParticipant = new \IPS\core\api\GraphQL\Types\MessengerParticipantType());
	}

	/**
	 * @return MessengerReplyType
	 */
	public static function messengerReply()
	{
		return self::$messengerReply ?: (self::$messengerReply = new \IPS\core\api\GraphQL\Types\MessengerReplyType());
	}

	/**
	 * @return NotificationType
	 */
	public static function notification()
	{
		return self::$notification ?: (self::$notification = new \IPS\core\api\GraphQL\Types\NotificationType());
	}

	/**
	 * @return NotificationMethod
	 */
	public static function notificationMethod()
	{
		return self::$notificationMethod ?: (self::$notificationMethod = new \IPS\core\api\GraphQL\Types\NotificationMethodType());
	}

	/**
	 * @return NotificationTypeType
	 */
	public static function notificationType()
	{
		return self::$notificationType ?: (self::$notificationType = new \IPS\core\api\GraphQL\Types\NotificationTypeType());
	}

	/**
	 * @return PollType
	 */
	public static function poll()
	{
		return self::$poll ?: (self::$poll = new \IPS\core\api\GraphQL\Types\PollType());
	}

	/**
	 * @return PollQuestionType
	 */
	public static function pollQuestion()
	{
		return self::$pollQuestion ?: (self::$pollQuestion = new \IPS\core\api\GraphQL\Types\PollQuestionType());
	}

	/**
	 * @return PopularContributorType
	 */
	public static function popularContributor()
	{
		return self::$popularContributor ?: (self::$popularContributor = new \IPS\core\api\GraphQL\Types\PopularContributorType());
	}

	/**
	 * @return ProfileFieldGroupType
	 */
	public static function profileFieldGroup()
	{
		return self::$profileFieldGroup ?: (self::$profileFieldGroup = new \IPS\core\api\GraphQL\Types\ProfileFieldGroupType());
	}

	/**
	 * @return ProfileFieldType
	 */
	public static function profileField()
	{
		return self::$profileField ?: (self::$profileField = new \IPS\core\api\GraphQL\Types\ProfileFieldType());
	}

	/**
	 * @return PromotedItemType
	 */
	public static function promotedItem()
	{
		return self::$promotedItem ?: (self::$promotedItem = new \IPS\core\api\GraphQL\Types\PromotedItemType());
	}

	/**
	 * @return ContentSearchResultType
	 */
	public static function contentSearchResult()
	{
		return self::$contentSearchResult ?: (self::$contentSearchResult = new \IPS\core\api\GraphQL\Types\ContentSearchResultType());
	}

	/**
	 * @return ReportType
	 */
	public static function report()
	{
		return self::$report ?: (self::$report = new \IPS\core\api\GraphQL\Types\ReportType());
	}

	/**
	 * @return ReportReasonType
	 */
	public static function reportReason()
	{
		return self::$reportReason ?: (self::$reportReason = new \IPS\core\api\GraphQL\Types\ReportReasonType());
	}

	/**
	 * @return SearchResultType
	 */
	public static function searchResult()
	{
		return self::$searchResult ?: (self::$searchResult = new \IPS\core\api\GraphQL\Types\SearchResultType());
	}

	/**
	 * @return SearchType
	 */
	public static function search()
	{
		return self::$search ?: (self::$search = new \IPS\core\api\GraphQL\Types\SearchType());
	}

	/**
	 * @return StatsType
	 */
	public static function stats()
	{
		return self::$stats ?: (self::$stats = new \IPS\core\api\GraphQL\Types\StatsType());
	}

	/**
	 * @return StreamType
	 */
	public static function stream()
	{
		return self::$stream ?: (self::$stream = new \IPS\core\api\GraphQL\Types\StreamType());
	}

	/**
	 * @return TagType
	 */
	public static function tag()
	{
		return self::$tag ?: (self::$tag = new \IPS\core\api\GraphQL\Types\TagType());
	}

	/**
	 * @return SettingsType
	 */
	public static function settings()
	{
		return self::$settings ?: (self::$settings = new \IPS\core\api\GraphQL\Types\SettingsType());
	}

	/**
	 * @return UploadProgressType
	 */
	public static function uploadProgress()
	{
		return self::$uploadProgress ?: (self::$uploadProgress = new \IPS\core\api\GraphQL\Types\UploadProgressType());
	}
}