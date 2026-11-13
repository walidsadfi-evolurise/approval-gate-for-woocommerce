<?php
/**
 * Admin screen: review queue, keys, settings.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

require_once WCAG_DIR . 'admin/class-wcag-list-table.php';

/**
 * WooCommerce → Approval Gate.
 */
class WCAG_Admin {

	const PAGE = 'wcag';

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wcag_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCAG_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		add_action( 'admin_notices', array( __CLASS__, 'keys_screen_notice' ) );
	}

	/**
	 * Menu entry with pending badge.
	 */
	public static function menu() {
		$pending = WCAG_Store::counts()['pending'];
		$label   = __( 'Approval Gate', 'approval-gate-for-woocommerce' );
		if ( $pending ) {
			$label .= ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>';
		}
		add_submenu_page(
			'woocommerce',
			__( 'Approval Gate', 'approval-gate-for-woocommerce' ),
			$label,
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Admin bar badge so reviewers see pending proposals from anywhere.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$pending = WCAG_Store::counts()['pending'];
		if ( ! $pending ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'wcag-pending',
				/* translators: %d: count */
				'title' => sprintf( esc_html__( '%d to approve', 'approval-gate-for-woocommerce' ), (int) $pending ),
				'href'  => admin_url( 'admin.php?page=wcag' ),
			)
		);
	}

	/**
	 * Hint on WooCommerce → Settings → Advanced → REST API.
	 */
	public static function keys_screen_notice() {
		if ( ! isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) || 'wc-settings' !== $_GET['page'] || 'advanced' !== $_GET['tab'] || 'keys' !== $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Approval Gate: put a key in proposal mode and its writes will wait for your approval instead of being applied.', 'approval-gate-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=wcag&tab=keys' ) ),
			esc_html__( 'Choose keys', 'approval-gate-for-woocommerce' )
		);
	}

	/**
	 * Settings link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wcag' ) ) . '">' . esc_html__( 'Review queue', 'approval-gate-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'wcag_settings_group',
			WCAG_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WCAG_Settings', 'sanitize' ),
				'default'           => WCAG_Settings::defaults(),
			)
		);
	}

	/**
	 * POST actions.
	 */
	public static function handle_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'approval-gate-for-woocommerce' ) );
		}
		check_admin_referer( 'wcag_action' );

		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$ids      = isset( $_POST['row_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['row_ids'] ) ) : array();
		$single   = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0;
		$redirect = admin_url( 'admin.php?page=wcag' );
		$msgs     = array();

		if ( $single ) {
			$ids = array( $single );
		}
		if ( in_array( $do, array( 'bulk_approve', 'approve' ), true ) ) {
			$do = 'approve';
		} elseif ( in_array( $do, array( 'bulk_reject', 'reject' ), true ) ) {
			$do = 'reject';
		}

		switch ( $do ) {
			case 'approve':
				foreach ( $ids as $id ) {
					$r = WCAG_Decision::approve( $id, get_current_user_id(), $note );
					if ( is_wp_error( $r ) ) {
						$msgs[] = "#{$id}: " . $r->get_error_message();
					} else {
						/* translators: 1: id, 2: status, 3: http code */
						$msgs[] = sprintf( __( '#%1$d %2$s (HTTP %3$d)', 'approval-gate-for-woocommerce' ), $id, $r['status'], $r['code'] );
					}
				}
				break;

			case 'reject':
				foreach ( $ids as $id ) {
					$r = WCAG_Decision::reject( $id, get_current_user_id(), $note );
					/* translators: %d: id */
					$msgs[] = is_wp_error( $r ) ? "#{$id}: " . $r->get_error_message() : sprintf( __( '#%d rejected', 'approval-gate-for-woocommerce' ), $id );
				}
				break;

			case 'save_keys':
				$settings               = WCAG_Settings::all();
				$settings['gated_keys'] = isset( $_POST['gated_keys'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['gated_keys'] ) ) : array();
				update_option( WCAG_Settings::OPTION, WCAG_Settings::sanitize( $settings ) );
				$msgs[]   = __( 'Keys saved.', 'approval-gate-for-woocommerce' );
				$redirect = admin_url( 'admin.php?page=wcag&tab=keys' );
				break;

			case 'expire_now':
				/* translators: %d: count */
				$msgs[] = sprintf( __( '%d proposals expired.', 'approval-gate-for-woocommerce' ), WCAG_Decision::expire() );
				break;
		}

		wp_safe_redirect( add_query_arg( 'wcag_msg', rawurlencode( implode( ' · ', $msgs ) ), $redirect ) );
		exit;
	}

	/**
	 * Render.
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'queue';
		$proposal = isset( $_GET['proposal'] ) ? (int) $_GET['proposal'] : 0;
		echo '<div class="wrap wcag-wrap"><h1>' . esc_html__( 'Approval Gate', 'approval-gate-for-woocommerce' ) . '</h1>';
		if ( isset( $_GET['wcag_msg'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wcag_msg'] ) ) ) . '</p></div>';
		}
		// phpcs:enable

		$counts = WCAG_Store::counts();
		$tabs   = array(
			'queue'    => sprintf( '%s (%d)', __( 'Review queue', 'approval-gate-for-woocommerce' ), $counts['pending'] ),
			'history'  => __( 'History', 'approval-gate-for-woocommerce' ),
			'keys'     => __( 'Keys', 'approval-gate-for-woocommerce' ),
			'settings' => __( 'Settings', 'approval-gate-for-woocommerce' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $tab === $k && ! $proposal ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=wcag&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</nav>';

		if ( $proposal ) {
			$row = WCAG_Store::get( $proposal );
			include WCAG_DIR . 'admin/views/proposal.php';
		} else {
			switch ( $tab ) {
				case 'settings':
					include WCAG_DIR . 'admin/views/settings.php';
					break;
				case 'keys':
					include WCAG_DIR . 'admin/views/keys.php';
					break;
				case 'history':
					$wcag_status = '';
					include WCAG_DIR . 'admin/views/queue.php';
					break;
				default:
					$wcag_status = 'pending';
					include WCAG_DIR . 'admin/views/queue.php';
			}
		}
		echo '</div>';
	}

	/**
	 * Badge markup.
	 *
	 * @param string $risk Risk.
	 * @return string
	 */
	public static function risk_badge( $risk ) {
		$colors = array(
			'low'    => '#2e7d32',
			'medium' => '#ef6c00',
			'high'   => '#c62828',
		);
		$c      = isset( $colors[ $risk ] ) ? $colors[ $risk ] : '#555';
		return '<span style="display:inline-block;padding:1px 7px;border-radius:9px;color:#fff;font-size:11px;background:' . esc_attr( $c ) . '">' . esc_html( $risk ) . '</span>';
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_badge( $status ) {
		$colors = array(
			'pending'  => '#1565c0',
			'approved' => '#2e7d32',
			'rejected' => '#616161',
			'expired'  => '#9e9e9e',
			'failed'   => '#c62828',
		);
		$c      = isset( $colors[ $status ] ) ? $colors[ $status ] : '#555';
		return '<span style="display:inline-block;padding:1px 7px;border-radius:9px;color:#fff;font-size:11px;background:' . esc_attr( $c ) . '">' . esc_html( $status ) . '</span>';
	}
}
