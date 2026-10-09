<?php
/**
 * Run on a disposable WordPress site with this plugin active:
 * wp --user=<administrator> eval-file .github/tests/test_form_recovery.php
 */

if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'Gallery_For_SpotWalla' ) ) {
	throw new RuntimeException( 'Run as an administrator with Gallery for SpotWalla active.' );
}

/**
 * Checks a form recovery expectation.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Description of the expectation.
 * @return void
 */
function gfsw_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Exercises the real save handler, stopping at its redirect rather than exit.
 *
 * @param array $values Submitted form fields.
 * @return array Redirect query arguments.
 */
function gfsw_submit( $values ) {
	$_POST = wp_slash( array_merge( $values, array( '_wpnonce' => wp_create_nonce( 'gfsw_save' ) ) ) );
	$_REQUEST = $_POST;
	$location = '';
	$stop = function ( $url ) use ( &$location ) {
		$location = $url;
		throw new RuntimeException( 'test redirect', 9001 );
	};
	add_filter( 'wp_redirect', $stop, -100 );
	try {
		Gallery_For_SpotWalla::save();
	} catch ( RuntimeException $error ) {
		if ( 9001 !== $error->getCode() ) {
			throw $error;
		}
	} finally {
		remove_filter( 'wp_redirect', $stop, -100 );
	}
	parse_str( wp_parse_url( $location, PHP_URL_QUERY ), $query );
	return $query;
}

/**
 * Renders the form at the recovery redirect.
 *
 * @param array $query Redirect query arguments.
 * @return string Admin page HTML.
 */
function gfsw_render( $query ) {
	$_GET = $query;
	ob_start();
	try {
		Gallery_For_SpotWalla::admin();
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
}

global $wpdb;
$table = $wpdb->prefix . 'SpotGal_items';
$relations = $wpdb->prefix . 'SpotGal_gallery_items';
$original_post = $_POST;
$original_get = $_GET;
$original_request = $_REQUEST;
$user_id = get_current_user_id();
$created = array();
$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

try {
	$wpdb->insert( $table, array( 'type' => 'gallery', 'title' => 'Recovery test gallery', 'description' => '', 'url' => '' ) );
	$gallery_id = (int) $wpdb->insert_id;
	gfsw_expect( $gallery_id > 0, 'Fixture gallery must be created.' );
	$created[] = $gallery_id;
	$base = array(
		'id' => '0', 'type' => 'trip', 'title' => "Recovery test O'Connor",
		'description' => "Line one\n<script>alert('test')</script>",
		'url' => 'https://example.invalid/map?x="bad"',
		'fill_factor' => '30', 'gallery_ids' => array( (string) $gallery_id ),
		'show_description' => '1', 'background' => '#123456', 'color' => '#abcdef',
		'width' => '930', 'height' => '670',
	);
	$query = gfsw_submit( $base );
	gfsw_expect( isset( $query['sw_form'] ) && 'maps' === $query['tab'], 'Invalid URL must return to Maps with a recovery token.' );
	gfsw_expect( ! isset( $query['url'] ), 'Submitted data must not be in the redirect URL.' );
	gfsw_expect( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) === $before + 1, 'Validation must not save a map.' );
	$key_method = new ReflectionMethod( 'Gallery_For_SpotWalla', 'form_key' );
	$key_method->setAccessible( true );
	$key = $key_method->invoke( null, $query['sw_form'] );
	$original_cookie = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? $_COOKIE[ LOGGED_IN_COOKIE ] : null;
	$_COOKIE[ LOGGED_IN_COOKIE ] = 'test|9999999999|different-session|test';
	gfsw_expect( false === get_transient( $key_method->invoke( null, $query['sw_form'] ) ), 'Another login session must not access recovery data.' );
	if ( null === $original_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $original_cookie;
	}
	wp_set_current_user( 999999 );
	gfsw_expect( false === get_transient( $key_method->invoke( null, $query['sw_form'] ) ), 'Another user must not access recovery data.' );
	wp_set_current_user( $user_id );
	$html = gfsw_render( $query );
	foreach ( array( esc_attr( $base['title'] ), esc_attr( $base['url'] ), esc_textarea( $base['description'] ), 'value="930"', 'value="670"', 'value="#123456"', 'value="#abcdef"', 'sw-error-url', 'aria-describedby="sw-error-url"', 'Enter an HTTPS public SpotWalla link' ) as $expected ) {
		gfsw_expect( false !== strpos( $html, $expected ), 'Recovered HTML must contain: ' . $expected );
	}
	gfsw_expect( false === strpos( $html, "<script>alert('test')</script>" ), 'Recovered text must be escaped.' );
	gfsw_expect( preg_match( '/name="show_description"[^>]*checked/', $html ) === 1, 'Checked description visibility must survive.' );
	gfsw_expect( preg_match( '/name="show_title"[^>]*checked/', $html ) === 0, 'Unchecked title visibility must survive.' );
	gfsw_expect( preg_match( '/name="inherit_theme"[^>]*checked/', $html ) === 0, 'Unchecked theme inheritance must survive.' );
	gfsw_expect( preg_match( '/value="30"[^>]*selected/', $html ) === 1, 'Density selection must survive.' );
	gfsw_expect( preg_match( '/name="gallery_ids\\[\\]" value="' . $gallery_id . '"[^>]*checked/', $html ) === 1, 'Gallery selection must survive.' );
	gfsw_expect( false === get_transient( $key ), 'Recovery data must be deleted after use.' );
	gfsw_expect( false !== strpos( gfsw_render( $query ), 'expired or was already opened' ), 'Replayed recovery token must show an explicit error.' );

	$invalid = array_merge( $base, array( 'title' => '', 'fill_factor' => 'invalid', 'gallery_ids' => array( '999999' ) ) );
	$html = gfsw_render( gfsw_submit( $invalid ) );
	foreach ( array( 'sw-error-title', 'sw-error-url', 'sw-error-fill_factor', 'sw-error-gallery_ids', 'Unavailable gallery (#999999)' ) as $expected ) {
		gfsw_expect( false !== strpos( $html, $expected ), 'All validation errors must render: ' . $expected );
	}
	$html = gfsw_render( gfsw_submit( array_merge( $base, array( 'title' => str_repeat( "\xc3\xa9", 128 ), 'type' => 'unknown' ) ) ) );
	gfsw_expect( false !== strpos( $html, 'Shorten the title to 255 bytes' ) && false !== strpos( $html, 'sw-error-type' ), 'Multibyte title limits and invalid types must show field errors.' );
	$expired = gfsw_submit( $base );
	delete_transient( $key_method->invoke( null, $expired['sw_form'] ) );
	gfsw_expect( false !== strpos( gfsw_render( $expired ), 'The form could not be recovered.' ), 'Expired drafts must not claim to retain entries.' );
	$valid = array_merge( $base, array( 'url' => 'https://spotwalla.com/trip/view?id=123' ) );
	$query = gfsw_submit( $valid );
	gfsw_expect( 'saved' === $query['sw_message'], 'Corrected form must save normally.' );
	$map_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE title = %s', $table, $base['title'] ) );
	$created[] = $map_id;
	gfsw_expect( $map_id > 0, 'Corrected form must create one map.' );
	gfsw_expect( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE item_id = %d AND gallery_id = %d', $relations, $map_id, $gallery_id ) ) === 1, 'Normal save must retain gallery membership.' );

	$html = gfsw_render( gfsw_submit( array_merge( $base, array( 'id' => (string) $map_id, 'title' => 'Unsaved edit' ) ) ) );
	gfsw_expect( false !== strpos( $html, 'Edit map' ) && false !== strpos( $html, 'value="Unsaved edit"' ), 'Failed edits must keep the editing context and new values.' );
	$failure = function ( $sql ) use ( $table ) {
		return preg_match( '/^INSERT INTO\\s+`?' . preg_quote( $table, '/' ) . '`?\\s/i', $sql ) ? 'SELECT * FROM nonexistent_gfsw_test_table' : $sql;
	};
	$old_suppress = $wpdb->suppress_errors( true );
	add_filter( 'query', $failure );
	try {
		$query = gfsw_submit( array_merge( $valid, array( 'title' => 'Database recovery test' ) ) );
	} finally {
		remove_filter( 'query', $failure );
		$wpdb->suppress_errors( $old_suppress );
	}
	$html = gfsw_render( $query );
	gfsw_expect( false !== strpos( $html, 'value="Database recovery test"' ) && false !== strpos( $html, 'database could not save' ), 'Database errors must keep entries and explain how to retry.' );
	gfsw_expect( false === strpos( $html, 'aria-invalid="true"' ), 'Database failure must not blame valid fields.' );
	$membership_failure = function ( $sql ) use ( $relations ) {
		return preg_match( '/^INSERT INTO\\s+`?' . preg_quote( $relations, '/' ) . '`?\\s/i', $sql ) ? 'SELECT * FROM nonexistent_gfsw_test_table' : $sql;
	};
	$membership_values = array_merge( $valid, array( 'title' => 'Membership recovery test' ) );
	$old_suppress = $wpdb->suppress_errors( true );
	add_filter( 'query', $membership_failure );
	try {
		$query = gfsw_submit( $membership_values );
	} finally {
		remove_filter( 'query', $membership_failure );
		$wpdb->suppress_errors( $old_suppress );
	}
	$partial_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE title = %s', $table, $membership_values['title'] ) );
	$created[] = $partial_id;
	$html = gfsw_render( $query );
	gfsw_expect( $partial_id > 0 && false !== strpos( $html, 'name="id" value="' . $partial_id . '"' ), 'Membership errors must retain the saved ID for retry.' );
	$query = gfsw_submit( array_merge( $membership_values, array( 'id' => (string) $partial_id ) ) );
	gfsw_expect( 'saved' === $query['sw_message'], 'Membership retry must succeed.' );
	gfsw_expect( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE title = %s', $table, $membership_values['title'] ) ) === 1, 'Membership retry must not duplicate the map.' );
	echo "PASS: validation, recovery, escaping, session isolation, replay protection, corrected saves, edits, and database errors.\n";
} finally {
	wp_set_current_user( $user_id );
	foreach ( $created as $id ) {
		$wpdb->delete( $relations, array( 'item_id' => $id ) );
		$wpdb->delete( $relations, array( 'gallery_id' => $id ) );
		$wpdb->delete( $table, array( 'id' => $id ) );
	}
	$_POST = $original_post;
	$_GET = $original_get;
	$_REQUEST = $original_request;
}
