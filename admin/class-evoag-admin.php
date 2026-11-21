<?php
/**
 * Admin screen: review queue, keys, settings.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

require_once EVOAG_DIR . 'admin/class-evoag-list-table.php';

/**
 * WooCommerce → Approval Gate.
 */
class EVOAG_Admin {

	const PAGE = 'evoag';

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_evoag_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EVOAG_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'keys_screen_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Styles and script for the plugin's own screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'evoag-admin', plugins_url( 'assets/admin.css', EVOAG_FILE ), array(), EVOAG_VERSION );
		wp_enqueue_script( 'evoag-admin', plugins_url( 'assets/admin.js', EVOAG_FILE ), array(), EVOAG_VERSION, true );
		wp_localize_script(
			'evoag-admin',
			'evoagAdmin',
			array(
				'confirmOne'  => __( 'Apply this change to the store now?', 'evolurise-approval-gate-for-woocommerce' ),
				'confirmMany' => __( 'Apply the selected change(s) to the store now?', 'evolurise-approval-gate-for-woocommerce' ),
			)
		);
	}

	/**
	 * Menu entry with pending badge.
	 */
	public static function menu() {
		$pending = EVOAG_Store::counts()['pending'];
		$label   = __( 'Approval Gate', 'evolurise-approval-gate-for-woocommerce' );
		if ( $pending ) {
			$label .= ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>';
		}
		add_submenu_page(
			'woocommerce',
			__( 'Approval Gate', 'evolurise-approval-gate-for-woocommerce' ),
			$label,
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
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
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Approval Gate: put a key in proposal mode and its writes will wait for your approval instead of being applied.', 'evolurise-approval-gate-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=evoag&tab=keys' ) ),
			esc_html__( 'Choose keys', 'evolurise-approval-gate-for-woocommerce' )
		);
	}

	/**
	 * Settings link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=evoag' ) ) . '">' . esc_html__( 'Review queue', 'evolurise-approval-gate-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'evoag_settings_group',
			EVOAG_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'EVOAG_Settings', 'sanitize' ),
				'default'           => EVOAG_Settings::defaults(),
			)
		);
	}

	/**
	 * POST actions.
	 */
	public static function handle_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'evolurise-approval-gate-for-woocommerce' ) );
		}
		check_admin_referer( 'evoag_action' );

		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$ids      = isset( $_POST['row_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['row_ids'] ) ) : array();
		$single   = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0;
		$redirect = admin_url( 'admin.php?page=evoag' );
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
					$r = EVOAG_Decision::approve( $id, get_current_user_id(), $note );
					if ( is_wp_error( $r ) ) {
						$msgs[] = "#{$id}: " . $r->get_error_message();
					} else {
						/* translators: 1: id, 2: status, 3: http code */
						$msgs[] = sprintf( __( '#%1$d %2$s (HTTP %3$d)', 'evolurise-approval-gate-for-woocommerce' ), $id, $r['status'], $r['code'] );
					}
				}
				break;

			case 'reject':
				foreach ( $ids as $id ) {
					$r = EVOAG_Decision::reject( $id, get_current_user_id(), $note );
					/* translators: %d: id */
					$msgs[] = is_wp_error( $r ) ? "#{$id}: " . $r->get_error_message() : sprintf( __( '#%d rejected', 'evolurise-approval-gate-for-woocommerce' ), $id );
				}
				break;

			case 'save_keys':
				$settings               = EVOAG_Settings::all();
				$settings['gated_keys'] = isset( $_POST['gated_keys'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['gated_keys'] ) ) : array();
				update_option( EVOAG_Settings::OPTION, EVOAG_Settings::sanitize( $settings ) );
				$msgs[]   = __( 'Keys saved.', 'evolurise-approval-gate-for-woocommerce' );
				$redirect = admin_url( 'admin.php?page=evoag&tab=keys' );
				break;

			case 'expire_now':
				/* translators: %d: count */
				$msgs[] = sprintf( __( '%d proposals expired.', 'evolurise-approval-gate-for-woocommerce' ), EVOAG_Decision::expire() );
				break;
		}

		if ( $msgs ) {
			// Shown once on the next page load; never read back from the URL.
			set_transient( 'evoag_msg_' . get_current_user_id(), implode( ' · ', $msgs ), MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render.
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'queue';
		$proposal = isset( $_GET['proposal'] ) ? (int) $_GET['proposal'] : 0;
		echo '<div class="wrap evoag-wrap"><h1>' . esc_html__( 'Approval Gate', 'evolurise-approval-gate-for-woocommerce' ) . '</h1>';
		// phpcs:enable
		$msg = get_transient( 'evoag_msg_' . get_current_user_id() );
		if ( false !== $msg ) {
			delete_transient( 'evoag_msg_' . get_current_user_id() );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		$counts = EVOAG_Store::counts();
		$tabs   = array(
			'queue'    => sprintf( '%s (%d)', __( 'Review queue', 'evolurise-approval-gate-for-woocommerce' ), $counts['pending'] ),
			'history'  => __( 'History', 'evolurise-approval-gate-for-woocommerce' ),
			'keys'     => __( 'Keys', 'evolurise-approval-gate-for-woocommerce' ),
			'settings' => __( 'Settings', 'evolurise-approval-gate-for-woocommerce' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $tab === $k && ! $proposal ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=evoag&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</nav>';

		if ( $proposal ) {
			$row = EVOAG_Store::get( $proposal );
			include EVOAG_DIR . 'admin/views/proposal.php';
		} else {
			switch ( $tab ) {
				case 'settings':
					include EVOAG_DIR . 'admin/views/settings.php';
					break;
				case 'keys':
					include EVOAG_DIR . 'admin/views/keys.php';
					break;
				case 'history':
					$evoag_status = '';
					include EVOAG_DIR . 'admin/views/queue.php';
					break;
				default:
					$evoag_status = 'pending';
					include EVOAG_DIR . 'admin/views/queue.php';
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
