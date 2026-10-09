<?php
/**
 * Short-lived, user/session-scoped failed-form storage.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Form_Recovery {
	/**
	 * @param string $token Random recovery token.
	 * @return string Transient key.
	 */
	public static function key( $token ) {
		return 'gfsw_form_' . hash( 'sha256', get_current_user_id() . ':' . wp_get_session_token() . ':' . $token );
	}

	/**
	 * @param array $draft Submitted fields, selections, and errors.
	 * @return string|false Token, or failure to preserve the form.
	 */
	public function save( array $draft ) {
		$token = wp_generate_password( 32, false, false );
		return set_transient( self::key( $token ), $draft, 10 * MINUTE_IN_SECONDS ) ? $token : false;
	}

	/**
	 * @param string $token Recovery token.
	 * @return array|false Draft, or unavailable/expired token.
	 */
	public function consume( $token ) {
		if ( ! preg_match( '/^[a-zA-Z0-9]{32}$/', $token ) ) {
			return false;
		}
		$draft = get_transient( self::key( $token ) );
		if ( is_array( $draft ) ) {
			delete_transient( self::key( $token ) );
		}
		return $draft;
	}
}
