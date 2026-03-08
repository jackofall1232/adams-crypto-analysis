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

foreach ( $adamca_option_names as $adamca_option_name ) {
    delete_option( $adamca_option_name );
}

// Delete all analysis transients and meta options via direct DB query.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$adamca_meta_options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        'adamca\_analysis\_%'
    )
);

foreach ( $adamca_meta_options as $adamca_meta_option_name ) {
    delete_option( $adamca_meta_option_name );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$adamca_transient_options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '\_transient\_adamca\_analysis\_%',
        '\_transient\_timeout\_adamca\_analysis\_%'
    )
);

foreach ( $adamca_transient_options as $adamca_transient_name ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->delete( $wpdb->options, array( 'option_name' => $adamca_transient_name ) );
}
