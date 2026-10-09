<?php
/**
 * Plugin Name: Gallery for SpotWalla
 * Plugin URI: https://github.com/oconnoradv/SpotwallaGallary
 * Description: Manage and embed public SpotWalla tracks, trips, retrospectives, and galleries. Independent project; not affiliated with or approved by SpotWalla.
 * Version: 1.0.6
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Brian O'Connor
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gallery-for-spotwalla
 * Domain Path: /languages
 */

/*
 * The plugin stores its data in its own tables, which the WordPress object cache and
 * core APIs do not cover. Table names come from $wpdb->prefix and are passed to
 * $wpdb->prepare() as %i identifiers.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery for SpotWalla plugin.
 *
 * Stores SpotWalla maps (tracks, trips, retrospectives) and galleries in custom
 * tables, provides the Maps, Galleries, and About admin tabs, and renders the
 * [gallery_for_spotwalla] shortcode. All members are static; the class is never instantiated.
 */
final class Gallery_For_SpotWalla {
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

	/**
	 * Returns the full name of one of the plugin's tables.
	 *
	 * @param string $name   Table suffix: items, settings, or gallery_items.
	 * @param string $prefix Plugin table prefix. Pass LEGACY_TABLE_PREFIX for the
	 *                       tables used before version 1.0.3.
	 * @return string Table name including the site's database prefix.
	 */
	private static function table( $name, $prefix = self::TABLE_PREFIX ) {
		global $wpdb;
		return $wpdb->prefix . $prefix . $name;
	}

	/**
	 * Checks whether a database table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
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
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Please activate Gallery for SpotWalla separately on each site, not network-wide.', 'gallery-for-spotwalla' ) );
		}
		self::install();
	}

	/**
	 * Creates or upgrades one set of plugin tables with dbDelta().
	 *
	 * @param string $prefix Plugin table prefix: TABLE_PREFIX or LEGACY_TABLE_PREFIX.
	 * @return void
	 */
	private static function create_tables( $prefix ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$items     = self::table( 'items', $prefix );
		$settings  = self::table( 'settings', $prefix );
		$relations = self::table( 'gallery_items', $prefix );
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
	private static function import_legacy_tables() {
		global $wpdb;
		$old_items = self::table( 'items', self::LEGACY_TABLE_PREFIX );
		$items     = self::table( 'items' );
		if ( ! self::table_exists( $old_items ) || null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i LIMIT 1', $items ) ) ) {
			return true;
		}
		// Bring the old tables up to the current schema so the column lists match.
		self::create_tables( self::LEGACY_TABLE_PREFIX );
		$old_settings  = self::table( 'settings', self::LEGACY_TABLE_PREFIX );
		$old_relations = self::table( 'gallery_items', self::LEGACY_TABLE_PREFIX );
		$relations     = self::table( 'gallery_items' );
		// Version 1.0.0 stored one gallery per map in items.gallery_id.
		$copied = ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $old_items, 'gallery_id' ) ) ||
			false !== $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (gallery_id, item_id) SELECT m.gallery_id, m.id FROM %i m INNER JOIN %i g ON g.id = m.gallery_id AND g.type = 'gallery' WHERE m.type <> 'gallery'", $old_relations, $old_items, $old_items ) );
		$copied = $copied &&
			false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (id, type, title, description, url, show_title, show_description, member_title, member_description, fill_factor, inherit_theme, background, color, width, height) SELECT id, type, title, description, url, show_title, show_description, member_title, member_description, fill_factor, inherit_theme, background, color, width, height FROM %i', $items, $old_items ) ) &&
			false !== $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (gallery_id, item_id) SELECT gallery_id, item_id FROM %i', $relations, $old_relations ) ) &&
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $items ) ) === $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $old_items ) );
		$retention = $copied ? $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key = %s', $old_settings, 'delete_on_deactivation' ) ) : null;
		if ( null !== $retention ) {
			$copied = false !== $wpdb->replace( self::table( 'settings' ), array( 'setting_key' => 'delete_on_deactivation', 'setting_value' => '1' === $retention ? '1' : '0' ) );
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
	private static function install() {
		global $wpdb;
		self::create_tables( self::TABLE_PREFIX );
		$settings = self::table( 'settings' );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (setting_key, setting_value) VALUES ('delete_on_deactivation', '0')", $settings ) );
		if ( ! self::import_legacy_tables() ) {
			return;
		}
		$wpdb->replace( $settings, array( 'setting_key' => 'db_version', 'setting_value' => self::DB_VERSION ) );
	}

	/**
	 * Reads a value from the plugin's settings table.
	 *
	 * @param string $key Setting key, such as db_version or delete_on_deactivation.
	 * @return string|null The stored value, or null if it is missing.
	 */
	private static function setting( $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key = %s', self::table( 'settings' ), $key ) );
	}

	/**
	 * Runs install() when the tables are missing or older than DB_VERSION.
	 *
	 * Hooked to plugins_loaded so updates that replace the files without
	 * reactivating the plugin still upgrade the schema.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors();
		$version  = self::setting( 'db_version' );
		$wpdb->suppress_errors( $suppress );
		if ( null === $version || version_compare( $version, self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Deactivation hook. Drops the plugin tables only if the administrator opted in.
	 *
	 * By default all maps, galleries, and settings are kept for reactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		global $wpdb;
		if ( '1' === self::setting( 'delete_on_deactivation' ) ) {
			foreach ( array( 'gallery_items', 'items', 'settings' ) as $name ) {
				// Deleting the plugin's own tables is an explicit, opt-in administrator choice.
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			}
		}
	}

	/**
	 * Registers the plugin's hooks, admin-post handlers, and shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
		add_action( 'admin_post_gfsw_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_gfsw_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_gfsw_settings', array( __CLASS__, 'settings' ) );
		add_shortcode( 'gallery_for_spotwalla', array( __CLASS__, 'shortcode' ) );
		// Shortcode name used before the plugin was renamed in version 1.0.2.
		if ( ! shortcode_exists( 'spotwalla_gallery' ) ) {
			add_shortcode( 'spotwalla_gallery', array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * Adds the plugin page to the admin menu, labeled "Spotwalla Gallery" in the menu only.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page( __( 'Gallery for SpotWalla', 'gallery-for-spotwalla' ), __( 'Spotwalla Gallery', 'gallery-for-spotwalla' ), 'manage_options', self::SLUG, array( __CLASS__, 'admin' ), 'dashicons-location-alt' );
	}

	/**
	 * Adds admin CSS: a purple menu icon on every admin screen and the page header layout.
	 *
	 * @return void
	 */
	public static function admin_styles() {
		$plugin = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		$menu   = '#adminmenu .toplevel_page_' . self::SLUG;
		$css    = $menu . ' div.wp-menu-image:before,' . $menu . ':hover div.wp-menu-image:before,' . $menu . ' a:focus div.wp-menu-image:before,' . $menu . '.current div.wp-menu-image:before,' . $menu . '.wp-has-current-submenu div.wp-menu-image:before{color:#b48cf5}'
			. '.gfsw-header{display:flex;align-items:center;gap:16px;margin:12px 0 8px}'
			. '.gfsw-header img{flex:none;width:128px;height:128px;border-radius:4px}'
			. '.wrap .gfsw-header h1{margin:0;padding:0;font-size:2em;line-height:1.2}'
			. '.gfsw-header .gfsw-tagline{margin:4px 0 0;font-size:14px;font-style:italic;color:#646970}'
			. '@media screen and (max-width:600px){.gfsw-header img{width:64px;height:64px}.wrap .gfsw-header h1{font-size:1.5em}}';
		wp_register_style( 'gallery-for-spotwalla-admin', false, array(), $plugin['version'] );
		wp_enqueue_style( 'gallery-for-spotwalla-admin' );
		wp_add_inline_style( 'gallery-for-spotwalla-admin', $css );
	}

	/**
	 * Outputs the page header: logo, plugin name, and tagline.
	 *
	 * @return void
	 */
	private static function header() {
		$plugin = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		?>
		<div class="gfsw-header">
			<img src="<?php echo esc_url( plugins_url( 'images/logo-256x256.jpg', __FILE__ ) ); ?>" width="128" height="128" alt="" />
			<div>
				<h1><?php esc_html_e( 'Gallery for SpotWalla', 'gallery-for-spotwalla' ); ?></h1>
				<p class="gfsw-tagline">
					<?php esc_html_e( "an O'ConnorADV project", 'gallery-for-spotwalla' ); ?>
					<span class="gfsw-version"><?php /* translators: %s: installed plugin version. */ echo esc_html( sprintf( __( 'Version %s', 'gallery-for-spotwalla' ), $plugin['version'] ) ); ?></span>
				</p>
			</div>
		</div>
		<hr class="wp-header-end" />
		<?php
	}

	/**
	 * Warns administrators while the pre-1.0.2 "SpotWalla Gallery" plugin is still active.
	 *
	 * Since 1.0.3 this plugin moves the data into its own SpotGal_* tables, so
	 * changes made in the old copy are no longer shown. The
	 * notice appears only on the Plugins screen and this plugin's admin page.
	 *
	 * @return void
	 */
	public static function legacy_notice() {
		$screen = get_current_screen();
		if ( ! class_exists( 'SW_Gallery', false ) || ! current_user_can( 'activate_plugins' ) || ! $screen ||
			! in_array( $screen->id, array( 'plugins', 'toplevel_page_' . self::SLUG ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The old "SpotWalla Gallery" plugin is still active. Gallery for SpotWalla replaces it and has moved its maps and galleries into its own tables, so changes made in the old plugin are no longer shown. Deactivate and delete "SpotWalla Gallery".', 'gallery-for-spotwalla' ) . '</p></div>';
	}

	/**
	 * Stops the request unless the user can manage options and the nonce is valid.
	 *
	 * @param string $action Nonce action the submitted form was created with.
	 * @return void
	 */
	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage SpotWalla galleries.', 'gallery-for-spotwalla' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Returns an unslashed scalar value from $_POST.
	 *
	 * Callers verify the nonce with authorize() first and sanitize the value for
	 * its own context. Arrays and other non-scalar values return $default.
	 *
	 * @param string $key     Field name.
	 * @param string $default Value to return when the field is missing or not scalar.
	 * @return string
	 */
	private static function posted( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Returns a list of unique positive integer IDs from a $_POST array field.
	 *
	 * Callers verify the nonce with authorize() first.
	 *
	 * @param string $key Field name, such as gallery_ids.
	 * @return int[]|null The IDs (empty if the field is missing), or null if the
	 *                    field is not an array or contains an invalid value.
	 */
	private static function posted_ids( $key ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}
		if ( ! is_array( $_POST[ $key ] ) ) {
			return null;
		}
		$ids = array();
		foreach ( wp_unslash( $_POST[ $key ] ) as $value ) {
			if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $value ) ) {
				return null;
			}
			$ids[] = absint( $value );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Returns the admin tab that manages an item type.
	 *
	 * @param string $type Item type.
	 * @return string "galleries" for galleries, otherwise "maps".
	 */
	private static function tab_for( $type ) {
		return 'gallery' === $type ? 'galleries' : 'maps';
	}

	/**
	 * Redirects back to the plugin page with a status message and exits.
	 *
	 * @param string $message Message key: saved, deleted, invalid, or error.
	 * @param string $tab     Tab to return to.
	 * @return void
	 */
	private static function redirect( $message, $tab = 'maps' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'sw_message' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Loads a map or gallery by ID.
	 *
	 * @param int $id Item ID.
	 * @return array|null The item's row, or null if it does not exist.
	 */
	private static function item( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table( 'items' ), $id ), ARRAY_A );
	}

	/**
	 * Returns the IDs of the galleries a map belongs to.
	 *
	 * @param int $item_id Map ID.
	 * @return int[]
	 */
	private static function gallery_ids_for( $item_id ) {
		global $wpdb;
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT gallery_id FROM %i WHERE item_id = %d', self::table( 'gallery_items' ), $item_id ) ) );
	}

	/**
	 * Validates a SpotWalla link.
	 *
	 * Only HTTPS URLs on spotwalla.com, www.spotwalla.com, or new.spotwalla.com,
	 * without credentials and on the default port, are accepted.
	 *
	 * @param string $url URL to check.
	 * @return string The sanitized URL, or an empty string if it is not allowed.
	 */
	private static function public_url( $url ) {
		$url   = esc_url_raw( trim( $url ), array( 'https' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ||
			'https' !== strtolower( $parts['scheme'] ) ||
			! in_array( strtolower( $parts['host'] ), array( 'spotwalla.com', 'www.spotwalla.com', 'new.spotwalla.com' ), true ) ||
			isset( $parts['user'] ) || isset( $parts['pass'] ) ||
			( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Reads and validates a gallery visibility override from $_POST.
	 *
	 * @param string $key Field name: member_title or member_description.
	 * @return string|null One of OVERRIDES (default "item"), or null if invalid.
	 */
	private static function override( $key ) {
		$value = sanitize_key( self::posted( $key, 'item' ) );
		return in_array( $value, self::OVERRIDES, true ) ? $value : null;
	}

	/**
	 * Validates a density (fill percentage) value.
	 *
	 * @param mixed $value Submitted or stored value.
	 * @return string|null The value if it is empty (use the default) or a key of
	 *                      FILL_FACTORS, otherwise null.
	 */
	private static function fill_factor( $value ) {
		$value = (string) $value;
		return '' === $value || array_key_exists( $value, self::FILL_FACTORS ) ? $value : null;
	}

	/**
	 * Handles the add/edit form for maps and galleries (admin-post action gfsw_save).
	 *
	 * Validates every field, rejects type changes and unknown galleries, saves the
	 * item, and replaces a map's gallery memberships. Always redirects.
	 *
	 * @return void
	 */
	public static function save() {
		self::authorize( 'gfsw_save' );
		global $wpdb;
		$items      = self::table( 'items' );
		$relations  = self::table( 'gallery_items' );
		$id         = absint( self::posted( 'id' ) );
		$type       = sanitize_key( self::posted( 'type' ) );
		$is_gallery = 'gallery' === $type;
		$tab        = self::tab_for( $type );
		$title      = sanitize_text_field( self::posted( 'title' ) );
		$url        = $is_gallery ? '' : self::public_url( self::posted( 'url' ) );
		if ( ! in_array( $type, array_merge( self::MAP_TYPES, array( 'gallery' ) ), true ) ||
			'' === $title || strlen( $title ) > 255 || ( ! $is_gallery && '' === $url ) ) {
			self::redirect( 'invalid', $tab );
		}
		$existing = $id ? self::item( $id ) : null;
		if ( $id && ( ! $existing || $existing['type'] !== $type ) ) {
			self::redirect( 'invalid', $tab );
		}
		$gallery_ids        = $is_gallery ? array() : self::posted_ids( 'gallery_ids' );
		$member_title       = $is_gallery ? self::override( 'member_title' ) : 'item';
		$member_description = $is_gallery ? self::override( 'member_description' ) : 'item';
		$fill_factor        = self::fill_factor( self::posted( 'fill_factor' ) );
		if ( null === $gallery_ids || null === $member_title || null === $member_description || null === $fill_factor ) {
			self::redirect( 'invalid', $tab );
		}
		if ( $gallery_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $gallery_ids ), '%d' ) );
			// $placeholders contains only %d placeholders, one per ID.
			$found = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE type = 'gallery' AND id IN ($placeholders)", array_merge( array( $items ), $gallery_ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( count( $found ) !== count( $gallery_ids ) ) {
				self::redirect( 'invalid', $tab );
			}
		}
		$data = array(
			'type'               => $type,
			'title'              => $title,
			'description'        => sanitize_textarea_field( self::posted( 'description' ) ),
			'url'                => $url,
			'show_title'         => $is_gallery || '1' === self::posted( 'show_title' ) ? 1 : 0,
			'show_description'   => $is_gallery || '1' === self::posted( 'show_description' ) ? 1 : 0,
			'member_title'       => $member_title,
			'member_description' => $member_description,
			'fill_factor'        => $fill_factor,
			'inherit_theme'      => '1' === self::posted( 'inherit_theme' ) ? 1 : 0,
			'background'         => sanitize_hex_color( self::posted( 'background' ) ) ?: '#ffffff',
			'color'              => sanitize_hex_color( self::posted( 'color' ) ) ?: '#222222',
			'width'              => max( 200, min( 2400, absint( self::posted( 'width', '800' ) ) ) ),
			'height'             => max( 200, min( 2400, absint( self::posted( 'height', '450' ) ) ) ),
		);
		$result = $id ? $wpdb->update( $items, $data, array( 'id' => $id ) ) : $wpdb->insert( $items, $data );
		if ( false === $result ) {
			self::redirect( 'error', $tab );
		}
		if ( ! $is_gallery ) {
			$item_id = $id ?: (int) $wpdb->insert_id;
			if ( false === $wpdb->delete( $relations, array( 'item_id' => $item_id ) ) ) {
				self::redirect( 'error', $tab );
			}
			foreach ( $gallery_ids as $gallery_id ) {
				if ( false === $wpdb->insert( $relations, array( 'gallery_id' => $gallery_id, 'item_id' => $item_id ) ) ) {
					self::redirect( 'error', $tab );
				}
			}
		}
		self::redirect( 'saved', $tab );
	}

	/**
	 * Deletes a map or gallery and its memberships (admin-post action gfsw_delete).
	 *
	 * Deleting a gallery keeps its maps. Always redirects.
	 *
	 * @return void
	 */
	public static function delete() {
		$id = absint( self::posted( 'id' ) );
		self::authorize( 'gfsw_delete_' . $id );
		global $wpdb;
		$item = self::item( $id );
		if ( ! $item ) {
			self::redirect( 'invalid' );
		}
		$tab    = self::tab_for( $item['type'] );
		$column = 'gallery' === $item['type'] ? 'gallery_id' : 'item_id';
		if ( false === $wpdb->delete( self::table( 'gallery_items' ), array( $column => $id ) ) ) {
			self::redirect( 'error', $tab );
		}
		$result = $wpdb->delete( self::table( 'items' ), array( 'id' => $id ) );
		self::redirect( false === $result ? 'error' : 'deleted', $tab );
	}

	/**
	 * Saves the data-retention setting (admin-post action gfsw_settings).
	 *
	 * @return void
	 */
	public static function settings() {
		self::authorize( 'gfsw_settings' );
		global $wpdb;
		$tab    = 'galleries' === sanitize_key( self::posted( 'tab' ) ) ? 'galleries' : 'maps';
		$result = $wpdb->replace(
			self::table( 'settings' ),
			array( 'setting_key' => 'delete_on_deactivation', 'setting_value' => '1' === self::posted( 'delete_on_deactivation' ) ? '1' : '0' )
		);
		self::redirect( false === $result ? 'error' : 'saved', $tab );
	}

	/**
	 * Outputs a form row for a gallery's title or description visibility override.
	 *
	 * @param string $name  Field name.
	 * @param string $label Row label.
	 * @param string $value Currently selected override.
	 * @return void
	 */
	private static function override_select( $name, $label, $value ) {
		$labels = array(
			'item' => __( "Use each map's setting", 'gallery-for-spotwalla' ),
			'show' => __( 'Show for all maps', 'gallery-for-spotwalla' ),
			'hide' => __( 'Hide for all maps', 'gallery-for-spotwalla' ),
		);
		?>
		<tr><th><label for="sw-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $labels as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select></td></tr>
		<?php
	}

	/**
	 * Outputs the density (fill percentage) form row.
	 *
	 * @param string $value      Currently selected value; empty uses the default.
	 * @param bool   $is_gallery Whether the row is for a gallery (an override for
	 *                           its maps) or for a map.
	 * @return void
	 */
	private static function fill_select( $value, $is_gallery ) {
		$label = $is_gallery ? __( 'Map density in this gallery', 'gallery-for-spotwalla' ) : __( 'Density/Fill percentage', 'gallery-for-spotwalla' );
		?>
		<tr><th><label for="sw-fill-factor"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-fill-factor" name="fill_factor">
			<option value="" <?php selected( $value, '' ); ?>><?php echo esc_html( $is_gallery ? __( "Use each map's setting", 'gallery-for-spotwalla' ) : __( 'SpotWalla trip setting', 'gallery-for-spotwalla' ) ); ?></option>
			<?php foreach ( self::FILL_FACTORS as $key => $text ) : ?>
				<?php
				if ( '0' === (string) $key ) {
					$text = __( 'None', 'gallery-for-spotwalla' );
				} elseif ( '100' === (string) $key ) {
					/* translators: %s: the percentage "100%". */
					$text = sprintf( __( 'All (%s)', 'gallery-for-spotwalla' ), '100%' );
				}
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) $value, (string) $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select><p class="description"><?php echo esc_html( $is_gallery ? __( 'Overrides the density of every trip map shown in this gallery.', 'gallery-for-spotwalla' ) : __( "Sets SpotWalla's Density/Fill Percentage (number of locations shown). Applies to trips only.", 'gallery-for-spotwalla' ) ); ?></p></td></tr>
		<?php
	}

	/**
	 * Returns the translated label for an item type.
	 *
	 * @param string $type Item type: track, trip, retrospective, or gallery.
	 * @return string
	 */
	private static function type_label( $type ) {
		$labels = array(
			'track'         => __( 'Track', 'gallery-for-spotwalla' ),
			'trip'          => __( 'Trip', 'gallery-for-spotwalla' ),
			'retrospective' => __( 'Retrospective', 'gallery-for-spotwalla' ),
			'gallery'       => __( 'Gallery', 'gallery-for-spotwalla' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Returns the " (opens in a new tab)" text for screen readers.
	 *
	 * @return string HTML.
	 */
	private static function new_tab_text() {
		return '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'gallery-for-spotwalla' ) . '</span>';
	}

	/**
	 * Outputs the Maps, Galleries, and About tab navigation.
	 *
	 * @param string $tab Active tab.
	 * @return void
	 */
	private static function nav( $tab ) {
		$tabs = array(
			'maps'      => __( 'Maps', 'gallery-for-spotwalla' ),
			'galleries' => __( 'Galleries', 'gallery-for-spotwalla' ),
			'about'     => __( 'About', 'gallery-for-spotwalla' ),
		);
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Gallery for SpotWalla sections', 'gallery-for-spotwalla' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab<?php echo esc_attr( $tab === $key ? ' nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"<?php if ( $tab === $key ) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Outputs the About tab: version, disclaimer, links, license, recommendations, and issue-reporting steps.
	 *
	 * @return void
	 */
	private static function about() {
		global $wp_version;
		$plugin  = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		$links   = array(
			__( 'Repository', 'gallery-for-spotwalla' )                  => self::REPOSITORY,
			__( 'README', 'gallery-for-spotwalla' )                      => self::REPOSITORY . '/blob/main/README.md',
			__( 'License (GPLv2 or later)', 'gallery-for-spotwalla' )    => self::REPOSITORY . '/blob/main/LICENSE',
			__( 'SpotWalla terms and privacy', 'gallery-for-spotwalla' ) => 'https://spotwalla.com/tos',
		);
		/* translators: 1: plugin version, 2: WordPress version, 3: PHP version. */
		$details = sprintf( __( 'Plugin %1$s, WordPress %2$s, PHP %3$s', 'gallery-for-spotwalla' ), $plugin['version'], $wp_version, PHP_VERSION );
		$allowed = array(
			'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
			'code'   => array(),
			'span'   => array( 'class' => array() ),
			'strong' => array(),
		);
		?>
		<div class="wrap">
			<?php self::header(); ?>
			<?php self::nav( 'about' ); ?>
			<h2><?php esc_html_e( 'About', 'gallery-for-spotwalla' ); ?></h2>
			<p><?php esc_html_e( "Gallery for SpotWalla manages and embeds public SpotWalla tracks, trips, retrospectives, and galleries. Embedded maps are loaded from spotwalla.com in each visitor's browser and are subject to SpotWalla's terms and privacy policy.", 'gallery-for-spotwalla' ); ?></p>
			<div class="notice notice-info inline"><p><?php echo wp_kses( __( '<strong>Disclaimer:</strong> Gallery for SpotWalla is an independent project. It is <strong>not</strong> affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used here only to describe compatibility. Direct questions about the plugin to this project, not to SpotWalla.', 'gallery-for-spotwalla' ), $allowed ); ?></p></div>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Version', 'gallery-for-spotwalla' ); ?></th><td><?php echo esc_html( $plugin['version'] ); ?></td></tr>
				<?php foreach ( $links as $label => $url ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ) . wp_kses( self::new_tab_text(), $allowed ); ?></a></td></tr>
				<?php endforeach; ?>
			</table>
			<p><?php echo wp_kses( __( "The plugin is free software released under the GNU General Public License, version 2 or (at your option) any later version. Copies of <code>readme.txt</code>, <code>README.md</code>, and <code>LICENSE</code> are included in the plugin's folder.", 'gallery-for-spotwalla' ), $allowed ); ?></p>
			<h2><?php esc_html_e( 'Recommended Plugins', 'gallery-for-spotwalla' ); ?></h2>
			<p>
				<?php
				/* translators: 1: link to the Motorcycle Rally Scoring App project, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'For motorcycle rally event management and scoring, see the related <a href="%1$s" target="_blank" rel="noopener noreferrer">Motorcycle Rally Scoring App plugin</a>, a separate, optional O\'ConnorADV project%2$s.', 'gallery-for-spotwalla' ), esc_url( 'https://github.com/oconnoradv/MotorcycleRallyScoringApp' ), self::new_tab_text() ), $allowed );
				?>
			</p>
			<h2><?php esc_html_e( 'Reporting issues', 'gallery-for-spotwalla' ); ?></h2>
			<ol>
				<li>
				<?php
				/* translators: 1: link to the GitHub issue list, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'Search <a href="%1$s" target="_blank" rel="noopener noreferrer">existing issues%2$s</a> to see whether the problem is already reported.', 'gallery-for-spotwalla' ), esc_url( self::REPOSITORY . '/issues' ), self::new_tab_text() ), $allowed );
				?>
				</li>
				<li>
				<?php
				/* translators: 1: link to the new GitHub issue form, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'If not, sign in to GitHub and <a href="%1$s" target="_blank" rel="noopener noreferrer">open a new issue%2$s</a> with a short, descriptive title.', 'gallery-for-spotwalla' ), esc_url( self::REPOSITORY . '/issues/new' ), self::new_tab_text() ), $allowed );
				?>
				</li>
				<li><?php esc_html_e( 'Describe what you expected and what happened, the steps to reproduce it, the map type (trip, track, or retrospective), and any error messages or screenshots.', 'gallery-for-spotwalla' ); ?></li>
				<li>
				<?php
				/* translators: %s: plugin, WordPress, and PHP versions. */
				echo wp_kses( sprintf( __( 'Include your environment: %s.', 'gallery-for-spotwalla' ), '<code>' . esc_html( $details ) . '</code>' ), $allowed );
				?>
				</li>
				<li><?php esc_html_e( 'Do not include passwords, private SpotWalla links, or other personal data. Report security vulnerabilities privately to the repository owner rather than in a public issue.', 'gallery-for-spotwalla' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Outputs the plugin's admin page.
	 *
	 * The tab and edit ID come from the query string. Tab values are copied from
	 * string literals so request data is never echoed. Editing an item always
	 * opens the tab for its type.
	 *
	 * @return void
	 */
	public static function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table     = self::table( 'items' );
		$relations = self::table( 'gallery_items' );
		// Read-only navigation parameters; every state change is a nonce-protected POST.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = 'maps';
		if ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'galleries' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'galleries';
		} elseif ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'about' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'about';
		}
		$edit    = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? self::item( absint( $_GET['edit'] ) ) : null;
		$message = isset( $_GET['sw_message'] ) && is_scalar( $_GET['sw_message'] ) ? sanitize_key( $_GET['sw_message'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $edit && 'gallery' === $edit['type'] ) {
			$tab = 'galleries';
		} elseif ( $edit ) {
			$tab = 'maps';
		}
		if ( 'about' === $tab ) {
			self::about();
			return;
		}
		$is_gallery = 'galleries' === $tab;
		$galleries  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE type = 'gallery' ORDER BY id DESC", $table ), ARRAY_A );
		$rows       = $is_gallery ? $galleries : $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE type <> 'gallery' ORDER BY id DESC", $table ), ARRAY_A );
		$by_item    = array();
		$counts     = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT r.gallery_id, r.item_id FROM %i r INNER JOIN %i g ON g.id = r.gallery_id AND g.type = 'gallery' INNER JOIN %i m ON m.id = r.item_id AND m.type <> 'gallery' ORDER BY r.gallery_id ASC", $relations, $table, $table ), ARRAY_A ) as $relation ) {
			$by_item[ $relation['item_id'] ][] = $relation['gallery_id'];
			$counts[ $relation['gallery_id'] ] = ( isset( $counts[ $relation['gallery_id'] ] ) ? $counts[ $relation['gallery_id'] ] : 0 ) + 1;
		}
		$defaults = array( 'id' => 0, 'type' => $is_gallery ? 'gallery' : 'trip', 'title' => '', 'description' => '', 'url' => '', 'show_title' => 1, 'show_description' => 1, 'member_title' => 'item', 'member_description' => 'item', 'fill_factor' => '', 'inherit_theme' => 1, 'background' => '#ffffff', 'color' => '#222222', 'width' => 800, 'height' => 450 );
		$item     = $edit ?: $defaults;
		$selected = $edit && ! $is_gallery ? self::gallery_ids_for( $edit['id'] ) : array();
		if ( $is_gallery ) {
			$form_heading = $edit ? __( 'Edit gallery', 'gallery-for-spotwalla' ) : __( 'Add gallery', 'gallery-for-spotwalla' );
			$save_label   = __( 'Save gallery', 'gallery-for-spotwalla' );
		} else {
			$form_heading = $edit ? __( 'Edit map', 'gallery-for-spotwalla' ) : __( 'Add map', 'gallery-for-spotwalla' );
			$save_label   = __( 'Save map', 'gallery-for-spotwalla' );
		}
		$messages = array(
			'saved'   => __( 'Saved.', 'gallery-for-spotwalla' ),
			'deleted' => $is_gallery ? __( 'Deleted. Maps in a deleted gallery remain available individually.', 'gallery-for-spotwalla' ) : __( 'Deleted.', 'gallery-for-spotwalla' ),
			'invalid' => __( 'Not saved. Supply a title (maximum 255 bytes), valid options and galleries, and an HTTPS SpotWalla public URL for maps. Existing types cannot be changed.', 'gallery-for-spotwalla' ),
			'error'   => __( 'A database error occurred. Please try again.', 'gallery-for-spotwalla' ),
		);
		$base_url = admin_url( 'admin.php' );
		?>
		<div class="wrap">
			<?php self::header(); ?>
			<?php self::nav( $tab ); ?>
			<?php if ( isset( $messages[ $message ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $message, array( 'invalid', 'error' ), true ) ? 'notice-error' : 'notice-success' ); ?>"><p><?php echo esc_html( $messages[ $message ] ); ?></p></div>
			<?php endif; ?>
			<p>
			<?php
			echo esc_html( $is_gallery ? __( "Create galleries here, then add maps to them from the Maps tab. A gallery can override its maps' title and description visibility.", 'gallery-for-spotwalla' ) : __( 'Add tracks, trips, and retrospectives, and assign each map to any number of galleries.', 'gallery-for-spotwalla' ) );
			echo ' ';
			/* translators: 1: example shortcode, 2: shortcode name used by earlier versions. */
			echo wp_kses( sprintf( __( 'Embed any map or gallery with %1$s. Existing %2$s shortcodes continue to work.', 'gallery-for-spotwalla' ), '<code>[gallery_for_spotwalla id="123"]</code>', '<code>[spotwalla_gallery]</code>' ), array( 'code' => array() ) );
			?>
			</p>
			<h2><?php echo esc_html( $form_heading ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gfsw_save">
				<input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php if ( $is_gallery ) : ?><input type="hidden" name="type" value="gallery"><?php endif; ?>
				<?php wp_nonce_field( 'gfsw_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="sw-title"><?php esc_html_e( 'Title', 'gallery-for-spotwalla' ); ?></label></th><td><input class="regular-text" id="sw-title" name="title" required maxlength="255" value="<?php echo esc_attr( $item['title'] ); ?>"></td></tr>
					<?php if ( ! $is_gallery ) : ?>
						<tr><th><label for="sw-type"><?php esc_html_e( 'Type', 'gallery-for-spotwalla' ); ?></label></th><td>
							<?php if ( $edit ) : ?>
								<input type="hidden" name="type" value="<?php echo esc_attr( $item['type'] ); ?>">
								<span><?php echo esc_html( self::type_label( $item['type'] ) ); ?></span>
							<?php else : ?>
								<select id="sw-type" name="type">
									<?php foreach ( self::MAP_TYPES as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['type'], $type ); ?>><?php echo esc_html( self::type_label( $type ) ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td></tr>
					<?php endif; ?>
					<tr><th><label for="sw-description"><?php esc_html_e( 'Description', 'gallery-for-spotwalla' ); ?></label></th><td><textarea class="large-text" id="sw-description" name="description" rows="4"><?php
						// WordPress esc_textarea() escapes output for this textarea context.
						// nosemgrep: php.lang.security.injection.echoed-request.echoed-request
						echo esc_textarea( $item['description'] );
					?></textarea></td></tr>
					<?php if ( $is_gallery ) : ?>
						<?php self::override_select( 'member_title', __( 'Map titles in this gallery', 'gallery-for-spotwalla' ), $item['member_title'] ); ?>
						<?php self::override_select( 'member_description', __( 'Map descriptions in this gallery', 'gallery-for-spotwalla' ), $item['member_description'] ); ?>
						<?php self::fill_select( $item['fill_factor'], true ); ?>
					<?php else : ?>
						<tr><th><label for="sw-url"><?php esc_html_e( 'Public SpotWalla URL', 'gallery-for-spotwalla' ); ?></label></th><td><input type="url" class="large-text" id="sw-url" name="url" required value="<?php echo esc_attr( $item['url'] ); ?>"><p class="description"><?php esc_html_e( 'Use the HTTPS public or embed link supplied by SpotWalla.', 'gallery-for-spotwalla' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Visibility', 'gallery-for-spotwalla' ); ?></th><td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Visibility', 'gallery-for-spotwalla' ); ?></legend>
							<label><input type="checkbox" name="show_title" value="1" <?php checked( $item['show_title'], 1 ); ?>> <?php esc_html_e( 'Show title', 'gallery-for-spotwalla' ); ?></label><br>
							<label><input type="checkbox" name="show_description" value="1" <?php checked( $item['show_description'], 1 ); ?>> <?php esc_html_e( 'Show description', 'gallery-for-spotwalla' ); ?></label>
							<p class="description"><?php esc_html_e( 'A gallery can override these settings when it displays this map. Hiding the title also hides its link to SpotWalla.', 'gallery-for-spotwalla' ); ?></p>
						</fieldset></td></tr>
						<?php self::fill_select( $item['fill_factor'], false ); ?>
						<tr><th><?php esc_html_e( 'Galleries', 'gallery-for-spotwalla' ); ?></th><td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Galleries', 'gallery-for-spotwalla' ); ?></legend>
							<?php foreach ( $galleries as $gallery ) : ?>
								<label><input type="checkbox" name="gallery_ids[]" value="<?php echo esc_attr( $gallery['id'] ); ?>" <?php checked( in_array( (int) $gallery['id'], $selected, true ) ); ?>> <?php echo esc_html( $gallery['title'] . ' (#' . $gallery['id'] . ')' ); ?></label><br>
							<?php endforeach; ?>
							<?php if ( ! $galleries ) : ?>
								<p class="description"><?php esc_html_e( 'No galleries yet.', 'gallery-for-spotwalla' ); ?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => 'galleries' ), $base_url ) ); ?>"><?php esc_html_e( 'Create one on the Galleries tab.', 'gallery-for-spotwalla' ); ?></a></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Select any number of galleries. Galleries cannot be nested.', 'gallery-for-spotwalla' ); ?></p>
							<?php endif; ?>
						</fieldset></td></tr>
					<?php endif; ?>
					<tr><th><?php esc_html_e( 'Description appearance', 'gallery-for-spotwalla' ); ?></th><td><label><input type="checkbox" name="inherit_theme" value="1" <?php checked( $item['inherit_theme'], 1 ); ?>> <?php esc_html_e( 'Inherit site theme (ignore custom colors and dimensions)', 'gallery-for-spotwalla' ); ?></label></td></tr>
					<tr><th><label for="sw-background"><?php esc_html_e( 'Description background color', 'gallery-for-spotwalla' ); ?></label></th><td><input type="color" id="sw-background" name="background" value="<?php echo esc_attr( $item['background'] ); ?>"></td></tr>
					<tr><th><label for="sw-color"><?php esc_html_e( 'Description text and link color', 'gallery-for-spotwalla' ); ?></label></th><td><input type="color" id="sw-color" name="color" value="<?php echo esc_attr( $item['color'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Custom map dimensions', 'gallery-for-spotwalla' ); ?></th><td>
						<label for="sw-width"><?php esc_html_e( 'Width (px)', 'gallery-for-spotwalla' ); ?></label> <input type="number" id="sw-width" name="width" min="200" max="2400" value="<?php echo esc_attr( $item['width'] ); ?>">
						<?php if ( ! $is_gallery ) : ?>
							<label for="sw-height"><?php esc_html_e( 'Map height (px)', 'gallery-for-spotwalla' ); ?></label> <input type="number" id="sw-height" name="height" min="200" max="2400" value="<?php echo esc_attr( $item['height'] ); ?>">
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Widths shrink to fit small screens. Colors apply to the card, not the remote map.', 'gallery-for-spotwalla' ); ?></p>
					</td></tr>
				</table>
				<?php submit_button( $save_label ); ?>
				<?php if ( $edit ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab ), $base_url ) ); ?>"><?php esc_html_e( 'Cancel editing', 'gallery-for-spotwalla' ); ?></a><?php endif; ?>
			</form>
			<h2><?php echo esc_html( $is_gallery ? __( 'Galleries', 'gallery-for-spotwalla' ) : __( 'Maps', 'gallery-for-spotwalla' ) ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'ID', 'gallery-for-spotwalla' ); ?></th><th><?php esc_html_e( 'Title', 'gallery-for-spotwalla' ); ?></th><?php if ( ! $is_gallery ) : ?><th><?php esc_html_e( 'Type', 'gallery-for-spotwalla' ); ?></th><?php endif; ?><th><?php echo esc_html( $is_gallery ? __( 'Maps', 'gallery-for-spotwalla' ) : __( 'Gallery IDs', 'gallery-for-spotwalla' ) ); ?></th><th><?php esc_html_e( 'Shortcode', 'gallery-for-spotwalla' ); ?></th><th><?php esc_html_e( 'Actions', 'gallery-for-spotwalla' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['title'] ); ?></td>
						<?php if ( $is_gallery ) : ?>
							<td><?php echo esc_html( isset( $counts[ $row['id'] ] ) ? $counts[ $row['id'] ] : 0 ); ?></td>
						<?php else : ?>
							<td><?php echo esc_html( self::type_label( $row['type'] ) ); ?></td><td><?php echo esc_html( isset( $by_item[ $row['id'] ] ) ? implode( ', ', $by_item[ $row['id'] ] ) : '—' ); ?></td>
						<?php endif; ?>
						<td><code><?php echo esc_html( '[gallery_for_spotwalla id="' . $row['id'] . '"]' ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'edit' => $row['id'] ), $base_url ) ); ?>"><?php esc_html_e( 'Edit', 'gallery-for-spotwalla' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="gfsw_delete"><input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>">
								<?php wp_nonce_field( 'gfsw_delete_' . $row['id'] ); ?>
								<button class="button-link-delete" type="submit"><?php esc_html_e( 'Delete', 'gallery-for-spotwalla' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><tr><td colspan="<?php echo esc_attr( $is_gallery ? 5 : 6 ); ?>"><?php echo esc_html( $is_gallery ? __( 'No galleries yet.', 'gallery-for-spotwalla' ) : __( 'No maps yet.', 'gallery-for-spotwalla' ) ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Data retention', 'gallery-for-spotwalla' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gfsw_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'gfsw_settings' ); ?>
				<label><input type="checkbox" name="delete_on_deactivation" value="1" <?php checked( self::setting( 'delete_on_deactivation' ), '1' ); ?>> <?php esc_html_e( 'Permanently delete all plugin tables, settings, maps, and galleries on deactivation.', 'gallery-for-spotwalla' ); ?></label>
				<p><?php esc_html_e( 'Unchecked by default: retain data for reactivation. Deletion cannot be undone; embedded shortcodes will have no content.', 'gallery-for-spotwalla' ); ?></p>
								<?php submit_button( __( 'Save retention setting', 'gallery-for-spotwalla' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Builds the inline style attribute for a map card or gallery.
	 *
	 * @param array $item Map or gallery row.
	 * @return string The attribute (with a leading space), or an empty string when
	 *                 the item inherits the site theme.
	 */
	private static function style( $item ) {
		if ( $item['inherit_theme'] ) {
			return '';
		}
		$background = sanitize_hex_color( $item['background'] ) ?: '#ffffff';
		$color      = sanitize_hex_color( $item['color'] ) ?: '#222222';
		$width      = max( 200, min( 2400, absint( $item['width'] ) ) );
		return ' style="' . esc_attr( "background-color:$background;color:$color;width:{$width}px;max-width:100%;" ) . '"';
	}

	/**
	 * Decides whether a map's title or description is shown.
	 *
	 * A gallery's "show" or "hide" override wins; otherwise the map's own setting applies.
	 *
	 * @param array      $item    Map row.
	 * @param array|null $gallery Gallery being rendered, or null for a single map.
	 * @param string     $field   "title" or "description".
	 * @return bool
	 */
	private static function visible( $item, $gallery, $field ) {
		$override = $gallery && isset( $gallery[ 'member_' . $field ] ) ? $gallery[ 'member_' . $field ] : 'item';
		if ( 'show' === $override || 'hide' === $override ) {
			return 'show' === $override;
		}
		return ! isset( $item[ 'show_' . $field ] ) || '1' === (string) $item[ 'show_' . $field ];
	}

	/**
	 * Adds the density setting to a trip's embed URL.
	 *
	 * A gallery's density, when set, overrides the map's. Tracks and
	 * retrospectives are returned unchanged because SpotWalla ignores fillFactor there.
	 *
	 * @param string     $url     Validated SpotWalla URL.
	 * @param array      $item    Map row.
	 * @param array|null $gallery Gallery being rendered, or null for a single map.
	 * @return string
	 */
	private static function embed_url( $url, $item, $gallery ) {
		if ( 'trip' !== $item['type'] ) {
			return $url;
		}
		$fill = $gallery && isset( $gallery['fill_factor'] ) && '' !== (string) $gallery['fill_factor'] ? $gallery['fill_factor'] : ( isset( $item['fill_factor'] ) ? $item['fill_factor'] : '' );
		$fill = self::fill_factor( $fill );
		return null === $fill || '' === $fill ? $url : add_query_arg( 'fillFactor', $fill, $url );
	}

	/**
	 * Renders one map: optional title link and description, then a lazy-loaded iframe.
	 *
	 * @param array      $item    Map row.
	 * @param array|null $gallery Gallery being rendered, or null for a single map.
	 * @return string HTML, or an empty string if the stored URL is no longer valid.
	 */
	private static function card( $item, $gallery = null ) {
		$url = self::public_url( $item['url'] );
		if ( ! $url ) {
			return '';
		}
		$height     = $item['inherit_theme'] ? 450 : max( 200, min( 2400, absint( $item['height'] ) ) );
		$link_style = $item['inherit_theme'] ? '' : ' style="color:inherit;"';
		$html       = '<article class="sw-gallery-entry sw-gallery-' . esc_attr( $item['type'] ) . '"' . self::style( $item ) . '>';
		if ( self::visible( $item, $gallery, 'title' ) ) {
			$html .= '<h3><a href="' . esc_url( $url ) . '"' . $link_style . '>' . esc_html( $item['title'] ) . '</a></h3>';
		}
		if ( self::visible( $item, $gallery, 'description' ) ) {
			$html .= '<p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		}
		return $html . '<iframe src="' . esc_url( self::embed_url( $url, $item, $gallery ) ) . '" title="' . esc_attr( $item['title'] ) . '" loading="lazy" referrerpolicy="no-referrer" width="100%" height="' . esc_attr( $height ) . '" style="display:block;width:100%;max-width:100%;border:0;" allowfullscreen></iframe>' .
			'</article>';
	}

	/**
	 * Shortcode callback for [gallery_for_spotwalla] and the legacy [spotwalla_gallery].
	 *
	 * Renders a single map, or a gallery with each of its maps in creation order.
	 *
	 * @param array|string $attributes Shortcode attributes; "id" is the map or gallery ID.
	 * @return string HTML, or an empty string for a missing or invalid ID.
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'gallery_for_spotwalla' );
		if ( ! is_scalar( $attributes['id'] ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $attributes['id'] ) ) {
			return '';
		}
		$item = self::item( absint( $attributes['id'] ) );
		if ( ! $item ) {
			return '';
		}
		if ( 'gallery' !== $item['type'] ) {
			return self::card( $item );
		}
		global $wpdb;
		$table     = self::table( 'items' );
		$relations = self::table( 'gallery_items' );
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT m.* FROM %i m INNER JOIN %i r ON r.item_id = m.id WHERE r.gallery_id = %d AND m.type <> 'gallery' ORDER BY m.id ASC", $table, $relations, $item['id'] ), ARRAY_A );
		$html      = '<section class="sw-gallery-group"' . self::style( $item ) . '><h2>' . esc_html( $item['title'] ) . '</h2><p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		foreach ( $rows as $row ) {
			$html .= self::card( $row, $item );
		}
		return $html . '</section>';
	}
}

register_activation_hook( __FILE__, array( 'Gallery_For_SpotWalla', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Gallery_For_SpotWalla', 'deactivate' ) );
Gallery_For_SpotWalla::init();
