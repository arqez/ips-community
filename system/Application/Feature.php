<?php
/**
 * @brief		Application Feature Highlight Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Nov 2013
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Feature extends \IPS\Patterns\ActiveRecord implements \JsonSerializable
{
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'feature_';
	
	/**
	 * @brief	[ActiveRecord] Database table
	 * @note	This MUST be over-ridden
	 */
	public static $databaseTable	= 'core_members_features';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 * @note	This needs to be declared in any child classes as well, only declaring here for editor code-complete/error-check functionality
	 */
	protected static $multitons	= array();
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 * @note	If using this, declare a static $multitonMap = array(); in the child class to prevent duplicate loading queries
	 */
	protected static $databaseIdFields = array( 'feature_unique_key' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Developer Management Table
	 *
	 * @param	\IPS\Http\Url	$url	URL of the page the table is shown on.
	 * @param	string			$appKay	Application Key
	 * @return	string
	 */
	public static function devTable( \IPS\Http\Url $url, string $appKey ): string
	{
		$application = \IPS\Application::load( $appKey );
		
		if ( isset( \IPS\Request::i()->featureAction ) )
		{
			switch( \IPS\Request::i()->featureAction )
			{
				case 'form':
					$current = NULL;
					if ( isset( \IPS\Request::i()->uniqueKey ) )
					{
						try
						{
							$current = static::load( \IPS\Request::i()->uniqueKey, 'feature_unique_key' );
						}
						catch( \OutOfRangeException $e ) {}
					}
					$form = new \IPS\Helpers\Form;
					static::form( $form, $application, $current, isset( \IPS\Request::i()->version ) ? \IPS\Request::i()->version : NULL );
					if ( $values = $form->values() )
					{
						if ( $current === NULL )
						{
							$current = new static;
						}
						
						$current->formatFormValues( $values );
						$current->save();
						
						\IPS\Output::i()->redirect( $url, 'saved' );
					}
					
					return $form;
					break;
				
				case 'delete':
					
					$feature = static::load( \IPS\Request::i()->uniqueKey, 'feature_unique_key' );
					$feature->delete();
					
					\IPS\Output::i()->redirect( $url, 'deleted' );
				
					break;
			}
		}
		
		$tree = new \IPS\Helpers\Tree\Tree(
			$url,
			'dev_features',
			function() use ( $application, $url ) {
				$return = array();
				$return[] = static::_devTableRow( -1, FALSE, $application, $url );
				$versions = $application->getAllVersions();
				krsort( $versions );
				foreach( $versions AS $long => $human )
				{
					$return[] = static::_devTableRow( $long, FALSE, $application, $url );
				}
				return $return;
			},
			function( $long, $root=FALSE ) use ( $application, $url ) {
				return static::_devTableRow( $long, $root, $application, $url );
			},
			function( $long ) {
				return NULL;
			},
			function( $long ) use ( $application, $url ) {
				$return = array();
				
				$file = \IPS\ROOT_PATH . "/applications/{$application->directory}/setup/upg_" . ( $long == -1 ? 'working' : $long ) . "/whatsnew.json";
				if ( file_exists( $file ) )
				{
					if ( $data = @json_decode( \file_get_contents( $file ), TRUE ) )
					{
						foreach( $data AS $key => $row )
						{
							$return[] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $url, $long . '.' . $key, $row['feature_title'], FALSE, array(
								'edit'	=> array(
									'icon'		=> 'pencil',
									'title'		=> 'edit',
									'link'		=> $url->setQueryString( array( 'featureAction' => 'form', 'uniqueKey' => $row['feature_unique_key'] ) ),
									'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
								),
								'delete'	=> array(
									'icon'		=> 'times',
									'title'		=> 'delete',
									'link'		=> $url->setQueryString( array( 'featureAction' => 'delete', 'uniqueKey' => $row['feature_unique_key'] ) ),
									'data'		=> array( 'confirm' => '' )
								)
							), NULL, NULL, NULL, FALSE );
						}
					}
				}
				
				return $return;
			}
		);
		
		return $tree;
	}

	/**
	 * Developer Management Table row
	 *
	 * @param	string|int			$long			Long version
	 * @param	bool				$root			Is this a root row
	 * @param	\IPS\Application	$application	The application this feature belongs to
	 * @param	\IPS\Http\Url		$url			Base URL
	 * @return	string
	 */
	public static function _devTableRow( $long, $root, $application, $url )
	{
		$file = \IPS\ROOT_PATH . "/applications/{$application->directory}/setup/upg_" . ( $long == -1 ? 'working' : $long ) . "/whatsnew.json";
		
		$hasChildren = FALSE;
		if ( file_exists( $file ) )
		{
			if ( $data = @json_decode( \file_get_contents( $file ), TRUE ) )
			{
				if ( \count( $data ) )
				{
					$hasChildren = TRUE;
				}
			}
		}
		
		if ( $root === FALSE )
		{
			$buttons = array(
				'add'	=> array(
					'icon'	=> 'plus-circle',
					'title'	=> 'add_feature_highlight',
					'link'	=> $url->setQueryString( array( 'featureAction' => 'form', 'version' => $long ) ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add_feature_highlight') )
				)
			);
		}
		else
		{
			$buttons = array();
		}
		
		if ( $long === -1 )
		{
			$name = \IPS\Member::loggedIn()->language()->addToStack( 'versions_working' );
		}
		else
		{
			$name = $application->getAllVersions()[ $long ];
		}
		
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $url, $long, $name, $hasChildren, $buttons, ( $long === -1 ) ? NULL : $long, NULL, NULL, $root );
	}
	
	/**
	 * Form
	 *
	 * @param	\IPS\Helpers\Form				$form			The form, passed by reference
	 * @param	\IPS\Application					$application	Current application
	 * @param	NULL|\IPS\Application\Feature	$current		Currently editing, or NULL for new.
	 * @return	void
	 */
	public static function form( &$form, \IPS\Application $application, ?\IPS\Application\Feature $current, ?int $version )
	{
		$form->hiddenValues['feature_app']				= $application->directory;
		$form->hiddenValues['feature_version_added']	= ( $current ) ? $current->version_added : $version;
		$form->add( new \IPS\Helpers\Form\Text( 'feature_title', ( $current ) ? $current->title : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'feature_text', ( $current ) ? $current->text : NULL, TRUE, array( 'maxLength' => 300 ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'feature_image', NULL, FALSE, array( 'image' => TRUE, 'temporary' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'feature_url', ( $current ) ? $current->url : NULL, FALSE ) );
	}
	
	/**
	 * Save
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		
		\IPS\Db::i()->insert( 'core_members_feature_seen', array( 'member_id' => \IPS\Member::loggedIn()->member_id, 'feature_id' => $this->id ), FALSE, TRUE );
		
		static::writeJsonFile( $this->app, $this->version_added );
	}
	
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $this->image )
		{
			@\unlink( \IPS\ROOT_PATH . "/applications/{$this->app}/setup/upg_" . ( $this->version_added == -1 ? 'working' : $this->version_added ) . "/features/{$this->image}" );
		}
		
		parent::delete();
		
		/* If there's no more features for this version, make sure features folder is cleaned up */
		if ( \is_dir( \IPS\ROOT_PATH . "/applications/{$this->app}/setup/upg_" . ( $this->version_added == -1 ? 'working' : $this->version_added ) . "/features/" ) AND !\IPS\Db::i()->select( 'COUNT(*)', 'core_members_features', array( "feature_version_added=? AND feature_app=?", $this->version_added, $this->app ) )->first() )
		{
			@\rmdir( \IPS\ROOT_PATH . "/applications/{$this->app}/setup/upg_" . ( $this->version_added == -1 ? 'working' : $this->version_added ) . "/features/" );
		}
		
		static::writeJsonFile( $this->app, $this->version_added );
	}
	
	/**
	 * Get Application
	 *
	 * @return	\IPS\Application
	 */
	public function get__app(): \IPS\Application
	{
		return \IPS\Application::load( $this->_data['app'] );
	}
	
	/**
	 * Format Form Values
	 *
	 * @param	array	$values		Form Values
	 * @return	void
	 */
	public function formatFormValues( array $values )
	{
		$this->title				= $values['feature_title'];
		$this->text				= $values['feature_text'];
		$this->url				= (string) $values['feature_url'];
		$this->version_added		= ( isset( $values['feature_version_added'] ) AND $values['feature_version_added'] ) ? $values['feature_version_added'] : -1;
		$this->app				= $values['feature_app'];
		
		if ( \array_key_exists( 'feature_image', $values ) AND $values['feature_image'] )
		{
			/* Make a friendly file name */
			$filename = \IPS\Http\Url\Friendly::seoTitle( $values['feature_title'] ) . image_type_to_extension( exif_imagetype( $values['feature_image'] ) );
			
			if ( !\is_dir( \IPS\ROOT_PATH . "/applications/{$values['feature_app']}/setup/upg_" . ( $values['feature_version_added'] == -1 ? "working" : $values['feature_version_added'] ) . "/" ) )
			{
				mkdir( \IPS\ROOT_PATH . "/applications/{$values['feature_app']}/setup/upg_" . ( $values['feature_version_added'] == -1 ? "working" : $values['feature_version_added'] ) . "/" );
				chmod( \IPS\ROOT_PATH . "/applications/{$values['feature_app']}/setup/upg_" . ( $values['feature_version_added'] == -1 ? "working" : $values['feature_version_added'] ) . "/", \IPS\IPS_FOLDER_PERMISSION );
			}
			
			$dir = \IPS\ROOT_PATH . "/applications/{$values['feature_app']}/setup/upg_" . ( $values['feature_version_added'] == -1 ? "working" : $values['feature_version_added'] ) . "/features/";
			if ( !\is_dir( $dir ) )
			{
				mkdir( $dir );
				chmod( $dir, \IPS\IPS_FOLDER_PERMISSION );
			}
			
			/* If there is an existing file, remove it */
			if ( $this->image AND @\file_exists( $dir . '/' . $this->image ) )
			{
				@\unlink( $dir . '/' . $this->image );
			}

			\move_uploaded_file( $values['feature_image'], $dir . '/' . $filename );
			$this->image = $filename;
		}
		
		if ( !$this->unique_key )
		{
			$this->unique_key		= md5( $values['feature_app'] . $values['feature_title'] . mt_rand( 0, 1000 ) );
		}
	}
	
	/**
	 * Mark feature as read
	 *
	 * @param	\IPS\Member|NULL		$member		The member, or NULL for currently logged in member.
	 * @param	\IPS\Member\Feature	$features	Variable length amount of features to mark as read.
	 * @return	void
	 */
	public static function markRead( ?\IPS\Member $member, \IPS\Application\Feature ...$features )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if( !$member->member_id )
		{
			return;
		}
		
		$insert = array();
		foreach( $features AS $feature )
		{
			$insert[] = array( 'member_id' => $member->member_id, 'feature_id' => $feature->id );
		}
		
		if ( \count( $insert ) )
		{
			\IPS\Db::i()->insert( 'core_members_feature_seen', $insert, TRUE );
		}
	}
	
	/**
	 * JSON Serialize
	 *
	 * @return	array
	 */
	public function jsonSerialize(): array
	{
		$versions = $this->_app->getAllVersions();
		return array(
			'id'				=> $this->id,
			'version'		=> ( $this->version_added AND isset( $versions[ $this->version_added ] ) ) ? $versions[$this->version_added] : $this->_app->version,
			'title'			=> $this->title,
			'description'	=> $this->text,
			'more_info'		=> $this->url,
			'image'			=> $this->image ? (string) \IPS\Http\Url::internal( "/applications/{$this->app}/setup/upg_" . ( $this->version_added == -1 ? 'working' : $this->version_added ) . "/features/{$this->image}", 'none' ) : NULL
		);
	}
	
	/**
	 * Write Json File
	 *
	 * @param	string	$appKey		Application key to write.
	 * @param	int		$versionId	Long version
	 * @return	void
	 */
	public static function writeJsonFile( string $appKey, int $versionId )
	{
		$path = \IPS\ROOT_PATH . "/applications/{$appKey}/setup/upg_" . ( $versionId == -1 ? 'working' : $versionId ) . "/whatsnew.json";
		@unlink( $path );
		
		if ( !\is_dir( \IPS\ROOT_PATH . "/applications/{$appKey}/setup/upg_" . ( $versionId == -1 ? 'working' : $versionId ) . "/" ) )
		{
			mkdir( \IPS\ROOT_PATH . "/applications/{$appKey}/setup/upg_" . ( $versionId == -1 ? 'working' : $versionId ) . "/" );
			chmod( \IPS\ROOT_PATH . "/applications/{$appKey}/setup/upg_" . ( $versionId == -1 ? 'working' : $versionId ) . "/", \IPS\IPS_FOLDER_PERMISSION );
		}
		
		$data = array();
		foreach( \IPS\Db::i()->select( '*', 'core_members_features', array( "feature_app=? AND feature_version_added=?", $appKey, $versionId ), "feature_version_added DESC" ) AS $row )
		{
			$data[ $row['feature_unique_key'] ] = $row;
		}
		
		if ( \count( $data ) )
		{
			\file_put_contents( $path, json_encode( $data, JSON_PRETTY_PRINT ) );
		}
		else
		{
			@unlink( $path );
		}
	}
}