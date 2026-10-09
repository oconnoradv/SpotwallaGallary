<?php
/**
 * Authorized admin request handling for Gallery for SpotWalla.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Admin {
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

	/**
	 * Stops the request unless the user can manage options and the nonce is valid.
	 *
	 * @param string $action Nonce action the submitted form was created with.
	 * @return void
	 */
	private function authorize( $action ) {
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
	private function posted( $key, $default = '' ) {
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
	private function posted_ids( $key ) {
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
	private function tab_for( $type ) {
		return 'gallery' === $type ? 'galleries' : 'maps';
	}

	/**
	 * Redirects back to the plugin page with a status message and exits.
	 *
	 * @param string $message Message key: saved, deleted, invalid, or error.
	 * @param string $tab     Tab to return to.
	 * @return void
	 */
	private function redirect( $message, $tab = 'maps' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => $tab, 'sw_message' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Preserves the submitted form and redirects to field-specific error feedback.
	 *
	 * Called only after authorization. Values are escaped when redisplayed, not
	 * sanitized here, so users can correct the original input without retyping.
	 *
	 * @param array  $errors Field names mapped to error messages.
	 * @param string $tab    Form tab.
	 * @param int    $id     Existing item ID, including an item saved before a membership error.
	 * @return void
	 */
	private function save_error( $errors, $tab, $id ) {
		$item = array( 'id' => $id );
		foreach ( array( 'type', 'title', 'description', 'url', 'member_title', 'member_description', 'fill_factor', 'background', 'color', 'width', 'height' ) as $field ) {
			$item[ $field ] = $this->posted( $field );
		}
		foreach ( array( 'show_title', 'show_description', 'inherit_theme' ) as $field ) {
			$item[ $field ] = '1' === $this->posted( $field ) ? 1 : 0;
		}
		$token = $this->recovery->save( array( 'item' => $item, 'gallery_ids' => $this->posted_ids( 'gallery_ids' ) ?: array(), 'errors' => $errors ) );
		if ( false === $token ) {
			wp_die( esc_html__( 'The form could not be preserved. Use your browser Back button to recover your entries and try again.', 'gallery-for-spotwalla' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => GFSW_Config::SLUG, 'tab' => $tab, 'sw_form' => $token ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handles the add/edit form for maps and galleries (admin-post action gfsw_save).
	 *
	 * Validates every field, rejects type changes and unknown galleries, saves the
	 * item, and replaces a map's gallery memberships. Always redirects.
	 *
	 * @return void
	 */
	public function save() {
		$this->authorize( 'gfsw_save' );
		$input = array();
		$defaults = array( 'type' => '', 'title' => '', 'description' => '', 'url' => '', 'show_title' => '', 'show_description' => '', 'member_title' => 'item', 'member_description' => 'item', 'fill_factor' => '', 'inherit_theme' => '', 'background' => '', 'color' => '', 'width' => '800', 'height' => '450' );
		foreach ( $defaults as $field => $default ) {
			$input[ $field ] = $this->posted( $field, $default );
		}
		$id         = absint( $this->posted( 'id' ) );
		$type       = sanitize_key( $input['type'] );
		$is_gallery = 'gallery' === $type;
		$tab        = $this->tab_for( $type );
		$existing = $id ? $this->store->item( $id ) : null;
		$gallery_ids = $is_gallery ? array() : $this->posted_ids( 'gallery_ids' );
		$found = $gallery_ids ? $this->store->existing_gallery_ids( $gallery_ids ) : array();
		$prepared = GFSW_Validator::prepare( $input, $existing, $gallery_ids, $found );
		$errors = $prepared['errors'];
		if ( $id && ! $existing ) {
			$errors['form'] = __( 'This item no longer exists. Your entries have been kept so you can add it again.', 'gallery-for-spotwalla' );
			$id = 0;
		} elseif ( $existing && $existing['type'] !== $type ) {
			$tab = $this->tab_for( $existing['type'] );
		}
		if ( $errors ) {
			$this->save_error( $errors, $tab, $id );
		}
		$result = $this->store->save_item( $id, $prepared['data'] );
		if ( false === $result ) {
			$this->save_error( array( 'form' => __( 'The database could not save this item. Your entries have been kept. Try saving again; if the problem continues, contact your site administrator.', 'gallery-for-spotwalla' ) ), $tab, $id );
		}
		if ( ! $is_gallery ) {
			$item_id = $result;
			if ( false === $this->store->clear_memberships( $item_id ) ) {
				$this->save_error( array( 'gallery_ids' => __( 'The map was saved, but its gallery memberships could not be updated. Check your selections and save again.', 'gallery-for-spotwalla' ) ), $tab, $item_id );
			}
			foreach ( $gallery_ids as $gallery_id ) {
				if ( false === $this->store->add_membership( $gallery_id, $item_id ) ) {
					$this->save_error( array( 'gallery_ids' => __( 'The map was saved, but some gallery memberships could not be saved. Check your selections and save again.', 'gallery-for-spotwalla' ) ), $tab, $item_id );
				}
			}
		}
		$this->redirect( 'saved', $tab );
	}

	/**
	 * Deletes a map or gallery and its memberships (admin-post action gfsw_delete).
	 *
	 * Deleting a gallery keeps its maps. Always redirects.
	 *
	 * @return void
	 */
	public function delete() {
		$id = absint( $this->posted( 'id' ) );
		$this->authorize( 'gfsw_delete_' . $id );
		$item = $this->store->item( $id );
		if ( ! $item ) {
			$this->redirect( 'invalid' );
		}
		$tab    = $this->tab_for( $item['type'] );
		if ( false === $this->store->clear_memberships( $id, 'gallery' === $item['type'] ) ) {
			$this->redirect( 'error', $tab );
		}
		$result = $this->store->delete_item( $id );
		$this->redirect( false === $result ? 'error' : 'deleted', $tab );
	}

	/**
	 * Saves the data-retention setting (admin-post action gfsw_settings).
	 *
	 * @return void
	 */
	public function settings() {
		$this->authorize( 'gfsw_settings' );
		$tab    = 'galleries' === sanitize_key( $this->posted( 'tab' ) ) ? 'galleries' : 'maps';
		$result = $this->store->set_setting( 'delete_on_deactivation', '1' === $this->posted( 'delete_on_deactivation' ) ? '1' : '0' );
		$this->redirect( false === $result ? 'error' : 'saved', $tab );
	}

}
