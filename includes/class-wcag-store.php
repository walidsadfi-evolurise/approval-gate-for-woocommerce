<?php
/**
 * Proposals table.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per proposed write.
 */
class WCAG_Store {

	const DB_VERSION = '1';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wcag_proposals';
	}

	/**
	 * Create / upgrade.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			key_description VARCHAR(200) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			method VARCHAR(10) NOT NULL,
			route VARCHAR(255) NOT NULL,
			resource VARCHAR(32) NOT NULL DEFAULT '',
			query_params LONGTEXT NULL,
			body LONGTEXT NULL,
			summary VARCHAR(500) NOT NULL DEFAULT '',
			risk VARCHAR(10) NOT NULL DEFAULT 'low',
			reason TEXT NULL,
			idempotency_key VARCHAR(100) NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			result_code SMALLINT UNSIGNED NULL,
			result_body LONGTEXT NULL,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			decision_note TEXT NULL,
			expires_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status_created (status, created_at),
			KEY key_id (key_id),
			KEY idem (key_id, idempotency_key(80))
		) {$charset};";
		dbDelta( $sql );
		update_option( 'wcag_db_version', self::DB_VERSION, false );
	}

	/**
	 * Drop.
	 */
	public static function drop() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		delete_option( 'wcag_db_version' );
	}

	/**
	 * Insert.
	 *
	 * @param array $row Row.
	 * @return int|false Row id.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$row['uuid']       = isset( $row['uuid'] ) ? $row['uuid'] : wp_generate_uuid4();
		$row['created_at'] = isset( $row['created_at'] ) ? $row['created_at'] : current_time( 'mysql', true );
		foreach ( array( 'query_params', 'body' ) as $k ) {
			if ( isset( $row[ $k ] ) && ! is_string( $row[ $k ] ) ) {
				$row[ $k ] = wp_json_encode( $row[ $k ] );
			}
		}
		$ok = $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update.
	 *
	 * @param int   $id   Id.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get one.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get by uuid.
	 *
	 * @param string $uuid UUID.
	 * @return object|null
	 */
	public static function get_by_uuid( $uuid ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", (string) $uuid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Find a pending proposal with the same idempotency key from the same key.
	 *
	 * @param int    $key_id Key id.
	 * @param string $idem   Idempotency key.
	 * @return object|null
	 */
	public static function find_idempotent( $key_id, $idem ) {
		global $wpdb;
		if ( '' === (string) $idem ) {
			return null;
		}
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE key_id = %d AND idempotency_key = %s ORDER BY id DESC LIMIT 1", (int) $key_id, (string) $idem ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Query.
	 *
	 * @param array $args status, key_id, risk, search, per_page, page, orderby, order.
	 * @return array rows, total
	 */
	public static function query( array $args ) {
		global $wpdb;
		$table = self::table();
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( ! empty( $args['key_id'] ) ) {
			$where[] = 'key_id = %d';
			$vals[]  = (int) $args['key_id'];
		}
		if ( ! empty( $args['risk'] ) ) {
			$where[] = 'risk = %s';
			$vals[]  = $args['risk'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(summary LIKE %s OR route LIKE %s OR key_description LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 );
		$page      = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$orderby   = in_array( isset( $args['orderby'] ) ? $args['orderby'] : '', array( 'id', 'created_at', 'risk', 'status', 'key_id' ), true ) ? $args['orderby'] : 'id';
		$order     = isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ? 'ASC' : 'DESC';
		$offset    = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$rows_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$total     = (int) $wpdb->get_var( $vals ? $wpdb->prepare( $count_sql, $vals ) : $count_sql );
		$rows      = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $vals, array( $per_page, $offset ) ) ) );
		// phpcs:enable
		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Counts per status.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$out   = array(
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
			'expired'  => 0,
			'failed'   => 0,
		);
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	/**
	 * Expire pending proposals past their date.
	 *
	 * @return int
	 */
	public static function expire_due() {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired', decided_at = %s WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < %s", $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Purge decided proposals older than N days.
	 *
	 * @param int $days Days.
	 * @return int
	 */
	public static function purge( $days ) {
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status <> 'pending' AND created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * WooCommerce API keys (id, description, user_id, permissions).
	 *
	 * @return array
	 */
	public static function wc_keys() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT key_id, user_id, description, permissions, truncated_key FROM {$wpdb->prefix}woocommerce_api_keys ORDER BY key_id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $rows ? $rows : array();
	}

	/**
	 * One WooCommerce key row.
	 *
	 * @param int $key_id Key id.
	 * @return object|null
	 */
	public static function wc_key( $key_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT key_id, user_id, description, permissions, truncated_key FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d", (int) $key_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Find the WooCommerce key row matching the consumer key of the current request.
	 *
	 * @param string $consumer_key Consumer key (plain).
	 * @return object|null
	 */
	public static function wc_key_by_consumer_key( $consumer_key ) {
		global $wpdb;
		if ( '' === (string) $consumer_key || ! function_exists( 'wc_api_hash' ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT key_id, user_id, description, permissions, truncated_key FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s", wc_api_hash( sanitize_text_field( $consumer_key ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
