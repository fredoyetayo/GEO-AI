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
        add_action( 'geoai_background_audit', array( $this, 'handle_background_audit' ) );
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

        // Queue background audit if Action Scheduler is available
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'geoai_background_audit', array( 'post_id' => $post_id ) );
        }
    }

    /**
     * Handle the scheduled background audit.
     *
     * @param mixed $post_id Post identifier passed from the scheduler.
     */
    public function handle_background_audit( $post_id ) {
        if ( is_array( $post_id ) ) {
            if ( isset( $post_id['post_id'] ) ) {
                $post_id = $post_id['post_id'];
            } else {
                $post_id = reset( $post_id );
            }
        }

        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return;
        }

        $this->analyze_post( $post_id );
    }

    public function analyze_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'invalid_post', __( 'Post not found.', 'geo-ai' ) );
        }

        // Check for API key
        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'Google Gemini API key not configured.', 'geo-ai' ) );
        }

        // Get rendered content
        $content = $this->get_rendered_content( $post );

        // Call Gemini API
        $audit_result = $this->call_gemini_api( $content, $api_key );

        if ( is_wp_error( $audit_result ) ) {
            return $audit_result;
        }

        // Save to postmeta
        update_post_meta( $post_id, '_geoai_audit', wp_json_encode( $audit_result ) );
        update_post_meta( $post_id, '_geoai_audit_timestamp', current_time( 'mysql' ) );

        return $audit_result;
    }

    private function get_api_key() {
        $stored = get_option( 'geoai_api_key', '' );
        
        if ( empty( $stored ) ) {
            return '';
        }
        
        // Check if it's plain text storage (fallback when encryption fails)
        if ( 0 === strpos( $stored, 'plain:' ) ) {
            return substr( $stored, 6 ); // Remove 'plain:' prefix
        }
        
        // Try to decrypt
        try {
            return $this->decrypt( $stored );
        } catch ( \Exception $e ) {
            error_log( 'GEO AI: Error decrypting API key - ' . $e->getMessage() );
            return '';
        }
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
        $prompt = $this->build_audit_prompt( $content );

        $response = wp_remote_post(
            $this->api_endpoint . '?key=' . $api_key,
            array(
                'timeout' => 45,
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
                            'temperature'     => 0.3,
                            'topK'            => 40,
                            'topP'            => 0.95,
                            'maxOutputTokens' => 2048,
                        ),
                        'safetySettings' => array(
                            array(
                                'category'  => 'HARM_CATEGORY_HARASSMENT',
                                'threshold' => 'BLOCK_NONE',
                            ),
                            array(
                                'category'  => 'HARM_CATEGORY_HATE_SPEECH',
                                'threshold' => 'BLOCK_NONE',
                            ),
                            array(
                                'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                                'threshold' => 'BLOCK_NONE',
                            ),
                            array(
                                'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                                'threshold' => 'BLOCK_NONE',
                            ),
                        ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'GEO AI: API Error - ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            error_log( 'GEO AI: API HTTP Error ' . $status_code . ' - ' . wp_remote_retrieve_body( $response ) );
            // translators: %d: HTTP status code from remote API response.
            return new \WP_Error( 'api_error', sprintf( __( 'API returned status code %d', 'geo-ai' ), $status_code ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            error_log( 'GEO AI: Invalid API response - ' . wp_remote_retrieve_body( $response ) );
            return new \WP_Error( 'api_error', __( 'Invalid API response structure.', 'geo-ai' ) );
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
            return new \WP_Error( 'parse_error', __( 'Gemini response did not include JSON audit data.', 'geo-ai' ) );
        }

        $json = substr( $response_text, $json_start, $json_end - $json_start + 1 );
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'parse_error', __( 'Gemini response was not valid JSON.', 'geo-ai' ) );
        }

        if ( empty( $data['scores'] ) || ! is_array( $data['scores'] ) ) {
            return new \WP_Error( 'invalid_data', __( 'Gemini response missing score data.', 'geo-ai' ) );
        }

        $data['scores'] = array_map( 'intval', $data['scores'] );
        $data['scores'] = wp_parse_args(
            $data['scores'],
            array(
                'answerability' => 0,
                'structure'     => 0,
                'trust'         => 0,
                'technical'     => 0,
                'total'         => 0,
            )
        );

        $data['issues']      = isset( $data['issues'] ) && is_array( $data['issues'] ) ? $data['issues'] : array();
        $data['schema']      = isset( $data['schema'] ) && is_array( $data['schema'] ) ? $data['schema'] : array();
        $data['suggestions'] = isset( $data['suggestions'] ) && is_array( $data['suggestions'] ) ? $data['suggestions'] : array();

        $data['runAt'] = current_time( 'c' );

        return $data;
    }

    /**
     * Generate AI-powered meta title and description
     *
     * @param int $post_id Post ID
     * @return array|WP_Error Array with 'title' and 'description' or WP_Error
     */
    public function generate_meta_content( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'invalid_post', __( 'Post not found.', 'geo-ai' ) );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'Google Gemini API key not configured.', 'geo-ai' ) );
        }

        // Get content preview
        $content = $this->get_rendered_content( $post );
        $content_preview = substr( $content, 0, 5000 ); // Limit for meta generation

        // Build prompt
        $prompt = $this->build_meta_prompt( $post->post_title, $content_preview );

        // Call API
        $response = $this->call_gemini_meta_api( $prompt, $api_key );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response;
    }

    /**
     * Build prompt for meta content generation
     */
    private function build_meta_prompt( $title, $content ) {
        return sprintf(
            'You are an expert SEO copywriter. Based on the following content, generate an optimized meta title and meta description for maximum search engine and social media performance.

Guidelines:
- Meta Title: 50-60 characters, compelling, includes primary keyword, action-oriented
- Meta Description: 150-160 characters, persuasive, includes call-to-action, enticing click-through

Return ONLY a JSON object with this exact structure (no markdown, no code blocks):
{
  "title": "Your optimized meta title here",
  "description": "Your optimized meta description here"
}

Current Title: %s

Content Preview:
%s

Return the JSON now:',
            $title,
            $content
        );
    }

    /**
     * Call Gemini API for meta generation
     */
    private function call_gemini_meta_api( $prompt, $api_key ) {
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
                            'temperature'     => 0.7,
                            'topK'            => 40,
                            'topP'            => 0.95,
                            'maxOutputTokens' => 512,
                        ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'GEO AI Meta Generation: API Error - ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'GEO AI Meta Generation: HTTP Error ' . $status_code . ' - ' . $body );
            // translators: %d: HTTP status code from remote API response.
            return new \WP_Error( 'api_error', sprintf( __( 'API returned status code %d', 'geo-ai' ), $status_code ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            error_log( 'GEO AI Meta Generation: Invalid response structure' );
            return new \WP_Error( 'api_error', __( 'Invalid API response structure.', 'geo-ai' ) );
        }

        $result_text = $body['candidates'][0]['content']['parts'][0]['text'];
        
        return $this->parse_meta_response( $result_text );
    }

    /**
     * Parse meta generation response
     */
    private function parse_meta_response( $response_text ) {
        // Clean up response - remove markdown code blocks if present
        $response_text = preg_replace( '/```json\s*/i', '', $response_text );
        $response_text = preg_replace( '/```\s*$/', '', $response_text );
        $response_text = trim( $response_text );

        // Extract JSON
        $json_start = strpos( $response_text, '{' );
        $json_end   = strrpos( $response_text, '}' );
        
        if ( false === $json_start || false === $json_end ) {
            error_log( 'GEO AI Meta: No JSON found in response: ' . $response_text );
            return new \WP_Error( 'parse_error', __( 'Could not extract JSON from API response.', 'geo-ai' ) );
        }

        $json = substr( $response_text, $json_start, $json_end - $json_start + 1 );
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'GEO AI Meta: JSON parse error: ' . json_last_error_msg() . ' - JSON: ' . $json );
            return new \WP_Error( 'parse_error', __( 'Could not parse API response as JSON.', 'geo-ai' ) );
        }

        // Validate structure
        if ( empty( $data['title'] ) || empty( $data['description'] ) ) {
            error_log( 'GEO AI Meta: Missing title or description in response' );
            return new \WP_Error( 'invalid_data', __( 'API response missing required fields.', 'geo-ai' ) );
        }

        // Sanitize and trim
        $data['title'] = sanitize_text_field( $data['title'] );
        $data['description'] = sanitize_text_field( $data['description'] );

        // Enforce length limits
        if ( mb_strlen( $data['title'] ) > 70 ) {
            $data['title'] = mb_substr( $data['title'], 0, 67 ) . '...';
        }

        if ( mb_strlen( $data['description'] ) > 165 ) {
            $data['description'] = mb_substr( $data['description'], 0, 162 ) . '...';
        }

        return $data;
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error Success message or error
     */
    public function test_api_connection() {
        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'No API key configured.', 'geo-ai' ) );
        }

        $test_prompt = 'Respond with exactly: {"status":"success","message":"API connection working"}';

        $response = wp_remote_post(
            $this->api_endpoint . '?key=' . $api_key,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'contents' => array(
                            array(
                                'parts' => array(
                                    array( 'text' => $test_prompt ),
                                ),
                            ),
                        ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            // translators: %d: HTTP status code from remote API response.
            return new \WP_Error( 'api_error', sprintf( __( 'API returned status code %d', 'geo-ai' ), $status_code ) );
        }

        return array(
            'status'  => 'success',
            'message' => __( 'API connection successful!', 'geo-ai' ),
        );
    }
}

