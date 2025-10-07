<?php
/**
 * 404 Monitor.
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 404 monitoring class.
 */
class GeoAI_404 {
    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'geoai_404_log';

        add_action( 'template_redirect', array( $this, 'log_404' ) );
    }

    public function log_404() {
        if ( ! is_404() ) {
            return;
        }

        $settings = get_option( 'geoai_404_settings', array() );
        
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        global $wpdb;

        $url      = $_SERVER['REQUEST_URI'];
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $ip       = $this->get_client_ip();

        $wpdb->insert(
            $this->table_name,
            array(
                'url'       => $url,
                'referrer'  => $referrer,
                'ip'        => $ip,
                'timestamp' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        $this->cleanup_old_logs();
    }

    private function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field( $ip );
    }

    private function cleanup_old_logs() {
        global $wpdb;

        $settings = get_option( 'geoai_404_settings', array() );
        $retention = $settings['retention'] ?? 30;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention
            )
        );
    }
}
