<?php
/**
 * Bootstrap.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything together once WooCommerce is loaded.
 */
class EVOAG_Plugin {

	const CRON_EXPIRE = 'evoag_expire_proposals';

	/**
	 * Singleton.
	 *
	 * @var EVOAG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return EVOAG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		if ( get_option( 'evoag_db_version' ) !== EVOAG_Store::DB_VERSION ) {
			EVOAG_Store::install();
		}

		EVOAG_Gate::init();
		EVOAG_Decision::init();
		EVOAG_Rest::init();

		if ( ! wp_next_scheduled( self::CRON_EXPIRE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_EXPIRE );
		}

		if ( is_admin() ) {
			require_once EVOAG_DIR . 'admin/class-evoag-admin.php';
			EVOAG_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EVOAG_DIR . 'includes/class-evoag-cli.php';
			WP_CLI::add_command( 'wc approval-gate', 'EVOAG_CLI' );
		}
	}

	/**
	 * Notice when WooCommerce is absent.
	 */
	public function missing_woocommerce_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Evolurise Approval Gate for WooCommerce needs WooCommerce 8.0 or newer to be active.', 'evolurise-approval-gate-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		EVOAG_Store::install();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( self::CRON_EXPIRE );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_EXPIRE );
		}
	}
}
