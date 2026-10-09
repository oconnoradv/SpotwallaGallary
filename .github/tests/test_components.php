<?php
/**
 * Component regression tests.
 *
 * Run on a disposable WordPress site with the plugin active:
 * wp --user=<administrator> eval-file .github/tests/test_components.php
 */

if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'Gallery_For_SpotWalla' ) ) {
	throw new RuntimeException( 'Run as an administrator with the plugin active.' );
}

/**
 * Independent read-only adapter proving rendering does not require SQL.
 */
final class GFSW_Test_Reader implements GFSW_Item_Reader {
	/** @var array */
	public $items = array();
	/** @var array */
	public $maps = array();

	/** @inheritDoc */
	public function item( $id ) {
		return isset( $this->items[ $id ] ) ? $this->items[ $id ] : null;
	}

	/** @inheritDoc */
	public function gallery_maps( $id ) {
		return $this->maps;
	}
}

/**
 * @param bool $condition Expected outcome.
 * @param string $message Failure description.
 * @return void
 */
function gfsw_component_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$reader = new GFSW_Test_Reader();
$renderer = new GFSW_Renderer( $reader );
$map = array(
	'id' => 1, 'type' => 'trip', 'title' => '<Trip>', 'description' => "Line 1\nLine 2",
	'url' => 'https://spotwalla.com/trip/view?id=123', 'show_title' => 1, 'show_description' => 1,
	'member_title' => 'item', 'member_description' => 'item', 'fill_factor' => '30',
	'inherit_theme' => 1, 'background' => '#ffffff', 'color' => '#222222', 'width' => 800, 'height' => 450,
);
$reader->items[1] = $map;
$html = $renderer->shortcode( array( 'id' => 1 ) );
foreach ( array( '&lt;Trip&gt;', 'fillFactor=30', 'sandbox="allow-scripts"', 'referrerpolicy="no-referrer"', 'loading="lazy"', 'height="450"' ) as $expected ) {
	gfsw_component_expect( false !== strpos( $html, $expected ), 'Rendered map must contain: ' . $expected );
}
foreach ( array( 'track', 'retrospective' ) as $type ) {
	$reader->items[1]['type'] = $type;
	gfsw_component_expect( false === strpos( $renderer->shortcode( array( 'id' => 1 ) ), 'fillFactor=' ), 'Density must apply only to trips.' );
}
$reader->items[1] = $map;
$reader->items[1]['show_title'] = 0;
$reader->items[1]['show_description'] = 0;
$html = $renderer->shortcode( array( 'id' => 1 ) );
gfsw_component_expect( false === strpos( $html, '<h3>' ) && false === strpos( $html, '<p>' ), 'Visibility settings must hide title and description.' );
$gallery = array_merge( $map, array( 'id' => 2, 'type' => 'gallery', 'title' => 'Group', 'member_title' => 'hide', 'member_description' => 'show', 'fill_factor' => '50' ) );
$reader->items[2] = $gallery;
$reader->maps = array( $map );
$html = $renderer->shortcode( array( 'id' => 2 ) );
gfsw_component_expect( false !== strpos( $html, '<h2>Group</h2>' ) && false === strpos( $html, '<h3>' ) && false !== strpos( $html, 'fillFactor=50' ), 'Gallery overrides must win.' );
$reader->items[1] = array_merge( $map, array( 'inherit_theme' => 0, 'width' => 930, 'height' => 670, 'background' => '#123456', 'color' => '#abcdef' ) );
$html = $renderer->shortcode( array( 'id' => 1 ) );
gfsw_component_expect( false !== strpos( $html, 'width:930px' ) && false !== strpos( $html, 'height="670"' ), 'Custom dimensions must survive.' );
foreach ( array( 'https://example.com/', 'https://spotwalla.com.evil.invalid/', 'javascript:alert(1)', 'http://spotwalla.com/', 'https://user:pass@spotwalla.com/', 'https://spotwalla.com:444/' ) as $bad_url ) {
	$reader->items[1]['url'] = $bad_url;
	gfsw_component_expect( '' === $renderer->shortcode( array( 'id' => 1 ) ), 'Unsafe URLs must not render.' );
	gfsw_component_expect( '' === GFSW_Validator::public_url( $bad_url ), 'Unsafe URLs must not validate.' );
}
foreach ( array( 0, -1, '1x', array( 1 ), 999 ) as $bad_id ) {
	gfsw_component_expect( '' === $renderer->shortcode( array( 'id' => $bad_id ) ), 'Invalid/missing shortcode IDs must render nothing.' );
}
gfsw_component_expect( null === GFSW_Validator::fill_factor( 'invalid' ) && '30' === GFSW_Validator::fill_factor( '30' ), 'Density policy must retain its supported values.' );

global $wpdb;
$original_prefix = $wpdb->prefix;
$wpdb->prefix = $original_prefix . 'gfswtest_' . strtolower( wp_generate_password( 8, false, false ) ) . '_';
$store = new GFSW_Wpdb_Store( $wpdb );
$lifecycle = new GFSW_Lifecycle( $store, $wpdb );
$test_tables = array();
foreach ( array( GFSW_Config::TABLE_PREFIX, GFSW_Config::LEGACY_TABLE_PREFIX ) as $table_prefix ) {
	foreach ( array( 'items', 'settings', 'gallery_items' ) as $name ) {
		$test_tables[] = $store->table( $name, $table_prefix );
	}
}
try {
	$lifecycle->activate();
	gfsw_component_expect( GFSW_Config::DB_VERSION === $store->setting( 'db_version' ), 'Activation must record the schema version.' );
	$map_data = $map;
	unset( $map_data['id'] );
	$map_id = $store->save_item( 0, $map_data );
	$gallery_data = $gallery;
	unset( $gallery_data['id'] );
	$gallery_id = $store->save_item( 0, $gallery_data );
	gfsw_component_expect( $map_id > 0 && $gallery_id > 0, 'Database adapter must insert items.' );
	gfsw_component_expect( false !== $store->add_membership( $gallery_id, $map_id ), 'Database adapter must insert membership.' );
	gfsw_component_expect( array( $gallery_id ) === $store->gallery_ids_for( $map_id ), 'Database adapter must return integer gallery IDs.' );
	gfsw_component_expect( 1 === count( $store->gallery_maps( $gallery_id ) ) && 1 === count( $store->items( true ) ), 'Database adapter must read maps and galleries.' );
	$lifecycle->deactivate();
	gfsw_component_expect( null !== $store->item( $map_id ), 'Default deactivation must retain content.' );
	$store->set_setting( 'db_version', '3' );
	$lifecycle->maybe_upgrade();
	gfsw_component_expect( GFSW_Config::DB_VERSION === $store->setting( 'db_version' ) && null !== $store->item( $map_id ), 'Schema upgrades must preserve rows.' );

	// Seed legacy tables with the current schema to exercise copy/verify/drop migration.
	$create = new ReflectionMethod( 'GFSW_Lifecycle', 'create_tables' );
	$create->setAccessible( true );
	$create->invoke( $lifecycle, GFSW_Config::LEGACY_TABLE_PREFIX );
	foreach ( array( 'items', 'settings', 'gallery_items' ) as $name ) {
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $store->table( $name, GFSW_Config::LEGACY_TABLE_PREFIX ), $store->table( $name ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $store->table( $name ) ) );
	}
	$lifecycle->maybe_upgrade();
	gfsw_component_expect( $map['title'] === $store->item( $map_id )['title'] && 1 === count( $store->gallery_maps( $gallery_id ) ), 'Legacy migration must preserve IDs, content, and memberships.' );
	gfsw_component_expect( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $store->table( 'items', GFSW_Config::LEGACY_TABLE_PREFIX ) ) ) ), 'Verified migration must remove legacy tables.' );
	gfsw_component_expect( false !== $store->clear_memberships( $gallery_id, true ) && false !== $store->delete_item( $gallery_id ) && null !== $store->item( $map_id ), 'Deleting a gallery must retain maps.' );
	$store->set_setting( 'delete_on_deactivation', '1' );
	$lifecycle->deactivate();
	gfsw_component_expect( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $store->table( 'items' ) ) ) ), 'Opt-in deactivation must drop tables.' );
	echo "PASS: injected reader, rendering, URL and density policy, storage contract, activation, upgrades, migration, and retention.\n";
} finally {
	foreach ( $test_tables as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
	$wpdb->prefix = $original_prefix;
}
