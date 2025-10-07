<?php
/**
 * XML Sitemaps generator.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sitemaps class.
 */
class GeoAI_Sitemaps {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'serve_sitemap' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?geoai_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([^/]+)\.xml$', 'index.php?geoai_sitemap=$matches[1]', 'top' );
        
        add_rewrite_tag( '%geoai_sitemap%', '([^&]+)' );
    }

    public function serve_sitemap() {
        $sitemap = get_query_var( 'geoai_sitemap' );
        
        if ( ! $sitemap ) {
            return;
        }

        $settings = get_option( 'geoai_sitemaps', array() );
        
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        header( 'Content-Type: application/xml; charset=utf-8' );
        
        if ( 'index' === $sitemap ) {
            echo $this->generate_sitemap_index();
        } else {
            echo $this->generate_sitemap( $sitemap );
        }
        
        exit;
    }

    private function generate_sitemap_index() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $settings = get_option( 'geoai_sitemaps', array() );
        $post_types = $settings['post_types'] ?? array( 'post', 'page' );

        foreach ( $post_types as $post_type ) {
            $xml .= sprintf(
                '<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>' . "\n",
                esc_url( home_url( "/sitemap-{$post_type}.xml" ) ),
                esc_xml( current_time( 'c' ) )
            );
        }

        $xml .= '</sitemapindex>';
        
        return $xml;
    }

    private function generate_sitemap( $type ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        $posts = get_posts(
            array(
                'post_type'      => $type,
                'post_status'    => 'publish',
                'posts_per_page' => 1000,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        foreach ( $posts as $post ) {
            $xml .= '<url>' . "\n";
            $xml .= sprintf( '<loc>%s</loc>' . "\n", esc_url( get_permalink( $post ) ) );
            $xml .= sprintf( '<lastmod>%s</lastmod>' . "\n", esc_xml( get_the_modified_date( 'c', $post ) ) );
            $xml .= '<changefreq>weekly</changefreq>' . "\n";
            $xml .= '<priority>0.8</priority>' . "\n";

            $settings = get_option( 'geoai_sitemaps', array() );
            if ( ! empty( $settings['images'] ) ) {
                $thumbnail = get_the_post_thumbnail_url( $post, 'full' );
                if ( $thumbnail ) {
                    $xml .= sprintf( '<image:image><image:loc>%s</image:loc></image:image>' . "\n", esc_url( $thumbnail ) );
                }
            }

            $xml .= '</url>' . "\n";
        }

        $xml .= '</urlset>';
        
        return $xml;
    }
}
