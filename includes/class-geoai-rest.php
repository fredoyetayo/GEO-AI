<?php
/**
 * REST API endpoints.
 *
 * @package GeoAI
 */

namespace GeoAI\REST;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API handler class.
 */
class GeoAI_REST {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'geoai/v1',
            '/audit',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'run_audit' ),
                'permission_callback' => array( $this, 'check_audit_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            'geoai/v1',
            '/audit/(?P<post_id>\\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_audit' ),
                'permission_callback' => array( $this, 'check_audit_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            'geoai/v1',
            '/quick-fix',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'apply_quick_fix' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'fix_id'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    public function check_audit_permission( $request ) {
        $post_id = $request->get_param( 'post_id' );
        return current_user_can( 'edit_post', $post_id );
    }

    public function check_edit_permission( $request ) {
        $post_id = $request->get_param( 'post_id' );
        return current_user_can( 'edit_post', $post_id );
    }

    public function run_audit( $request ) {
        $post_id = $request->get_param( 'post_id' );

        // PHASE 2: This will call the analyzer
        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();
        $result   = $analyzer->analyze_post( $post_id );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                500
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => $result,
            ),
            200
        );
    }

    public function get_audit( $request ) {
        $post_id = $request->get_param( 'post_id' );

        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();
        $result   = $analyzer->get_latest_audit( $post_id );

        if ( is_wp_error( $result ) ) {
            $status = 'not_found' === $result->get_error_code() ? 404 : 500;

            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                $status
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => $result,
            ),
            200
        );
    }

    public function apply_quick_fix( $request ) {
        $post_id = $request->get_param( 'post_id' );
        $fix_id  = $request->get_param( 'fix_id' );

        // PHASE 3: Apply quick fixes like inserting Answer Card
        $result = $this->apply_fix( $post_id, $fix_id );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                500
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Quick fix applied successfully.', 'geo-ai' ),
            ),
            200
        );
    }

    private function apply_fix( $post_id, $fix_id ) {
        switch ( $fix_id ) {
            case 'insert_answer_card':
                return $this->insert_answer_card_block( $post_id );
            case 'add_author':
                // Future: Add author byline
                return true;
            default:
                return new \WP_Error( 'invalid_fix', __( 'Invalid fix ID.', 'geo-ai' ) );
        }
    }

    private function insert_answer_card_block( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'invalid_post', __( 'Post not found.', 'geo-ai' ) );
        }

        // Check if Answer Card already exists
        if ( has_block( 'geoai/answer-card', $post ) ) {
            return new \WP_Error( 'already_exists', __( 'Answer Card already exists in this post.', 'geo-ai' ) );
        }

        // Insert Answer Card block at the beginning
        $answer_card_block = '<!-- wp:geoai/answer-card {"tldr":"","keyFacts":[]} /-->';
        $updated_content   = $answer_card_block . "\n\n" . $post->post_content;

        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $updated_content,
            )
        );

        return true;
    }
}
