<?php
/**
 * @brief		Support Applications in sitemaps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Mar 2020
 */

namespace IPS\core\extensions\core\Sitemap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Applications in sitemaps
 */
class _Applications
{
	/**
	 * @brief	Recommended Settings
	 */
	public $recommendedSettings = array();

	/**
	 * Add settings for ACP configuration to the form
	 *
	 * @return	array
	 */
	public function settings()
	{
	    return array();
	}

	/**
	 * Save settings for ACP configuration
	 *
	 * @param	array	$values	Values
	 * @return	void
	 */
	public function saveSettings( $values )
	{
	}

	/**
	 * Get the sitemap filename(s)
	 *
	 * @return	array
	 */
	public function getFilenames()
	{
		return array( 'sitemap_' . mb_strtolower('Applications') );
	}

	/**
	 * Generate the sitemap
	 *
	 * @param	string			$filename	The sitemap file to build (should be one returned from getFilenames())
	 * @param	\IPS\Sitemap	$sitemap	Sitemap object reference
	 * @return	void
	 */
	public function generateSitemap( $filename, $sitemap )
	{
		$links	= array();

		foreach( \IPS\Application::enabledApplications() as $app )
		{
			foreach( $app->sitemapLinks() as $link )
			{
				$links[] = array( 'url' => $link );
			}
		}

		/* Build the file */
		$sitemap->buildSitemapFile( $filename, $links );
	}
}