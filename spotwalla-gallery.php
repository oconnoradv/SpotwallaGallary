<?php
/**
 * Plugin Name: SpotWalla Gallery
 * Description: Manage and embed public SpotWalla tracks, trips, retrospectives, and gallery groups. Independent project; not affiliated with or approved by SpotWalla.
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: spotwalla-gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SW_Gallery {
	const DB_VERSION   = '3';
	const REPOSITORY   = 'https://github.com/oconnoradv/SpotwallaGallary';
	const MAP_TYPES    = array( 'track', 'trip', 'retrospective' );
	const OVERRIDES    = array( 'item', 'show', 'hide' );
	// Density/Fill Percentage values offered by the SpotWalla trip viewer (fillFactor).
	const FILL_FACTORS = array( '0' => 'None', '0.1' => '0.1%', '0.3' => '0.3%', '0.5' => '0.5%', '1' => '1%', '3' => '3%', '5' => '5%', '10' => '10%', '20' => '20%', '30' => '30%', '40' => '40%', '50' => '50%', '60' => '60%', '70' => '70%', '80' => '80%', '90' => '90%', '100' => 'All (100%)' );

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'SW_' . $name;
	}

	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Please activate SpotWalla Gallery separately on each site, not network-wide.', 'spotwalla-gallery' ) );
		}
		self::install();
	}

	private static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$items     = self::table( 'items' );
		$settings  = self::table( 'settings' );
		$relations = self::table( 'gallery_items' );
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
		$wpdb->query( "INSERT IGNORE INTO $settings (setting_key, setting_value) VALUES ('delete_on_deactivation', '0')" );
		// Version 1.0.0 stored one group per map in items.gallery_id.
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $items LIKE %s", 'gallery_id' ) ) &&
			false === $wpdb->query( "INSERT IGNORE INTO $relations (gallery_id, item_id) SELECT m.gallery_id, m.id FROM $items m INNER JOIN $items g ON g.id = m.gallery_id AND g.type = 'gallery' WHERE m.type <> 'gallery'" ) ) {
			return;
		}
		$wpdb->replace( $settings, array( 'setting_key' => 'db_version', 'setting_value' => self::DB_VERSION ) );
	}

	public static function maybe_upgrade() {
		global $wpdb;
		$settings = self::table( 'settings' );
		$suppress = $wpdb->suppress_errors();
		$version  = $wpdb->get_var( "SELECT setting_value FROM $settings WHERE setting_key = 'db_version'" );
		$wpdb->suppress_errors( $suppress );
		if ( null === $version || version_compare( $version, self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	public static function deactivate() {
		global $wpdb;
		$settings = self::table( 'settings' );
		if ( '1' === $wpdb->get_var( "SELECT setting_value FROM $settings WHERE setting_key = 'delete_on_deactivation'" ) ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( 'gallery_items' ) );
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( 'items' ) );
			$wpdb->query( "DROP TABLE IF EXISTS $settings" );
		}
	}

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sw_gallery_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_sw_gallery_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_sw_gallery_settings', array( __CLASS__, 'settings' ) );
		add_shortcode( 'spotwalla_gallery', array( __CLASS__, 'shortcode' ) );
	}

	public static function menu() {
		add_menu_page( 'SpotWalla Gallery', 'SpotWalla Gallery', 'manage_options', 'spotwalla-gallery', array( __CLASS__, 'admin' ), 'dashicons-location-alt' );
	}

	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage SpotWalla galleries.', 'spotwalla-gallery' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private static function posted( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : $default;
	}

	private static function posted_ids( $key ) {
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
		return array_values( array_unique( $ids ) );
	}

	private static function tab_for( $type ) {
		return 'gallery' === $type ? 'galleries' : 'maps';
	}

	private static function redirect( $message, $tab = 'maps' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'spotwalla-gallery', 'tab' => $tab, 'sw_message' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function item( $id ) {
		global $wpdb;
		$table = self::table( 'items' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
	}

	private static function gallery_ids_for( $item_id ) {
		global $wpdb;
		$relations = self::table( 'gallery_items' );
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT gallery_id FROM $relations WHERE item_id = %d", $item_id ) ) );
	}

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

	private static function override( $key ) {
		$value = sanitize_key( self::posted( $key, 'item' ) );
		return in_array( $value, self::OVERRIDES, true ) ? $value : null;
	}

	private static function fill_factor( $value ) {
		$value = (string) $value;
		return '' === $value || array_key_exists( $value, self::FILL_FACTORS ) ? $value : null;
	}

	public static function save() {
		self::authorize( 'sw_gallery_save' );
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
			$found        = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $items WHERE type = 'gallery' AND id IN ($placeholders)", $gallery_ids ) );
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

	public static function delete() {
		$id = absint( self::posted( 'id' ) );
		self::authorize( 'sw_gallery_delete_' . $id );
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

	public static function settings() {
		self::authorize( 'sw_gallery_settings' );
		global $wpdb;
		$tab    = 'galleries' === sanitize_key( self::posted( 'tab' ) ) ? 'galleries' : 'maps';
		$result = $wpdb->replace(
			self::table( 'settings' ),
			array( 'setting_key' => 'delete_on_deactivation', 'setting_value' => '1' === self::posted( 'delete_on_deactivation' ) ? '1' : '0' )
		);
		self::redirect( false === $result ? 'error' : 'saved', $tab );
	}

	private static function override_select( $name, $label, $value ) {
		$labels = array( 'item' => "Use each map's setting", 'show' => 'Show for all maps', 'hide' => 'Hide for all maps' );
		?>
		<tr><th><label for="sw-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $labels as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select></td></tr>
		<?php
	}

	private static function fill_select( $value, $is_gallery ) {
		$label = $is_gallery ? 'Map density in this gallery' : 'Density/Fill percentage';
		?>
		<tr><th><label for="sw-fill-factor"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-fill-factor" name="fill_factor">
			<option value="" <?php selected( $value, '' ); ?>><?php echo esc_html( $is_gallery ? "Use each map's setting" : 'SpotWalla trip setting' ); ?></option>
			<?php foreach ( self::FILL_FACTORS as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) $value, (string) $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select><p class="description"><?php echo esc_html( $is_gallery ? 'Overrides the density of every trip map shown in this gallery.' : 'Sets SpotWalla\'s Density/Fill Percentage (number of locations shown). Applies to trips only.' ); ?></p></td></tr>
		<?php
	}

	private static function nav( $tab ) {
		?>
		<nav class="nav-tab-wrapper" aria-label="SpotWalla Gallery sections">
			<?php foreach ( array( 'maps' => 'Maps', 'galleries' => 'Galleries', 'about' => 'About' ) as $key => $label ) : ?>
				<a class="nav-tab<?php echo esc_attr( $tab === $key ? ' nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'spotwalla-gallery', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"<?php if ( $tab === $key ) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function about() {
		global $wp_version;
		$plugin  = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		$links   = array(
			'Repository' => self::REPOSITORY,
			'README'     => self::REPOSITORY . '/blob/main/README.md',
			'License'    => self::REPOSITORY . '/blob/main/LICENSE',
		);
		$details = sprintf( 'Plugin %s, WordPress %s, PHP %s', $plugin['version'], $wp_version, PHP_VERSION );
		?>
		<div class="wrap">
			<h1>SpotWalla Gallery</h1>
			<?php self::nav( 'about' ); ?>
			<h2>About</h2>
			<p>SpotWalla Gallery manages and embeds public SpotWalla tracks, trips, retrospectives, and galleries.</p>
			<div class="notice notice-info inline"><p><strong>Disclaimer:</strong> SpotWalla Gallery is an independent project. It is <strong>not</strong> affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used here only to describe compatibility. Direct questions about the plugin to this project, not to SpotWalla.</p></div>
			<table class="form-table" role="presentation">
				<tr><th>Version</th><td><?php echo esc_html( $plugin['version'] ); ?></td></tr>
				<?php foreach ( $links as $label => $url ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?><span class="screen-reader-text"> (opens in a new tab)</span></a></td></tr>
				<?php endforeach; ?>
			</table>
			<p>The plugin is released under the MIT License. Copies of <code>README.md</code> and <code>LICENSE</code> are also included in the plugin's folder.</p>
			<h2>Reporting issues</h2>
			<ol>
				<li>Search <a href="<?php echo esc_url( self::REPOSITORY . '/issues' ); ?>" target="_blank" rel="noopener noreferrer">existing issues<span class="screen-reader-text"> (opens in a new tab)</span></a> to see whether the problem is already reported.</li>
				<li>If not, sign in to GitHub and <a href="<?php echo esc_url( self::REPOSITORY . '/issues/new' ); ?>" target="_blank" rel="noopener noreferrer">open a new issue<span class="screen-reader-text"> (opens in a new tab)</span></a> with a short, descriptive title.</li>
				<li>Describe what you expected and what happened, the steps to reproduce it, the map type (trip, track, or retrospective), and any error messages or screenshots.</li>
				<li>Include your environment: <code><?php echo esc_html( $details ); ?></code>.</li>
				<li>Do not include passwords, private SpotWalla links, or other personal data. Report security vulnerabilities privately to the repository owner rather than in a public issue.</li>
			</ol>
		</div>
		<?php
	}

	public static function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table     = self::table( 'items' );
		$relations = self::table( 'gallery_items' );
		$tab = 'maps';
		if ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'galleries' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'galleries';
		} elseif ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'about' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'about';
		}
		$edit = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? self::item( absint( $_GET['edit'] ) ) : null;
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
		$galleries  = $wpdb->get_results( "SELECT * FROM $table WHERE type = 'gallery' ORDER BY id DESC", ARRAY_A );
		$rows       = $is_gallery ? $galleries : $wpdb->get_results( "SELECT * FROM $table WHERE type <> 'gallery' ORDER BY id DESC", ARRAY_A );
		$by_item    = array();
		$counts     = array();
		foreach ( $wpdb->get_results( "SELECT r.gallery_id, r.item_id FROM $relations r INNER JOIN $table g ON g.id = r.gallery_id AND g.type = 'gallery' INNER JOIN $table m ON m.id = r.item_id AND m.type <> 'gallery' ORDER BY r.gallery_id ASC", ARRAY_A ) as $relation ) {
			$by_item[ $relation['item_id'] ][] = $relation['gallery_id'];
			$counts[ $relation['gallery_id'] ] = ( isset( $counts[ $relation['gallery_id'] ] ) ? $counts[ $relation['gallery_id'] ] : 0 ) + 1;
		}
		$defaults = array( 'id' => 0, 'type' => $is_gallery ? 'gallery' : 'trip', 'title' => '', 'description' => '', 'url' => '', 'show_title' => 1, 'show_description' => 1, 'member_title' => 'item', 'member_description' => 'item', 'fill_factor' => '', 'inherit_theme' => 1, 'background' => '#ffffff', 'color' => '#222222', 'width' => 800, 'height' => 450 );
		$item     = $edit ?: $defaults;
		$selected = $edit && ! $is_gallery ? self::gallery_ids_for( $edit['id'] ) : array();
		$noun     = $is_gallery ? 'gallery' : 'map';
		$messages = array(
			'saved'   => 'Saved.',
			'deleted' => $is_gallery ? 'Deleted. Maps in a deleted gallery remain available individually.' : 'Deleted.',
			'invalid' => 'Not saved. Supply a title (maximum 255 bytes), valid options and galleries, and an HTTPS SpotWalla public URL for maps. Existing types cannot be changed.',
			'error'   => 'A database error occurred. Please try again.',
		);
		$message  = isset( $_GET['sw_message'] ) && is_scalar( $_GET['sw_message'] ) ? sanitize_key( $_GET['sw_message'] ) : '';
		$base_url = admin_url( 'admin.php' );
		?>
		<div class="wrap">
			<h1>SpotWalla Gallery</h1>
			<?php self::nav( $tab ); ?>
			<?php if ( isset( $messages[ $message ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $message, array( 'invalid', 'error' ), true ) ? 'notice-error' : 'notice-success' ); ?>"><p><?php echo esc_html( $messages[ $message ] ); ?></p></div>
			<?php endif; ?>
			<p><?php echo esc_html( $is_gallery ? 'Create galleries here, then add maps to them from the Maps tab. A gallery can override its maps\' title and description visibility.' : 'Add tracks, trips, and retrospectives, and assign each map to any number of galleries.' ); ?> Embed any map or gallery with <code>[spotwalla_gallery id="123"]</code>.</p>
			<h2><?php echo esc_html( ( $edit ? 'Edit ' : 'Add ' ) . $noun ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sw_gallery_save">
				<input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php if ( $is_gallery ) : ?><input type="hidden" name="type" value="gallery"><?php endif; ?>
				<?php wp_nonce_field( 'sw_gallery_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="sw-title">Title</label></th><td><input class="regular-text" id="sw-title" name="title" required maxlength="255" value="<?php echo esc_attr( $item['title'] ); ?>"></td></tr>
					<?php if ( ! $is_gallery ) : ?>
						<tr><th><label for="sw-type">Type</label></th><td>
							<?php if ( $edit ) : ?>
								<input type="hidden" name="type" value="<?php echo esc_attr( $item['type'] ); ?>">
								<span><?php echo esc_html( ucfirst( $item['type'] ) ); ?></span>
							<?php else : ?>
								<select id="sw-type" name="type">
									<?php foreach ( self::MAP_TYPES as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td></tr>
					<?php endif; ?>
					<tr><th><label for="sw-description">Description</label></th><td><textarea class="large-text" id="sw-description" name="description" rows="4"><?php
						// WordPress esc_textarea() escapes output for this textarea context.
						// nosemgrep: php.lang.security.injection.echoed-request.echoed-request
						echo esc_textarea( $item['description'] );
					?></textarea></td></tr>
					<?php if ( $is_gallery ) : ?>
						<?php self::override_select( 'member_title', 'Map titles in this gallery', $item['member_title'] ); ?>
						<?php self::override_select( 'member_description', 'Map descriptions in this gallery', $item['member_description'] ); ?>
						<?php self::fill_select( $item['fill_factor'], true ); ?>
					<?php else : ?>
						<tr><th><label for="sw-url">Public SpotWalla URL</label></th><td><input type="url" class="large-text" id="sw-url" name="url" required value="<?php echo esc_attr( $item['url'] ); ?>"><p class="description">Use the HTTPS public or embed link supplied by SpotWalla.</p></td></tr>
						<tr><th>Visibility</th><td><fieldset><legend class="screen-reader-text">Visibility</legend>
							<label><input type="checkbox" name="show_title" value="1" <?php checked( $item['show_title'], 1 ); ?>> Show title</label><br>
							<label><input type="checkbox" name="show_description" value="1" <?php checked( $item['show_description'], 1 ); ?>> Show description</label>
							<p class="description">A gallery can override these settings when it displays this map. Hiding the title also hides its link to SpotWalla.</p>
						</fieldset></td></tr>
						<?php self::fill_select( $item['fill_factor'], false ); ?>
						<tr><th>Galleries</th><td><fieldset><legend class="screen-reader-text">Galleries</legend>
							<?php foreach ( $galleries as $gallery ) : ?>
								<label><input type="checkbox" name="gallery_ids[]" value="<?php echo esc_attr( $gallery['id'] ); ?>" <?php checked( in_array( (int) $gallery['id'], $selected, true ) ); ?>> <?php echo esc_html( $gallery['title'] . ' (#' . $gallery['id'] . ')' ); ?></label><br>
							<?php endforeach; ?>
							<?php if ( ! $galleries ) : ?>
								<p class="description">No galleries yet. <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'spotwalla-gallery', 'tab' => 'galleries' ), $base_url ) ); ?>">Create one on the Galleries tab.</a></p>
							<?php else : ?>
								<p class="description">Select any number of galleries. Galleries cannot be nested.</p>
							<?php endif; ?>
						</fieldset></td></tr>
					<?php endif; ?>
					<tr><th>Description appearance</th><td><label><input type="checkbox" name="inherit_theme" value="1" <?php checked( $item['inherit_theme'], 1 ); ?>> Inherit site theme (ignore custom colors and dimensions)</label></td></tr>
					<tr><th><label for="sw-background">Description background color</label></th><td><input type="color" id="sw-background" name="background" value="<?php echo esc_attr( $item['background'] ); ?>"></td></tr>
					<tr><th><label for="sw-color">Description text and link color</label></th><td><input type="color" id="sw-color" name="color" value="<?php echo esc_attr( $item['color'] ); ?>"></td></tr>
					<tr><th>Custom map dimensions</th><td>
						<label for="sw-width">Width (px)</label> <input type="number" id="sw-width" name="width" min="200" max="2400" value="<?php echo esc_attr( $item['width'] ); ?>">
						<?php if ( ! $is_gallery ) : ?>
							<label for="sw-height">Map height (px)</label> <input type="number" id="sw-height" name="height" min="200" max="2400" value="<?php echo esc_attr( $item['height'] ); ?>">
						<?php endif; ?>
						<p class="description">Widths shrink to fit small screens. Colors apply to the card, not the remote map.</p>
					</td></tr>
				</table>
				<?php submit_button( 'Save ' . $noun ); ?>
				<?php if ( $edit ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'spotwalla-gallery', 'tab' => $tab ), $base_url ) ); ?>">Cancel editing</a><?php endif; ?>
			</form>
			<h2><?php echo esc_html( $is_gallery ? 'Galleries' : 'Maps' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th>Title</th><?php if ( ! $is_gallery ) : ?><th>Type</th><?php endif; ?><th><?php echo esc_html( $is_gallery ? 'Maps' : 'Gallery IDs' ); ?></th><th>Shortcode</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['title'] ); ?></td>
						<?php if ( $is_gallery ) : ?>
							<td><?php echo esc_html( isset( $counts[ $row['id'] ] ) ? $counts[ $row['id'] ] : 0 ); ?></td>
						<?php else : ?>
							<td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( isset( $by_item[ $row['id'] ] ) ? implode( ', ', $by_item[ $row['id'] ] ) : '—' ); ?></td>
						<?php endif; ?>
						<td><code><?php echo esc_html( '[spotwalla_gallery id="' . $row['id'] . '"]' ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'spotwalla-gallery', 'tab' => $tab, 'edit' => $row['id'] ), $base_url ) ); ?>">Edit</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="sw_gallery_delete"><input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>">
								<?php wp_nonce_field( 'sw_gallery_delete_' . $row['id'] ); ?>
								<button class="button-link-delete" type="submit">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><tr><td colspan="<?php echo esc_attr( $is_gallery ? 5 : 6 ); ?>"><?php echo esc_html( $is_gallery ? 'No galleries yet.' : 'No maps yet.' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
			<h2>Data retention</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sw_gallery_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'sw_gallery_settings' ); ?>
				<?php $settings = self::table( 'settings' ); ?>
				<label><input type="checkbox" name="delete_on_deactivation" value="1" <?php checked( $wpdb->get_var( "SELECT setting_value FROM $settings WHERE setting_key = 'delete_on_deactivation'" ), '1' ); ?>> Permanently delete all plugin tables, settings, maps, and galleries on deactivation.</label>
				<p>Unchecked by default: retain data for reactivation. Deletion cannot be undone; embedded shortcodes will have no content.</p>
				<?php submit_button( 'Save retention setting' ); ?>
			</form>
		</div>
		<?php
	}

	private static function style( $item ) {
		if ( $item['inherit_theme'] ) {
			return '';
		}
		$background = sanitize_hex_color( $item['background'] ) ?: '#ffffff';
		$color      = sanitize_hex_color( $item['color'] ) ?: '#222222';
		$width      = max( 200, min( 2400, absint( $item['width'] ) ) );
		return ' style="' . esc_attr( "background-color:$background;color:$color;width:{$width}px;max-width:100%;" ) . '"';
	}

	private static function visible( $item, $gallery, $field ) {
		$override = $gallery && isset( $gallery[ 'member_' . $field ] ) ? $gallery[ 'member_' . $field ] : 'item';
		if ( 'show' === $override || 'hide' === $override ) {
			return 'show' === $override;
		}
		return ! isset( $item[ 'show_' . $field ] ) || '1' === (string) $item[ 'show_' . $field ];
	}

	private static function embed_url( $url, $item, $gallery ) {
		if ( 'trip' !== $item['type'] ) {
			return $url;
		}
		$fill = $gallery && isset( $gallery['fill_factor'] ) && '' !== (string) $gallery['fill_factor'] ? $gallery['fill_factor'] : ( isset( $item['fill_factor'] ) ? $item['fill_factor'] : '' );
		$fill = self::fill_factor( $fill );
		return null === $fill || '' === $fill ? $url : add_query_arg( 'fillFactor', $fill, $url );
	}

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

	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'spotwalla_gallery' );
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
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT m.* FROM $table m INNER JOIN $relations r ON r.item_id = m.id WHERE r.gallery_id = %d AND m.type <> 'gallery' ORDER BY m.id ASC", $item['id'] ), ARRAY_A );
		$html      = '<section class="sw-gallery-group"' . self::style( $item ) . '><h2>' . esc_html( $item['title'] ) . '</h2><p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		foreach ( $rows as $row ) {
			$html .= self::card( $row, $item );
		}
		return $html . '</section>';
	}
}

register_activation_hook( __FILE__, array( 'SW_Gallery', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SW_Gallery', 'deactivate' ) );
SW_Gallery::init();
