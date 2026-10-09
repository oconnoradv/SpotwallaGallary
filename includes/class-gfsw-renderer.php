<?php
/**
 * Renderer component for Gallery for SpotWalla.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSW_Renderer {
	/** @var GFSW_Item_Reader */
	private $store;

	/**
	 * @param GFSW_Item_Reader $store Persistence dependency.
	 */
	public function __construct( GFSW_Item_Reader $store ) {
		$this->store = $store;
	}

	/**
	 * Builds the inline style attribute for a map card or gallery.
	 *
	 * @param array $item Map or gallery row.
	 * @return string The attribute (with a leading space), or an empty string when
	 *                 the item inherits the site theme.
	 */
	private function style( $item ) {
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
	private function visible( $item, $gallery, $field ) {
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
	private function embed_url( $url, $item, $gallery ) {
		if ( 'trip' !== $item['type'] ) {
			return $url;
		}
		$fill = $gallery && isset( $gallery['fill_factor'] ) && '' !== (string) $gallery['fill_factor'] ? $gallery['fill_factor'] : ( isset( $item['fill_factor'] ) ? $item['fill_factor'] : '' );
		$fill = GFSW_Validator::fill_factor( $fill );
		return null === $fill || '' === $fill ? $url : add_query_arg( 'fillFactor', $fill, $url );
	}


	/**
	 * Renders one map and its sandboxed, lazy-loaded SpotWalla iframe.
	 *
	 * @param array      $item    Map row.
	 * @param array|null $gallery Gallery being rendered, or null for a single map.
	 * @return string HTML, or an empty string if the stored URL is no longer valid.
	 */
	private function card( $item, $gallery = null ) {
		$url = GFSW_Validator::public_url( $item['url'] );
		if ( ! $url ) {
			return '';
		}
		$height     = $item['inherit_theme'] ? 450 : max( 200, min( 2400, absint( $item['height'] ) ) );
		$link_style = $item['inherit_theme'] ? '' : ' style="color:inherit;"';
		$html       = '<article class="sw-gallery-entry sw-gallery-' . esc_attr( $item['type'] ) . '"' . $this->style( $item ) . '>';
		if ( $this->visible( $item, $gallery, 'title' ) ) {
			$html .= '<h3><a href="' . esc_url( $url ) . '"' . $link_style . '>' . esc_html( $item['title'] ) . '</a></h3>';
		}
		if ( $this->visible( $item, $gallery, 'description' ) ) {
			$html .= '<p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		}
		return $html . '<iframe src="' . esc_url( $this->embed_url( $url, $item, $gallery ) ) . '" title="' . esc_attr( $item['title'] ) . '" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts" width="100%" height="' . esc_attr( $height ) . '" style="display:block;width:100%;max-width:100%;border:0;" allowfullscreen></iframe>' .
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
	public function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'gallery_for_spotwalla' );
		if ( ! is_scalar( $attributes['id'] ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $attributes['id'] ) ) {
			return '';
		}
		$item = $this->store->item( absint( $attributes['id'] ) );
		if ( ! $item ) {
			return '';
		}
		if ( 'gallery' !== $item['type'] ) {
			return $this->card( $item );
		}
		$rows      = $this->store->gallery_maps( $item['id'] );
		$html      = '<section class="sw-gallery-group"' . $this->style( $item ) . '><h2>' . esc_html( $item['title'] ) . '</h2><p>' . nl2br( esc_html( $item['description'] ) ) . '</p>';
		foreach ( $rows as $row ) {
			$html .= $this->card( $row, $item );
		}
		return $html . '</section>';
	}
}
