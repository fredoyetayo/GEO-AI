<?php
/**
 * Meta tags handler (titles, descriptions, robots).
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta tags class.
 */
class GeoAI_Meta {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
        add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 10 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ) );
    }

    public function filter_title( $title ) {
        if ( ! GeoAI_Compat::get_instance()->should_output_meta() ) {
            return $title;
        }

        $custom_title = $this->get_current_title();
        return $custom_title ? $custom_title : $title;
    }

    public function output_meta_tags() {
        if ( ! GeoAI_Compat::get_instance()->should_output_meta() ) {
            return;
        }

        $description = $this->get_current_description();
        $robots      = $this->get_current_robots();
        $canonical   = $this->get_current_canonical();

        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
        }

        if ( $robots ) {
            echo '<meta name="robots" content="' . esc_attr( $robots ) . '" />' . "\n";
        }

        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
        }
    }

    private function get_current_title() {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $custom  = get_post_meta( $post_id, '_geoai_title', true );
            
            if ( $custom ) {
                return $custom;
            }

            return $this->parse_template( $this->get_template_for( 'post' ) );
        }

        if ( is_home() || is_front_page() ) {
            return $this->parse_template( $this->get_template_for( 'home' ) );
        }

        return '';
    }

    private function get_current_description() {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $custom  = get_post_meta( $post_id, '_geoai_meta_desc', true );
            
            return $custom ? $custom : wp_trim_words( get_the_excerpt(), 30 );
        }

        return '';
    }

    private function get_current_robots() {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            return get_post_meta( $post_id, '_geoai_robots', true );
        }

        return '';
    }

    private function get_current_canonical() {
        if ( is_singular() ) {
            return get_permalink();
        }

        return '';
    }

    private function get_template_for( $type ) {
        $templates = get_option( 'geoai_titles_templates', array() );
        return $templates[ $type ] ?? '%%title%% %%sep%% %%sitename%%';
    }

    private function parse_template( $template ) {
        $replacements = array(
            '%%title%%'    => get_the_title(),
            '%%sitename%%' => get_bloginfo( 'name' ),
            '%%sitedesc%%' => get_bloginfo( 'description' ),
            '%%sep%%'      => '|',
            '%%excerpt%%'  => get_the_excerpt(),
            '%%date%%'     => get_the_date(),
            '%%modified%%' => get_the_modified_date(),
            '%%id%%'       => get_the_ID(),
            '%%author%%'   => get_the_author(),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    public function add_meta_box() {
        $post_types = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'geoai_meta',
                __( 'GEO AI SEO', 'geo-ai' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'geoai_meta_box', 'geoai_meta_box_nonce' );

        $title       = get_post_meta( $post->ID, '_geoai_title', true );
        $description = get_post_meta( $post->ID, '_geoai_meta_desc', true );
        $robots      = get_post_meta( $post->ID, '_geoai_robots', true );
        ?>
        <div class="geoai-meta-box">
            <div style="background: #f0f6fc; padding: 12px; border-radius: 4px; margin-bottom: 15px; border-left: 3px solid #667eea;">
                <p style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-lightbulb" style="color: #667eea;"></span>
                    <strong><?php esc_html_e( 'AI-Powered Generation', 'geo-ai' ); ?></strong>
                </p>
                <p style="margin: 8px 0 0; font-size: 13px; color: #646970;">
                    <?php esc_html_e( 'Click the button below to automatically generate optimized meta title and description using Google Gemini AI.', 'geo-ai' ); ?>
                </p>
                <button type="button" id="geoai-generate-meta-btn" class="button button-primary" style="margin-top: 10px;" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Generate with AI', 'geo-ai' ); ?>
                </button>
                <span id="geoai-generate-meta-status" style="margin-left: 10px; font-style: italic; color: #646970;"></span>
            </div>

            <p>
                <label for="geoai_title"><strong><?php esc_html_e( 'SEO Title', 'geo-ai' ); ?></strong> <span style="color: #646970; font-weight: normal;">(<?php echo mb_strlen( $title ); ?> chars)</span></label><br/>
                <input type="text" id="geoai_title" name="geoai_title" value="<?php echo esc_attr( $title ); ?>" class="large-text" maxlength="70" />
                <p class="description"><?php esc_html_e( 'Optimal length: 50-60 characters', 'geo-ai' ); ?></p>
            </p>
            <p>
                <label for="geoai_meta_desc"><strong><?php esc_html_e( 'Meta Description', 'geo-ai' ); ?></strong> <span style="color: #646970; font-weight: normal;">(<?php echo mb_strlen( $description ); ?> chars)</span></label><br/>
                <textarea id="geoai_meta_desc" name="geoai_meta_desc" rows="3" class="large-text" maxlength="165"><?php echo esc_textarea( $description ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Optimal length: 150-160 characters', 'geo-ai' ); ?></p>
            </p>
            <p>
                <label for="geoai_robots"><strong><?php esc_html_e( 'Robots Meta', 'geo-ai' ); ?></strong></label><br/>
                <select id="geoai_robots" name="geoai_robots">
                    <option value="" <?php selected( $robots, '' ); ?>><?php esc_html_e( 'Default (index, follow)', 'geo-ai' ); ?></option>
                    <option value="noindex,follow" <?php selected( $robots, 'noindex,follow' ); ?>><?php esc_html_e( 'No Index, Follow', 'geo-ai' ); ?></option>
                    <option value="index,nofollow" <?php selected( $robots, 'index,nofollow' ); ?>><?php esc_html_e( 'Index, No Follow', 'geo-ai' ); ?></option>
                    <option value="noindex,nofollow" <?php selected( $robots, 'noindex,nofollow' ); ?>><?php esc_html_e( 'No Index, No Follow', 'geo-ai' ); ?></option>
                </select>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Character counters
            function updateCharCount(inputId, labelSelector) {
                var $input = $('#' + inputId);
                var $label = $input.prev('label');
                $input.on('input', function() {
                    var length = $(this).val().length;
                    $label.find('span').text('(' + length + ' chars)');
                });
            }
            
            updateCharCount('geoai_title', 'label');
            updateCharCount('geoai_meta_desc', 'label');

            // AI Generation
            $('#geoai-generate-meta-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#geoai-generate-meta-status');
                var postId = $btn.data('post-id');

                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php esc_html_e( 'Generating...', 'geo-ai' ); ?>');
                $status.text('<?php esc_html_e( 'This may take 10-15 seconds...', 'geo-ai' ); ?>').css('color', '#2271b1');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'geoai_generate_meta',
                        post_id: postId,
                        nonce: geoaiAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#geoai_title').val(response.data.title).trigger('input');
                            $('#geoai_meta_desc').val(response.data.description).trigger('input');
                            $status.text('<?php esc_html_e( 'Generated successfully!', 'geo-ai' ); ?>').css('color', '#00a32a');
                        } else {
                            $status.text('<?php esc_html_e( 'Error: ', 'geo-ai' ); ?>' + response.data.message).css('color', '#d63638');
                        }
                    },
                    error: function() {
                        $status.text('<?php esc_html_e( 'Request failed. Please try again.', 'geo-ai' ); ?>').css('color', '#d63638');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Generate with AI', 'geo-ai' ); ?>');
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 3000);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['geoai_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['geoai_meta_box_nonce'], 'geoai_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['geoai_title'] ) ) {
            update_post_meta( $post_id, '_geoai_title', sanitize_text_field( wp_unslash( $_POST['geoai_title'] ) ) );
        }

        if ( isset( $_POST['geoai_meta_desc'] ) ) {
            update_post_meta( $post_id, '_geoai_meta_desc', sanitize_textarea_field( wp_unslash( $_POST['geoai_meta_desc'] ) ) );
        }

        if ( isset( $_POST['geoai_robots'] ) ) {
            update_post_meta( $post_id, '_geoai_robots', sanitize_text_field( wp_unslash( $_POST['geoai_robots'] ) ) );
        }
    }
}
