<?php
/**
 * Plugin Name: SpotWalla Gallery
 * Description: Manage and embed public SpotWalla tracks, trips, retrospectives, and gallery groups.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: spotwalla-gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SW_Gallery {
	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'SW_' . $name;
	}

	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Please activate SpotWalla Gallery separately on each site, not network-wide.', 'spotwalla-gallery' ) );
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$items    = self::table( 'items' );
		$settings = self::table( 'settings' );
		$charset  = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				gallery_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(20) NOT NULL DEFAULT 'trip',
				title varchar(255) NOT NULL,
				description longtext NOT NULL,
				url text NOT NULL,
				inherit_theme tinyint(1) NOT NULL DEFAULT 1,
				background varchar(7) NOT NULL DEFAULT '#ffffff',
				color varchar(7) NOT NULL DEFAULT '#222222',
				width int unsigned NOT NULL DEFAULT 800,
				height int unsigned NOT NULL DEFAULT 450,
				PRIMARY KEY  (id),
				KEY gallery_id (gallery_id)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE $settings (
				setting_key varchar(64) NOT NULL,
				setting_value text NOT NULL,
				PRIMARY KEY  (setting_key)
			) $charset;"
		);
		$wpdb->query( "INSERT IGNORE INTO $settings (setting_key, setting_value) VALUES ('delete_on_deactivation', '0')" );
	}

	public static function deactivate() {
		global $wpdb;
		$settings = self::table( 'settings' );
		if ( '1' === $wpdb->get_var( "SELECT setting_value FROM $settings WHERE setting_key = 'delete_on_deactivation'" ) ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( 'items' ) );
			$wpdb->query( "DROP TABLE IF EXISTS $settings" );
		}
	}

	public static function init() {
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

	private static function redirect( $message ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'spotwalla-gallery', 'sw_message' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function item( $id ) {
		global $wpdb;
		$table = self::table( 'items' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
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

	public static function save() {
		self::authorize( 'sw_gallery_save' );
		global $wpdb;
		$id    = absint( self::posted( 'id' ) );
		$type  = sanitize_key( self::posted( 'type' ) );
		$title = sanitize_text_field( self::posted( 'title' ) );
		$url   = 'gallery' === $type ? '' : self::public_url( self::posted( 'url' ) );
		if ( ! in_array( $type, array( 'track', 'trip', 'retrospective', 'gallery' ), true ) ||
			'' === $title || strlen( $title ) > 255 || ( 'gallery' !== $type && '' === $url ) ) {
			self::redirect( 'invalid' );
		}
		$existing = $id ? self::item( $id ) : null;
		if ( $id && ( ! $existing || $existing['type'] !== $type ) ) {
			self::redirect( 'invalid' );
		}
		$gallery_id = 'gallery' === $type ? 0 : absint( self::posted( 'gallery_id' ) );
		$gallery    = $gallery_id ? self::item( $gallery_id ) : null;
		if ( $gallery_id && ( ! $gallery || 'gallery' !== $gallery['type'] ) ) {
			self::redirect( 'invalid' );
		}
		$data = array(
			'gallery_id'    => $gallery_id,
			'type'          => $type,
			'title'         => $title,
			'description'   => sanitize_textarea_field( self::posted( 'description' ) ),
			'url'           => $url,
			'inherit_theme' => '1' === self::posted( 'inherit_theme' ) ? 1 : 0,
			'background'    => sanitize_hex_color( self::posted( 'background' ) ) ?: '#ffffff',
			'color'         => sanitize_hex_color( self::posted( 'color' ) ) ?: '#222222',
			'width'         => max( 200, min( 2400, absint( self::posted( 'width', '800' ) ) ) ),
			'height'        => max( 200, min( 2400, absint( self::posted( 'height', '450' ) ) ) ),
		);
		$result = $id ? $wpdb->update( self::table( 'items' ), $data, array( 'id' => $id ) ) : $wpdb->insert( self::table( 'items' ), $data );
		self::redirect( false === $result ? 'error' : 'saved' );
	}

	public static function delete() {
		$id = absint( self::posted( 'id' ) );
		self::authorize( 'sw_gallery_delete_' . $id );
		global $wpdb;
		$item = self::item( $id );
		if ( ! $item ) {
			self::redirect( 'invalid' );
		}
		if ( 'gallery' === $item['type'] &&
			false === $wpdb->update( self::table( 'items' ), array( 'gallery_id' => 0 ), array( 'gallery_id' => $id ) ) ) {
			self::redirect( 'error' );
		}
		$result = $wpdb->delete( self::table( 'items' ), array( 'id' => $id ) );
		self::redirect( false === $result ? 'error' : 'deleted' );
	}

	public static function settings() {
		self::authorize( 'sw_gallery_settings' );
		global $wpdb;
		$result = $wpdb->replace(
			self::table( 'settings' ),
			array( 'setting_key' => 'delete_on_deactivation', 'setting_value' => '1' === self::posted( 'delete_on_deactivation' ) ? '1' : '0' )
		);
		self::redirect( false === $result ? 'error' : 'saved' );
	}

	public static function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table = self::table( 'items' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC", ARRAY_A );
		$edit  = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? self::item( absint( $_GET['edit'] ) ) : null;
		$item  = $edit ?: array( 'id' => 0, 'gallery_id' => 0, 'type' => 'trip', 'title' => '', 'description' => '', 'url' => '', 'inherit_theme' => 1, 'background' => '#ffffff', 'color' => '#222222', 'width' => 800, 'height' => 450 );
		$messages = array(
			'saved'   => 'Saved.',
			'deleted' => 'Deleted. Entries in a deleted group remain available individually.',
			'invalid' => 'Not saved. Supply a title (maximum 255 bytes), a valid type/group, and an HTTPS SpotWalla public URL for entries. Existing entry types cannot be changed.',
			'error'   => 'A database error occurred. Please try again.',
		);
		$message = isset( $_GET['sw_message'] ) && is_scalar( $_GET['sw_message'] ) ? sanitize_key( $_GET['sw_message'] ) : '';
		?>
		<div class="wrap">
			<h1>SpotWalla Gallery</h1>
			<?php if ( isset( $messages[ $message ] ) ) : ?>
				<div class="notice <?php echo in_array( $message, array( 'invalid', 'error' ), true ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $messages[ $message ] ); ?></p></div>
			<?php endif; ?>
			<p>Create a gallery group, then assign tracks, trips, or retrospectives to it. Embed any entry or group with <code>[spotwalla_gallery id="123"]</code>.</p>
			<h2><?php echo $edit ? 'Edit entry' : 'Add entry or gallery group'; ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sw_gallery_save">
				<input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php wp_nonce_field( 'sw_gallery_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="sw-title">Title</label></th><td><input class="regular-text" id="sw-title" name="title" required maxlength="255" value="<?php echo esc_attr( $item['title'] ); ?>"></td></tr>
					<tr><th><label for="sw-type">Type</label></th><td>
						<?php if ( $edit ) : ?>
							<input type="hidden" name="type" value="<?php echo esc_attr( $item['type'] ); ?>">
							<span><?php echo esc_html( ucfirst( $item['type'] ) ); ?></span>
						<?php else : ?>
							<select id="sw-type" name="type">
								<?php foreach ( array( 'track', 'trip', 'retrospective', 'gallery' ) as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td></tr>
					<tr><th><label for="sw-description">Description</label></th><td><textarea class="large-text" id="sw-description" name="description" rows="4"><?php
						// WordPress esc_textarea() escapes output for this textarea context.
						// nosemgrep: php.lang.security.injection.echoed-request.echoed-request
						echo esc_textarea( $item['description'] );
					?></textarea></td></tr>
					<tr><th><label for="sw-url">Public SpotWalla URL</label></th><td><input type="url" class="large-text" id="sw-url" name="url" value="<?php echo esc_attr( $item['url'] ); ?>"><p class="description">Use the HTTPS public or embed link supplied by SpotWalla. Not needed for gallery groups.</p></td></tr>
					<tr><th><label for="sw-group">Gallery group</label></th><td><select id="sw-group" name="gallery_id">
						<option value="0">None</option>
						<?php foreach ( $rows as $row ) : ?>
							<?php if ( 'gallery' === $row['type'] ) : ?>
								<option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( $item['gallery_id'], $row['id'] ); ?>><?php echo esc_html( $row['title'] . ' (#' . $row['id'] . ')' ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select><p class="description">Groups cannot be nested. Each entry belongs to at most one group.</p></td></tr>
					<tr><th>Appearance</th><td><label><input type="checkbox" name="inherit_theme" value="1" <?php checked( $item['inherit_theme'], 1 ); ?>> Inherit site theme (ignore custom colors and dimensions)</label></td></tr>
					<tr><th><label for="sw-background">Background color</label></th><td><input type="color" id="sw-background" name="background" value="<?php echo esc_attr( $item['background'] ); ?>"></td></tr>
					<tr><th><label for="sw-color">Text and link color</label></th><td><input type="color" id="sw-color" name="color" value="<?php echo esc_attr( $item['color'] ); ?>"></td></tr>
					<tr><th>Custom dimensions</th><td>
						<label for="sw-width">Width (px)</label> <input type="number" id="sw-width" name="width" min="200" max="2400" value="<?php echo esc_attr( $item['width'] ); ?>">
						<label for="sw-height">Map height (px)</label> <input type="number" id="sw-height" name="height" min="200" max="2400" value="<?php echo esc_attr( $item['height'] ); ?>">
						<p class="description">Widths shrink to fit small screens. Colors apply to the card, not the remote map.</p>
					</td></tr>
				</table>
				<?php submit_button( 'Save entry' ); ?>
				<?php if ( $edit ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=spotwalla-gallery' ) ); ?>">Cancel editing</a><?php endif; ?>
			</form>
			<h2>Entries and gallery groups</h2>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Group ID</th><th>Shortcode</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['title'] ); ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['gallery_id'] ?: '—' ); ?></td>
						<td><code><?php echo esc_html( '[spotwalla_gallery id="' . $row['id'] . '"]' ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'spotwalla-gallery', 'edit' => $row['id'] ), admin_url( 'admin.php' ) ) ); ?>">Edit</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="sw_gallery_delete"><input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>">
								<?php wp_nonce_field( 'sw_gallery_delete_' . $row['id'] ); ?>
								<button class="button-link-delete" type="submit">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><tr><td colspan="6">No entries yet.</td></tr><?php endif; ?>
				</tbody>
			</table>
			<h2>Data retention</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sw_gallery_settings">
				<?php wp_nonce_field( 'sw_gallery_settings' ); ?>
				<?php $settings = self::table( 'settings' ); ?>
				<label><input type="checkbox" name="delete_on_deactivation" value="1" <?php checked( $wpdb->get_var( "SELECT setting_value FROM $settings WHERE setting_key = 'delete_on_deactivation'" ), '1' ); ?>> Permanently delete all plugin tables, settings, and entries on deactivation.</label>
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

	private static function card( $item ) {
		$url = self::public_url( $item['url'] );
		if ( ! $url ) {
			return '';
		}
		$height = $item['inherit_theme'] ? 450 : max( 200, min( 2400, absint( $item['height'] ) ) );
		$link_style = $item['inherit_theme'] ? '' : ' style="color:inherit;"';
		return '<article class="sw-gallery-entry sw-gallery-' . esc_attr( $item['type'] ) . '"' . self::style( $item ) . '>' .
			'<h3><a href="' . esc_url( $url ) . '"' . $link_style . '>' . esc_html( $item['title'] ) . '</a></h3>' .
			'<p>' . nl2br( esc_html( $item['description'] ) ) . '</p>' .
			'<iframe src="' . esc_url( $url ) . '" title="' . esc_attr( $item['title'] ) . '" loading="lazy" referrerpolicy="no-referrer" width="100%" height="' . esc_attr( $height ) . '" style="display:block;width:100%;max-width:100%;border:0;" allowfullscreen></iframe>' .
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
		$table = self::table( 'items' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE gallery_id = %d AND type <> 'gallery' ORDER BY id ASC", $item['id'] ), ARRAY_A );
		$html  = '<section class="sw-gallery-group"' . self::style( $item ) . '><h2>' . esc_html( $item['title'] ) . '</h2><p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		foreach ( $rows as $row ) {
			$html .= self::card( $row );
		}
		return $html . '</section>';
	}
}

register_activation_hook( __FILE__, array( 'SW_Gallery', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SW_Gallery', 'deactivate' ) );
SW_Gallery::init();
