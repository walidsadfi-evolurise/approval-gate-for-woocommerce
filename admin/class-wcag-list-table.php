<?php
/**
 * Proposals list table.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Queue / history.
 */
class WCAG_List_Table extends WP_List_Table {

	/**
	 * Fixed status filter ('' = all).
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Constructor.
	 *
	 * @param string $status Status.
	 */
	public function __construct( $status = 'pending' ) {
		$this->status = $status;
		parent::__construct(
			array(
				'singular' => 'proposal',
				'plural'   => 'proposals',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$cols = array(
			'cb'         => '<input type="checkbox" />',
			'summary'    => __( 'Proposed change', 'approval-gate-for-woocommerce' ),
			'risk'       => __( 'Risk', 'approval-gate-for-woocommerce' ),
			'key'        => __( 'From key', 'approval-gate-for-woocommerce' ),
			'request'    => __( 'Request', 'approval-gate-for-woocommerce' ),
			'created_at' => __( 'Proposed', 'approval-gate-for-woocommerce' ),
		);
		if ( 'pending' === $this->status ) {
			$cols['expires_at'] = __( 'Expires', 'approval-gate-for-woocommerce' );
		} else {
			$cols['status']  = __( 'Status', 'approval-gate-for-woocommerce' );
			$cols['decided'] = __( 'Decided', 'approval-gate-for-woocommerce' );
			unset( $cols['cb'] );
		}
		return $cols;
	}

	/**
	 * Sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'risk'       => array( 'risk', false ),
			'created_at' => array( 'created_at', true ),
			'key'        => array( 'key_id', false ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		if ( 'pending' !== $this->status ) {
			return array();
		}
		return array(
			'bulk_approve' => __( 'Approve & apply', 'approval-gate-for-woocommerce' ),
			'bulk_reject'  => __( 'Reject', 'approval-gate-for-woocommerce' ),
		);
	}

	/**
	 * Filters.
	 *
	 * @return array
	 */
	protected function get_views() {
		if ( 'pending' === $this->status ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$counts  = WCAG_Store::counts();
		$views   = array();
		$all     = array( '' => __( 'All', 'approval-gate-for-woocommerce' ) ) + array(
			'approved' => __( 'Approved', 'approval-gate-for-woocommerce' ),
			'rejected' => __( 'Rejected', 'approval-gate-for-woocommerce' ),
			'expired'  => __( 'Expired', 'approval-gate-for-woocommerce' ),
			'failed'   => __( 'Failed', 'approval-gate-for-woocommerce' ),
		);
		foreach ( $all as $k => $label ) {
			$n         = '' === $k ? array_sum( $counts ) - $counts['pending'] : $counts[ $k ];
			$views[ $k ? $k : 'all' ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'page' => 'wcag', 'tab' => 'history', 'status' => $k ), admin_url( 'admin.php' ) ) ),
				$current === $k ? 'current' : '',
				esc_html( $label ),
				$n
			);
		}
		return $views;
	}

	/**
	 * Load.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = 25;
		$status   = $this->status;
		if ( '' === $status && ! empty( $_GET['status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['status'] ) );
		}
		$args = array(
			'status'   => $status,
			'key_id'   => isset( $_GET['key_id'] ) ? (int) $_GET['key_id'] : 0,
			'risk'     => isset( $_GET['risk'] ) ? sanitize_key( wp_unslash( $_GET['risk'] ) ) : '',
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'per_page' => $per_page,
			'page'     => $this->get_pagenum(),
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ( 'pending' === $this->status ? 'created_at' : 'id' ),
			'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : ( 'pending' === $this->status ? 'asc' : 'desc' ),
		);
		// phpcs:enable
		if ( '' === $status ) {
			// History: everything except pending. Emulate with a NOT by querying all and filtering is wasteful; use separate statuses.
			$res = $this->query_not_pending( $args );
		} else {
			$res = WCAG_Store::query( $args );
		}
		$this->items = $res['rows'];
		$this->set_pagination_args(
			array(
				'total_items' => $res['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * History query (status <> pending).
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function query_not_pending( array $args ) {
		global $wpdb;
		$table = WCAG_Store::table();
		$where = array( "status <> 'pending'" );
		$vals  = array();
		if ( $args['key_id'] ) {
			$where[] = 'key_id = %d';
			$vals[]  = $args['key_id'];
		}
		if ( $args['risk'] ) {
			$where[] = 'risk = %s';
			$vals[]  = $args['risk'];
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(summary LIKE %s OR route LIKE %s OR key_description LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		$w       = implode( ' AND ', $where );
		$orderby = in_array( $args['orderby'], array( 'id', 'created_at', 'risk', 'status', 'key_id' ), true ) ? $args['orderby'] : 'id';
		$order   = 'asc' === $args['order'] ? 'ASC' : 'DESC';
		$offset  = ( $args['page'] - 1 ) * $args['per_page'];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$count = "SELECT COUNT(*) FROM {$table} WHERE {$w}";
		$total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( $count, $vals ) : $count );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$w} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", array_merge( $vals, array( $args['per_page'], $offset ) ) ) );
		// phpcs:enable
		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" name="row_ids[]" value="' . (int) $item->id . '" />';
	}

	/**
	 * Summary with row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_summary( $item ) {
		$view = admin_url( 'admin.php?page=wcag&proposal=' . (int) $item->id );
		$out  = '<strong><a href="' . esc_url( $view ) . '">' . esc_html( $item->summary ) . '</a></strong>';
		if ( $item->reason ) {
			$out .= '<br><em style="color:#555">' . esc_html( mb_substr( $item->reason, 0, 140 ) ) . '</em>';
		}
		$actions = array( 'view' => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'Inspect payload', 'approval-gate-for-woocommerce' ) . '</a>' );
		if ( 'pending' === $item->status ) {
			$actions['approve'] = sprintf( '<a href="#" class="wcag-quick" data-do="approve" data-id="%d" style="color:#2e7d32">%s</a>', (int) $item->id, esc_html__( 'Approve & apply', 'approval-gate-for-woocommerce' ) );
			$actions['reject']  = sprintf( '<a href="#" class="wcag-quick" data-do="reject" data-id="%d" style="color:#c62828">%s</a>', (int) $item->id, esc_html__( 'Reject', 'approval-gate-for-woocommerce' ) );
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Default column.
	 *
	 * @param object $item   Row.
	 * @param string $column Column.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'risk':
				return WCAG_Admin::risk_badge( $item->risk );
			case 'status':
				return WCAG_Admin::status_badge( $item->status );
			case 'key':
				return esc_html( $item->key_description ) . ' <span style="color:#888">#' . (int) $item->key_id . '</span>';
			case 'request':
				return '<code>' . esc_html( $item->method ) . '</code> <code>' . esc_html( $item->route ) . '</code>';
			case 'created_at':
				/* translators: %s: human time diff */
				return esc_html( sprintf( __( '%s ago', 'approval-gate-for-woocommerce' ), human_time_diff( strtotime( $item->created_at . ' UTC' ) ) ) );
			case 'expires_at':
				if ( ! $item->expires_at ) {
					return '—';
				}
				$in = strtotime( $item->expires_at . ' UTC' ) - time();
				/* translators: %s: human time diff */
				return $in > 0 ? esc_html( sprintf( __( 'in %s', 'approval-gate-for-woocommerce' ), human_time_diff( time(), time() + $in ) ) ) : esc_html__( 'now', 'approval-gate-for-woocommerce' );
			case 'decided':
				if ( ! $item->decided_at ) {
					return '—';
				}
				$who = $item->decided_by ? get_user_by( 'id', $item->decided_by ) : null;
				$out = esc_html( get_date_from_gmt( $item->decided_at, 'Y-m-d H:i' ) );
				if ( $who ) {
					$out .= '<br>' . esc_html( $who->display_name );
				}
				if ( null !== $item->result_code ) {
					$out .= ' <code>HTTP ' . (int) $item->result_code . '</code>';
				}
				return $out;
		}
		return '';
	}

	/**
	 * Empty.
	 */
	public function no_items() {
		if ( 'pending' === $this->status ) {
			esc_html_e( 'Nothing to approve. Writes from gated keys will appear here.', 'approval-gate-for-woocommerce' );
		} else {
			esc_html_e( 'No decided proposals yet.', 'approval-gate-for-woocommerce' );
		}
	}
}
