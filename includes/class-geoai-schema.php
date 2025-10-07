<?php
/**
 * Schema.org JSON-LD output.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema markup class.
 */
class GeoAI_Schema {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 2 );
    }

    public function output_schema() {
        if ( ! GeoAI_Compat::get_instance()->should_output_meta() ) {
            return;
        }

        $defaults = get_option( 'geoai_schema_defaults', array() );
        $schema   = array();

        if ( is_front_page() && ! empty( $defaults['website'] ) ) {
            $schema[] = $this->get_website_schema();
        }

        if ( is_front_page() && ! empty( $defaults['organization'] ) ) {
            $schema[] = $this->get_organization_schema();
        }

        if ( is_singular( 'post' ) && ! empty( $defaults['article'] ) ) {
            $schema[] = $this->get_article_schema();
        }

        $schema = apply_filters( 'geoai_schema_output', $schema );

        if ( ! empty( $schema ) ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $schema ), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n" . '</script>' . "\n";
        }
    }

    private function get_website_schema() {
        return array(
            '@type'         => 'WebSite',
            '@id'           => home_url( '/#website' ),
            'url'           => home_url( '/' ),
            'name'          => get_bloginfo( 'name' ),
            'description'   => get_bloginfo( 'description' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => array(
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url( '/?s={search_term_string}' ),
                ),
                'query-input' => 'required name=search_term_string',
            ),
        );
    }

    private function get_organization_schema() {
        return array(
            '@type' => 'Organization',
            '@id'   => home_url( '/#organization' ),
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        );
    }

    private function get_article_schema() {
        $post = get_post();
        
        return array(
            '@type'            => 'Article',
            '@id'              => get_permalink() . '#article',
            'headline'         => get_the_title(),
            'description'      => wp_trim_words( get_the_excerpt(), 30 ),
            'datePublished'    => get_the_date( 'c' ),
            'dateModified'     => get_the_modified_date( 'c' ),
            'author'           => array(
                '@type' => 'Person',
                'name'  => get_the_author(),
            ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink(),
            ),
        );
    }
}
