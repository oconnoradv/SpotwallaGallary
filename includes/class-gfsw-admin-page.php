<?php
/**
 * Admin page presentation for Gallery for SpotWalla.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Admin_Page {
	/** @var GFSW_Store */
	private $store;

	/** @var GFSW_Form_Recovery */
	private $recovery;

	/**
	 * @param GFSW_Store $store Persistence dependency.
	 * @param GFSW_Form_Recovery $recovery Failed-form storage.
	 */
	public function __construct( GFSW_Store $store, GFSW_Form_Recovery $recovery ) {
		$this->store = $store;
		$this->recovery = $recovery;
	}

	/** @var array Field errors for the current form. */
	private $form_errors = array();

	/**
	 * Adds the plugin page to the admin menu, labeled "Spotwalla Gallery" in the menu only.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page( __( 'Gallery for SpotWalla', 'gallery-for-spotwalla' ), __( 'Spotwalla Gallery', 'gallery-for-spotwalla' ), 'manage_options', GFSW_Config::SLUG, array( $this, 'admin' ), 'dashicons-location-alt' );
	}

	/**
	 * Adds admin CSS: a purple menu icon on every admin screen and the page header layout.
	 *
	 * @return void
	 */
	public function admin_styles() {
		$plugin = get_file_data( GFSW_PLUGIN_FILE, array( 'version' => 'Version' ) );
		$menu   = '#adminmenu .toplevel_page_' . GFSW_Config::SLUG;
		$css    = $menu . ' div.wp-menu-image:before,' . $menu . ':hover div.wp-menu-image:before,' . $menu . ' a:focus div.wp-menu-image:before,' . $menu . '.current div.wp-menu-image:before,' . $menu . '.wp-has-current-submenu div.wp-menu-image:before{color:#b48cf5}'
			. '.gfsw-header{display:flex;align-items:center;gap:16px;margin:12px 0 8px}'
			. '.gfsw-header img{flex:none;width:128px;height:128px;border-radius:4px}'
			. '.wrap .gfsw-header h1{margin:0;padding:0;font-size:2em;line-height:1.2}'
			. '.gfsw-header .gfsw-tagline{margin:4px 0 0;font-size:14px;font-style:italic;color:#646970}'
			. '.gfsw-form [aria-invalid="true"]{border:2px solid #b32d2e}.gfsw-field-error{color:#b32d2e;font-weight:600}'
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
	private function header() {
		$plugin = get_file_data( GFSW_PLUGIN_FILE, array( 'version' => 'Version' ) );
		?>
		<div class="gfsw-header">
			<img src="<?php echo esc_url( plugins_url( 'images/logo-256x256.jpg', GFSW_PLUGIN_FILE ) ); ?>" width="128" height="128" alt="" />
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
	public function legacy_notice() {
		$screen = get_current_screen();
		if ( ! class_exists( 'SW_Gallery', false ) || ! current_user_can( 'activate_plugins' ) || ! $screen ||
			! in_array( $screen->id, array( 'plugins', 'toplevel_page_' . GFSW_Config::SLUG ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The old "SpotWalla Gallery" plugin is still active. Gallery for SpotWalla replaces it and has moved its maps and galleries into its own tables, so changes made in the old plugin are no longer shown. Deactivate and delete "SpotWalla Gallery".', 'gallery-for-spotwalla' ) . '</p></div>';
	}

	/**
	 * Outputs accessibility attributes linking a field to its inline error.
	 *
	 * @param string $field Field name.
	 * @return void
	 */
	private function error_attributes( $field ) {
		if ( isset( $this->form_errors[ $field ] ) ) {
			echo ' aria-invalid="true" aria-describedby="' . esc_attr( 'sw-error-' . $field ) . '"';
		}
	}

	/**
	 * Outputs a field's corrective error text, if present.
	 *
	 * @param string $field Field name.
	 * @return void
	 */
	private function field_error( $field ) {
		if ( isset( $this->form_errors[ $field ] ) ) {
			echo '<p class="gfsw-field-error" id="' . esc_attr( 'sw-error-' . $field ) . '">' . esc_html( $this->form_errors[ $field ] ) . '</p>';
		}
	}

	/**
	 * Outputs a form row for a gallery's title or description visibility override.
	 *
	 * @param string $name  Field name.
	 * @param string $label Row label.
	 * @param string $value Currently selected override.
	 * @return void
	 */
	private function override_select( $name, $label, $value ) {
		$labels = array(
			'item' => __( "Use each map's setting", 'gallery-for-spotwalla' ),
			'show' => __( 'Show for all maps', 'gallery-for-spotwalla' ),
			'hide' => __( 'Hide for all maps', 'gallery-for-spotwalla' ),
		);
		?>
		<tr><th><label for="sw-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php $this->error_attributes( $name ); ?>>
			<?php if ( ! isset( $labels[ $value ] ) ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php endif; ?>
			<?php foreach ( $labels as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select><?php $this->field_error( $name ); ?></td></tr>
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
	private function fill_select( $value, $is_gallery ) {
		$label = $is_gallery ? __( 'Map density in this gallery', 'gallery-for-spotwalla' ) : __( 'Density/Fill percentage', 'gallery-for-spotwalla' );
		?>
		<tr><th><label for="sw-fill-factor"><?php echo esc_html( $label ); ?></label></th><td><select id="sw-fill-factor" name="fill_factor"<?php $this->error_attributes( 'fill_factor' ); ?>>
			<?php if ( null === GFSW_Validator::fill_factor( $value ) ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php endif; ?>
			<option value="" <?php selected( $value, '' ); ?>><?php echo esc_html( $is_gallery ? __( "Use each map's setting", 'gallery-for-spotwalla' ) : __( 'SpotWalla trip setting', 'gallery-for-spotwalla' ) ); ?></option>
			<?php foreach ( GFSW_Config::FILL_FACTORS as $key => $text ) : ?>
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
		</select><?php $this->field_error( 'fill_factor' ); ?><p class="description"><?php echo esc_html( $is_gallery ? __( 'Overrides the density of every trip map shown in this gallery.', 'gallery-for-spotwalla' ) : __( "Sets SpotWalla's Density/Fill Percentage (number of locations shown). Applies to trips only.", 'gallery-for-spotwalla' ) ); ?></p></td></tr>
		<?php
	}

	/**
	 * Returns the translated label for an item type.
	 *
	 * @param string $type Item type: track, trip, retrospective, or gallery.
	 * @return string
	 */
	private function type_label( $type ) {
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
	private function new_tab_text() {
		return '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'gallery-for-spotwalla' ) . '</span>';
	}

	/**
	 * Outputs the Maps, Galleries, and About tab navigation.
	 *
	 * @param string $tab Active tab.
	 * @return void
	 */
	private function nav( $tab ) {
		$tabs = array(
			'maps'      => __( 'Maps', 'gallery-for-spotwalla' ),
			'galleries' => __( 'Galleries', 'gallery-for-spotwalla' ),
			'about'     => __( 'About', 'gallery-for-spotwalla' ),
		);
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Gallery for SpotWalla sections', 'gallery-for-spotwalla' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab<?php echo esc_attr( $tab === $key ? ' nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"<?php if ( $tab === $key ) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Outputs the About tab: version, disclaimer, links, license, recommendations, and issue-reporting steps.
	 *
	 * @return void
	 */
	private function about() {
		global $wp_version;
		$plugin  = get_file_data( GFSW_PLUGIN_FILE, array( 'version' => 'Version' ) );
		$links   = array(
			__( 'Repository', 'gallery-for-spotwalla' )                  => GFSW_Config::REPOSITORY,
			__( 'README', 'gallery-for-spotwalla' )                      => GFSW_Config::REPOSITORY . '/blob/main/README.md',
			__( 'License (GPLv2 or later)', 'gallery-for-spotwalla' )    => GFSW_Config::REPOSITORY . '/blob/main/LICENSE',
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
			<?php $this->header(); ?>
			<?php $this->nav( 'about' ); ?>
			<h2><?php esc_html_e( 'About', 'gallery-for-spotwalla' ); ?></h2>
			<p><?php esc_html_e( "Gallery for SpotWalla manages and embeds public SpotWalla tracks, trips, retrospectives, and galleries. Embedded maps are loaded from spotwalla.com in each visitor's browser and are subject to SpotWalla's terms and privacy policy.", 'gallery-for-spotwalla' ); ?></p>
			<div class="notice notice-info inline"><p><?php echo wp_kses( __( '<strong>Disclaimer:</strong> Gallery for SpotWalla is an independent project. It is <strong>not</strong> affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used here only to describe compatibility. Direct questions about the plugin to this project, not to SpotWalla.', 'gallery-for-spotwalla' ), $allowed ); ?></p></div>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Version', 'gallery-for-spotwalla' ); ?></th><td><?php echo esc_html( $plugin['version'] ); ?></td></tr>
				<?php foreach ( $links as $label => $url ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ) . wp_kses( $this->new_tab_text(), $allowed ); ?></a></td></tr>
				<?php endforeach; ?>
			</table>
			<p><?php echo wp_kses( __( "The plugin is free software released under the GNU General Public License, version 2 or (at your option) any later version. Copies of <code>readme.txt</code>, <code>README.md</code>, and <code>LICENSE</code> are included in the plugin's folder.", 'gallery-for-spotwalla' ), $allowed ); ?></p>
			<h2><?php esc_html_e( 'Recommended Plugins', 'gallery-for-spotwalla' ); ?></h2>
			<p>
				<?php
				/* translators: 1: link to the Motorcycle Rally Scoring App project, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'For motorcycle rally event management and scoring, see the related <a href="%1$s" target="_blank" rel="noopener noreferrer">Motorcycle Rally Scoring App plugin</a>, a separate, optional O\'ConnorADV project%2$s.', 'gallery-for-spotwalla' ), esc_url( 'https://github.com/oconnoradv/MotorcycleRallyScoringApp' ), $this->new_tab_text() ), $allowed );
				?>
			</p>
			<h2><?php esc_html_e( 'Reporting issues', 'gallery-for-spotwalla' ); ?></h2>
			<ol>
				<li>
				<?php
				/* translators: 1: link to the GitHub issue list, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'Search <a href="%1$s" target="_blank" rel="noopener noreferrer">existing issues%2$s</a> to see whether the problem is already reported.', 'gallery-for-spotwalla' ), esc_url( GFSW_Config::REPOSITORY . '/issues' ), $this->new_tab_text() ), $allowed );
				?>
				</li>
				<li>
				<?php
				/* translators: 1: link to the new GitHub issue form, 2: "(opens in a new tab)" text for screen readers. */
				echo wp_kses( sprintf( __( 'If not, sign in to GitHub and <a href="%1$s" target="_blank" rel="noopener noreferrer">open a new issue%2$s</a> with a short, descriptive title.', 'gallery-for-spotwalla' ), esc_url( GFSW_Config::REPOSITORY . '/issues/new' ), $this->new_tab_text() ), $allowed );
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
	public function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Read-only navigation parameters; every state change is a nonce-protected POST.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = 'maps';
		if ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'galleries' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'galleries';
		} elseif ( isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && 'about' === sanitize_key( $_GET['tab'] ) ) {
			$tab = 'about';
		}
		$edit    = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? $this->store->item( absint( $_GET['edit'] ) ) : null;
		$message = isset( $_GET['sw_message'] ) && is_scalar( $_GET['sw_message'] ) ? sanitize_key( $_GET['sw_message'] ) : '';
		$token   = isset( $_GET['sw_form'] ) && is_scalar( $_GET['sw_form'] ) ? sanitize_text_field( wp_unslash( $_GET['sw_form'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$this->form_errors = array();
		$draft = null;
		if ( '' !== $token ) {
			$draft = $this->recovery->consume( $token );
			if ( is_array( $draft ) ) {
				$this->form_errors = $draft['errors'];
				$edit = $draft['item']['id'] ? $this->store->item( $draft['item']['id'] ) : null;
			} else {
				$draft = null;
				$this->form_errors['form'] = __( 'The recovered form has expired or was already opened. Please enter your details again.', 'gallery-for-spotwalla' );
			}
		}
		if ( $edit && 'gallery' === $edit['type'] ) {
			$tab = 'galleries';
		} elseif ( $edit ) {
			$tab = 'maps';
		}
		if ( 'about' === $tab ) {
			$this->about();
			return;
		}
		$is_gallery = 'galleries' === $tab;
		$galleries  = $this->store->items( true );
		$rows       = $is_gallery ? $galleries : $this->store->items( false );
		$by_item    = array();
		$counts     = array();
		foreach ( $this->store->memberships() as $relation ) {
			$by_item[ $relation['item_id'] ][] = $relation['gallery_id'];
			$counts[ $relation['gallery_id'] ] = ( isset( $counts[ $relation['gallery_id'] ] ) ? $counts[ $relation['gallery_id'] ] : 0 ) + 1;
		}
		$defaults = array( 'id' => 0, 'type' => $is_gallery ? 'gallery' : 'trip', 'title' => '', 'description' => '', 'url' => '', 'show_title' => 1, 'show_description' => 1, 'member_title' => 'item', 'member_description' => 'item', 'fill_factor' => '', 'inherit_theme' => 1, 'background' => '#ffffff', 'color' => '#222222', 'width' => 800, 'height' => 450 );
		$item     = $edit ?: $defaults;
		$selected = $edit && ! $is_gallery ? $this->store->gallery_ids_for( $edit['id'] ) : array();
		if ( $draft ) {
			$item = array_merge( $item, $draft['item'] );
			if ( $edit ) {
				$item['type'] = $edit['type'];
			}
			$selected = $draft['gallery_ids'];
		}
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
			<?php $this->header(); ?>
			<?php $this->nav( $tab ); ?>
			<?php if ( $this->form_errors ) : ?>
				<div class="notice notice-error" role="alert">
					<p><?php echo esc_html( $draft ? __( 'Review the errors below. Your submitted entries have been kept.', 'gallery-for-spotwalla' ) : __( 'The form could not be recovered.', 'gallery-for-spotwalla' ) ); ?></p>
					<ul>
						<?php foreach ( $this->form_errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
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
			<form class="gfsw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gfsw_save">
				<input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>">
				<?php if ( $is_gallery ) : ?><input type="hidden" name="type" value="gallery"><?php endif; ?>
				<?php wp_nonce_field( 'gfsw_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="sw-title"><?php esc_html_e( 'Title', 'gallery-for-spotwalla' ); ?></label></th><td><input class="regular-text" id="sw-title" name="title" required maxlength="255" value="<?php echo esc_attr( $item['title'] ); ?>"<?php $this->error_attributes( 'title' ); ?>><?php $this->field_error( 'title' ); ?></td></tr>
					<?php if ( ! $is_gallery ) : ?>
						<tr><th><label for="sw-type"><?php esc_html_e( 'Type', 'gallery-for-spotwalla' ); ?></label></th><td>
							<?php if ( $edit ) : ?>
								<input type="hidden" name="type" value="<?php echo esc_attr( $item['type'] ); ?>">
								<span><?php echo esc_html( $this->type_label( $item['type'] ) ); ?></span>
							<?php else : ?>
								<select id="sw-type" name="type"<?php $this->error_attributes( 'type' ); ?>>
									<?php if ( ! in_array( $item['type'], GFSW_Config::MAP_TYPES, true ) ) : ?>
										<option value="<?php echo esc_attr( $item['type'] ); ?>" selected><?php echo esc_html( $item['type'] ); ?></option>
									<?php endif; ?>
									<?php foreach ( GFSW_Config::MAP_TYPES as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['type'], $type ); ?>><?php echo esc_html( $this->type_label( $type ) ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
							<?php $this->field_error( 'type' ); ?>
						</td></tr>
					<?php endif; ?>
					<tr><th><label for="sw-description"><?php esc_html_e( 'Description', 'gallery-for-spotwalla' ); ?></label></th><td><textarea class="large-text" id="sw-description" name="description" rows="4"><?php
						// WordPress esc_textarea() escapes output for this textarea context.
						// nosemgrep: php.lang.security.injection.echoed-request.echoed-request
						echo esc_textarea( $item['description'] );
					?></textarea></td></tr>
					<?php if ( $is_gallery ) : ?>
						<?php $this->override_select( 'member_title', __( 'Map titles in this gallery', 'gallery-for-spotwalla' ), $item['member_title'] ); ?>
						<?php $this->override_select( 'member_description', __( 'Map descriptions in this gallery', 'gallery-for-spotwalla' ), $item['member_description'] ); ?>
						<?php $this->fill_select( $item['fill_factor'], true ); ?>
					<?php else : ?>
						<tr><th><label for="sw-url"><?php esc_html_e( 'Public SpotWalla URL', 'gallery-for-spotwalla' ); ?></label></th><td><input type="url" class="large-text" id="sw-url" name="url" required value="<?php echo esc_attr( $item['url'] ); ?>"<?php $this->error_attributes( 'url' ); ?>><?php $this->field_error( 'url' ); ?><p class="description"><?php esc_html_e( 'Use the HTTPS public or embed link supplied by SpotWalla.', 'gallery-for-spotwalla' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Visibility', 'gallery-for-spotwalla' ); ?></th><td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Visibility', 'gallery-for-spotwalla' ); ?></legend>
							<label><input type="checkbox" name="show_title" value="1" <?php checked( $item['show_title'], 1 ); ?>> <?php esc_html_e( 'Show title', 'gallery-for-spotwalla' ); ?></label><br>
							<label><input type="checkbox" name="show_description" value="1" <?php checked( $item['show_description'], 1 ); ?>> <?php esc_html_e( 'Show description', 'gallery-for-spotwalla' ); ?></label>
							<p class="description"><?php esc_html_e( 'A gallery can override these settings when it displays this map. Hiding the title also hides its link to SpotWalla.', 'gallery-for-spotwalla' ); ?></p>
						</fieldset></td></tr>
						<?php $this->fill_select( $item['fill_factor'], false ); ?>
						<tr><th><?php esc_html_e( 'Galleries', 'gallery-for-spotwalla' ); ?></th><td><fieldset<?php $this->error_attributes( 'gallery_ids' ); ?>><legend class="screen-reader-text"><?php esc_html_e( 'Galleries', 'gallery-for-spotwalla' ); ?></legend>
							<?php foreach ( $galleries as $gallery ) : ?>
								<label><input type="checkbox" name="gallery_ids[]" value="<?php echo esc_attr( $gallery['id'] ); ?>" <?php checked( in_array( (int) $gallery['id'], $selected, true ) ); ?>> <?php echo esc_html( $gallery['title'] . ' (#' . $gallery['id'] . ')' ); ?></label><br>
							<?php endforeach; ?>
							<?php foreach ( array_diff( $selected, array_map( 'absint', wp_list_pluck( $galleries, 'id' ) ) ) as $missing_id ) : ?>
								<label><input type="checkbox" name="gallery_ids[]" value="<?php echo esc_attr( $missing_id ); ?>" checked>
									<?php
									/* translators: %d: the ID of a gallery that no longer exists. */
									echo esc_html( sprintf( __( 'Unavailable gallery (#%d) - uncheck this selection.', 'gallery-for-spotwalla' ), $missing_id ) );
									?>
								</label><br>
							<?php endforeach; ?>
							<?php $this->field_error( 'gallery_ids' ); ?>
							<?php if ( ! $galleries ) : ?>
								<p class="description"><?php esc_html_e( 'No galleries yet.', 'gallery-for-spotwalla' ); ?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => 'galleries' ), $base_url ) ); ?>"><?php esc_html_e( 'Create one on the Galleries tab.', 'gallery-for-spotwalla' ); ?></a></p>
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
				<?php if ( $edit ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => $tab ), $base_url ) ); ?>"><?php esc_html_e( 'Cancel editing', 'gallery-for-spotwalla' ); ?></a><?php endif; ?>
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
							<td><?php echo esc_html( $this->type_label( $row['type'] ) ); ?></td><td><?php echo esc_html( isset( $by_item[ $row['id'] ] ) ? implode( ', ', $by_item[ $row['id'] ] ) : '—' ); ?></td>
						<?php endif; ?>
						<td><code><?php echo esc_html( '[gallery_for_spotwalla id="' . $row['id'] . '"]' ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => $tab, 'edit' => $row['id'] ), $base_url ) ); ?>"><?php esc_html_e( 'Edit', 'gallery-for-spotwalla' ); ?></a>
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
				<label><input type="checkbox" name="delete_on_deactivation" value="1" <?php checked( $this->store->setting( 'delete_on_deactivation' ), '1' ); ?>> <?php esc_html_e( 'Permanently delete all plugin tables, settings, maps, and galleries on deactivation.', 'gallery-for-spotwalla' ); ?></label>
				<p><?php esc_html_e( 'Unchecked by default: retain data for reactivation. Deletion cannot be undone; embedded shortcodes will have no content.', 'gallery-for-spotwalla' ); ?></p>
								<?php submit_button( __( 'Save retention setting', 'gallery-for-spotwalla' ) ); ?>
			</form>
		</div>
		<?php
	}

}
