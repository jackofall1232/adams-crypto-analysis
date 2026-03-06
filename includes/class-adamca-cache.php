<?php
/**
 * Cache helpers using WordPress transients.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

class ADAMCA_Cache {

    /**
     * Retrieve cached analysis HTML for a coin.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return string|false Cached HTML or false on miss.
     */
    public static function retrieve( $coin_id ) {
        $cache_key = 'adamca_analysis_' . sanitize_key( $coin_id );
        $cached_html = get_transient( $cache_key );

        if ( false !== $cached_html ) {
            error_log( '[ADAMCA Cache] Hit for ' . $coin_id );
            return $cached_html;
        }

        error_log( '[ADAMCA Cache] Miss for ' . $coin_id );
        return false;
    }

    /**
     * Store analysis HTML in transient cache with metadata.
     *
     * @param string $coin_id     CoinGecko coin identifier.
     * @param string $html_output The analysis HTML to cache.
     * @return void
     */
    public static function store_analysis( $coin_id, $html_output ) {
        $sanitized_id   = sanitize_key( $coin_id );
        $cache_key      = 'adamca_analysis_' . $sanitized_id;
        $meta_key       = 'adamca_analysis_' . $sanitized_id . '_meta';
        $expiry_seconds = (int) get_option( 'adamca_cache_expiry', 43200 );

        set_transient( $cache_key, $html_output, $expiry_seconds );

        $metadata = array(
            'coin_id'   => $coin_id,
            'timestamp' => time(),
            'source'    => 'on_demand',
            'expiry'    => $expiry_seconds,
            'provider'  => get_option( 'adamca_ai_provider' ),
            'model'     => get_option( 'adamca_ai_model' ),
        );
        update_option( $meta_key, $metadata, false );

        // Track this coin in the cached coins list.
        $coins_list = get_option( 'adamca_cached_coins_list', array() );
        if ( ! in_array( $sanitized_id, $coins_list, true ) ) {
            $coins_list[] = $sanitized_id;
            update_option( 'adamca_cached_coins_list', $coins_list, false );
        }

        error_log( '[ADAMCA Cache] Stored analysis for ' . $coin_id . ' (TTL: ' . $expiry_seconds . 's)' );
    }

    /**
     * Clear cached analysis for a single coin.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return void
     */
    public static function clear_cache( $coin_id ) {
        $sanitized_id = sanitize_key( $coin_id );
        $cache_key    = 'adamca_analysis_' . $sanitized_id;
        $meta_key     = 'adamca_analysis_' . $sanitized_id . '_meta';

        delete_transient( $cache_key );
        delete_option( $meta_key );

        // Remove from tracking list.
        $coins_list = get_option( 'adamca_cached_coins_list', array() );
        $coins_list = array_values( array_diff( $coins_list, array( $sanitized_id ) ) );
        update_option( 'adamca_cached_coins_list', $coins_list, false );

        error_log( '[ADAMCA Cache] Cleared cache for ' . $coin_id );
    }

    /**
     * Clear all cached analyses.
     *
     * @return int Number of coins cleared.
     */
    public static function clear_all_cache() {
        $coins_list = get_option( 'adamca_cached_coins_list', array() );
        $clear_count = 0;

        foreach ( $coins_list as $sanitized_id ) {
            delete_transient( 'adamca_analysis_' . $sanitized_id );
            delete_option( 'adamca_analysis_' . $sanitized_id . '_meta' );
            $clear_count++;
        }

        update_option( 'adamca_cached_coins_list', array(), false );
        error_log( '[ADAMCA Cache] Cleared all cache (' . $clear_count . ' coins)' );

        return $clear_count;
    }

    /**
     * Get cache metadata for a coin.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return array|false Metadata array or false if not cached.
     */
    public static function get_cache_metadata( $coin_id ) {
        $meta_key = 'adamca_analysis_' . sanitize_key( $coin_id ) . '_meta';
        $metadata = get_option( $meta_key, false );
        return is_array( $metadata ) ? $metadata : false;
    }

    /**
     * Get status of all cached coins.
     *
     * @return array Array of coin status entries.
     */
    public static function get_all_cached_status() {
        $coins_list   = get_option( 'adamca_cached_coins_list', array() );
        $status_list  = array();
        $current_time = time();

        foreach ( $coins_list as $sanitized_id ) {
            $metadata = self::get_cache_metadata( $sanitized_id );
            if ( ! $metadata ) {
                continue;
            }

            $age_seconds   = $current_time - $metadata['timestamp'];
            $status_list[] = array(
                'coin_id'           => $metadata['coin_id'],
                'cache_age_minutes' => (int) floor( $age_seconds / 60 ),
                'expiry_seconds'    => $metadata['expiry'],
                'provider'          => $metadata['provider'],
                'model'             => $metadata['model'],
                'is_top_ten'        => self::is_top_ten( $sanitized_id ),
                'still_valid'       => ( false !== get_transient( 'adamca_analysis_' . $sanitized_id ) ),
            );
        }

        return $status_list;
    }

    /**
     * Check if a coin is in the top 10 list.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return bool True if coin is in the top 10 list.
     */
    public static function is_top_ten( $coin_id ) {
        $top_ten_raw  = get_option( 'adamca_top10_coins', '' );
        $top_ten_list = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( "\n", $top_ten_raw ) ) ) );
        return in_array( sanitize_key( $coin_id ), $top_ten_list, true );
    }
}
