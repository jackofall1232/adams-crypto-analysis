<?php
/**
 * Clean up all plugin data on uninstall.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete known options.
$adamca_option_names = array(
    'adamca_coingecko_api_key',
    'adamca_ai_provider',
    'adamca_ai_api_key',
    'adamca_ai_model',
    'adamca_top10_coins',
    'adamca_cache_expiry',
    'adamca_cached_coins_list',
);

foreach ( $adamca_option_names as $option_name ) {
    delete_option( $option_name );
}

// Delete all analysis transients and meta options via direct DB query.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$meta_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'adamca_analysis_%'"
);

foreach ( $meta_options as $adamca_meta_option_name ) {
    delete_option( $adamca_meta_option_name );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$transient_options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_adamca_analysis_%' OR option_name LIKE '_transient_timeout_adamca_analysis_%'"
);

foreach ( $transient_options as $transient_name ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->delete( $wpdb->options, array( 'option_name' => $transient_name ) );
}
