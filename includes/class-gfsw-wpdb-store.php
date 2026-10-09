<?php
/**
 * WordPress database adapter for content and settings.
 *
 * @package Gallery_For_SpotWalla
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables.

final class GFSW_Wpdb_Store implements GFSW_Store {
	/** @var wpdb */
	private $db;

	/** @param wpdb $db WordPress database connection. */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * @param string $name Table suffix.
	 * @param string $prefix Plugin table prefix.
	 * @return string Full table name.
	 */
	public function table( $name, $prefix = GFSW_Config::TABLE_PREFIX ) {
		return $this->db->prefix . $prefix . $name;
	}

	/** @inheritDoc */
	public function item( $id ) {
		return $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table( 'items' ), $id ), ARRAY_A );
	}

	/** @inheritDoc */
	public function gallery_maps( $id ) {
		return $this->db->get_results( $this->db->prepare( "SELECT m.* FROM %i m INNER JOIN %i r ON r.item_id = m.id WHERE r.gallery_id = %d AND m.type <> 'gallery' ORDER BY m.id ASC", $this->table( 'items' ), $this->table( 'gallery_items' ), $id ), ARRAY_A );
	}

	/** @inheritDoc */
	public function setting( $key ) {
		return $this->db->get_var( $this->db->prepare( 'SELECT setting_value FROM %i WHERE setting_key = %s', $this->table( 'settings' ), $key ) );
	}

	/** @inheritDoc */
	public function set_setting( $key, $value ) {
		return $this->db->replace( $this->table( 'settings' ), array( 'setting_key' => $key, 'setting_value' => $value ) );
	}

	/** @inheritDoc */
	public function items( $galleries ) {
		$sql = $galleries ? "SELECT * FROM %i WHERE type = 'gallery' ORDER BY id DESC" : "SELECT * FROM %i WHERE type <> 'gallery' ORDER BY id DESC";
		return $this->db->get_results( $this->db->prepare( $sql, $this->table( 'items' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is one of two fixed literals.
	}

	/** @inheritDoc */
	public function memberships() {
		return $this->db->get_results( $this->db->prepare( "SELECT r.gallery_id, r.item_id FROM %i r INNER JOIN %i g ON g.id = r.gallery_id AND g.type = 'gallery' INNER JOIN %i m ON m.id = r.item_id AND m.type <> 'gallery' ORDER BY r.gallery_id ASC", $this->table( 'gallery_items' ), $this->table( 'items' ), $this->table( 'items' ) ), ARRAY_A );
	}

	/** @inheritDoc */
	public function gallery_ids_for( $id ) {
		return array_map( 'absint', $this->db->get_col( $this->db->prepare( 'SELECT gallery_id FROM %i WHERE item_id = %d', $this->table( 'gallery_items' ), $id ) ) );
	}

	/** @inheritDoc */
	public function existing_gallery_ids( $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// $placeholders contains only %d placeholders, one per ID.
		return array_map( 'absint', $this->db->get_col( $this->db->prepare( "SELECT id FROM %i WHERE type = 'gallery' AND id IN ($placeholders)", array_merge( array( $this->table( 'items' ) ), $ids ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @inheritDoc */
	public function save_item( $id, array $data ) {
		$result = $id ? $this->db->update( $this->table( 'items' ), $data, array( 'id' => $id ) ) : $this->db->insert( $this->table( 'items' ), $data );
		return false === $result ? false : ( $id ?: (int) $this->db->insert_id );
	}

	/** @inheritDoc */
	public function delete_item( $id ) {
		return $this->db->delete( $this->table( 'items' ), array( 'id' => $id ) );
	}

	/** @inheritDoc */
	public function clear_memberships( $id, $gallery = false ) {
		return $this->db->delete( $this->table( 'gallery_items' ), array( $gallery ? 'gallery_id' : 'item_id' => $id ) );
	}

	/** @inheritDoc */
	public function add_membership( $gallery_id, $item_id ) {
		return $this->db->insert( $this->table( 'gallery_items' ), array( 'gallery_id' => $gallery_id, 'item_id' => $item_id ) );
	}
}
