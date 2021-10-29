<?php
/**
 * @brief		Community in the Cloud Utility Functions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 May 2019
 */

namespace IPS\Cicloud;

/**
 * Send IPS Cloud applylatestfiles command
 * Fallback for autoupgrade failures
 *
 * @param	int		$version		If we're upgrading, the current version we are running.
 * @return	void
 */
function applyLatestFilesIPSCloud( ?int $version = NULL )
{
	if ( $user = \IPS\Cicloud\getCicUsername() )
	{
		try
		{
			$query = array();
			$query['user']	= $user;
			$query['key']	= md5( $user . \IPS\Settings::i()->sql_pass );
			if ( \IPS\CIC2 )
			{
				$query['currentversion'] = 'cloud2';
			}
			elseif ( $version )
			{
				$query['currentversion'] = $version;
			}
			\IPS\Http\Url::external( \IPS\IPS::$cicConfig['applylatestfiles']['endpoint'] )
				->setQueryString( $query )
				->request()
				->post();
		}
		catch ( \Exception $e ) { }
	}
}

/**
 * Get CiCloud User
 *
 * @return	string|NULL
 */
function getCicUsername(): ?string
{
	if ( \IPS\CIC2 )
	{
		return $_SERVER['IPS_CLOUD2_ID'];
	}
	
	if ( preg_match( '/^\/var\/www\/html\/(.+?)(?:\/|$)/i', \IPS\ROOT_PATH, $matches ) )
	{
		return $matches[1];
	}
	return NULL;
}

/**
 * Resync IPS Cloud File System
 * Must be called when writing any files to disk on IPS Community in the Cloud
 *
 * @param	string	$reason	Reason
 * @return	void
 */
function resyncIPSCloud( ?string $reason = NULL )
{
	if ( \IPS\CIC2 )
	{
		return;
	}
	
	if ( $user = \IPS\Cicloud\getCicUsername() )
	{
		try
		{
			\IPS\Http\Url::external('http://ips-cic-fileupdate.invisioncic.com/')
				->setQueryString( array( 'user' => $user, 'reason' => $reason ) )
				->request()
				->post();
		}
		catch ( \Exception $e ) { }
	}
}

/**
 * Unpack the special IPS_CLOUD_CONFIG environment variable
 *
 * @return void
 * @note This function *must* run before any other instantiation happens. As such, it cannot rely on any framework classes (besides static members of \IPS\IPS) or defined constants.
 */
function unpackCicConfig()
{
	if( isset( $_SERVER['IPS_CLOUD2_ID'] ) )
	{
		if( isset( $_SERVER['IPS_CLOUD2_EU'] ) AND $_SERVER['IPS_CLOUD2_EU'] )
		{
			include '/var/www/sharedresources/eu.php';
		}
		else
		{
			include '/var/www/sharedresources/us.php';
		}

		\IPS\IPS::$cicConfig = $CLOUDCONFIG;
	}
	elseif ( isset( $_SERVER['IPS_CLOUD_CONF'] ) )
	{
		$config = json_decode( base64_decode( $_SERVER['IPS_CLOUD_CONF'] ), TRUE );
		$defaults = array(
			'guests'                => [
				'guest_cache'           => TRUE,
				'guest_cache_timeout'   => 900
			],
			'email'                 => [
				'endpoint'      => 'http://ips-cic-email.invisioncic.com/sendEmail.php',
				'quota_check'   => 'http://ips-cic-email.invisioncic.com/blocked.php'
			],
			'applylatestfiles'      => [ 'endpoint' => 'http://ips-cic-fileupdate.invisioncic.com/applylatestfiles.php' ]
		);

		if ( ! empty( $config['redis'] ) and \intval( $config['redis'] ) === 1 )
		{
			$config['redis'] = array(
				'guest_cache'   => TRUE,
				'enabled'       => TRUE,
				'mode'          => 'replica',
				'replicas'      => array( 'internal-redis-replica.invisioncic.com' ),
				'primary'       => 'internal-redis-primary.invisioncic.com'
			);
		}

		if ( \is_array( $config ) and \count( $config ) )
		{
			\IPS\IPS::$cicConfig = array_merge( $defaults, $config );
		}
	}
}

/**
 * Compile Redis configuration
 * 
 * @return	string      JSON Encoded string
 */
function compileRedisConfig(): string
{
	if( isset( $_SERVER['IPS_CLOUD2_ID'] ) )
	{
		return \IPS\IPS::$cicConfig['redis']['config'];
	}

	if ( isset( \IPS\IPS::$cicConfig['redis']['replicas'] ) and \count( \IPS\IPS::$cicConfig['redis']['replicas'] ) and \IPS\IPS::$cicConfig['redis']['mode'] == 'replica' )
	{
		/* RW separation baby */
		$redisConfig = array(
			'write' => array(
				'server' => \IPS\IPS::$cicConfig['redis']['primary'],
				'port'   => 6379
			),
			'read' => array()
		);
		
		foreach( \IPS\IPS::$cicConfig['redis']['replicas'] as $replica )
		{
			$redisConfig['read'][] = array(
				'server' => $replica,
				'port'   => 6379
			);
		}
	}
	else
	{
		/* Single mode */
		$redisConfig = array(
			'server' => \IPS\IPS::$cicConfig['redis']['primary'],
			'port'   => 6379
		);
	}

	return json_encode( $redisConfig );
}

/**
 * S3 File Object
 *
 * @return	object
 */
function s3file()
{
	/* Load up the base Amazon File Storage configuration. If not present, something is very wrong, so intentionally no try/catch. */
	$configuration = json_decode( \IPS\Db::i()->select( 'configuration', 'core_file_storage', array( "method=? AND configuration LIKE CONCAT( '%', ?, '%' )", 'Amazon', 'ips-cic-filestore' ) )->first(), TRUE );
	
	/* Adjust the bucket and URL - no need to adjust Access Key or Secret as it'll be the same. */
	$configuration['bucket']			= 'ips-cloud2-filesystem';
	$configuration['bucket_path']	= $_SERVER['IPS_CLOUD2_ID']; # This probably doesn't need adjusting, but going ahead and doing so just to be sane.
	$configuration['custom_url']		= '';
	$configuration['region']			= 'us-east-1';
	
	/* Now save. AWS will handle syncing to the workers. */
	$obj = new class( $configuration ) extends \IPS\File\Amazon
	{
		public function isPrivate()
		{
			return TRUE;
		}
	};
	
	$obj->configurationId = NULL;
	$obj->storageExtension = NULL;
	
	return $obj;
}

/**
 * File Handling
 *
 * @param	string		$filename		THe filename
 * @param	string		$contents		The contents of the file.
 * @param	string|NULL	$container		The container the file should be stored in, or NULL for root of the bucket.
 * @return	void
 */
function file( string $filename, string $contents, ?string $container = NULL )
{
	/* Load up the base Amazon File Storage configuration. If not present, something is very wrong, so intentionally no try/catch. */
	$obj = s3file();
	
	$obj->container = $container;
	$obj->setFilename( $filename, FALSE );
	$obj->replace( $contents );
}

/**
 * File Delete
 *
 * @param	string		$filename		THe path to the file.
 * @return	void
 */
function fileDelete( string $path )
{
	$obj = s3file();
	
	$obj->get( NULL, $path );
	$obj->delete();
}

/**
 * Folder Delete
 *
 * @param	string		$folder		The folder to delete.
 * @return	void
 */
function folderDelete( string $folder )
{
	$obj = s3file();
	
	$obj->container = $folder;
	$obj->deleteContainer( $folder );
}

/**
 * Should archiving be forced for this site?
 * Abstracted so we can adjust conditions centrally in the future if desired.
 *
 * @return bool
 */
function getForcedArchiving()
{
	if ( !empty( \IPS\IPS::$cicConfig['archive']['enabled'] ) and !empty( \IPS\IPS::$cicConfig['archive']['minimum_posts'] ) )
	{
		return ( \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts' )->first() > \IPS\IPS::$cicConfig['archive']['minimum_posts'] );
	}

	return FALSE;
}

/**
 * Return the database connection for archived forum posts
 *
 * @return \IPS\Db
 */
function getForumArchiveDb()
{
	/* If archiving is enabled, but without a server, use current DB server */
	if( !empty( \IPS\IPS::$cicConfig['archive']['enabled'] ) AND empty( \IPS\IPS::$cicConfig['archive']['db_server'] ) )
	{
		return \IPS\Db::i();
	}
	/* If archiving is disabled, use the current database since we still run queries on the archive table. */
	elseif( empty( \IPS\IPS::$cicConfig['archive']['enabled'] ) )
	{
		return \IPS\Db::i();
	}

	/* Use remote db server */
	require( \IPS\SITE_FILES_PATH . '/conf_global.php' );
	return \IPS\Db::i( 'archive', array(
		'sql_host'		=> \IPS\IPS::$cicConfig['archive']['db_server'],
		'sql_user'		=> $INFO['sql_user'],
		'sql_pass'		=> $_SERVER['IPS_CLOUD2_DBPASS'],
		'sql_database'	=> $INFO['sql_database'],
		'sql_port'		=> ( isset( $INFO['sql_port'] ) and $INFO['sql_port']) ? $INFO['sql_port'] : NULL,
		'sql_socket'	=> ( isset( $INFO['sql_socket'] ) and $INFO['sql_socket'] ) ? $INFO['sql_socket'] : NULL,
		'sql_tbl_prefix'=> ( isset( $INFO['sql_tbl_prefix'] ) and $INFO['sql_tbl_prefix'] ) ? $INFO['sql_tbl_prefix'] : NULL,
		'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
	) );
}

/**
 * Get the forum archive where caluse
 *
 * @return \IPS\Helpers\Form
 */
function getForumArchiveWhere()
{
	/* Fail safe */
	if ( \IPS\Settings::i()->archive_last_post_cloud > 0 )
	{
		return array(
			array( '(last_post > 0 AND last_post < ?)', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->archive_last_post_cloud . 'Y' ) )->getTimestamp() ),
			array( 'pinned=0' ),
			array( 'featured=0' )
		);
	}
	
	throw new \UnderflowException;
}

/**
 * Get the forum archive form
 *
 * @return \IPS\Helpers\Form
 */
function getForumArchiveForm()
{
	$form = new \IPS\Helpers\Form;
	$form->addMessage( getForcedArchiving() ? 'archiving_blurb_cloud' : 'archiving_blurb_cloud_notforced', 'ipsMessage ipsMessage_info' );
	
	$form->addHeader( 'archive_settings_cloud' );

	if( getForcedArchiving() )
	{
		$form->add( new \IPS\Helpers\Form\Number( 'archive_last_post_cloud', \IPS\Settings::i()->archive_last_post_cloud, FALSE, array( 'min' => 1, 'max' => 12 ), NULL, \IPS\Member::loggedIn()->language()->addToStack('greater_than'), \IPS\Member::loggedIn()->language()->addToStack('archive_last_post_suffix') ) );
	}
	else
	{
		$form->add( new \IPS\Helpers\Form\Number( 'archive_last_post_cloud', \IPS\Settings::i()->archive_last_post_cloud, FALSE, array( 'min' => 1, 'max' => 99, 'unlimited' => 100, 'unlimitedLang' => 'disable_cloud_archiving' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('greater_than'), \IPS\Member::loggedIn()->language()->addToStack('archive_last_post_suffix') ) );
	}

	/* Handle submissions */
	if ( $values = $form->values() )
	{ 
		$form->saveAsSettings( $values );
		
		/* Make sure archiving is on */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'archive' ) );
	}
	
	return $form;
}

/**
 * Post install items
 *
 * @return void
 */
function postInstall()
{
	// Perform any post install tasks we may need to do
}

/**
 * Get User Key for Cloud2
 *
 * @return	string|NULL
 */
function getUserKey(): ?string
{
	if ( \IPS\CIC2 )
	{
		return md5( $_SERVER['IPS_CLOUD2_ID'] . $_SERVER['IPS_CLOUD2_DBPASS'] );
	}
	
	return NULL;
}

/**
 * Return the support email address for managed clients
 *
 * @return string
 */
function managedSupportEmail(): string
{
	if( isManaged() )
	{
		return "managedsupport@invisionpower.com";
	}

	return "support@invisionpower.com";
}

/**
 * Is this a managed client?
 *
 * @return bool
 */
function isManaged(): bool
{
	$licenseData = \IPS\IPS::licenseKey();

	if( isset( $licenseData['cloud'] ) and $licenseData['cloud'] AND (int) $licenseData['account'] === 100396 ) // Managed support account id
	{
		return TRUE;
	}

	return FALSE;
}

/**
 * Can manage resources check
 *
 * @return  bool
 */
function canManageResources(): bool
{
	$licenseData = \IPS\IPS::licenseKey();

	if( \IPS\Member::loggedIn()->members_bitoptions['is_support_account'] !== TRUE AND isManaged() ) // Managed support account id
	{
		return FALSE;
	}

	return TRUE;
}