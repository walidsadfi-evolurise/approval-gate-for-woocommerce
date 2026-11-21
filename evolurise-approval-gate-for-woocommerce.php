<?php
/**
 * Plugin Name:       Evolurise Approval Gate for WooCommerce
 * Plugin URI:        https://github.com/walidsadfi-evolurise/approval-gate-for-woocommerce
 * Description:       REST API writes from selected API keys (AI agents, automations, contractors) become proposals. A human reviews and approves them in wp-admin; only then is the change applied, by WooCommerce itself, exactly as requested.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Evolurise
 * Author URI:        https://evolurise.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       evolurise-approval-gate-for-woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

define( 'EVOAG_VERSION', '1.0.0' );
define( 'EVOAG_FILE', __FILE__ );
define( 'EVOAG_DIR', plugin_dir_path( __FILE__ ) );

require_once EVOAG_DIR . 'includes/class-evoag-summary.php';
require_once EVOAG_DIR . 'includes/class-evoag-settings.php';
require_once EVOAG_DIR . 'includes/class-evoag-store.php';
require_once EVOAG_DIR . 'includes/class-evoag-gate.php';
require_once EVOAG_DIR . 'includes/class-evoag-decision.php';
require_once EVOAG_DIR . 'includes/class-evoag-rest.php';
require_once EVOAG_DIR . 'includes/class-evoag-plugin.php';

register_activation_hook( __FILE__, array( 'EVOAG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EVOAG_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'EVOAG_Plugin', 'instance' ), 20 );
