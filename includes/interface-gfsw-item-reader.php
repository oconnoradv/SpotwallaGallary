<?php
/**
 * Read-only content contract used by the shortcode renderer.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface GFSW_Item_Reader {
	/**
	 * @param int $id Item ID.
	 * @return array|null Stored item.
	 */
	public function item( $id );

	/**
	 * @param int $id Gallery ID.
	 * @return array Maps in creation order.
	 */
	public function gallery_maps( $id );
}
