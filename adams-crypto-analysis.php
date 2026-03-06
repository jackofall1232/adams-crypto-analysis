<?php
/**
 * Plugin Name: Adams Crypto Analysis
 * Plugin URI:  https://github.com/jackofall1232/adams-crypto-analysis
 * Description: AI-powered cryptocurrency technical analysis with BUY/SELL/HOLD signals via a shortcode.
 * Version:     1.0.0
 * Author:      Adams Crypto
 * Author URI:  https://github.com/jackofall1232
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adams-crypto-analysis
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

define( 'ADAMS_CRYPTO_ANALYSIS_VERSION', '1.0.0' );
define( 'ADAMS_CRYPTO_ANALYSIS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADAMS_CRYPTO_ANALYSIS_URL', plugin_dir_url( __FILE__ ) );

require_once ADAMS_CRYPTO_ANALYSIS_PATH . 'includes/class-adamca-cache.php';
require_once ADAMS_CRYPTO_ANALYSIS_PATH . 'includes/class-adamca-coingecko.php';
require_once ADAMS_CRYPTO_ANALYSIS_PATH . 'includes/class-adamca-ai-client.php';
require_once ADAMS_CRYPTO_ANALYSIS_PATH . 'includes/class-adamca-admin.php';
require_once ADAMS_CRYPTO_ANALYSIS_PATH . 'includes/class-adamca-core.php';

add_action( 'plugins_loaded', function () {
    new ADAMCA_Core();
} );

register_activation_hook( __FILE__, function () {
    $defaults = array(
        'adamca_coingecko_api_key' => '',
        'adamca_ai_provider'       => 'openai',
        'adamca_ai_api_key'        => '',
        'adamca_ai_model'          => '',
        'adamca_cache_expiry'      => 43200,
        'adamca_top10_coins'       => implode( "\n", array(
            'bitcoin',
            'ethereum',
            'binancecoin',
            'solana',
            'ripple',
            'cardano',
            'dogecoin',
            'avalanche-2',
            'chainlink',
            'polkadot',
        ) ),
    );

    foreach ( $defaults as $option_name => $default_value ) {
        if ( false === get_option( $option_name ) ) {
            add_option( $option_name, $default_value );
        }
    }
} );
