<?php
/**
 * Core orchestrator: shortcode, REST API routes, asset enqueue.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

class ADAMCA_Core {

    /**
     * Track whether the shortcode is present on the current page.
     *
     * @var bool
     */
    private static $shortcode_present = false;

    /**
     * Constructor — wire up all hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_shortcode' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );

        ADAMCA_Admin::register_hooks();
    }

    /**
     * Register the [crypto_analysis] shortcode.
     */
    public function register_shortcode() {
        add_shortcode( 'crypto_analysis', array( $this, 'render_shortcode' ) );
    }

    /**
     * Render the shortcode output.
     *
     * @param array|string $attributes Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $attributes ) {
        self::$shortcode_present = true;

        $parsed_atts = shortcode_atts( array(
            'title'    => __( 'Crypto Analysis', 'adams-crypto-analysis' ),
            'subtitle' => __( 'AI-Powered Technical Analysis', 'adams-crypto-analysis' ),
        ), $attributes, 'crypto_analysis' );

        $title_text    = esc_html( $parsed_atts['title'] );
        $subtitle_text = esc_html( $parsed_atts['subtitle'] );

        // Enqueue assets now that we know shortcode is used.
        $this->enqueue_frontend_assets();

        $banner_url = esc_url( ADAMS_CRYPTO_ANALYSIS_URL . 'assets/images/scbanner.png' );
        $cg_icon_url = esc_url( ADAMS_CRYPTO_ANALYSIS_URL . 'assets/images/CG-icon.png' );

        $output_html = '<div id="adamca-app" class="adamca-container">';

        // Branding banner.
        $output_html .= '<div class="adamca-banner-wrap">';
        $output_html .= '<img src="' . $banner_url . '" alt="' . esc_attr__( 'Crypto Nerd Analysis', 'adams-crypto-analysis' ) . '" class="adamca-banner">';
        $output_html .= '</div>';

        // Legal disclaimer.
        $output_html .= '<div class="adamca-disclaimer">';
        $output_html .= '<p>' . esc_html__( 'For informational purposes only. Not financial advice. Cryptocurrency markets are highly volatile — always do your own research before making any investment decisions. Analysis may take up to 2 minutes depending on server load.', 'adams-crypto-analysis' ) . '</p>';
        $output_html .= '</div>';

        $output_html .= '<div class="adamca-inner">';

        $output_html .= '<div class="adamca-header">';
        $output_html .= '<h1 class="adamca-title">' . $title_text . '</h1>';
        $output_html .= '<p class="adamca-subtitle">' . $subtitle_text . '</p>';
        $output_html .= '<button type="button" class="adamca-dark-toggle" id="adamca-dark-toggle" aria-label="' . esc_attr__( 'Toggle dark mode', 'adams-crypto-analysis' ) . '">&#9789;</button>';
        $output_html .= '</div>';

        $output_html .= '<div class="adamca-mode-tabs">';
        $output_html .= '<button type="button" class="adamca-tab active" data-mode="top100">' . esc_html__( 'Top 100', 'adams-crypto-analysis' ) . '</button>';
        $output_html .= '<button type="button" class="adamca-tab" data-mode="trending">' . esc_html__( 'Trending', 'adams-crypto-analysis' ) . '</button>';
        $output_html .= '<button type="button" class="adamca-tab" data-mode="custom">' . esc_html__( 'Custom', 'adams-crypto-analysis' ) . '</button>';
        $output_html .= '</div>';

        $output_html .= '<div class="adamca-coin-selector">';
        $output_html .= '<select id="adamca-coin-select" class="adamca-select"><option value="">' . esc_html__( 'Select a coin...', 'adams-crypto-analysis' ) . '</option></select>';
        $output_html .= '<input type="text" id="adamca-custom-input" class="adamca-custom-input" placeholder="' . esc_attr__( 'Enter CoinGecko ID (e.g. bitcoin)', 'adams-crypto-analysis' ) . '" style="display:none;">';
        $output_html .= '<button type="button" id="adamca-analyze-btn" class="adamca-analyze-button">' . esc_html__( 'Analyze', 'adams-crypto-analysis' ) . '</button>';
        $output_html .= '</div>';

        $output_html .= '<div id="adamca-chart-area" class="adamca-tradingview-chart" style="display:none;"></div>';

        $output_html .= '<div id="adamca-loading" class="adamca-loading-spinner" style="display:none;">';
        $output_html .= '<div class="adamca-spinner"></div>';
        $output_html .= '<p>' . esc_html__( 'Generating analysis&hellip; this may take up to 2 minutes depending on server load.', 'adams-crypto-analysis' ) . '</p>';
        $output_html .= '</div>';

        $output_html .= '<div id="adamca-cache-info" class="adamca-cache-indicator" style="display:none;">';
        $output_html .= '<span id="adamca-cache-dot" class="adamca-cache-dot"></span>';
        $output_html .= '<span id="adamca-cache-text"></span>';
        $output_html .= '</div>';

        $output_html .= '<div id="adamca-result" class="adamca-result-area"></div>';
        $output_html .= '<div id="adamca-error" class="adamca-error" style="display:none;"></div>';

        // CoinGecko attribution.
        $output_html .= '<div class="adamca-cg-attribution">';
        $output_html .= '<img src="' . $cg_icon_url . '" alt="CoinGecko" class="adamca-cg-icon">';
        $output_html .= '<a href="https://www.coingecko.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Data provided by CoinGecko', 'adams-crypto-analysis' ) . '</a>';
        $output_html .= '</div>';

        $output_html .= '</div>'; // .adamca-inner

        $output_html .= '</div>'; // .adamca-container

        return $output_html;
    }

    /**
     * Conditionally enqueue assets on wp_enqueue_scripts (fallback).
     */
    public function maybe_enqueue_assets() {
        global $post;

        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'crypto_analysis' ) ) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Enqueue frontend CSS and JS with localized data.
     */
    private function enqueue_frontend_assets() {
        static $already_enqueued = false;
        if ( $already_enqueued ) {
            return;
        }
        $already_enqueued = true;

        $plugin_version = ADAMS_CRYPTO_ANALYSIS_VERSION;

        wp_enqueue_style(
            'adamca-frontend',
            ADAMS_CRYPTO_ANALYSIS_URL . 'assets/css/crypto-analysis.css',
            array(),
            $plugin_version
        );

        wp_enqueue_script(
            'adamca-frontend',
            ADAMS_CRYPTO_ANALYSIS_URL . 'assets/js/crypto-analysis.js',
            array(),
            $plugin_version,
            true
        );

        $top10_raw   = get_option( 'adamca_top10_coins', '' );
        $top10_coins = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( "\n", $top10_raw ) ) ) );

        wp_localize_script( 'adamca-frontend', 'ADAMCA', array(
            'apiEndpoint' => esc_url_raw( rest_url( 'adams-crypto/v1/analyze' ) ),
            'top10Coins'  => array_values( $top10_coins ),
            'pluginUrl'   => esc_url_raw( ADAMS_CRYPTO_ANALYSIS_URL ),
        ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        $namespace = 'adams-crypto/v1';

        register_rest_route( $namespace, '/analyze', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_analyze_request' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_status_request' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( $namespace, '/clear', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_clear_request' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    /**
     * Handle POST /analyze request.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function handle_analyze_request( $request ) {
        $coin_id = isset( $request['coin_id'] ) ? sanitize_key( $request['coin_id'] ) : '';

        if ( ! preg_match( '/^[a-z0-9\-]{1,100}$/', $coin_id ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => __( 'Invalid coin ID.', 'adams-crypto-analysis' ),
            ), 400 );
        }

        // Check cache first.
        $cached_html = ADAMCA_Cache::retrieve( $coin_id );
        if ( false !== $cached_html ) {
            $metadata        = ADAMCA_Cache::get_cache_metadata( $coin_id );
            $age_minutes     = $metadata ? (int) floor( ( time() - $metadata['timestamp'] ) / 60 ) : 0;

            return new WP_REST_Response( array(
                'success'           => true,
                'html'              => $cached_html,
                'cached'            => true,
                'cache_age_minutes' => $age_minutes,
                'is_top_ten'        => ADAMCA_Cache::is_top_ten( $coin_id ),
                'coin_id'           => $coin_id,
                'provider'          => $metadata ? $metadata['provider'] : '',
                'model'             => $metadata ? $metadata['model'] : '',
            ), 200 );
        }

        // Fetch from CoinGecko.
        $market_data = ADAMCA_CoinGecko::fetch_analysis_data( $coin_id );
        if ( is_wp_error( $market_data ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => $market_data->get_error_message(),
            ), 500 );
        }

        // Generate AI analysis.
        $html_output = ADAMCA_AI_Client::generate_analysis( $coin_id, $market_data );
        if ( is_wp_error( $html_output ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => $html_output->get_error_message(),
            ), 500 );
        }

        // Store in cache.
        ADAMCA_Cache::store_analysis( $coin_id, $html_output );

        return new WP_REST_Response( array(
            'success'           => true,
            'html'              => $html_output,
            'cached'            => false,
            'cache_age_minutes' => 0,
            'is_top_ten'        => ADAMCA_Cache::is_top_ten( $coin_id ),
            'coin_id'           => $coin_id,
            'provider'          => get_option( 'adamca_ai_provider' ),
            'model'             => get_option( 'adamca_ai_model' ),
        ), 200 );
    }

    /**
     * Handle GET /status request (admin only).
     *
     * @return WP_REST_Response Cache status response.
     */
    public function handle_status_request() {
        return new WP_REST_Response( array(
            'success'      => true,
            'cached_coins' => ADAMCA_Cache::get_all_cached_status(),
        ), 200 );
    }

    /**
     * Handle POST /clear request (admin only).
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response.
     */
    public function handle_clear_request( $request ) {
        $coin_id = isset( $request['coin_id'] ) ? sanitize_key( $request['coin_id'] ) : '';

        if ( empty( $coin_id ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'error'   => __( 'No coin ID provided.', 'adams-crypto-analysis' ),
            ), 400 );
        }

        if ( 'all' === $coin_id ) {
            $clear_count = ADAMCA_Cache::clear_all_cache();
            return new WP_REST_Response( array(
                'success' => true,
                'cleared' => $clear_count,
            ), 200 );
        }

        ADAMCA_Cache::clear_cache( $coin_id );
        return new WP_REST_Response( array(
            'success' => true,
            'cleared' => $coin_id,
        ), 200 );
    }
}
