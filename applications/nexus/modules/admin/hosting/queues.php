<?php
/**
 * @brief		Hosting Queues
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		06 Aug 2014
 */

namespace IPS\nexus\modules\admin\hosting;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Hosting Queues
 */
class _queues extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Hosting\Queue';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'queues_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( 'hosting_queues_blurb' );
		return parent::manage();
	}
}