<?php
/**
 * @brief		menu
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		12 Jun 2019
 */

namespace IPS\core\modules\admin\mobile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * menu
 */
class _menu extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'menu_manage' );
		\IPS\Output::i()->responsive = FALSE;
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/menumanager.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_system.js', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.nestedSortable.js', 'core', 'interface' ) );
		parent::execute();
	}

	/**
	 * Manage Menu
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Get items from the database */
		$items = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_mobileapp_menu', NULL, 'position' ) as $item )
		{
			if ( \IPS\Application::appIsEnabled( $item['app'] ) )
			{
				$class = 'IPS\\' . $item['app'] . '\extensions\core\MobileNavigation\\' . $item['extension'];
				if ( class_exists( $class ) )
				{
					$items[ $item['id'] ] = new $class( json_decode( $item['config'], TRUE ), $item['id'], $item['permissions'] );
				}
			}
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_mobile_menu');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'applications' )->menuManager( array( 0 => $items ), array(), \IPS\Http\Url::internal('app=core&module=mobile&controller=menu'), FALSE, FALSE );
	}
	
	/**
	 * Add Menu Item
	 *
	 * @return	void
	 */
	protected function form()
	{
		/* What menu items have we already configured? */
		$current = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_mobileapp_menu', NULL, 'position' ) as $item )
		{
			if ( !isset( $current[ $item['app'] ][ $item['extension'] ] ) )
			{
				$current[ $item['app'] ][ $item['extension'] ] = 0;
			}
			$current[ $item['app'] ][ $item['extension'] ]++;
		}
		
		/* Are we editing an existing item? */
		$existing = NULL;
		if ( \IPS\Request::i()->id )
		{
			try
			{
				$existing = \IPS\Db::i()->select( '*', 'core_mobileapp_menu', array( 'id=?', \IPS\Request::i()->id ) )->first();
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* What options are available? */		
		$options = array();
		$disabled = array();
		$toggles = array();
		$fieldNames = array();
		$extraFields = array();
		$haveInheritable = FALSE;
		foreach ( \IPS\Application::allExtensions( 'core', 'MobileNavigation', FALSE, 'core', NULL, FALSE ) as $key => $class )
		{
			if ( method_exists( $class, 'typeTitle' ) )
			{
				$exploded = explode( '_', $key );
				if ( $class::allowMultiple() or !isset( $current[ $exploded[0] ][ $exploded[1] ] ) or ( $existing and $existing['app'] == $exploded[0] and $existing['extension'] == $exploded[1] ) )
				{
					$options[ $key ] = $class::typeTitle();
					
					foreach ( $class::configuration( $existing ? json_decode( $existing['config'], TRUE ) : array(), $existing ? $existing['id'] : NULL ) as $field )
					{
						if ( !$field->htmlId )
						{
							$field->htmlId = md5( mt_rand() );
						}
						
						$toggles[ $key ][ $field->name ] = $field->htmlId;
						
						$fieldNames[ $key ][] = $field->name;
						$extraFields[] = $field;
					}
					
					if ( $class::permissionsCanInherit() )
					{
						$haveInheritable = TRUE;
						$toggles[ $key ][] = 'menu_manager_access_type';
					}
					else
					{
						$toggles[ $key ][] = 'menu_manager_access';
					}
				}
				if ( !$class::isEnabled() )
				{
					$disabled[] = $key;
				}
			}
		}
		
		/* Create the form */
		$form = new \IPS\Helpers\Form( 'menu_item', 'save_menu_item' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Radio( 'menu_manager_extension', $existing ? "{$existing['app']}_{$existing['extension']}" : NULL, TRUE, array( 'options' => $options, 'toggles' => $toggles, 'disabled' => $disabled ) ) );
		foreach ( $extraFields as $field )
		{
			$form->add( $field );
		}
		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		if ( $haveInheritable )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'menu_manager_access_type', ( $existing and $existing['permissions'] !== NULL ) ? 1 : 0, TRUE, array(
				'options'	=> array( 0 => 'menu_manager_access_type_inherit', 1 => 'menu_manager_access_type_override' ),
				'toggles'	=> array( 1 => array( 'menu_manager_access' ) )
			), NULL, NULL, NULL, 'menu_manager_access_type' ) );
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'menu_manager_access', $existing ? ( $existing['permissions'] == '*' ? '*' : explode( ',', $existing['permissions'] ) ) : '*', NULL, array( 'multiple' => TRUE, 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'menu_manager_access' ) );
		
		if( !$existing ){
			$form->hiddenValues['newItem'] = TRUE;
		}

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$exploded = explode( '_', $values['menu_manager_extension'] );
			$class = 'IPS\\' . $exploded[0] . '\extensions\core\MobileNavigation\\' . $exploded[1];
			
			$config = array();
			if ( isset( $fieldNames[ $values['menu_manager_extension'] ] ) )
			{
				foreach ( $values as $k => $v )
				{
					if ( \in_array( $k, $fieldNames[ $values['menu_manager_extension'] ] ) )
					{
						$config[ $k ] = $v;
					}
				}
			}
			
			$save = array(
				'app'			=> $exploded[0],
				'extension'		=> $exploded[1],
				'config'		=> json_encode( $config ),
			);
			
			/* First we need to determine if the access type option was even shown */
			$hasAccessType = ( \in_array( 'menu_manager_access_type', $toggles[ $values['menu_manager_extension'] ] ) ) ? TRUE : FALSE;

			/* If we didn't have the access type field, then we should just check the permissions that were specified */
			if ( !$hasAccessType or $values['menu_manager_access_type'] )
			{
				$save['permissions'] = $values['menu_manager_access'] == '*' ? '*' : implode( ',', $values['menu_manager_access'] );
			}
			else
			{
				$save['permissions'] = $class::permissionsCanInherit() ? NULL : '';
			}
			
			if ( $existing )
			{
				$id = $existing['id'];
				
				$_config = $class::parseConfiguration( $config, $id );
				
				if ( $_config != $config )
				{
					$save['config'] = json_encode( $_config );
				}
				
				\IPS\Db::i()->update( 'core_mobileapp_menu', $save, array( 'id=?', $id ) );
			}
			else
			{
				try
				{
					$save['position'] = \IPS\Db::i()->select( 'MAX(position)', 'core_mobileapp_menu' )->first() + 1;
				}
				catch ( \UnderflowException $e )
				{
					$save['position'] = 1;
				}
				
				$id = \IPS\Db::i()->insert( 'core_mobileapp_menu', $save );
				
				$_config = $class::parseConfiguration( $config, $id );
			
				if ( $_config != $config )
				{
					\IPS\Db::i()->update( 'core_mobileapp_menu', array( 'config' => json_encode( $_config ) ), array( 'id=?', $id ) );
				}
			}

			if( \IPS\Request::i()->isAjax() )
			{
				$item = new $class( $_config, $id, $save['permissions'] );
				$output = array(
					'menu_item' => \IPS\Theme::i()->getTemplate( 'applications' )->menuItem( $item, $id, \IPS\Http\Url::internal('app=core&module=mobile&controller=menu') ),
					'id' => $id
				);
				
				\IPS\Output::i()->json( $output );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=mobile&controller=menu') );
			}
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'applications', 'core' ), 'menuManagerForm' ) );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Output::i()->output );
		}
	}	
	
	/**
	 * Remove an item
	 *
	 * @return	void
	 */
	protected function remove()
	{
		\IPS\Session::i()->csrfCheck();
		
		static::_remove( \intval( \IPS\Request::i()->id ) );
		\IPS\Output::i()->json('OK');
	}
	
	/**
	 * Remove a menu item
	 *
	 * @param	int	$id	ID of item to remove
	 * @return	void
	 */
	protected static function _remove( $id )
	{
		\IPS\Db::i()->delete( 'core_mobileapp_menu', array( 'id=?', $id ) );

		/* remove the title language strings */
		\IPS\Lang::deleteCustom( 'core', "mobilemenu_item_{$id}" );
	}
	
	/**
	 * Reorder Items
	 *
	 * @return	void
	 */
	protected function reorder()
	{
		\IPS\Session::i()->csrfCheck();

		$position = 1;
		foreach ( \IPS\Request::i()->menu_order as $id )
		{
			\IPS\Db::i()->update( 'core_mobileapp_menu', array(
				'position' => $position++,
			), array( 'id=?', $id ) );
		}
		\IPS\Output::i()->json('OK');
	}
}