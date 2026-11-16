<?php
/**
 * Plugin Name:       Approval Gate for WooCommerce
 * Plugin URI:        https://github.com/walidsadfi-evolurise/approval-gate-for-woocommerce
 * Description:       REST API writes from selected API keys (AI agents, automations, contractors) become proposals. A human reviews and approves them in wp-admin; only then is the change applied, by WooCommerce itself, exactly as requested.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Walid Sadfi
 * Author URI:        https://walidsadfi.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       approval-gate-for-woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

define( 'WCAG_VERSION', '1.0.0' );
define( 'WCAG_FILE', __FILE__ );
define( 'WCAG_DIR', plugin_dir_path( __FILE__ ) );

require_once WCAG_DIR . 'includes/class-wcag-summary.php';
require_once WCAG_DIR . 'includes/class-wcag-settings.php';
require_once WCAG_DIR . 'includes/class-wcag-store.php';
require_once WCAG_DIR . 'includes/class-wcag-gate.php';
require_once WCAG_DIR . 'includes/class-wcag-decision.php';
require_once WCAG_DIR . 'includes/class-wcag-rest.php';
require_once WCAG_DIR . 'includes/class-wcag-plugin.php';

register_activation_hook( __FILE__, array( 'WCAG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCAG_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'WCAG_Plugin', 'instance' ), 20 );
