<?php
/**
 * Redirects manager.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Redirects class.
 */
class GeoAI_Redirects {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
    }

    public function maybe_redirect() {
        if ( is_admin() ) {
            return;
        }

        $redirects    = get_option( 'geoai_redirects', array() );
        $request_path = $_SERVER['REQUEST_URI'];

        foreach ( $redirects as $redirect ) {
            if ( empty( $redirect['from'] ) || empty( $redirect['to'] ) ) {
                continue;
            }

            $from = $redirect['from'];
            $to   = $redirect['to'];
            $type = $redirect['type'] ?? 301;

            if ( $this->matches( $request_path, $from ) ) {
                wp_redirect( $to, $type );
                exit;
            }
        }
    }

    private function matches( $request, $pattern ) {
        $pattern = str_replace( '*', '(.*)', $pattern );
        $pattern = '#^' . $pattern . '$#';
        
        return (bool) preg_match( $pattern, $request );
    }
}
