<?php
/**
 * Lifecycle component for Gallery for SpotWalla.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned schema and migrations.

final class GFSW_Lifecycle {
	/** @var GFSW_Store */
	private $store;

	/** @var wpdb Schema and migration connection. */
	private $db;

	/**
	 * @param GFSW_Store $store Persistence dependency.
	 * @param wpdb $db Schema and migration connection.
	 */
	public function __construct( GFSW_Store $store, wpdb $db ) {
		$this->store = $store;
		$this->db = $db;
	}

	/**
	 * Returns the full name of one of the plugin's tables.
	 *
	 * @param string $name   Table suffix: items, settings, or gallery_items.
	 * @param string $prefix Plugin table prefix. Pass LEGACY_TABLE_PREFIX for the
	 *                       tables used before version 1.0.3.
	 * @return string Table name including the site's database prefix.
	 */
	private function table( $name, $prefix = GFSW_Config::TABLE_PREFIX ) {
		$wpdb = $this->db;
		return $wpdb->prefix . $prefix . $name;
	}


	/**
	 * Checks whether a database table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		$wpdb = $this->db;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}


	/**
	 * Activation hook. Creates or upgrades the tables for the current site.
	 *
	 * Network activation is refused because each site needs its own tables.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 * @return void
	 */
	public function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Please activate Gallery for SpotWalla separately on each site, not network-wide.', 'gallery-for-spotwalla' ) );
		}
		$this->install();
	}


	/**
	 * Creates or upgrades one set of plugin tables with dbDelta().
	 *
	 * @param string $prefix Plugin table prefix: TABLE_PREFIX or LEGACY_TABLE_PREFIX.
	 * @return void
	 */
	private function create_tables( $prefix ) {
		$wpdb = $this->db;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$items     = $this->table( 'items', $prefix );
		$settings  = $this->table( 'settings', $prefix );
		$relations = $this->table( 'gallery_items', $prefix );
		$charset   = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				type varchar(20) NOT NULL DEFAULT 'trip',
				title varchar(255) NOT NULL,
				description longtext NOT NULL,
				url text NOT NULL,
				show_title tinyint(1) NOT NULL DEFAULT 1,
				show_description tinyint(1) NOT NULL DEFAULT 1,
				member_title varchar(10) NOT NULL DEFAULT 'item',
				member_description varchar(10) NOT NULL DEFAULT 'item',
				fill_factor varchar(5) NOT NULL DEFAULT '',
				inherit_theme tinyint(1) NOT NULL DEFAULT 1,
				background varchar(7) NOT NULL DEFAULT '#ffffff',
				color varchar(7) NOT NULL DEFAULT '#222222',
				width int unsigned NOT NULL DEFAULT 800,
				height int unsigned NOT NULL DEFAULT 450,
				PRIMARY KEY  (id)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE $settings (
				setting_key varchar(64) NOT NULL,
				setting_value text NOT NULL,
				PRIMARY KEY  (setting_key)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE $relations (
				gallery_id bigint(20) unsigned NOT NULL,
				item_id bigint(20) unsigned NOT NULL,
				PRIMARY KEY  (gallery_id,item_id),
				KEY item_id (item_id)
			) $charset;"
		);
	}


	/**
	 * Moves data from the tables used before version 1.0.3 ({prefix}SW_*).
	 *
	 * Runs only when the old items table exists and the new one is empty, so it
	 * never overwrites data. Maps, galleries, memberships, and the data-retention
	 * setting are copied with their IDs, so existing shortcodes keep working, and
	 * the old tables are then dropped. Copying instead of renaming works on every
	 * database WordPress supports, including SQLite.
	 *
	 * @return bool False if a query failed. The copied rows are then removed and the
	 *              old tables kept, so the import is retried on the next load.
	 */
	private function import_legacy_tables() {
		$wpdb = $this->db;
		$old_items = $this->table( 'items', GFSW_Config::LEGACY_TABLE_PREFIX );
		$items     = $this->table( 'items' );
		if ( ! $this->table_exists( $old_items ) || null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i LIMIT 1', $items ) ) ) {
			return true;
		}
		// Bring the old tables up to the current schema so the column lists match.
		$this->create_tables( GFSW_Config::LEGACY_TABLE_PREFIX );
		$old_settings  = $this->table( 'settings', GFSW_Config::LEGACY_TABLE_PREFIX );
		$old_relations = $this->table( 'gallery_items', GFSW_Config::LEGACY_TABLE_PREFIX );
		$relations     = $this->table( 'gallery_items' );
		// Version 1.0.0 stored one gallery per map in items.gallery_id.
		$copied = ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $old_items, 'gallery_id' ) ) ||
			false !== $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (gallery_id, item_id) SELECT m.gallery_id, m.id FROM %i m INNER JOIN %i g ON g.id = m.gallery_id AND g.type = 'gallery' WHERE m.type <> 'gallery'", $old_relations, $old_items, $old_items ) );
		$copied = $copied &&
			false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (id, type, title, description, url, show_title, show_description, member_title, member_description, fill_factor, inherit_theme, background, color, width, height) SELECT id, type, title, description, url, show_title, show_description, member_title, member_description, fill_factor, inherit_theme, background, color, width, height FROM %i', $items, $old_items ) ) &&
			false !== $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (gallery_id, item_id) SELECT gallery_id, item_id FROM %i', $relations, $old_relations ) ) &&
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $items ) ) === $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $old_items ) );
		$retention = $copied ? $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key = %s', $old_settings, 'delete_on_deactivation' ) ) : null;
		if ( null !== $retention ) {
			$copied = false !== $wpdb->replace( $this->table( 'settings' ), array( 'setting_key' => 'delete_on_deactivation', 'setting_value' => '1' === $retention ? '1' : '0' ) );
		}
		if ( ! $copied ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $relations ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $items ) );
			return false;
		}
		foreach ( array( $old_relations, $old_items, $old_settings ) as $table ) {
			// The data now lives in the new tables.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		return true;
	}


	/**
	 * Creates or upgrades the plugin tables and records the schema version.
	 *
	 * Also adds the default data-retention setting and imports data from the
	 * tables used before version 1.0.3. The version is not recorded if the import
	 * fails, so it is retried on the next load.
	 *
	 * @return void
	 */
	private function install() {
		$wpdb = $this->db;
		$this->create_tables( GFSW_Config::TABLE_PREFIX );
		$settings = $this->table( 'settings' );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (setting_key, setting_value) VALUES ('delete_on_deactivation', '0')", $settings ) );
		if ( ! $this->import_legacy_tables() ) {
			return;
		}
		$wpdb->replace( $settings, array( 'setting_key' => 'db_version', 'setting_value' => GFSW_Config::DB_VERSION ) );
	}


	/**
	 * Runs install() when the tables are missing or older than DB_VERSION.
	 *
	 * Hooked to plugins_loaded so updates that replace the files without
	 * reactivating the plugin still upgrade the schema.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$wpdb = $this->db;
		$suppress = $wpdb->suppress_errors();
		$version  = $this->store->setting( 'db_version' );
		$wpdb->suppress_errors( $suppress );
		if ( null === $version || version_compare( $version, GFSW_Config::DB_VERSION, '<' ) ) {
			$this->install();
		}
	}


	/**
	 * Deactivation hook. Drops the plugin tables only if the administrator opted in.
	 *
	 * By default all maps, galleries, and settings are kept for reactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		$wpdb = $this->db;
		if ( '1' === $this->store->setting( 'delete_on_deactivation' ) ) {
			foreach ( array( 'gallery_items', 'items', 'settings' ) as $name ) {
				// Deleting the plugin's own tables is an explicit, opt-in administrator choice.
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			}
		}
	}
}
