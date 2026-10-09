<?php
/**
 * Plugin Name: Gallery for SpotWalla
 * Plugin URI: https://github.com/oconnoradv/SpotwallaGallary
 * Description: Manage and embed public SpotWalla tracks, trips, retrospectives, and galleries. Independent project; not affiliated with or approved by SpotWalla.
 * Version: 1.0.8
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Brian O'Connor
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gallery-for-spotwalla
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFSW_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-gfsw-config.php';
require_once __DIR__ . '/includes/interface-gfsw-item-reader.php';
require_once __DIR__ . '/includes/interface-gfsw-store.php';
require_once __DIR__ . '/includes/class-gfsw-wpdb-store.php';
require_once __DIR__ . '/includes/class-gfsw-validator.php';
require_once __DIR__ . '/includes/class-gfsw-form-recovery.php';
require_once __DIR__ . '/includes/class-gfsw-lifecycle.php';
require_once __DIR__ . '/includes/class-gfsw-admin.php';
require_once __DIR__ . '/includes/class-gfsw-admin-page.php';
require_once __DIR__ . '/includes/class-gfsw-renderer.php';

/**
 * Composition root and compatibility facade for existing public callbacks.
 *
 * Components own their behavior; this class only wires and delegates it.
 */
final class Gallery_For_SpotWalla {
	const SLUG                = GFSW_Config::SLUG;
	const DB_VERSION          = GFSW_Config::DB_VERSION;
	const TABLE_PREFIX        = GFSW_Config::TABLE_PREFIX;
	const LEGACY_TABLE_PREFIX = GFSW_Config::LEGACY_TABLE_PREFIX;
	const REPOSITORY          = GFSW_Config::REPOSITORY;
	const MAP_TYPES           = GFSW_Config::MAP_TYPES;
	const OVERRIDES           = GFSW_Config::OVERRIDES;
	const FILL_FACTORS        = GFSW_Config::FILL_FACTORS;

	/** @var GFSW_Lifecycle */
	private static $lifecycle;
	/** @var GFSW_Admin */
	private static $admin;
	/** @var GFSW_Admin_Page */
	private static $page;
	/** @var GFSW_Renderer */
	private static $renderer;

	/** @return void */
	public static function init() {
		if ( self::$admin ) {
			return;
		}
		global $wpdb;
		$store = new GFSW_Wpdb_Store( $wpdb );
		self::$lifecycle = new GFSW_Lifecycle( $store, $wpdb );
		self::$admin = new GFSW_Admin( $store, new GFSW_Form_Recovery() );
		self::$page = new GFSW_Admin_Page( $store, new GFSW_Form_Recovery() );
		self::$renderer = new GFSW_Renderer( $store );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
		add_action( 'admin_post_gfsw_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_gfsw_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_gfsw_settings', array( __CLASS__, 'settings' ) );
		add_shortcode( 'gallery_for_spotwalla', array( __CLASS__, 'shortcode' ) );
		if ( ! shortcode_exists( 'spotwalla_gallery' ) ) {
			add_shortcode( 'spotwalla_gallery', array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * @param bool $network_wide Whether network activation was requested.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		self::$lifecycle->activate( $network_wide );
	}

	/** @return void */
	public static function deactivate() {
		self::$lifecycle->deactivate();
	}

	/** @return void */
	public static function maybe_upgrade() {
		self::$lifecycle->maybe_upgrade();
	}

	/** @return void */
	public static function menu() {
		self::$page->menu();
	}

	/** @return void */
	public static function admin_styles() {
		self::$page->admin_styles();
	}

	/** @return void */
	public static function legacy_notice() {
		self::$page->legacy_notice();
	}

	/** @return void */
	public static function save() {
		self::$admin->save();
	}

	/** @return void */
	public static function delete() {
		self::$admin->delete();
	}

	/** @return void */
	public static function settings() {
		self::$admin->settings();
	}

	/** @return void */
	public static function admin() {
		self::$page->admin();
	}

	/**
	 * @param array|string $attributes Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function shortcode( $attributes ) {
		return self::$renderer->shortcode( $attributes );
	}
}

Gallery_For_SpotWalla::init();
register_activation_hook( __FILE__, array( 'Gallery_For_SpotWalla', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gallery_For_SpotWalla', 'deactivate' ) );
