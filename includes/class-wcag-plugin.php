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
class WCAG_Plugin {

	const CRON_EXPIRE = 'wcag_expire_proposals';

	/**
	 * Singleton.
	 *
	 * @var WCAG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return WCAG_Plugin
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

		load_plugin_textdomain( 'approval-gate-for-woocommerce', false, dirname( plugin_basename( WCAG_FILE ) ) . '/languages' );

		if ( get_option( 'wcag_db_version' ) !== WCAG_Store::DB_VERSION ) {
			WCAG_Store::install();
		}

		WCAG_Gate::init();
		WCAG_Decision::init();
		WCAG_Rest::init();

		if ( ! wp_next_scheduled( self::CRON_EXPIRE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_EXPIRE );
		}

		if ( is_admin() ) {
			require_once WCAG_DIR . 'admin/class-wcag-admin.php';
			WCAG_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WCAG_DIR . 'includes/class-wcag-cli.php';
			WP_CLI::add_command( 'wc approval-gate', 'WCAG_CLI' );
		}
	}

	/**
	 * Notice when WooCommerce is absent.
	 */
	public function missing_woocommerce_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Approval Gate for WooCommerce needs WooCommerce 8.0 or newer to be active.', 'approval-gate-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		WCAG_Store::install();
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
