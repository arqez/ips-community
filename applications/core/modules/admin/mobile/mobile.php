<?php
/**
 * @brief		Mobile App Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Mar 2019
 */

namespace IPS\core\modules\admin\mobile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mobile App Settings
 */
class _mobile extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\DEMO_MODE )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '1C418/1', 403, '' );
		}

		\IPS\Dispatcher::i()->checkAcpPermission( 'mobile_manage' );
		parent::execute();
	}
	
	/**
	 * Mobile App Page
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_mobile' );
		
		/* If the mobile app has been set up, show the details page... */
		if ( \IPS\Settings::i()->mobile_app_id )
		{
			try
			{
				/* Get details from directory */
				$details = \IPS\Http\Url::ips('mobile/directory')->setQueryString( array( 'id' => \IPS\Settings::i()->mobile_app_id ) )->request()->login( \IPS\Settings::i()->mobile_app_id, \IPS\Settings::i()->mobile_app_secret )->get()->decodeJson();
				if ( \count( $details ) !== 1 )
				{
					if ( \count( $details ) === 0 )
					{
						\IPS\Settings::i()->changeValues( array( 'mobile_app_id' => '', 'mobile_app_secret' => '' ) );
						return $this->_notSetup();
					}
					else
					{
						throw new \DomainException;
					}
				}
				$details = $details[0];
				$langaugeOptions = \IPS\Http\Url::ips('mobile/languages')->request()->get()->decodeJson();
				$languages = [];
				foreach ( $details['languages'] as $langCode )
				{
					if ( array_key_exists( $langCode, $langaugeOptions ) )
					{
						$langauges[] = $langaugeOptions[ $langCode ];
					}
				}
				$details['languages'] = $langauges;
				
				/* If it says the status is other than what we expect, refresh our license key cache */
				if ( $licenseKey = \IPS\IPS::licenseKey() and ( ( $details['status'] === 'active' and !$licenseKey['active'] ) or ( $details['status'] === 'expired' and !$licenseKey['active'] ) ) )
				{
					$licenseKey = \IPS\IPS::licenseKey( TRUE );
				}
				
				/* Unless we're suspended, show a button to turn off mobile app integration */
				if ( $details['category'] === 'unlisted' or \in_array( $details['status'], array( 'pending', 'active', 'expired' ) ) )
				{
					\IPS\Output::i()->sidebar['actions']['deregister']	= array(
						'icon'	=> 'times',
						'title'	=> 'disable',
						'link'	=> \IPS\Http\Url::internal('app=core&module=mobile&controller=mobile&do=deregister')->csrf(),
						'data'	=> array(
							'confirm'			=> '',
							'confirmSubMessage' 	=> $details['category'] === 'unlisted' ? '' : \IPS\Member::loggedIn()->language()->addToStack('mobile_app_deregister_confirm')
						)
					);
				}
				
				/* Build the settings form */
				$settings = NULL;
				if ( \in_array( $details['status'], array( 'active' ) ) )
				{
					\IPS\Output::i()->sidebar['actions']['tokens']	= array(
						'icon'	=> 'key',
						'title'	=> 'oauth_view_authorizations',
						'link'	=> \IPS\Http\Url::internal('app=core&module=applications&controller=api&tab=oauth')->setQueryString( array( 'do' => 'tokens', 'client_id' => $this->_getClient()->client_id ) )
					);
					
					$settings = new \IPS\Helpers\Form;
					$settings->add( new \IPS\Helpers\Form\Sort( 'mobile_app_home_blocks_order', json_decode( \IPS\Settings::i()->mobile_app_home_blocks_order, TRUE ), FALSE, array( 'checkboxes' => 'mobile_app_home_blocks_' ) ) );

					if ( $details['category'] === 'unlisted' )
					{
						$settings->add( new \IPS\Helpers\Form\Text( 'mobile_app_redirect_scheme', \IPS\Settings::i()->mobile_app_redirect_scheme, FALSE, array( 'placeholder' => 'your-app-schema' ), NULL, NULL, '://', 'mobile_app_redirect_scheme' ) );
					}

					$settings->add( new \IPS\Helpers\Form\YesNo( 'mobile_app_prompt', \IPS\Settings::i()->mobile_app_prompt, FALSE, array( 'togglesOn' => array( 'mobile_app_itunes_id', 'mobile_app_itunes_url', 'mobile_app_play_id', 'mobile_app_play_url', 'itunes_affiliate_string' ) ) ) );

					if( $details['category'] !== 'unlisted' )
					{
						$settings->add( new \IPS\Helpers\Form\YesNo( 'mobile_app_hide_stats', \IPS\Settings::i()->mobile_app_hide_stats, FALSE ) );
					}

					if ( $details['category'] === 'unlisted' )
					{
						$settings->add( new \IPS\Helpers\Form\Text( 'mobile_app_itunes_id', \IPS\Settings::i()->mobile_app_itunes_id, FALSE, array( 'placeholder' => '123456789' ), NULL, NULL, NULL, 'mobile_app_itunes_id' ) );
						$settings->add( new \IPS\Helpers\Form\Url( 'mobile_app_itunes_url', \IPS\Settings::i()->mobile_app_itunes_url, FALSE, array( 'placeholder' => 'https://itunes.apple.com/us/app/my-app/123456789' ), NULL, NULL, NULL, 'mobile_app_itunes_url' ) );
						$settings->add( new \IPS\Helpers\Form\Text( 'mobile_app_play_id', \IPS\Settings::i()->mobile_app_play_id, FALSE, array( 'placeholder' => 'com.company.appname' ), NULL, NULL, NULL, 'mobile_app_play_id' ) );
						$settings->add( new \IPS\Helpers\Form\Url( 'mobile_app_play_url', \IPS\Settings::i()->mobile_app_play_url, FALSE, array( 'placeholder' => 'https://play.google.com/store/apps/details?id=com.company.appname' ), NULL, NULL, NULL, 'mobile_app_play_url' ) );
					}
					$settings->add( new \IPS\Helpers\Form\Text( 'itunes_affiliate_string', \IPS\Settings::i()->itunes_affiliate_string, FALSE, array(), NULL, NULL, NULL, 'itunes_affiliate_string' ) );
					
					if ( $values = $settings->values() )
					{
						$values['mobile_app_home_blocks_order'] = json_encode( $values['mobile_app_home_blocks_order'] );
						if ( $details['category'] !== 'unlisted' )
						{
							$values['mobile_app_redirect_scheme'] = '';
							$values['mobile_app_itunes_id'] = '';
							$values['mobile_app_itunes_url'] = '';
							$values['mobile_app_play_id'] = '';
							$values['mobile_app_play_url'] = '';
						}
						
						$settings->saveAsSettings( $values );
						if ( $details['category'] == 'unlisted' and $values['mobile_app_redirect_scheme'] )
						{
							$this->_updateClient( $this->_getClient() );
						}
						unset( \IPS\Data\Store::i()->manifest );
						
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=mobile&controller=mobile') );
					}
				}
				elseif ( \IPS\Settings::i()->mobile_app_prompt )
				{
					\IPS\Settings::i()->changeValues( array( 'mobile_app_prompt' => 0 ) );
					unset( \IPS\Data\Store::i()->manifest );
				}			
				
				/* Display */
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'mobile' )->details( $details, $settings );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'mobile_app_registration_error', '4C397/7', 500, NULL, array(), \IPS\IPS::getExceptionDetails( $e ) );
			}
		}
		/* Otherwise show the splash screen */
		else
		{
			return $this->_notSetup();
		}
	}
	
	/**
	 * Mobile App: Not Setup
	 *
	 * @return	void
	 */
	protected function _notSetup()
	{
		\IPS\Output::i()->sidebar['actions']['manual']	= array(
			'icon'	=> 'cog',
			'title'	=> 'mobile_app_splash_manual',
			'link'	=> \IPS\Http\Url::internal('app=core&module=mobile&controller=mobile&do=manual'),
			'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('mobile_app_splash_manual') )
		);
		
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'mobile' )->splash();
	}
	
	/**
	 * Mobile App: Submit to Multi Community
	 *
	 * @return	void
	 */
	protected function submit()
	{
		/* Before we do anything, check the license key */
		if ( $licenseKey = \IPS\IPS::licenseKey( TRUE ) )
		{
			if ( !$licenseKey['active'] )
			{
				\IPS\Output::i()->error( 'mobile_app_requires_active_license', '1C397/8', 403 );
			}
		}
		else
		{
			\IPS\Output::i()->error( 'mobile_app_requires_license', '1C397/9', 403 );
		}
		
		/* Create the form */
		$form = new \IPS\Helpers\Form( 'mobile_app_submit', 'submit' );
		$existing = NULL;
		if ( \IPS\Settings::i()->mobile_app_id )
		{
			try
			{
				$details = \IPS\Http\Url::ips('mobile/directory')->setQueryString( array( 'id' => \IPS\Settings::i()->mobile_app_id ) )->request()->login( \IPS\Settings::i()->mobile_app_id, \IPS\Settings::i()->mobile_app_secret )->get()->decodeJson();
				if ( \count( $details ) !== 1 )
				{
					throw new \DomainException;
				}
				$existing = $details[0];
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'mobile_app_registration_error', '4C397/6', 500, NULL, array(), \IPS\IPS::getExceptionDetails( $e ) );
			}
			$form->addMessage('mobile_app_listing_update_info');
		}
		$form->add( new \IPS\Helpers\Form\Text( 'mobile_app_listing_name', $existing ? ( $existing['pending_name'] ?: $existing['name'] ) : \IPS\Settings::i()->board_name, TRUE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'mobile_app_listing_desc', $existing ? ( $existing['pending_description'] ?: $existing['description'] ) : NULL, TRUE, array( 'maxLength' => 125 ) ) );
		if ( $existing )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'mobile_app_listing_logo_choose', 'keep', TRUE, array(
				'options' => array(
					'keep'	=> 'mobile_app_listing_logo_keep',
					'change'	=> 'mobile_app_listing_logo_change',
				),
				'toggles' => array(
					'change' => array( 'mobile_app_listing_logo_upload' )
				)
			) ) );
		}
		$homeScreen = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) ?? array();
		$form->add( new \IPS\Helpers\Form\Upload( 'mobile_app_listing_logo', $existing ? NULL : ( ( isset( $homeScreen['original'] ) ) ? \IPS\File::get( 'core_Icons', $homeScreen['original'] ) : NULL ), TRUE, array( 'image' => TRUE, 'storageExtension' => 'core_Icons' ), function ( $file ) {
			if ( $file )
			{
				$image = \IPS\Image::create( $file->contents() );
				if ( !$image->isSquare() )
				{
					throw new \DomainException('mobile_app_listing_logo_square');
				}
				if ( $image->width < 512 )
				{
					throw new \DomainException('mobile_app_listing_logo_dims');
				}
			}
		}, NULL, NULL, 'mobile_app_listing_logo_upload' ) );
		try
		{
			$categoryData = \IPS\Http\Url::ips('mobile/categories')->request()->get()->decodeJson();
			$categories = array();
			
			foreach( $categoryData as $_key => $_cat )
			{
				$categories[ $_key ] = $_cat['title'];
			}

			$form->add( new \IPS\Helpers\Form\Select( 'mobile_app_listing_category', $existing ? $existing['category'] : 'general', TRUE, array( 'options' => $categories ) ) );
			
			$langaugeOptions = \IPS\Http\Url::ips('mobile/languages')->request()->get()->decodeJson();
			if ( $existing )
			{
				$langauges = explode( ',', $existing['languages'] );
			}
			else
			{
				$langauges = [];
				foreach ( \IPS\Lang::languages() as $lang )
				{
					$code = mb_substr( $lang->bcp47(), 0, 2 );
					if ( array_key_exists( $code, $langaugeOptions ) )
					{
						$langauges[] = $code;
					}
				}
				if ( !$langauges )
				{
					$langauges = ['en'];
				}
			}
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'mobile_app_listing_languages', $existing ? explode( ',', $existing['languages'] ): ['en'], TRUE, array( 'multiple' => TRUE, 'options' => $langaugeOptions ) ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'mobile_app_registration_error', '4C397/3', 500, NULL, array(), \IPS\IPS::getExceptionDetails( $e ) );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* If we haven't set application icons for our manifest, use the icon provided */
			if ( !isset( $homeScreen['original'] ) )
			{
				\IPS\Settings::i()->changeValues( \IPS\core\modules\admin\customization\icons::processApplicationIcon( array( 'icons_homescreen' => $values['mobile_app_listing_logo'] ), $homeScreen ) );
			}
			
			try
			{
				/* Work out logo */
				$logo = NULL;
				$logoExt = NULL;
				$logoMime = NULL;
				if ( !$existing or $values['mobile_app_listing_logo_choose'] === 'change' )
				{
					$logo = $values['mobile_app_listing_logo']->contents();
					$logoExt = mb_substr( $values['mobile_app_listing_logo']->originalFilename, mb_strrpos( $values['mobile_app_listing_logo']->originalFilename, '.' ) + 1 );
					$logoMime = \IPS\File::getMimeType( $values['mobile_app_listing_logo']->originalFilename );
				}
																
				/* Submit to IPS */
				$response = $this->_updateRegistration( array(
					'name' 			=> $values['mobile_app_listing_name'],
					'description' 	=> $values['mobile_app_listing_desc'],
					'category' 		=> $values['mobile_app_listing_category'],
					'logo'			=> $logo,
					'logo_type'		=> $logoExt,
					'logo_mime'		=> $logoMime,
					'languages'		=> implode( ',', $values['mobile_app_listing_languages'] )
				), $existing ? \IPS\Settings::i()->mobile_app_id : NULL, $existing ? \IPS\Settings::i()->mobile_app_secret : NULL );
				
				/* If we already had an icon for our manifest, delete the logo we just uploaded */
				if ( isset( $homeScreen['original'] ) and $values['mobile_app_listing_logo'] and (string) $homeScreen['original'] != (string) $values['mobile_app_listing_logo'] )
				{
					$values['mobile_app_listing_logo']->delete();
				}
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '2C397/4', 500 );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'mobile_app_registration_error', '4C397/5', 500, NULL, array(), \IPS\IPS::getExceptionDetails( $e ) );
			}
			
			if ( $existing )
			{
				\IPS\Session::i()->log( 'acplogs__mobile_app_updated' );
			}
			else
			{
				$this->_doEnable( $response['id'], $response['secret'] );
				
				\IPS\Session::i()->log( 'acplogs__mobile_app_registered' );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=mobile&controller=mobile') );
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_mobile_mobile' );
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Mobile App: Activate Manually
	 *
	 * @return	void
	 */
	protected function manual()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'mobile_app_splash_manual' );
				
		$form = new \IPS\Helpers\Form;
		$form->addDummy( 'oauth_client_id', $this->_getClient( TRUE )->client_id );
		$form->add( new \IPS\Helpers\Form\Text( 'mobile_app_splash_manual_id', NULL, TRUE, array(), function( $val ) {
			try
			{
				$response = \IPS\Http\Url::ips('mobile/directory')->setQueryString( array( 'id' => $val ) )->request()->get()->decodeJson();
			}
			catch ( \Exception $e )
			{
				throw new \DomainException('mobile_app_registration_error');
			}
			
			if ( \count( $response ) !== 1 )
			{
				throw new \DomainException('mobile_app_splash_manual_error');
			}
			if ( $response[0]['url'] !== \IPS\Settings::i()->base_url )
			{
				throw new \DomainException('mobile_app_splash_manual_error_url');
			}
		} ) );
		$form->add( new \IPS\Helpers\Form\Text( 'mobile_app_splash_manual_secret', NULL, TRUE, array(), function( $val ) {
			try
			{
				$response = \IPS\Http\Url::ips('mobile/notify')->request()->login( \IPS\Request::i()->mobile_app_splash_manual_id, $val )->post( array( 'data' => array() ) );
			}
			catch ( \Exception $e )
			{
				throw new \DomainException('mobile_app_registration_error');
			}
			
			if ( $response->httpResponseCode != 200 )
			{
				throw new \DomainException('mobile_app_splash_manual_error');
			}
		} ) );
		if ( $values = $form->values() )
		{
			try
			{
				$this->_updateRegistration( array( ), $values['mobile_app_splash_manual_id'], $values['mobile_app_splash_manual_secret'] );
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '2C397/1', 500 );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'mobile_app_registration_error', '4C397/2', 500, NULL, array(), \IPS\IPS::getExceptionDetails( $e ) );
			}
			
			$this->_doEnable( $values['mobile_app_splash_manual_id'], $values['mobile_app_splash_manual_secret'] );
						
			\IPS\Session::i()->log( 'acplogs__mobile_app_activated' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=mobile&controller=mobile') );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Mobile App: Deregister
	 *
	 * @return	void
	 */
	protected function deregister()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\Settings::i()->mobile_app_id )
		{
			try
			{
				\IPS\Http\Url::ips('mobile/unregister')->request()->login( \IPS\Settings::i()->mobile_app_id, \IPS\Settings::i()->mobile_app_secret )->post( array(
					'lkey'		=> trim( \IPS\Settings::i()->ipb_reg_number ),
				) );
			}
			catch ( \Exception $e )
			{
				// It doesn't really matter if this failed (which could happen if the stores secret was somehow wrong)
				// It might just require tech support to go in and manually remove them before they can register again
			}
			
			\IPS\Settings::i()->changeValues( array(
				'mobile_app_id'		=> '',
				'mobile_app_secret'	=> '',
			) );
		}
		if ( $client = $this->_getClient() )
		{
			$client->delete();
		}
		
		\IPS\Session::i()->log( 'acplogs__mobile_app_unregistered' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=mobile&controller=mobile' ) );
	}
	
	/**
	 * Get OAuth Client if there is one
	 *
	 * @bool		bool		$createIfNot		If there isn't one, create one
	 * @return	\IPS\Api\OAuthClient|NULL
	 */
	protected function _getClient( $createIfNot = FALSE )
	{
		try
		{
			return \IPS\Api\OAuthClient::constructFromData( \IPS\Db::i()->select( '*', 'core_oauth_clients', array( 'oauth_type=?', 'mobile' ) )->first() );
		}
		catch ( \UnderflowException $e )
		{
			if ( $createIfNot )
			{
				$client = $this->_createClient();
				$this->_updateClient( $client );
				return $client;
			}
			else
			{
				return NULL;
			}
		}		
	}
	
	/**
	 * Create an OAuth Client
	 *
	 * @return	\IPS\Api\OAuthClient
	 */
	protected function _createClient()
	{
		$client = new \IPS\Api\OAuthClient;
		do
		{
			$client->client_id = \IPS\Login::generateRandomString( 32 );
		}
		while ( \IPS\Db::i()->select( 'COUNT(*)', 'core_oauth_clients', array( 'oauth_client_id=?', $client->client_id ) )->first() );
		$client->enabled = TRUE;
		$client->prompt = 'none';
		$client->ucp = FALSE;
		
		\IPS\Lang::copyCustom( 'core', 'mobile_app_default_client_name', "core_oauth_client_{$client->client_id}" );
		
		return $client;
	}
	
	/**
	 * Update OAuth Client with appropriate settings
	 *
	 * @param	\IPS\Api\OAuthClient $client	The client
	 * @return	void
	 */
	protected function _updateClient( \IPS\Api\OAuthClient $client )
	{
		$client->client_secret = NULL;
		foreach ( \IPS\Api\OAuthClient::mobileAppValues() as $k => $v )
		{
			if ( $k === 'redirect_uris' or $k === 'scopes' )
			{
				$client->$k = json_encode( $v );
			}
			else
			{
				$client->$k = \is_array( $v ) ? implode( ',', $v ) : $v;
			}
		}
		$client->save();
	}
	
	/**
	 * Update registration details on remoteservices
	 *
	 * @param	array		$details		Details to update (name, description, etc)
	 * @param	string|NULL	$appId		If updating, the ID
	 * @param	string|NULL	$appSecret	If updating, the secret
	 * @return	array
	 */
	protected function _updateRegistration( $details, $appId = NULL, $appSecret = NULL )
	{
		/* First of all, create or update our OAuth Client */
		$client = $this->_getClient();
		if ( !$client )
		{
			$client = $this->_createClient();
		}
		$this->_updateClient( $client );
				
		/* Set details to send */
		$details['lkey'] = trim( \IPS\Settings::i()->ipb_reg_number );
		$details['url'] = \IPS\Settings::i()->base_url;
		$details['client_id'] = $client->client_id;
		
		/* Register or update remoteservices */
		if ( $appId )
		{
			$response = \IPS\Http\Url::ips('mobile/register')->request()->login( $appId, $appSecret )->post( $details );
									
			if ( $response->httpResponseCode != 200 )
			{
				throw new \DomainException( ( (string) $response ) ?: 'mobile_app_registration_error' );
			}
		}
		else
		{
			$response = \IPS\Http\Url::ips('mobile/register')->request()->post( $details );
			
			if ( $response->httpResponseCode != 201 )
			{
				throw new \DomainException( ( (string) $response ) ?: 'mobile_app_registration_error' );
			}
		}
		return $response->decodeJson();
	}
	
	/**
	 * Enable mobile app
	 *
	 * @param	string	$appId		If updating, the ID
	 * @param	string	$appSecret	If updating, the secret
	 * @return	array
	 */
	protected function _doEnable( $appId, $appSecret )
	{
		\IPS\Settings::i()->changeValues( array(
			'mobile_app_id'		=> $appId,
			'mobile_app_secret'	=> $appSecret,
		) );
		
		/* Add it to our notification defaults */
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_notification_defaults' )->first() )
		{
			$keysToAddPushTo = array();
			
			foreach( \IPS\Application::allExtensions( 'core', 'Notifications', FALSE ) as $group => $class )
			{
				if ( method_exists( $class, 'configurationOptions' ) )
				{
					foreach ( $class->configurationOptions( NULL ) as $key => $data )
					{
						if ( $data['type'] == 'standard' and \in_array( 'push', $data['default'] ) )
						{
							$keysToAddPushTo[] = $key;
							foreach ( $data['notificationTypes'] as $type )
							{
								$keysToAddPushTo[] = $type;
							}
						}
					}
				}
				elseif ( method_exists( $class, 'getConfiguration' ) )
				{
					$configuration = $class->getConfiguration( NULL );
					if ( !empty( $configuration ) )
					{
						foreach ( $configuration as $key => $data )
						{
							if ( \in_array( 'push', $data['default'] ) )
							{
								$keysToAddPushTo[] = $key;
							}
						}
					}
				}
			}
			
			if ( \count( $keysToAddPushTo ) )
			{
				\IPS\Db::i()->update( 'core_notification_defaults', "`default` = IF( `default` = '', 'push', CONCAT_WS( ',', `default`, 'push' ) )", \IPS\Db::i()->in( 'notification_key', $keysToAddPushTo ) );
			}
		}
	}
}