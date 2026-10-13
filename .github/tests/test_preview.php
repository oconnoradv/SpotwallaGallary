<?php
/**
 * Run on a disposable WordPress site with the plugin active:
 * wp --user=<administrator> eval-file .github/tests/test_preview.php
 */

if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'Gallery_For_SpotWalla' ) ) {
	throw new RuntimeException( 'Run as an administrator with the plugin active.' );
}

/**
 * @param bool   $condition Expected outcome.
 * @param string $message Failure description.
 * @return void
 */
function gfsw_preview_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Captures the real AJAX handler's response without terminating the test.
 *
 * @param array $values Submitted values.
 * @return array Status code and decoded JSON.
 */
function gfsw_preview_request( $values ) {
	$_POST = wp_slash( $values );
	$_REQUEST = $_POST;
	$status = 200;
	$status_filter = function ( $header, $code ) use ( &$status ) {
		$status = $code;
		return $header;
	};
	$die_handler = function () {
		return function () {
			throw new RuntimeException( 'test JSON response', 9002 );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die_handler );
	add_filter( 'status_header', $status_filter, 10, 2 );
	ob_start();
	try {
		do_action( 'wp_ajax_gfsw_preview' );
	} catch ( RuntimeException $error ) {
		if ( 9002 !== $error->getCode() ) {
			throw $error;
		}
	} finally {
		$json = ob_get_clean();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $die_handler );
		remove_filter( 'status_header', $status_filter, 10 );
	}
	$response = json_decode( $json, true );
	gfsw_preview_expect( is_array( $response ), 'Preview must return valid JSON.' );
	return array( 'status' => $status, 'response' => $response );
}

global $wpdb;
$store = new GFSW_Wpdb_Store( $wpdb );
$renderer = new GFSW_Renderer( $store );
$original_post = $_POST;
$original_request = $_REQUEST;
$original_get = $_GET;
$user_id = get_current_user_id();
$created = array();

try {
	$gallery_id = $store->save_item( 0, array( 'type' => 'gallery', 'title' => 'Preview test gallery', 'description' => '', 'url' => '' ) );
	gfsw_preview_expect( false !== $gallery_id, 'Gallery fixture must be created.' );
	$created[] = $gallery_id;
	$map_id = $store->save_item( 0, array( 'type' => 'trip', 'title' => 'Saved preview test', 'description' => 'Saved', 'url' => 'https://new.spotwalla.com/trip/example/view' ) );
	gfsw_preview_expect( false !== $map_id, 'Map fixture must be created.' );
	$created[] = $map_id;
	gfsw_preview_expect( false !== $store->add_membership( $gallery_id, $map_id ), 'Membership fixture must be created.' );
	$before = array( 'items' => $store->items( false ), 'galleries' => $store->items( true ), 'memberships' => $store->memberships() );
	$base = array(
		'_ajax_nonce' => wp_create_nonce( 'gfsw_preview' ),
		'id' => '0', 'type' => 'trip', 'title' => "Unsaved O'Connor",
		'description' => '<script>alert("test")</script>',
		'url' => 'https://new.spotwalla.com/trip/example/view?foo=bar',
		'fill_factor' => '30', 'gallery_ids' => array( (string) $gallery_id ),
		'show_title' => '1', 'show_description' => '1',
		'background' => '#123456', 'color' => '#abcdef', 'width' => '930', 'height' => '670',
	);
	$result = gfsw_preview_request( $base );
	gfsw_preview_expect( 200 === $result['status'] && $result['response']['success'], 'Valid unsaved entries must preview.' );
	$html = $result['response']['data']['html'];
	foreach ( array( 'Unsaved O&#039;Connor', 'fillFactor=30', 'foo=bar', 'width:930px', 'height="670"', 'sandbox="allow-scripts allow-same-origin"' ) as $expected ) {
		gfsw_preview_expect( false !== strpos( $html, $expected ), 'Preview must contain: ' . $expected );
	}
	gfsw_preview_expect( false === strpos( $html, '<script>' ), 'Preview must not execute submitted markup.' );
	$result = gfsw_preview_request( array_merge( $base, array( 'show_title' => '', 'show_description' => '', 'inherit_theme' => '1' ) ) );
	$html = $result['response']['data']['html'];
	gfsw_preview_expect( false === strpos( $html, '<h3>' ) && false === strpos( $html, '<p>' ) && false !== strpos( $html, 'height="450"' ), 'Preview must honor visibility and theme dimensions.' );
	$result = gfsw_preview_request( array( '_ajax_nonce' => $base['_ajax_nonce'], 'saved_id' => (string) $map_id ) );
	gfsw_preview_expect( $result['response']['success'] && $renderer->shortcode( array( 'id' => $map_id ) ) === $result['response']['data']['html'], 'Saved previews must match shortcodes.' );
	foreach ( array(
		array( 'url' => 'https://example.invalid/' ),
		array( 'title' => '' ),
		array( 'fill_factor' => 'invalid' ),
		array( 'gallery_ids' => array( '999999999' ) ),
		array( 'type' => 'gallery' ),
		array( 'id' => (string) $map_id, 'type' => 'track' ),
		array( 'id' => '999999999' ),
	) as $invalid ) {
		$result = gfsw_preview_request( array_merge( $base, $invalid ) );
		gfsw_preview_expect( 422 === $result['status'] && ! $result['response']['success'] && ! empty( $result['response']['data']['errors'] ), 'Invalid preview fields must report errors.' );
	}
	foreach ( array( '999999999', '-1', '1x', (string) $gallery_id ) as $saved_id ) {
		$result = gfsw_preview_request( array( '_ajax_nonce' => $base['_ajax_nonce'], 'saved_id' => $saved_id ) );
		gfsw_preview_expect( 404 === $result['status'] && ! $result['response']['success'], 'Unavailable or non-map IDs must not preview.' );
	}
	$result = gfsw_preview_request( array_merge( $base, array( '_ajax_nonce' => 'invalid' ) ) );
	gfsw_preview_expect( 403 === $result['status'] && ! $result['response']['success'], 'Invalid nonces must not preview.' );
	wp_set_current_user( 0 );
	$result = gfsw_preview_request( $base );
	gfsw_preview_expect( 403 === $result['status'] && ! $result['response']['success'], 'Unauthorized users must not preview.' );
	wp_set_current_user( $user_id );
	gfsw_preview_expect( ! has_action( 'wp_ajax_nopriv_gfsw_preview' ), 'Preview must not register a public AJAX endpoint.' );
	gfsw_preview_expect( $before === array( 'items' => $store->items( false ), 'galleries' => $store->items( true ), 'memberships' => $store->memberships() ), 'All preview requests must leave content and memberships unchanged.' );
	$_GET = array( 'tab' => 'maps' );
	ob_start();
	Gallery_For_SpotWalla::admin();
	$page = ob_get_clean();
	gfsw_preview_expect( false !== strpos( $page, 'id="gfsw-preview"' ) && false !== strpos( $page, 'data-map-id="' . $map_id . '"' ) && false !== strpos( $page, 'images/logo-map.svg' ), 'Maps tab must expose form/list previews and the new logo.' );
} finally {
	wp_set_current_user( $user_id );
	foreach ( array_reverse( $created ) as $id ) {
		$item = $store->item( $id );
		$store->clear_memberships( $id, $item && 'gallery' === $item['type'] );
		$store->delete_item( $id );
	}
	$_POST = $original_post;
	$_REQUEST = $original_request;
	$_GET = $original_get;
}

echo "Preview tests passed.\n";
