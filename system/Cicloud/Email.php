<?php
/**
 * @brief		IPS CiC2 Email Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Aug 2020
 */

namespace IPS\Cicloud;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IPS CiC Email Class
 */
class _Email extends \IPS\Email
{
	/* !Configuration */
	
	/**
	 * @brief	The number of emails that can be sent in one "go"
	 */
	const MAX_EMAILS_PER_GO = 200;
	
	/**
	 * Send the email
	 * 
	 * @param	mixed	$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	mixed	$fromEmail			The email address to send from. If NULL, default setting is used
	 * @param	mixed	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array	$additionalHeaders	The name the email should appear from. If NULL, default setting is used
	 * @return	void
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	public function _send( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array() )
	{
		try
		{
			\IPS\Http\Url::external( \IPS\IPS::$cicConfig['email']['endpoint'] )->request()->post( array(
				'siteId'			=> $_SERVER['IPS_CLOUD2_ID'],
				'to'				=> array_map( 'trim', explode( ',', static::_parseRecipients( $to, TRUE ) ) ),
				'cc'				=> array_map( 'trim', explode( ',', static::_parseRecipients( $cc, TRUE ) ) ),
				'bcc'				=> array_map( 'trim', explode( ',', static::_parseRecipients( $bcc, TRUE ) ) ),
				'fromEmail'			=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'fromName'			=> $fromName ?: \IPS\Settings::i()->board_name,
				'additionalHeaders'	=> $additionalHeaders,
				'subject'			=> $this->compileSubject( static::_getMemberFromRecipients( $to ) ),
				'html'				=> $this->compileContent( 'html', static::_getMemberFromRecipients( $to ) ),
				'plaintext'			=> $this->compileContent( 'plaintext', static::_getMemberFromRecipients( $to ) ),
				'precedence'		=> ( $this->type === static::TYPE_LIST ) ? 'list' : ( $this->type === static::TYPE_BULK ? 'bulk' : '' ),
				'key'				=> \IPS\Cicloud\getUserKey()
			) );
		}
		catch( \IPS\Http\Request\Exception $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage() );
		}
	}
	
}