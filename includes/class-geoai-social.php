<?php
/**
 * Social media meta tags (OpenGraph, Twitter).
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Social meta tags class.
 */
class GeoAI_Social {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output_og_tags' ), 1 );
    }

    public function output_og_tags() {
        if ( ! GeoAI_Compat::get_instance()->should_output_meta() ) {
            return;
        }

        $defaults = get_option( 'geoai_social_defaults', array() );

        if ( is_singular() ) {
            $post_id   = get_the_ID();
            $overrides = get_post_meta( $post_id, '_geoai_social_overrides', true );
            $overrides = is_array( $overrides ) ? $overrides : array();

            $og_title       = $overrides['og_title'] ?? get_the_title();
            $og_description = $overrides['og_desc'] ?? wp_trim_words( get_the_excerpt(), 30 );
            $og_image       = $overrides['og_image'] ?? ( get_the_post_thumbnail_url( $post_id, 'large' ) ?: $defaults['og_image'] ?? '' );
            $og_url         = get_permalink();

            // OpenGraph
            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
            
            if ( $og_image ) {
                echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            }

            // Twitter
            $tw_card = $defaults['tw_card'] ?? 'summary_large_image';
            echo '<meta name="twitter:card" content="' . esc_attr( $tw_card ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            
            if ( $og_image ) {
                echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            }

            if ( ! empty( $defaults['tw_site'] ) ) {
                echo '<meta name="twitter:site" content="' . esc_attr( $defaults['tw_site'] ) . '" />' . "\n";
            }
        }
    }
}
