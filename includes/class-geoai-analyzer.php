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
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';

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
            return;
        }

        // If Action Scheduler is not available, fall back to running immediately.
        $result = $this->analyze_post( $post_id );

        if ( is_wp_error( $result ) ) {
            error_log( 'GEO AI: Auto audit failed - ' . $result->get_error_message() );
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

        $result = $this->analyze_post( $post_id );

        if ( is_wp_error( $result ) ) {
            error_log( 'GEO AI: Background audit failed - ' . $result->get_error_message() );
        }
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
        $api_key = get_option( 'geoai_api_key', '' );
        // Handle legacy plain prefix and trim
        if ( 0 === strpos( $api_key, 'plain:' ) ) {
            $api_key = substr( $api_key, 6 );
        }
        return trim( $api_key );
    }

    private function get_rendered_content( $post_obj ) {
        global $post;

        $original_post = $post instanceof \WP_Post ? $post : null;

        $post = $post_obj;
        setup_postdata( $post );
        $content = apply_filters( 'the_content', $post->post_content );
        wp_reset_postdata();

        if ( $original_post instanceof \WP_Post ) {
            $post = $original_post;
        }

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
                        'systemInstruction' => array(
                            'parts' => array(
                                array(
                                    'text' => 'You are GEO AI, a WordPress SEO assistant. Only respond with valid JSON that matches the requested schema.'
                                ),
                            ),
                        ),
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
                            'responseMimeType' => 'application/json',
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

        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        $result_text = '';

        if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $result_text = $body['candidates'][0]['content']['parts'][0]['text'];
        } elseif ( isset( $body['text'] ) ) {
            // Newer Gemini responses can use responseMimeType to return direct JSON.
            $result_text = is_string( $body['text'] ) ? $body['text'] : wp_json_encode( $body['text'] );
        } else {
            error_log( 'GEO AI: Invalid API response - ' . $raw_body );
            return new \WP_Error( 'api_error', __( 'Invalid API response structure.', 'geo-ai' ) );
        }

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
        $response_text = preg_replace( '/```json\s*/i', '', $response_text );
        $response_text = preg_replace( '/```\s*$/', '', $response_text );
        $response_text = trim( $response_text );

        if ( '' === $response_text ) {
            return new \WP_Error( 'parse_error', __( 'Gemini response did not include audit data.', 'geo-ai' ) );
        }

        // Attempt direct decode first for responses that respect responseMimeType.
        $data = json_decode( $response_text, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
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

        $data['issues'] = isset( $data['issues'] ) && is_array( $data['issues'] )
            ? array_values( array_map( array( $this, 'normalize_issue' ), $data['issues'] ) )
            : array();

        $data['schema'] = isset( $data['schema'] ) && is_array( $data['schema'] ) ? $data['schema'] : array();
        $data['schema'] = wp_parse_args(
            $data['schema'],
            array(
                'article' => false,
                'faq'     => false,
                'howto'   => false,
                'errors'  => array(),
            )
        );

        if ( ! is_array( $data['schema']['errors'] ) ) {
            $data['schema']['errors'] = array();
        }

        $data['suggestions'] = isset( $data['suggestions'] ) && is_array( $data['suggestions'] ) ? $data['suggestions'] : array();
        $data['suggestions'] = wp_parse_args(
            $data['suggestions'],
            array(
                'titleOptions' => array(),
                'entities'     => array(),
                'citations'    => array(),
            )
        );

        foreach ( array( 'titleOptions', 'entities', 'citations' ) as $key ) {
            if ( ! is_array( $data['suggestions'][ $key ] ) ) {
                $data['suggestions'][ $key ] = array();
            }
        }

        $data['runAt'] = current_time( 'c' );

        return $data;
    }

    /**
     * Normalize issue data coming from the AI response.
     *
     * @param mixed $issue Raw issue payload.
     * @return array
     */
    private function normalize_issue( $issue ) {
        if ( ! is_array( $issue ) ) {
            $issue = array( 'msg' => (string) $issue );
        }

        $issue = wp_parse_args(
            $issue,
            array(
                'id'       => wp_unique_id( 'issue_' ),
                'severity' => 'med',
                'msg'      => '',
                'quickFix' => null,
            )
        );

        $issue['id']       = sanitize_key( $issue['id'] );
        $issue['severity'] = in_array( $issue['severity'], array( 'high', 'med', 'low' ), true ) ? $issue['severity'] : 'med';
        $issue['msg']      = wp_strip_all_tags( (string) $issue['msg'] );
        $issue['quickFix'] = $issue['quickFix'] ? sanitize_key( $issue['quickFix'] ) : null;

        return $issue;
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
                        'systemInstruction' => array(
                            'parts' => array(
                                array(
                                    'text' => 'You are GEO AI, an SEO assistant. Only respond with valid JSON that matches the requested schema.'
                                ),
                            ),
                        ),
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
                            'responseMimeType' => 'application/json',
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

        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        $result_text = '';

        if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $result_text = $body['candidates'][0]['content']['parts'][0]['text'];
        } elseif ( isset( $body['text'] ) ) {
            $result_text = is_string( $body['text'] ) ? $body['text'] : wp_json_encode( $body['text'] );
        } else {
            error_log( 'GEO AI Meta Generation: Invalid response structure - ' . $raw_body );
            return new \WP_Error( 'api_error', __( 'Invalid API response structure.', 'geo-ai' ) );
        }

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

        if ( '' === $response_text ) {
            return new \WP_Error( 'parse_error', __( 'API response was empty.', 'geo-ai' ) );
        }

        $data = json_decode( $response_text, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            // Extract JSON when additional text slips in.
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
    public function test_api_connection( $override_key = null ) {
        $api_key = $override_key ? trim( (string) $override_key ) : $this->get_api_key();
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
                        'systemInstruction' => array(
                            'parts' => array(
                                array(
                                    'text' => 'You are GEO AI, a WordPress SEO assistant. Only respond with valid JSON.'
                                ),
                            ),
                        ),
                        'contents' => array(
                            array(
                                'parts' => array(
                                    array( 'text' => $test_prompt ),
                                ),
                            ),
                        ),
                        'generationConfig' => array(
                            'responseMimeType' => 'application/json',
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

