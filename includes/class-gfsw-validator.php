<?php
/**
 * Validator component for Gallery for SpotWalla.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Validator {
	/**
	 * Validates submitted content without reading requests or writing storage.
	 *
	 * @param array $input Unslashed scalar fields.
	 * @param array|null $existing Existing item, if editing.
	 * @param int[]|null $gallery_ids Parsed gallery selections, or invalid input.
	 * @param int[] $found Gallery IDs confirmed by storage.
	 * @return array Validated data and field errors; data must not be saved if errors exist.
	 */
	public static function prepare( array $input, $existing, $gallery_ids, array $found ) {
		$type = sanitize_key( $input['type'] );
		$is_gallery = 'gallery' === $type;
		$title = sanitize_text_field( $input['title'] );
		$url = $is_gallery ? '' : self::public_url( $input['url'] );
		$member_title = $is_gallery ? self::override( $input['member_title'] ) : 'item';
		$member_description = $is_gallery ? self::override( $input['member_description'] ) : 'item';
		$fill_factor = self::fill_factor( $input['fill_factor'] );
		$errors = array();
		if ( ! in_array( $type, array_merge( GFSW_Config::MAP_TYPES, array( 'gallery' ) ), true ) ) {
			$errors['type'] = __( 'Choose Trip, Track, or Retrospective for a map.', 'gallery-for-spotwalla' );
		}
		if ( '' === $title ) {
			$errors['title'] = __( 'Enter a title.', 'gallery-for-spotwalla' );
		} elseif ( strlen( $title ) > 255 ) {
			$errors['title'] = __( 'Shorten the title to 255 bytes or fewer. Accented characters and emoji may use more than one byte.', 'gallery-for-spotwalla' );
		}
		if ( ! $is_gallery && '' === $url ) {
			$errors['url'] = __( 'Enter an HTTPS public SpotWalla link on spotwalla.com, www.spotwalla.com, or new.spotwalla.com. Do not include login credentials or a non-default port.', 'gallery-for-spotwalla' );
		}
		if ( $existing && $existing['type'] !== $type ) {
			$errors['type'] = __( 'An existing map or gallery cannot change type. Restore its original type or create a new item.', 'gallery-for-spotwalla' );
		}
		if ( ! $is_gallery && null === $gallery_ids ) {
			$errors['gallery_ids'] = __( 'Choose galleries from the available list.', 'gallery-for-spotwalla' );
		} elseif ( ! $is_gallery && count( $found ) !== count( $gallery_ids ) ) {
			$errors['gallery_ids'] = __( 'One or more selected galleries no longer exist. Update your selection from the available galleries.', 'gallery-for-spotwalla' );
		}
		if ( null === $member_title ) {
			$errors['member_title'] = __( 'Choose a valid map title visibility setting.', 'gallery-for-spotwalla' );
		}
		if ( null === $member_description ) {
			$errors['member_description'] = __( 'Choose a valid map description visibility setting.', 'gallery-for-spotwalla' );
		}
		if ( null === $fill_factor ) {
			$errors['fill_factor'] = __( 'Choose a density from the available options, or use the SpotWalla default.', 'gallery-for-spotwalla' );
		}
		$data = array(
			'type'               => $type,
			'title'              => $title,
			'description'        => sanitize_textarea_field( $input['description'] ),
			'url'                => $url,
			'show_title'         => $is_gallery || '1' === $input['show_title'] ? 1 : 0,
			'show_description'   => $is_gallery || '1' === $input['show_description'] ? 1 : 0,
			'member_title'       => $member_title,
			'member_description' => $member_description,
			'fill_factor'        => $fill_factor,
			'inherit_theme'      => '1' === $input['inherit_theme'] ? 1 : 0,
			'background'         => sanitize_hex_color( $input['background'] ) ?: '#ffffff',
			'color'              => sanitize_hex_color( $input['color'] ) ?: '#222222',
			'width'              => max( 200, min( 2400, absint( $input['width'] ) ) ),
			'height'             => max( 200, min( 2400, absint( $input['height'] ) ) ),
		);
		return array( 'data' => $data, 'errors' => $errors );
	}

	/**
	 * @param string $value Visibility override.
	 * @return string|null Supported override, or invalid value.
	 */
	private static function override( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, GFSW_Config::OVERRIDES, true ) ? $value : null;
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
	public static function public_url( $url ) {
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
	 * Validates a density (fill percentage) value.
	 *
	 * @param mixed $value Submitted or stored value.
	 * @return string|null The value if it is empty (use the default) or a key of
	 *                      FILL_FACTORS, otherwise null.
	 */
	public static function fill_factor( $value ) {
		$value = (string) $value;
		return '' === $value || array_key_exists( $value, GFSW_Config::FILL_FACTORS ) ? $value : null;
	}
}
