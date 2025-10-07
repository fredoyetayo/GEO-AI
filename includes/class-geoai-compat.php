<?php
/**
 * Compatibility with other SEO plugins.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Compatibility handler class.
 */
class GeoAI_Compat {
    private static $instance = null;
    private $detected_plugins = array();

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->detected_plugins = $this->detect_conflicts();
        add_action( 'init', array( $this, 'init_compat_mode' ) );
    }

    public function detect_conflicts() {
        $conflicts = array();

        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
            $conflicts[] = 'Yoast SEO';
        }

        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            $conflicts[] = 'Rank Math';
        }

        if ( defined( 'SEOPRESS_VERSION' ) || class_exists( 'SEOPress' ) ) {
            $conflicts[] = 'SEOPress';
        }

        if ( class_exists( 'All_in_One_SEO_Pack' ) || defined( 'AIOSEO_VERSION' ) ) {
            $conflicts[] = 'All in One SEO';
        }

        return apply_filters( 'geoai_detected_conflicts', $conflicts );
    }

    public function init_compat_mode() {
        $compat_mode = get_option( 'geoai_compat_mode', 'standalone' );

        if ( 'coexist' === $compat_mode && ! empty( $this->detected_plugins ) ) {
            // Lower priority to let other plugins output first, then we suppress ours
            add_action( 'wp_head', array( $this, 'maybe_suppress_outputs' ), 999 );
        }
    }

    public function maybe_suppress_outputs() {
        // Check if other plugins already added meta tags
        if ( $this->has_existing_meta_tags() ) {
            // Remove our meta output hooks
            remove_action( 'wp_head', array( \GeoAI\Core\GeoAI_Meta::get_instance(), 'output_meta_tags' ), 1 );
            remove_action( 'wp_head', array( \GeoAI\Core\GeoAI_Social::get_instance(), 'output_og_tags' ), 1 );
        }
    }

    private function has_existing_meta_tags() {
        // In coexist mode, assume other plugins are handling meta if detected
        return ! empty( $this->detected_plugins );
    }

    public function is_standalone_mode() {
        return 'standalone' === get_option( 'geoai_compat_mode', 'standalone' );
    }

    public function should_output_meta() {
        return $this->is_standalone_mode() || empty( $this->detected_plugins );
    }
}
