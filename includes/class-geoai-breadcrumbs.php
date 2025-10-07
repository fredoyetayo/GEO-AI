<?php
/**
 * Breadcrumbs functionality.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Breadcrumbs class.
 */
class GeoAI_Breadcrumbs {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'geoai_breadcrumbs', array( $this, 'render_breadcrumbs' ) );
    }

    public function render_breadcrumbs( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'separator' => '/',
                'home_text' => __( 'Home', 'geo-ai' ),
            ),
            $atts
        );

        $items = $this->get_breadcrumb_items();
        
        if ( empty( $items ) ) {
            return '';
        }

        $html   = '<nav class="geoai-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'geo-ai' ) . '">';
        $html  .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
        $position = 1;

        foreach ( $items as $item ) {
            $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            
            if ( ! empty( $item['url'] ) ) {
                $html .= sprintf(
                    '<a href="%s" itemprop="item"><span itemprop="name">%s</span></a>',
                    esc_url( $item['url'] ),
                    esc_html( $item['title'] )
                );
            } else {
                $html .= '<span itemprop="name">' . esc_html( $item['title'] ) . '</span>';
            }
            
            $html .= '<meta itemprop="position" content="' . esc_attr( $position ) . '" />';
            $html .= '</li>';

            if ( $position < count( $items ) ) {
                $html .= '<li class="separator">' . esc_html( $atts['separator'] ) . '</li>';
            }

            $position++;
        }

        $html .= '</ol></nav>';
        
        return $html;
    }

    private function get_breadcrumb_items() {
        $items = array();

        $items[] = array(
            'title' => __( 'Home', 'geo-ai' ),
            'url'   => home_url( '/' ),
        );

        if ( is_singular() ) {
            $post = get_post();
            
            if ( $post->post_parent ) {
                $ancestors = array_reverse( get_post_ancestors( $post ) );
                foreach ( $ancestors as $ancestor_id ) {
                    $items[] = array(
                        'title' => get_the_title( $ancestor_id ),
                        'url'   => get_permalink( $ancestor_id ),
                    );
                }
            }

            $items[] = array(
                'title' => get_the_title(),
                'url'   => '',
            );
        }

        return apply_filters( 'geoai_breadcrumb_items', $items );
    }
}
