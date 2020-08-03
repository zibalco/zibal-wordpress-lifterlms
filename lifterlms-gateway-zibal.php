<?php
/**
 * Plugin Name: LifterLMS افزونه پرداخت زیبال
 * Plugin URI: https://zibal.ir/
 * Description: Sell LifterLMS courses and memberships using Zibal Gateway
 * Version: 1.0
 * Author: Yahya Kangi
 * Author URI: https://github.com/YahyaKng
 * Text Domain: lifterlms-zibal
 * Domain Path: /languages
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.2
 * Tested up to: 5.4.2
 *
 * @package     LifterLMS Zibal
 * @category 	Core
 * @author 		Yahya Kangi
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Restrict direct access

if ( ! class_exists( 'LifterLMS_Zibal') ) :

    final class LifterLMS_Zibal {

        /**
         * Plugin Version
         */
        public $version = '1.0';

        /**
         * Singleton class instance
         * @var  obj
         * @since  1.0.0
         * @version  1.0.0
         */
        protected static $_instance = null;

        /**
         * Main Instance of LifterLMS_Zibal
         * Ensures only one instance of LifterLMS_Zibal is loaded or can be loaded.
         * @see LLMS_Gateway_Zibal()
         * @return LifterLMS_Zibal - Main instance
         * @since  1.1.0
         * @version  1.0.0
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Constructor
         * @since  1.0.0
         * @version  1.0.0
         * @return  void
         */
        private function __construct() {

            $this->define_constants();

            add_action( 'plugins_loaded', array( $this, 'init' ), 10 );

        }

        /**
         * Define plugin constants
         * @return   void
         * @since    3.0.0
         * @version  3.0.0
         */
        private function define_constants() {
            // LLMS Zibal Plugin File
            if ( ! defined( 'LLMS_Zibal_PLUGIN_FILE' ) ) {
                define( 'LLMS_Zibal_PLUGIN_FILE', __FILE__ );
            }

            // LLMS Convert Kit Plugin Directory
            if ( ! defined( 'LLMS_Zibal_PLUGIN_DIR' ) ) {
                define( 'LLMS_Zibal_PLUGIN_DIR', WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__) ) . '/');
            }
        }

        /**
         * Initialize, require, add hooks & filters
         * @return  void
         * @since  1.0.0
         * @version  1.0.0
         */
        public function init() {

            // can only function with LifterLMS 3.0.0 or later
            if ( function_exists( 'LLMS' ) && version_compare( '3.0.0-alpha', LLMS()->version, '<=' ) ) {

                add_action( 'lifterlms_settings_save_checkout', array( $this, 'maybe_check_reference_transactions' ) );
                add_filter( 'lifterlms_payment_gateways', array( $this, 'register_gateway' ), 10, 1 );

                require_once 'includes/class.llms.payment.gateway.zibal.php';
            }

        }

        /**
         * When saving the Checkout tab, check reference transactions if the check button was clicked
         * @return   void
         * @since    1.0.0
         * @version  1.0.0
         */
        public function maybe_check_reference_transactions() {

            $gateways = LLMS()->payment_gateways();
            $g = $gateways->get_gateway_by_id( 'Zibal' );

            $check = false;

            // if live creds have changed we should check ref transactions on the new creds
            if ( isset( $_POST[ $g->get_option_name( 'MerchantID' ) ] ) && $g->get_MerchantID() !== $_POST[ $g->get_option_name( 'MerchantID' ) ] ) {

                $check = true;

            } elseif ( isset( $_POST['llms_gateway_Zibal_check_ref_trans'] ) ) {

                $check = true;

            }

            // checkem
            if ( $check ) {

                // wait until after settings are saved so that the check will always be run with the credentials that we're just submitted
                add_action( 'lifterlms_settings_saved', array( $g, 'check_reference_transactions' ) );

            }

        }

        /**
         * Register the gateway with LifterLMS
         * @param   array $gateways array of currently registered gateways
         * @return  array
         * @since  1.0.0
         * @version  1.0.0
         */
        public function register_gateway( $gateways ) {

            $gateways[] = 'LLMS_Payment_Gateway_Zibal';

            return $gateways;

        }

    }

endif;

/**
 * Returns the main instance of LifterLMS_Zibal
 * @return LifterLMS
 * @since  1.0.0
 * @version  1.0.0
 */
function LLMS_Gateway_Zibal() {
    return LifterLMS_Zibal::instance();
}
return LLMS_Gateway_Zibal();
