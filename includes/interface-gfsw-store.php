<?php
/**
 * Content and settings persistence contract for admin operations.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface GFSW_Store extends GFSW_Item_Reader {
	/**
	 * @param string $key Setting name.
	 * @return string|null Stored value.
	 */
	public function setting( $key );

	/**
	 * @param string $key Setting name.
	 * @param string $value Setting value.
	 * @return int|false Affected rows, or failure.
	 */
	public function set_setting( $key, $value );

	/**
	 * @param bool $galleries Whether to list galleries instead of maps.
	 * @return array Items, newest first.
	 */
	public function items( $galleries );

	/** @return array Valid gallery/map membership pairs. */
	public function memberships();

	/**
	 * @param int $id Map ID.
	 * @return int[] Gallery IDs.
	 */
	public function gallery_ids_for( $id );

	/**
	 * @param int[] $ids Selected gallery IDs.
	 * @return int[] IDs that exist and are galleries.
	 */
	public function existing_gallery_ids( $ids );

	/**
	 * @param int $id Item ID, or zero to insert.
	 * @param array $data Validated storage fields.
	 * @return int|false Saved ID, or failure.
	 */
	public function save_item( $id, array $data );

	/**
	 * @param int $id Item ID.
	 * @return int|false Affected rows, or failure.
	 */
	public function delete_item( $id );

	/**
	 * @param int $id Map or gallery ID.
	 * @param bool $gallery Whether to clear all members of a gallery.
	 * @return int|false Affected rows, or failure.
	 */
	public function clear_memberships( $id, $gallery = false );

	/**
	 * @param int $gallery_id Gallery ID.
	 * @param int $item_id Map ID.
	 * @return int|false Affected rows, or failure.
	 */
	public function add_membership( $gallery_id, $item_id );
}
