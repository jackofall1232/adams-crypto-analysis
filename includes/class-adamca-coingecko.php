<?php
/**
 * CoinGecko API calls and data shaping.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

class ADAMCA_CoinGecko {

    /**
     * Fetch and shape analysis data for a coin.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return array|WP_Error Shaped data array or WP_Error on failure.
     */
    public static function fetch_analysis_data( $coin_id ) {
        $ohlc_result = self::fetch_ohlc_candles( $coin_id );
        if ( is_wp_error( $ohlc_result ) ) {
            return $ohlc_result;
        }

        $chart_result = self::fetch_market_chart( $coin_id );
        if ( is_wp_error( $chart_result ) ) {
            return $chart_result;
        }

        return self::shape_market_data( $ohlc_result, $chart_result );
    }

    /**
     * Fetch 30-day OHLC candles (4H) from CoinGecko.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return array|WP_Error Raw OHLC array or WP_Error.
     */
    public static function fetch_ohlc_candles( $coin_id ) {
        $request_url = 'https://api.coingecko.com/api/v3/coins/'
            . sanitize_key( $coin_id )
            . '/ohlc?vs_currency=usd&days=30';

        $response = wp_remote_get( $request_url, array(
            'timeout' => 30,
            'headers' => self::build_request_headers(),
        ) );

        return self::validate_response( $response, 'OHLC for ' . $coin_id );
    }

    /**
     * Fetch 90-day market chart from CoinGecko.
     *
     * @param string $coin_id CoinGecko coin identifier.
     * @return array|WP_Error Raw market chart array or WP_Error.
     */
    public static function fetch_market_chart( $coin_id ) {
        $request_url = 'https://api.coingecko.com/api/v3/coins/'
            . sanitize_key( $coin_id )
            . '/market_chart?vs_currency=usd&days=90';

        $response = wp_remote_get( $request_url, array(
            'timeout' => 30,
            'headers' => self::build_request_headers(),
        ) );

        return self::validate_response( $response, 'Market chart for ' . $coin_id );
    }

    /**
     * Shape raw API responses into the structure needed for the AI prompt.
     *
     * @param array $ohlc_raw  Raw OHLC data from CoinGecko.
     * @param array $chart_raw Raw market chart data from CoinGecko.
     * @return array|WP_Error Shaped data or WP_Error if prices are missing.
     */
    public static function shape_market_data( $ohlc_raw, $chart_raw ) {
        // Convert OHLC timestamps to ISO 8601.
        $ohlc_formatted = array();
        foreach ( $ohlc_raw as $candle ) {
            if ( ! is_array( $candle ) || count( $candle ) < 5 ) {
                continue;
            }
            $ohlc_formatted[] = array(
                gmdate( 'c', (int) ( $candle[0] / 1000 ) ),
                $candle[1],
                $candle[2],
                $candle[3],
                $candle[4],
            );
        }

        // Extract prices from market chart.
        $prices_array = isset( $chart_raw['prices'] ) ? $chart_raw['prices'] : array();
        if ( empty( $prices_array ) ) {
            error_log( '[ADAMCA CoinGecko] Empty prices array in market chart response' );
            return new WP_Error( 'adamca_no_prices', __( 'No price data returned from CoinGecko.', 'adams-crypto-analysis' ) );
        }

        // Compute weekly samples (~12-13 evenly spaced points).
        $total_prices    = count( $prices_array );
        $sample_interval = max( 1, (int) floor( $total_prices / 13 ) );
        $weekly_samples  = array_values( array_filter( $prices_array, function ( $value, $index ) use ( $sample_interval ) {
            return ( $index % $sample_interval === 0 );
        }, ARRAY_FILTER_USE_BOTH ) );

        $start_price     = $prices_array[0][1];
        $end_price       = $prices_array[ $total_prices - 1 ][1];
        $change_percent  = round( ( ( $end_price - $start_price ) / $start_price ) * 100, 2 );

        $shaped_data = array(
            'short_term' => array(
                'ohlc' => $ohlc_formatted,
            ),
            'long_term'  => array(
                'weekly_closes_90d'    => $weekly_samples,
                'price_90d_start'      => $start_price,
                'price_90d_end'        => $end_price,
                'price_90d_change_pct' => $change_percent,
            ),
        );

        return $shaped_data;
    }

    /**
     * Build request headers, including CoinGecko Pro API key if configured.
     *
     * @return array Headers array.
     */
    private static function build_request_headers() {
        $headers   = array();
        $api_key   = get_option( 'adamca_coingecko_api_key', '' );

        if ( ! empty( $api_key ) ) {
            $headers['x-cg-pro-api-key'] = $api_key;
        }

        return $headers;
    }

    /**
     * Validate an HTTP response from CoinGecko.
     *
     * @param array|WP_Error $response HTTP response or WP_Error.
     * @param string         $context  Description for logging.
     * @return array|WP_Error Decoded JSON body or WP_Error.
     */
    private static function validate_response( $response, $context ) {
        if ( is_wp_error( $response ) ) {
            error_log( '[ADAMCA CoinGecko] Request failed for ' . $context . ': ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_message = 'HTTP ' . $status_code . ' for ' . $context;
            error_log( '[ADAMCA CoinGecko] ' . $error_message );
            return new WP_Error( 'adamca_coingecko_http', $error_message );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $response_body ) ) {
            $error_message = 'Invalid JSON response for ' . $context;
            error_log( '[ADAMCA CoinGecko] ' . $error_message );
            return new WP_Error( 'adamca_coingecko_json', $error_message );
        }

        return $response_body;
    }
}
