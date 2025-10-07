<?php
/**
 * AI-powered content analyzer (PHASE 2 - Stubbed).
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

use GeoAI\Traits\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Analyzer class using Google Gemini API.
 */
class GeoAI_Analyzer {
    use Encryption;

    private static $instance = null;
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'save_post', array( $this, 'maybe_auto_analyze' ), 20, 2 );
    }

    public function maybe_auto_analyze( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        $autorun = get_option( 'geoai_autorun_on_save', false );
        if ( ! $autorun ) {
            return;
        }

        // Queue background audit
        as_enqueue_async_action( 'geoai_background_audit', array( 'post_id' => $post_id ) );
    }

    public function analyze_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'invalid_post', __( 'Post not found.', 'geo-ai' ) );
        }

        // Check for API key
        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return $this->get_mock_audit_data();
        }

        // Get rendered content
        $content = $this->get_rendered_content( $post );

        // Call Gemini API
        $audit_result = $this->call_gemini_api( $content, $api_key );

        if ( is_wp_error( $audit_result ) ) {
            return $this->get_mock_audit_data(); // Fallback to mock data
        }

        // Save to postmeta
        update_post_meta( $post_id, '_geoai_audit', wp_json_encode( $audit_result ) );
        update_post_meta( $post_id, '_geoai_audit_timestamp', current_time( 'mysql' ) );

        return $audit_result;
    }

    private function get_api_key() {
        $encrypted = get_option( 'geoai_api_key', '' );
        return ! empty( $encrypted ) ? $this->decrypt( $encrypted ) : '';
    }

    private function get_rendered_content( $post ) {
        setup_postdata( $post );
        $content = apply_filters( 'the_content', $post->post_content );
        wp_reset_postdata();

        // Strip HTML and limit length
        $text = wp_strip_all_tags( $content );
        $text = substr( $text, 0, 20000 );

        return $text;
    }

    private function call_gemini_api( $content, $api_key ) {
        // PHASE 2: Implement actual Gemini API call
        $prompt = $this->build_audit_prompt( $content );

        $response = wp_remote_post(
            $this->api_endpoint . '?key=' . $api_key,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'contents' => array(
                            array(
                                'parts' => array(
                                    array( 'text' => $prompt ),
                                ),
                            ),
                        ),
                        'generationConfig' => array(
                            'temperature' => 0.2,
                        ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return new \WP_Error( 'api_error', __( 'Invalid API response.', 'geo-ai' ) );
        }

        $result_text = $body['candidates'][0]['content']['parts'][0]['text'];
        
        return $this->parse_gemini_response( $result_text );
    }

    private function build_audit_prompt( $content ) {
        return sprintf(
            'You are an SEO expert analyzing content for AI answer engines (Google AI Overviews, Perplexity, ChatGPT). Audit this content and return a JSON response with the following structure:

{
  "scores": {
    "answerability": 0-100,
    "structure": 0-100,
    "trust": 0-100,
    "technical": 0-100,
    "total": 0-100
  },
  "issues": [
    {"id":"unique_id","severity":"high|med|low","msg":"Description","quickFix":"fix_id or null"}
  ],
  "schema": {"article":true|false,"faq":true|false,"howto":true|false,"errors":[]},
  "suggestions": {
    "titleOptions":["..."],
    "entities":["..."],
    "citations":["https://..."]
  }
}

Content to analyze:
%s',
            $content
        );
    }

    private function parse_gemini_response( $response_text ) {
        // Extract JSON from response
        $json_start = strpos( $response_text, '{' );
        $json_end   = strrpos( $response_text, '}' );
        
        if ( false === $json_start || false === $json_end ) {
            return $this->get_mock_audit_data();
        }

        $json = substr( $response_text, $json_start, $json_end - $json_start + 1 );
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return $this->get_mock_audit_data();
        }

        $data['runAt'] = current_time( 'c' );
        
        return $data;
    }

    private function get_mock_audit_data() {
        return array(
            'scores'      => array(
                'answerability' => 65,
                'structure'     => 75,
                'trust'         => 60,
                'technical'     => 80,
                'total'         => 70,
            ),
            'issues'      => array(
                array(
                    'id'       => 'missing_tldr',
                    'severity' => 'high',
                    'msg'      => __( 'Add a TL;DR summary within 200 words.', 'geo-ai' ),
                    'quickFix' => 'insert_answer_card',
                ),
                array(
                    'id'       => 'no_author',
                    'severity' => 'med',
                    'msg'      => __( 'Add an author byline and last updated date.', 'geo-ai' ),
                    'quickFix' => null,
                ),
            ),
            'schema'      => array(
                'article' => true,
                'faq'     => false,
                'howto'   => false,
                'errors'  => array(),
            ),
            'suggestions' => array(
                'titleOptions' => array(
                    'Consider more descriptive titles',
                    'Add question-based headings',
                ),
                'entities'     => array( 'WordPress', 'SEO', 'AI' ),
                'citations'    => array(),
            ),
            'runAt'       => current_time( 'c' ),
        );
    }
}
