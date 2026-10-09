<?php
/**
 * Shared plugin identity and supported option values.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Config {
	/**
	 * Plugin slug, used as the admin page slug.
	 */
	const SLUG                = 'gallery-for-spotwalla';
	/**
	 * Database schema version; install() runs again when the stored version is lower.
	 */
	const DB_VERSION          = '4';
	/**
	 * Prefix of the plugin's tables, added after the site's database prefix.
	 */
	const TABLE_PREFIX        = 'SpotGal_';
	/**
	 * Table prefix used before version 1.0.3; data is imported from these tables.
	 */
	const LEGACY_TABLE_PREFIX = 'SW_';
	/**
	 * Source repository, linked from the About tab.
	 */
	const REPOSITORY          = 'https://github.com/oconnoradv/SpotwallaGallary';
	/**
	 * Item types managed on the Maps tab. Galleries use the type "gallery".
	 */
	const MAP_TYPES           = array( 'track', 'trip', 'retrospective' );
	/**
	 * Gallery visibility overrides: use each map's setting, or show or hide for all maps.
	 */
	const OVERRIDES           = array( 'item', 'show', 'hide' );
	/**
	 * Density/Fill Percentage values offered by the SpotWalla trip viewer (fillFactor),
	 * as value => label. An empty value means use the trip's own setting.
	 */
	const FILL_FACTORS        = array( '0' => 'None', '0.1' => '0.1%', '0.3' => '0.3%', '0.5' => '0.5%', '1' => '1%', '3' => '3%', '5' => '5%', '10' => '10%', '20' => '20%', '30' => '30%', '40' => '40%', '50' => '50%', '60' => '60%', '70' => '70%', '80' => '80%', '90' => '90%', '100' => 'All (100%)' );

}
