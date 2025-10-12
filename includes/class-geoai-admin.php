<?php
/**
 * Admin interface controller.
 *
 * @package GeoAI
 */

namespace GeoAI\Admin;

use GeoAI\Traits\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main admin class.
 */
class GeoAI_Admin {
    use Encryption;

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_seo_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_seo_meta' ), 10, 2 );
        
        // AJAX actions for AI features
        add_action( 'wp_ajax_geoai_generate_meta', array( $this, 'ajax_generate_meta' ) );
        add_action( 'wp_ajax_geoai_test_api', array( $this, 'ajax_test_api' ) );
    }

    public function add_admin_menu() {
        // Use custom SVG icon
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="white">
                <circle cx="50" cy="50" r="45" fill="none" stroke="white" stroke-width="3"/>
                <ellipse cx="50" cy="50" rx="45" ry="15" fill="none" stroke="white" stroke-width="1.5" opacity="0.6"/>
                <ellipse cx="50" cy="50" rx="45" ry="30" fill="none" stroke="white" stroke-width="1.5" opacity="0.6"/>
                <ellipse cx="50" cy="50" rx="15" ry="45" fill="none" stroke="white" stroke-width="1.5" opacity="0.6"/>
                <ellipse cx="50" cy="50" rx="30" ry="45" fill="none" stroke="white" stroke-width="1.5" opacity="0.6"/>
                <circle cx="50" cy="25" r="4" fill="white"/>
                <circle cx="70" cy="40" r="4" fill="white"/>
                <circle cx="70" cy="60" r="4" fill="white"/>
                <circle cx="50" cy="75" r="4" fill="white"/>
                <circle cx="30" cy="60" r="4" fill="white"/>
                <circle cx="30" cy="40" r="4" fill="white"/>
                <line x1="50" y1="25" x2="70" y2="40" stroke="white" stroke-width="2" opacity="0.5"/>
                <line x1="70" y1="40" x2="70" y2="60" stroke="white" stroke-width="2" opacity="0.5"/>
                <line x1="70" y1="60" x2="50" y2="75" stroke="white" stroke-width="2" opacity="0.5"/>
                <line x1="50" y1="75" x2="30" y2="60" stroke="white" stroke-width="2" opacity="0.5"/>
                <line x1="30" y1="60" x2="30" y2="40" stroke="white" stroke-width="2" opacity="0.5"/>
                <line x1="30" y1="40" x2="50" y2="25" stroke="white" stroke-width="2" opacity="0.5"/>
                <circle cx="50" cy="50" r="8" fill="white"/>
            </svg>'
        );

        add_menu_page(
            __( 'GEO AI Settings', 'geo-ai' ),
            __( 'GEO AI', 'geo-ai' ),
            'manage_options',
            'geoai-settings',
            array( $this, 'render_settings_page' ),
            $icon_svg,
            80
        );

        // Add SEO Dashboard submenu
        add_submenu_page(
            'geoai-settings',
            __( 'SEO Dashboard', 'geo-ai' ),
            __( 'SEO Dashboard', 'geo-ai' ),
            'manage_options',
            'geoai-dashboard',
            array( $this, 'render_dashboard_page' )
        );
    }

    public function register_settings() {
        // Register API key with sanitization callback
        register_setting(
            'geoai_settings',
            'geoai_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
            )
        );
        // Register remaining settings with explicit sanitization callbacks
        register_setting( 'geoai_settings', 'geoai_autorun_on_save', array(
            'type' => 'boolean',
            'sanitize_callback' => array( $this, 'sanitize_bool' ),
            'default' => 0,
        ) );

        register_setting( 'geoai_settings', 'geoai_compat_mode', array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_compat_mode' ),
            'default' => 'standalone',
        ) );

        register_setting( 'geoai_settings', 'geoai_titles_templates', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_titles_templates' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_meta_templates', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_meta_templates' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_social_defaults', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_social_defaults' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_schema_defaults', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_schema_defaults' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_sitemaps', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_sitemaps' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_crawler_prefs', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_crawler_prefs' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_redirects', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_redirects' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_404_settings', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_404_settings' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_roles_caps', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_roles_caps' ),
            'default' => array(),
        ) );

        register_setting( 'geoai_settings', 'geoai_debug_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => array( $this, 'sanitize_bool' ),
            'default' => 0,
        ) );
    }

    /**
     * Sanitize and encrypt API key.
     *
     * @param string $value The API key value.
     * @return string
     */
    public function sanitize_api_key( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $value = sanitize_text_field( $value );

        // Check if the value is already encrypted (comparing with stored value)
        $stored_key = get_option( 'geoai_api_key', '' );
        if ( ! empty( $stored_key ) ) {
            try {
                $decrypted_stored = $this->decrypt( $stored_key );
            } catch ( \Exception $e ) {
                error_log( 'GEO AI: Error decrypting stored API key during sanitization - ' . $e->getMessage() );
                add_settings_error(
                    'geoai_api_key',
                    'decryption_error',
                    __( 'Existing API key could not be decrypted. Please re-enter your key.', 'geo-ai' )
                );
                $decrypted_stored = '';
            }

            if ( $value === $decrypted_stored && ! empty( $stored_key ) ) {
                // Value hasn't changed, return existing encrypted value
                return $stored_key;
            }
        }

        // Encrypt the new value
        try {
            return $this->encrypt( $value );
        } catch ( \Exception $e ) {
            // Log error and return empty string to prevent crash
            error_log( 'GEO AI: Error encrypting API key - ' . $e->getMessage() );
            add_settings_error(
                'geoai_api_key',
                'encryption_error',
                __( 'Error encrypting API key. Please check server configuration.', 'geo-ai' )
            );
            return '';
        }
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_geoai-settings' !== $hook && 'geo-ai_page_geoai-dashboard' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'geoai-admin',
            GEOAI_PLUGIN_URL . 'assets/admin.css',
            array(),
            GEOAI_VERSION
        );

        // Enqueue Chart.js for dashboard
        if ( 'geo-ai_page_geoai-dashboard' === $hook ) {
            wp_enqueue_script(
                'chartjs',
                GEOAI_PLUGIN_URL . 'assets/js/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }

        wp_enqueue_script(
            'geoai-admin',
            GEOAI_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery' ),
            GEOAI_VERSION,
            true
        );

        wp_localize_script(
            'geoai-admin',
            'geoaiAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'geoai-admin' ),
            )
        );
    }

    public function enqueue_editor_assets() {
        $asset_path  = 'build/editor.asset.php';
        $script_path = 'build/editor.js';

        if ( ! file_exists( GEOAI_PLUGIN_DIR . $asset_path ) ) {
            // Support builds produced by default wp-scripts naming (index.js).
            $fallback_asset = 'build/index.asset.php';
            $fallback_js    = 'build/index.js';

            if ( file_exists( GEOAI_PLUGIN_DIR . $fallback_asset ) ) {
                $asset_path  = $fallback_asset;
                $script_path = $fallback_js;
            }
        }

        if ( file_exists( GEOAI_PLUGIN_DIR . $asset_path ) ) {
            $asset = include GEOAI_PLUGIN_DIR . $asset_path;

            wp_enqueue_script(
                'geoai-editor',
                GEOAI_PLUGIN_URL . $script_path,
                $asset['dependencies'],
                $asset['version'],
                true
            );
        } else {
            // Fallback if build file doesn't exist
            wp_enqueue_script(
                'geoai-editor',
                GEOAI_PLUGIN_URL . $script_path,
                array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post', 'wp-api-fetch' ),
                GEOAI_VERSION,
                true
            );
        }

        wp_enqueue_style(
            'geoai-editor',
            GEOAI_PLUGIN_URL . 'assets/editor.css',
            array( 'wp-edit-blocks' ),
            GEOAI_VERSION
        );

        wp_localize_script(
            'geoai-editor',
            'geoaiEditor',
            array(
                'apiUrl'  => rest_url( 'geoai/v1' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'geo-ai' ) );
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap geoai-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php $this->render_compat_notice(); ?>

            <h2 class="nav-tab-wrapper">
                <?php $this->render_tabs( $active_tab ); ?>
            </h2>

            <form method="post" action="options.php" class="geoai-settings-form">
                <?php
                settings_fields( 'geoai_settings' );
                $this->render_tab_content( $active_tab );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function render_tabs( $active_tab ) {
        $tabs = array(
            'general'   => __( 'General', 'geo-ai' ),
            'titles'    => __( 'Titles & Meta', 'geo-ai' ),
            'social'    => __( 'Social', 'geo-ai' ),
            'schema'    => __( 'Schema', 'geo-ai' ),
            'sitemaps'  => __( 'Sitemaps', 'geo-ai' ),
            'crawlers'  => __( 'Crawlers & Robots', 'geo-ai' ),
            'redirects' => __( 'Redirects & 404', 'geo-ai' ),
            'bulk'      => __( 'Bulk Editor', 'geo-ai' ),
            'tools'     => __( 'Tools', 'geo-ai' ),
            'advanced'  => __( 'Advanced', 'geo-ai' ),
        );

        foreach ( $tabs as $tab => $label ) {
            $active = $active_tab === $tab ? 'nav-tab-active' : '';
            $url    = add_query_arg( array( 'page' => 'geoai-settings', 'tab' => $tab ), admin_url( 'admin.php' ) );
            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_url( $url ),
                esc_attr( $active ),
                esc_html( $label )
            );
        }
    }

    private function render_compat_notice() {
        $compat = \GeoAI\Core\GeoAI_Compat::get_instance();
        $conflicts = $compat->detect_conflicts();

        if ( ! empty( $conflicts ) ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e( 'SEO Plugin Detected:', 'geo-ai' ); ?></strong>
                    <?php
                    printf(
                        /* translators: %s: List of detected plugins */
                        esc_html__( 'We detected the following SEO plugins: %s. Enable "Coexist Mode" in General settings to prevent conflicts.', 'geo-ai' ),
                        esc_html( implode( ', ', $conflicts ) )
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    private function render_tab_content( $tab ) {
        switch ( $tab ) {
            case 'general':
                $this->render_general_tab();
                break;
            case 'titles':
                $this->render_titles_tab();
                break;
            case 'social':
                $this->render_social_tab();
                break;
            case 'schema':
                $this->render_schema_tab();
                break;
            case 'sitemaps':
                $this->render_sitemaps_tab();
                break;
            case 'crawlers':
                $this->render_crawlers_tab();
                break;
            case 'redirects':
                $this->render_redirects_tab();
                break;
            case 'bulk':
                $this->render_bulk_tab();
                break;
            case 'tools':
                $this->render_tools_tab();
                break;
            case 'advanced':
                $this->render_advanced_tab();
                break;
            default:
                $this->render_general_tab();
        }
    }

    private function render_general_tab() {
        $api_key          = get_option( 'geoai_api_key', '' );
        $decrypted_key    = '';
        
        // Safely decrypt the API key
        if ( ! empty( $api_key ) ) {
            try {
                $decrypted_key = $this->decrypt( $api_key );
            } catch ( \Exception $e ) {
                error_log( 'GEO AI: Error decrypting API key - ' . $e->getMessage() );
                add_settings_error(
                    'geoai_api_key',
                    'decryption_error_display',
                    __( 'Stored API key could not be decrypted. Please re-enter your key.', 'geo-ai' ),
                    'error'
                );
                $decrypted_key = '';
            }
        }

        $autorun          = get_option( 'geoai_autorun_on_save', false );
        $compat_mode      = get_option( 'geoai_compat_mode', 'standalone' );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="geoai_api_key"><?php esc_html_e( 'Google Gemini API Key', 'geo-ai' ); ?></label>
                </th>
                <td>
                    <input type="text" id="geoai_api_key" name="geoai_api_key" value="<?php echo esc_attr( $decrypted_key ); ?>" class="regular-text" />
                    <button type="button" id="geoai-test-api-btn" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Test Connection', 'geo-ai' ); ?>
                    </button>
                    <span id="geoai-test-api-status" style="margin-left: 10px; font-style: italic;"></span>
                    <p class="description">
                        <?php esc_html_e( 'Your API key is encrypted when stored. Get one from ', 'geo-ai' ); ?>
                        <a href="https://ai.google.dev/gemini-api/docs/api-key" target="_blank"><?php esc_html_e( 'Google AI Studio', 'geo-ai' ); ?></a>.
                    </p>
                </td>
            </tr>
            <script>
            jQuery(document).ready(function($) {
                $('#geoai-test-api-btn').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#geoai-test-api-status');

                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php esc_html_e( 'Testing...', 'geo-ai' ); ?>');
                    $status.text('').show();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'geoai_test_api',
                            nonce: geoaiAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                            } else {
                                $status.text('✗ Error: ' + response.data.message).css('color', '#d63638');
                            }
                        },
                        error: function() {
                            $status.text('✗ Request failed').css('color', '#d63638');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Test Connection', 'geo-ai' ); ?>');
                        }
                    });
                });
            });
            </script>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-run Audit', 'geo-ai' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="geoai_autorun_on_save" value="1" <?php checked( $autorun, true ); ?> />
                        <?php esc_html_e( 'Run AI audit automatically when publishing or updating posts', 'geo-ai' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="geoai_compat_mode"><?php esc_html_e( 'Compatibility Mode', 'geo-ai' ); ?></label>
                </th>
                <td>
                    <select id="geoai_compat_mode" name="geoai_compat_mode">
                        <option value="standalone" <?php selected( $compat_mode, 'standalone' ); ?>><?php esc_html_e( 'Standalone (Full Control)', 'geo-ai' ); ?></option>
                        <option value="coexist" <?php selected( $compat_mode, 'coexist' ); ?>><?php esc_html_e( 'Coexist (Suppress Overlapping Outputs)', 'geo-ai' ); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Choose "Coexist" if using another SEO plugin to avoid duplicate meta tags.', 'geo-ai' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_titles_tab() {
        $templates = get_option( 'geoai_titles_templates', array() );
        $meta_templates = get_option( 'geoai_meta_templates', array() );
        ?>
        <h2><?php esc_html_e( 'Title & Meta Description Templates', 'geo-ai' ); ?></h2>
        
        <div class="geoai-info-box geoai-info-primary">
            <span class="dashicons dashicons-format-aside"></span>
            <div>
                <strong><?php esc_html_e( 'Search Engine Optimization', 'geo-ai' ); ?></strong>
                <p><?php esc_html_e( 'Templates define how your titles and descriptions appear in search results. Well-optimized titles and descriptions can improve your click-through rate by 20-30%.', 'geo-ai' ); ?></p>
            </div>
        </div>

        <div class="geoai-variables-box">
            <h3 class="geoai-toggle-heading">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
                <?php esc_html_e( 'Available Variables (Click to expand)', 'geo-ai' ); ?>
            </h3>
            <div class="geoai-variables-content" style="display: none;">
                <div class="geoai-variables-grid">
                    <div class="geoai-variable-item">
                        <code>%%title%%</code>
                        <span><?php esc_html_e( 'Post/page title', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%sitename%%</code>
                        <span><?php esc_html_e( 'Site name', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%sep%%</code>
                        <span><?php esc_html_e( 'Separator (|)', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%excerpt%%</code>
                        <span><?php esc_html_e( 'Post excerpt', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%category%%</code>
                        <span><?php esc_html_e( 'Primary category', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%tag%%</code>
                        <span><?php esc_html_e( 'First tag', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%date%%</code>
                        <span><?php esc_html_e( 'Publish date', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%author%%</code>
                        <span><?php esc_html_e( 'Author name', 'geo-ai' ); ?></span>
                    </div>
                    <div class="geoai-variable-item">
                        <code>%%sitedesc%%</code>
                        <span><?php esc_html_e( 'Site description', 'geo-ai' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <h3><?php esc_html_e( 'Title Templates', 'geo-ai' ); ?></h3>
        <table class="form-table geoai-template-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Post Title', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Optimal length: 50-60 characters. Include your main keyword near the beginning.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <input type="text" name="geoai_titles_templates[post]" value="<?php echo esc_attr( $templates['post'] ?? '%%title%% %%sep%% %%sitename%%' ); ?>" class="large-text geoai-title-input" data-type="title" maxlength="70" />
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 60 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Page Title', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Pages often target broader topics. Keep titles descriptive and clear.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <input type="text" name="geoai_titles_templates[page]" value="<?php echo esc_attr( $templates['page'] ?? '%%title%% %%sep%% %%sitename%%' ); ?>" class="large-text geoai-title-input" data-type="title" maxlength="70" />
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 60 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Archive Title', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Used for category, tag, and author archives. Make it clear what content visitors will find.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <input type="text" name="geoai_titles_templates[archive]" value="<?php echo esc_attr( $templates['archive'] ?? '%%archive_title%% %%sep%% %%sitename%%' ); ?>" class="large-text geoai-title-input" data-type="title" maxlength="70" />
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 60 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Homepage Title', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Your most important page! Make it compelling and include your main keywords.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <input type="text" name="geoai_titles_templates[home]" value="<?php echo esc_attr( $templates['home'] ?? '%%sitename%% %%sep%% %%sitedesc%%' ); ?>" class="large-text geoai-title-input" data-type="title" maxlength="70" />
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 60 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e( 'Meta Description Templates', 'geo-ai' ); ?></h3>
        <table class="form-table geoai-template-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Post Description', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Optimal length: 150-160 characters. Include a call-to-action and main keywords naturally.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <textarea name="geoai_meta_templates[post]" class="large-text geoai-desc-input" rows="3" maxlength="180"><?php echo esc_textarea( $meta_templates['post'] ?? '%%excerpt%%' ); ?></textarea>
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 160 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Page Description', 'geo-ai' ); ?></label>
                </th>
                <td>
                    <textarea name="geoai_meta_templates[page]" class="large-text geoai-desc-input" rows="3" maxlength="180"><?php echo esc_textarea( $meta_templates['page'] ?? '%%excerpt%%' ); ?></textarea>
                    <div class="geoai-char-counter">
                        <span class="geoai-char-count">0</span> / 160 <?php esc_html_e( 'characters', 'geo-ai' ); ?>
                        <span class="geoai-status-indicator"></span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="geoai-info-box geoai-info-success">
            <span class="dashicons dashicons-lightbulb"></span>
            <div>
                <strong><?php esc_html_e( 'SEO Best Practices', 'geo-ai' ); ?></strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li><?php esc_html_e( 'Keep titles between 50-60 characters to avoid truncation in search results', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Place important keywords at the beginning of titles for better ranking', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Meta descriptions should be 150-160 characters - compelling and actionable', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Include your brand name (%%sitename%%) for brand recognition', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Write unique descriptions - avoid duplicates across pages', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Use action words: "Learn", "Discover", "Find out", "Get started"', 'geo-ai' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function render_social_tab() {
        $defaults      = get_option( 'geoai_social_defaults', array() );
        $og_image_id   = $defaults['og_image_id'] ?? 0;
        $og_image_url  = $defaults['og_image'] ?? '';
        $image_preview = '';

        if ( $og_image_id ) {
            $image_preview = wp_get_attachment_image( $og_image_id, 'medium', false, array( 'id' => 'geoai-og-preview' ) );
        } elseif ( $og_image_url ) {
            $image_preview = '<img id="geoai-og-preview" src="' . esc_url( $og_image_url ) . '" style="max-width: 400px; height: auto;" />';
        }
        ?>
        <h2><?php esc_html_e( 'OpenGraph & Twitter Cards', 'geo-ai' ); ?></h2>
        
        <div class="geoai-info-box geoai-info-primary">
            <span class="dashicons dashicons-info"></span>
            <div>
                <strong><?php esc_html_e( 'Social Media Optimization', 'geo-ai' ); ?></strong>
                <p><?php esc_html_e( 'Control how your content appears when shared on Facebook, Twitter, LinkedIn, and other platforms. Good social previews increase click-through rates by up to 40%.', 'geo-ai' ); ?></p>
            </div>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Default OG Image', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'This image appears when your content is shared on social media. Recommended: 1200x630px, under 8MB, JPG or PNG format.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <div class="geoai-image-upload-wrap">
                        <input type="hidden" id="geoai_og_image_id" name="geoai_social_defaults[og_image_id]" value="<?php echo esc_attr( $og_image_id ); ?>" />
                        <input type="hidden" id="geoai_og_image_url" name="geoai_social_defaults[og_image]" value="<?php echo esc_url( $og_image_url ); ?>" />
                        
                        <div class="geoai-image-preview-container">
                            <?php if ( $image_preview ) : ?>
                                <div id="geoai-og-preview-wrap">
                                    <?php echo wp_kses_post( $image_preview ); ?>
                                    <button type="button" class="button geoai-remove-image"><?php esc_html_e( 'Remove', 'geo-ai' ); ?></button>
                                </div>
                            <?php else : ?>
                                <div id="geoai-og-preview-wrap" style="display: none;">
                                    <img id="geoai-og-preview" src="" style="max-width: 400px; height: auto;" />
                                    <button type="button" class="button geoai-remove-image"><?php esc_html_e( 'Remove', 'geo-ai' ); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <p>
                            <button type="button" class="button button-secondary" id="geoai-upload-og-image">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <?php esc_html_e( 'Select Image from Library', 'geo-ai' ); ?>
                            </button>
                        </p>

                        <div id="geoai-image-insights" style="display: none;" class="geoai-info-box geoai-info-secondary">
                            <strong><?php esc_html_e( 'Image Insights', 'geo-ai' ); ?></strong>
                            <ul id="geoai-image-insights-list"></ul>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Twitter Card Type', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Summary Large Image shows bigger previews and gets more engagement. Use Summary only if you prefer smaller images.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <select name="geoai_social_defaults[tw_card]">
                        <option value="summary" <?php selected( $defaults['tw_card'] ?? 'summary_large_image', 'summary' ); ?>><?php esc_html_e( 'Summary', 'geo-ai' ); ?></option>
                        <option value="summary_large_image" <?php selected( $defaults['tw_card'] ?? 'summary_large_image', 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image (Recommended)', 'geo-ai' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Twitter Site Handle', 'geo-ai' ); ?></label>
                    <span class="geoai-tooltip" data-tip="<?php esc_attr_e( 'Your Twitter/X username. This appears in Twitter Cards and helps people find your account.', 'geo-ai' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </th>
                <td>
                    <input type="text" name="geoai_social_defaults[tw_site]" value="<?php echo esc_attr( $defaults['tw_site'] ?? '' ); ?>" placeholder="@yoursite" class="regular-text" />
                </td>
            </tr>
        </table>

        <div class="geoai-info-box geoai-info-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div>
                <strong><?php esc_html_e( 'Best Practices', 'geo-ai' ); ?></strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li><?php esc_html_e( 'OpenGraph Image: 1200x630px (1.91:1 ratio) for best results', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'File size: Under 8MB, preferably under 1MB for fast loading', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Format: JPG or PNG (avoid WebP for compatibility)', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Include text overlay with your brand or key message', 'geo-ai' ); ?></li>
                    <li><?php esc_html_e( 'Test your cards using Facebook Sharing Debugger and Twitter Card Validator', 'geo-ai' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function render_schema_tab() {
        $defaults = get_option( 'geoai_schema_defaults', array() );
        ?>
        <h2><?php esc_html_e( 'Schema.org Defaults', 'geo-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Types', 'geo-ai' ); ?></th>
                <td>
                    <label><input type="checkbox" name="geoai_schema_defaults[article]" value="1" <?php checked( $defaults['article'] ?? true, true ); ?> /> Article</label><br/>
                    <label><input type="checkbox" name="geoai_schema_defaults[faq]" value="1" <?php checked( $defaults['faq'] ?? false, true ); ?> /> FAQPage</label><br/>
                    <label><input type="checkbox" name="geoai_schema_defaults[howto]" value="1" <?php checked( $defaults['howto'] ?? false, true ); ?> /> HowTo</label><br/>
                    <label><input type="checkbox" name="geoai_schema_defaults[organization]" value="1" <?php checked( $defaults['organization'] ?? true, true ); ?> /> Organization</label><br/>
                    <label><input type="checkbox" name="geoai_schema_defaults[website]" value="1" <?php checked( $defaults['website'] ?? true, true ); ?> /> WebSite + SearchAction</label>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_sitemaps_tab() {
        $settings = get_option( 'geoai_sitemaps', array() );
        ?>
        <h2><?php esc_html_e( 'XML Sitemaps', 'geo-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Sitemaps', 'geo-ai' ); ?></th>
                <td><label><input type="checkbox" name="geoai_sitemaps[enabled]" value="1" <?php checked( $settings['enabled'] ?? true, true ); ?> /> <?php esc_html_e( 'Generate XML sitemaps', 'geo-ai' ); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Include Images', 'geo-ai' ); ?></th>
                <td><label><input type="checkbox" name="geoai_sitemaps[images]" value="1" <?php checked( $settings['images'] ?? true, true ); ?> /> <?php esc_html_e( 'Add image URLs to sitemaps', 'geo-ai' ); ?></label></td>
            </tr>
        </table>
        <?php // translators: %s: The sitemap URL wrapped in <code> tags. ?>
        <p><?php printf( esc_html__( 'Sitemap URL: %s', 'geo-ai' ), '<code>' . esc_url( home_url( '/sitemap.xml' ) ) . '</code>' ); ?></p>
        <?php
    }

    private function render_crawlers_tab() {
        $prefs = get_option( 'geoai_crawler_prefs', array() );
        ?>
        <h2><?php esc_html_e( 'AI Crawler Controls', 'geo-ai' ); ?></h2>
        <p class="description"><?php esc_html_e( 'These settings generate suggested robots.txt rules. Blocking is not guaranteed as crawlers may not respect these directives.', 'geo-ai' ); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Block Crawlers', 'geo-ai' ); ?></th>
                <td>
                    <label><input type="checkbox" name="geoai_crawler_prefs[block_perplexity]" value="1" <?php checked( $prefs['block_perplexity'] ?? false, true ); ?> /> PerplexityBot</label><br/>
                    <label><input type="checkbox" name="geoai_crawler_prefs[block_gptbot]" value="1" <?php checked( $prefs['block_gptbot'] ?? false, true ); ?> /> GPTBot (ChatGPT)</label><br/>
                    <label><input type="checkbox" name="geoai_crawler_prefs[block_ccbot]" value="1" <?php checked( $prefs['block_ccbot'] ?? false, true ); ?> /> CCBot (Common Crawl)</label><br/>
                    <label><input type="checkbox" name="geoai_crawler_prefs[block_anthropic]" value="1" <?php checked( $prefs['block_anthropic'] ?? false, true ); ?> /> anthropic-ai</label>
                </td>
            </tr>
        </table>
        <?php $this->render_robots_preview( $prefs ); ?>
        <?php
    }

    private function render_robots_preview( $prefs ) {
        $rules = array();
        if ( ! empty( $prefs['block_perplexity'] ) ) {
            $rules[] = "User-agent: PerplexityBot\nDisallow: /";
        }
        if ( ! empty( $prefs['block_gptbot'] ) ) {
            $rules[] = "User-agent: GPTBot\nDisallow: /";
        }
        if ( ! empty( $prefs['block_ccbot'] ) ) {
            $rules[] = "User-agent: CCBot\nDisallow: /";
        }
        if ( ! empty( $prefs['block_anthropic'] ) ) {
            $rules[] = "User-agent: anthropic-ai\nDisallow: /";
        }

        if ( ! empty( $rules ) ) {
            ?>
            <h3><?php esc_html_e( 'Suggested robots.txt Rules', 'geo-ai' ); ?></h3>
            <textarea readonly rows="10" class="large-text code"><?php echo esc_textarea( implode( "\n\n", $rules ) ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Copy these rules to your robots.txt file. GEO AI does not write server files.', 'geo-ai' ); ?></p>
            <?php
        }
    }

    private function render_redirects_tab() {
        $redirects = get_option( 'geoai_redirects', array() );
        $settings_404 = get_option( 'geoai_404_settings', array() );
        ?>
        <h2><?php esc_html_e( '301/302 Redirects', 'geo-ai' ); ?></h2>
        <table class="widefat geoai-redirects-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'From URL', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'To URL', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'geo-ai' ); ?></th>
                </tr>
            </thead>
            <tbody id="geoai-redirects-tbody">
                <?php
                if ( ! empty( $redirects ) ) {
                    foreach ( $redirects as $index => $redirect ) {
                        ?>
                        <tr>
                            <td><input type="text" name="geoai_redirects[<?php echo esc_attr( $index ); ?>][from]" value="<?php echo esc_attr( $redirect['from'] ?? '' ); ?>" class="regular-text" placeholder="/old-page" /></td>
                            <td><input type="text" name="geoai_redirects[<?php echo esc_attr( $index ); ?>][to]" value="<?php echo esc_attr( $redirect['to'] ?? '' ); ?>" class="regular-text" placeholder="/new-page" /></td>
                            <td>
                                <select name="geoai_redirects[<?php echo esc_attr( $index ); ?>][type]">
                                    <option value="301" <?php selected( $redirect['type'] ?? 301, 301 ); ?>>301 Permanent</option>
                                    <option value="302" <?php selected( $redirect['type'] ?? 301, 302 ); ?>>302 Temporary</option>
                                </select>
                            </td>
                            <td><button type="button" class="button geoai-remove-redirect"><?php esc_html_e( 'Remove', 'geo-ai' ); ?></button></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <p><button type="button" id="geoai-add-redirect" class="button"><?php esc_html_e( 'Add Redirect', 'geo-ai' ); ?></button></p>
        <p class="description"><?php esc_html_e( 'Use * as wildcard. Example: /blog/* redirects all /blog/ pages.', 'geo-ai' ); ?></p>

        <hr />

        <h2><?php esc_html_e( '404 Monitor', 'geo-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable 404 Logging', 'geo-ai' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="geoai_404_settings[enabled]" value="1" <?php checked( $settings_404['enabled'] ?? false, true ); ?> />
                        <?php esc_html_e( 'Log 404 errors', 'geo-ai' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="geoai_404_retention"><?php esc_html_e( 'Retention Period (days)', 'geo-ai' ); ?></label></th>
                <td>
                    <input type="number" id="geoai_404_retention" name="geoai_404_settings[retention]" value="<?php echo esc_attr( $settings_404['retention'] ?? 30 ); ?>" min="1" max="365" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Automatically delete logs older than this many days.', 'geo-ai' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="geoai_404_max"><?php esc_html_e( 'Max Log Entries', 'geo-ai' ); ?></label></th>
                <td>
                    <input type="number" id="geoai_404_max" name="geoai_404_settings[max_logs]" value="<?php echo esc_attr( $settings_404['max_logs'] ?? 1000 ); ?>" min="100" max="10000" class="small-text" />
                </td>
            </tr>
        </table>

        <?php $this->render_404_log_viewer(); ?>
        <?php
    }

    private function render_404_log_viewer() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geoai_404_log';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
            return;
        }

        $logs = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 50",
            ARRAY_A
        );

        if ( empty( $logs ) ) {
            return;
        }
        ?>
        <h3><?php esc_html_e( 'Recent 404 Errors', 'geo-ai' ); ?></h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'URL', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'Referrer', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'IP', 'geo-ai' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'geo-ai' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $log['url'] ); ?></code></td>
                    <td><?php echo esc_html( $log['referrer'] ? $log['referrer'] : '-' ); ?></td>
                    <td><?php echo esc_html( $log['ip'] ); ?></td>
                    <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_bulk_tab() {
        $post_type = isset( $_GET['post_type_filter'] ) ? sanitize_key( $_GET['post_type_filter'] ) : 'post';
        $paged     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page  = 20;

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $query = new \WP_Query( $args );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <h2><?php esc_html_e( 'Bulk SEO Editor', 'geo-ai' ); ?></h2>
        
        <div class="geoai-bulk-filters">
            <label for="post-type-filter"><?php esc_html_e( 'Post Type:', 'geo-ai' ); ?></label>
            <select id="post-type-filter" onchange="location.href='?page=geoai-settings&tab=bulk&post_type_filter=' + this.value;">
                <?php foreach ( $post_types as $pt ) : ?>
                    <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $post_type, $pt->name ); ?>><?php echo esc_html( $pt->label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <form id="geoai-bulk-editor-form" method="post" action="">
            <?php wp_nonce_field( 'geoai_bulk_save', 'geoai_bulk_nonce' ); ?>
            
            <table class="widefat geoai-bulk-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e( 'Post', 'geo-ai' ); ?></th>
                        <th style="width: 35%;"><?php esc_html_e( 'SEO Title', 'geo-ai' ); ?></th>
                        <th style="width: 35%;"><?php esc_html_e( 'Meta Description', 'geo-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( $query->have_posts() ) {
                        while ( $query->have_posts() ) {
                            $query->the_post();
                            $post_id     = get_the_ID();
                            $seo_title   = get_post_meta( $post_id, '_geoai_title', true );
                            $seo_desc    = get_post_meta( $post_id, '_geoai_meta_desc', true );
                            ?>
                            <tr>
                                <td>
                                    <strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title() ); ?></a></strong>
                                    <br/>
                                    <small><?php echo esc_html( get_the_date() ); ?></small>
                                </td>
                                <td>
                                    <input type="text" name="geoai_bulk[<?php echo esc_attr( $post_id ); ?>][title]" value="<?php echo esc_attr( $seo_title ); ?>" class="large-text" placeholder="<?php echo esc_attr( get_the_title() ); ?>" />
                                </td>
                                <td>
                                    <textarea name="geoai_bulk[<?php echo esc_attr( $post_id ); ?>][desc]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Enter meta description...', 'geo-ai' ); ?>"><?php echo esc_textarea( $seo_desc ); ?></textarea>
                                </td>
                            </tr>
                            <?php
                        }
                        wp_reset_postdata();
                    } else {
                        ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e( 'No posts found.', 'geo-ai' ); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <?php
            if ( $query->max_num_pages > 1 ) {
                $page_links = paginate_links(
                    array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $query->max_num_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    )
                );
                if ( $page_links ) {
                    echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $page_links ) . '</div></div>';
                }
            }
            ?>

            <p class="submit">
                <button type="submit" name="geoai_bulk_save" class="button button-primary"><?php esc_html_e( 'Save All Changes', 'geo-ai' ); ?></button>
            </p>
        </form>
        <?php

        // Handle bulk save
        if ( isset( $_POST['geoai_bulk_save'] ) && check_admin_referer( 'geoai_bulk_save', 'geoai_bulk_nonce' ) ) {
            $this->handle_bulk_save();
        }
    }

    private function handle_bulk_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( empty( $_POST['geoai_bulk'] ) || ! is_array( $_POST['geoai_bulk'] ) ) {
            return;
        }

        $updated = 0;
        foreach ( $_POST['geoai_bulk'] as $post_id => $data ) {
            $post_id = absint( $post_id );
            
            if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
                continue;
            }

            if ( isset( $data['title'] ) ) {
                update_post_meta( $post_id, '_geoai_title', sanitize_text_field( $data['title'] ) );
            }

            if ( isset( $data['desc'] ) ) {
                update_post_meta( $post_id, '_geoai_meta_desc', sanitize_textarea_field( $data['desc'] ) );
            }

            $updated++;
        }

        if ( $updated > 0 ) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                /* translators: %d: Number of posts updated */
                esc_html( _n( '%d post updated.', '%d posts updated.', absint( $updated ), 'geo-ai' ) ),
                absint( $updated )
            );
            echo '</p></div>';
        }
    }

    private function render_tools_tab() {
        ?>
        <h2><?php esc_html_e( 'Import/Export & Tools', 'geo-ai' ); ?></h2>
        
        <h3><?php esc_html_e( 'Plugin Settings', 'geo-ai' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Export Settings', 'geo-ai' ); ?></th>
                <td><button type="button" class="button" id="geoai-export-settings"><?php esc_html_e( 'Download Settings JSON', 'geo-ai' ); ?></button></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Import Settings', 'geo-ai' ); ?></th>
                <td><input type="file" id="geoai-import-settings" accept=".json" /></td>
            </tr>
        </table>

        <hr />

        <h3><?php esc_html_e( 'SEO Meta Data (CSV)', 'geo-ai' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Export Meta Data', 'geo-ai' ); ?></th>
                <td>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'geoai_export_csv', 'geoai_export_csv_nonce' ); ?>
                        <select name="geoai_export_post_type" class="regular-text">
                            <?php
                            $post_types = get_post_types( array( 'public' => true ), 'objects' );
                            foreach ( $post_types as $pt ) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr( $pt->name ),
                                    esc_html( $pt->label )
                                );
                            }
                            ?>
                        </select>
                        <button type="submit" name="geoai_export_csv" class="button"><?php esc_html_e( 'Download CSV', 'geo-ai' ); ?></button>
                    </form>
                    <p class="description"><?php esc_html_e( 'Export titles and meta descriptions for all published posts of the selected type.', 'geo-ai' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Import Meta Data', 'geo-ai' ); ?></th>
                <td>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'geoai_import_csv', 'geoai_import_csv_nonce' ); ?>
                        <input type="file" name="geoai_import_csv_file" accept=".csv" required />
                        <button type="submit" name="geoai_import_csv" class="button"><?php esc_html_e( 'Upload CSV', 'geo-ai' ); ?></button>
                    </form>
                    <p class="description"><?php esc_html_e( 'Import CSV with columns: post_id, seo_title, meta_description', 'geo-ai' ); ?></p>
                </td>
            </tr>
        </table>

        <hr />

        <h3><?php esc_html_e( 'Cache & Data', 'geo-ai' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Clear Audit Cache', 'geo-ai' ); ?></th>
                <td>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'geoai_clear_cache', 'geoai_clear_cache_nonce' ); ?>
                        <button type="submit" name="geoai_clear_cache" class="button"><?php esc_html_e( 'Clear All Audits', 'geo-ai' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Remove all cached audit data from posts.', 'geo-ai' ); ?></p>
                    </form>
                </td>
            </tr>
        </table>
        <?php

        // Handle CSV export
        if ( isset( $_POST['geoai_export_csv'] ) && check_admin_referer( 'geoai_export_csv', 'geoai_export_csv_nonce' ) ) {
            $this->handle_csv_export();
        }

        // Handle CSV import
        if ( isset( $_POST['geoai_import_csv'] ) && check_admin_referer( 'geoai_import_csv', 'geoai_import_csv_nonce' ) ) {
            $this->handle_csv_import();
        }

        // Handle cache clear
        if ( isset( $_POST['geoai_clear_cache'] ) && check_admin_referer( 'geoai_clear_cache', 'geoai_clear_cache_nonce' ) ) {
            $this->handle_clear_cache();
        }
    }

    private function handle_csv_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $post_type = isset( $_POST['geoai_export_post_type'] ) ? sanitize_key( $_POST['geoai_export_post_type'] ) : 'post';

        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $filename = 'geoai-meta-' . $post_type . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        fputcsv( $output, array( 'post_id', 'post_title', 'seo_title', 'meta_description', 'post_url' ) );

        foreach ( $posts as $post ) {
            $seo_title = get_post_meta( $post->ID, '_geoai_title', true );
            $seo_desc  = get_post_meta( $post->ID, '_geoai_meta_desc', true );

            fputcsv(
                $output,
                array(
                    $post->ID,
                    $post->post_title,
                    $seo_title,
                    $seo_desc,
                    get_permalink( $post->ID ),
                )
            );
        }

        fclose( $output );
        exit;
    }

    private function handle_csv_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'geo-ai' ) );
        }

        if ( empty( $_FILES['geoai_import_csv_file']['tmp_name'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded.', 'geo-ai' ) . '</p></div>';
            return;
        }

        $file = fopen( $_FILES['geoai_import_csv_file']['tmp_name'], 'r' );
        
        if ( false === $file ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to open CSV file.', 'geo-ai' ) . '</p></div>';
            return;
        }

        $headers = fgetcsv( $file );
        $updated = 0;
        $skipped = 0;

        while ( ( $row = fgetcsv( $file ) ) !== false ) {
            if ( count( $row ) < 3 ) {
                continue;
            }

            $post_id   = absint( $row[0] );
            $seo_title = isset( $row[2] ) ? sanitize_text_field( $row[2] ) : '';
            $seo_desc  = isset( $row[3] ) ? sanitize_textarea_field( $row[3] ) : '';

            if ( ! $post_id || ! get_post( $post_id ) ) {
                $skipped++;
                continue;
            }

            if ( $seo_title ) {
                update_post_meta( $post_id, '_geoai_title', $seo_title );
            }

            if ( $seo_desc ) {
                update_post_meta( $post_id, '_geoai_meta_desc', $seo_desc );
            }

            $updated++;
        }

        fclose( $file );

        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            /* translators: 1: Number of posts updated, 2: Number of posts skipped */
            esc_html__( 'CSV imported. %1$d posts updated, %2$d skipped.', 'geo-ai' ),
            absint( $updated ),
            absint( $skipped )
        );
        echo '</p></div>';
    }

    private function handle_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->postmeta,
            array( 'meta_key' => '_geoai_audit' ),
            array( '%s' )
        );

        $wpdb->delete(
            $wpdb->postmeta,
            array( 'meta_key' => '_geoai_audit_timestamp' ),
            array( '%s' )
        );

        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            /* translators: %d: Number of audit caches cleared */
            esc_html__( '%d audit caches cleared.', 'geo-ai' ),
            absint( $deleted )
        );
        echo '</p></div>';
    }

    /**
     * Sanitizers for settings API
     */
    public function sanitize_bool( $value ) {
        return ! empty( $value ) ? 1 : 0;
    }

    public function sanitize_compat_mode( $value ) {
        $value = sanitize_text_field( (string) $value );
        return in_array( $value, array( 'standalone', 'coexist' ), true ) ? $value : 'standalone';
    }

    public function sanitize_titles_templates( $input ) {
        $out = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $k => $v ) {
                $out[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
            }
        }
        return $out;
    }

    public function sanitize_meta_templates( $input ) {
        $out = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $k => $v ) {
                $out[ sanitize_key( $k ) ] = sanitize_textarea_field( (string) $v );
            }
        }
        return $out;
    }

    public function sanitize_social_defaults( $input ) {
        $out = array();
        if ( is_array( $input ) ) {
            $out['og_image_id'] = isset( $input['og_image_id'] ) ? absint( $input['og_image_id'] ) : 0;
            $out['og_image']    = isset( $input['og_image'] ) ? esc_url_raw( $input['og_image'] ) : '';
            $out['tw_card']     = isset( $input['tw_card'] ) ? sanitize_text_field( $input['tw_card'] ) : 'summary_large_image';
            $out['tw_site']     = isset( $input['tw_site'] ) ? sanitize_text_field( $input['tw_site'] ) : '';
            $out['tw_creator']  = isset( $input['tw_creator'] ) ? sanitize_text_field( $input['tw_creator'] ) : '';
        }
        return $out;
    }

    public function sanitize_schema_defaults( $input ) {
        $keys = array( 'article','faq','howto','product','localbusiness','organization','website' );
        $out = array();
        foreach ( $keys as $k ) {
            $out[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
        }
        return $out;
    }

    public function sanitize_sitemaps( $input ) {
        $out = array();
        $out['enabled']    = ! empty( $input['enabled'] ) ? 1 : 0;
        $out['images']     = ! empty( $input['images'] ) ? 1 : 0;
        $out['post_types'] = array();
        if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            foreach ( $input['post_types'] as $pt ) {
                $out['post_types'][] = sanitize_key( $pt );
            }
        }
        $out['taxonomies'] = array();
        if ( isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
            foreach ( $input['taxonomies'] as $tx ) {
                $out['taxonomies'][] = sanitize_key( $tx );
            }
        }
        $out['ping_google'] = ! empty( $input['ping_google'] ) ? 1 : 0;
        $out['ping_bing']   = ! empty( $input['ping_bing'] ) ? 1 : 0;
        return $out;
    }

    public function sanitize_crawler_prefs( $input ) {
        $keys = array( 'block_perplexity','block_gptbot','block_ccbot','block_anthropic' );
        $out = array();
        foreach ( $keys as $k ) {
            $out[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
        }
        return $out;
    }

    public function sanitize_redirects( $input ) {
        $out = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $i => $row ) {
                $i = sanitize_key( $i );
                $out[ $i ] = array(
                    'from' => isset( $row['from'] ) ? esc_url_raw( $row['from'] ) : '',
                    'to'   => isset( $row['to'] ) ? esc_url_raw( $row['to'] ) : '',
                    'type' => isset( $row['type'] ) ? ( in_array( (int) $row['type'], array(301,302), true ) ? (int) $row['type'] : 301 ) : 301,
                );
            }
        }
        return $out;
    }

    public function sanitize_404_settings( $input ) {
        $out = array();
        $out['enabled']   = ! empty( $input['enabled'] ) ? 1 : 0;
        $out['retention'] = isset( $input['retention'] ) ? absint( $input['retention'] ) : 30;
        $out['max_logs']  = isset( $input['max_logs'] ) ? absint( $input['max_logs'] ) : 1000;
        return $out;
    }

    public function sanitize_roles_caps( $input ) {
        $out = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $role => $caps ) {
                $role = sanitize_key( $role );
                $out[ $role ] = array();
                if ( is_array( $caps ) ) {
                    foreach ( $caps as $cap ) {
                        $out[ $role ][] = sanitize_key( $cap );
                    }
                }
            }
        }
        return $out;
    }

    private function render_advanced_tab() {
        $debug = get_option( 'geoai_debug_mode', false );
        ?>
        <h2><?php esc_html_e( 'Advanced Settings', 'geo-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Debug Mode', 'geo-ai' ); ?></th>
                <td><label><input type="checkbox" name="geoai_debug_mode" value="1" <?php checked( $debug, true ); ?> /> <?php esc_html_e( 'Enable debug logging', 'geo-ai' ); ?></label></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render SEO Dashboard page.
     */
    public function render_dashboard_page() {
        $dashboard = new \GeoAI\Analyzers\SEO_Dashboard();
        $data = $dashboard->get_dashboard_data();
        
        // Prepare chart data
        $score_dist = $data['score_distribution'];
        ?>
        <div class="wrap geoai-dashboard-v2">
            <div class="geoai-dashboard-hero">
                <div class="hero-content">
                    <h1>
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e( 'SEO Analytics Dashboard', 'geo-ai' ); ?>
                    </h1>
                    <p class="hero-subtitle"><?php esc_html_e( 'Monitor your site\'s SEO performance at a glance', 'geo-ai' ); ?></p>
                </div>
                <div class="hero-score">
                    <div class="score-circle-large <?php echo esc_attr( $this->get_score_class( $data['overall_score'] ) ); ?>">
                        <div class="score-inner">
                            <span class="score-value"><?php echo esc_html( $data['overall_score'] ); ?></span>
                            <span class="score-max">/100</span>
                        </div>
                        <span class="score-label"><?php esc_html_e( 'SEO Health', 'geo-ai' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="geoai-quick-stats">
                <div class="stat-box">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <span class="dashicons dashicons-admin-post"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html( $data['post_stats']['total_posts'] ); ?></div>
                        <div class="stat-label"><?php esc_html_e( 'Total Posts', 'geo-ai' ); ?></div>
                    </div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <span class="dashicons dashicons-editor-alignleft"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html( $data['post_stats']['meta_percentage'] ); ?>%</div>
                        <div class="stat-label"><?php esc_html_e( 'With Meta Descriptions', 'geo-ai' ); ?></div>
                        <div class="stat-progress">
                            <div class="progress-bar" style="width: <?php echo esc_attr( $data['post_stats']['meta_percentage'] ); ?>%;"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html( $data['post_stats']['keyword_percentage'] ); ?>%</div>
                        <div class="stat-label"><?php esc_html_e( 'With Focus Keywords', 'geo-ai' ); ?></div>
                        <div class="stat-progress">
                            <div class="progress-bar" style="width: <?php echo esc_attr( $data['post_stats']['keyword_percentage'] ); ?>%;"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count( $data['issues'] ); ?></div>
                        <div class="stat-label"><?php esc_html_e( 'Issues Found', 'geo-ai' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="geoai-charts-row">
                <div class="chart-card">
                    <h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Score Distribution', 'geo-ai' ); ?></h3>
                    <canvas id="geoai-score-chart" height="300"></canvas>
                </div>

                <div class="chart-card">
                    <h3><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Content Quality Overview', 'geo-ai' ); ?></h3>
                    <canvas id="geoai-quality-chart" height="300"></canvas>
                </div>
            </div>

            <!-- Content Lists Row -->
            <div class="geoai-content-row">
                <!-- Top Performers -->
                <div class="content-card">
                    <h3><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Top Performers', 'geo-ai' ); ?></h3>
                    <?php if ( ! empty( $data['top_performers'] ) ) : ?>
                    <ul class="content-list">
                        <?php foreach ( $data['top_performers'] as $post ) : ?>
                        <li>
                            <a href="<?php echo esc_url( get_edit_post_link( $post['ID'] ) ); ?>">
                                <?php echo esc_html( wp_trim_words( $post['post_title'], 8 ) ); ?>
                            </a>
                            <div class="post-scores">
                                <?php if ( ! empty( $post['keyword_score'] ) ) : ?>
                                <span class="score-badge score-<?php echo esc_attr( $this->get_score_class( $post['keyword_score'] ) ); ?>">
                                    K: <?php echo esc_html( $post['keyword_score'] ); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ( ! empty( $post['readability_score'] ) ) : ?>
                                <span class="score-badge score-<?php echo esc_attr( $this->get_score_class( $post['readability_score'] ) ); ?>">
                                    R: <?php echo esc_html( $post['readability_score'] ); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <p class="no-data"><?php esc_html_e( 'No posts with high scores yet. Start optimizing!', 'geo-ai' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Needs Attention -->
                <div class="content-card attention">
                    <h3><span class="dashicons dashicons-flag"></span> <?php esc_html_e( 'Needs Attention', 'geo-ai' ); ?></h3>
                    <?php if ( ! empty( $data['needs_attention'] ) ) : ?>
                    <ul class="content-list">
                        <?php foreach ( $data['needs_attention'] as $post ) : ?>
                        <li>
                            <a href="<?php echo esc_url( get_edit_post_link( $post['ID'] ) ); ?>">
                                <?php echo esc_html( wp_trim_words( $post['post_title'], 8 ) ); ?>
                            </a>
                            <div class="post-scores">
                                <?php if ( isset( $post['keyword_score'] ) ) : ?>
                                <span class="score-badge score-<?php echo esc_attr( $this->get_score_class( $post['keyword_score'] ) ); ?>">
                                    K: <?php echo esc_html( $post['keyword_score'] ); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ( isset( $post['readability_score'] ) ) : ?>
                                <span class="score-badge score-<?php echo esc_attr( $this->get_score_class( $post['readability_score'] ) ); ?>">
                                    R: <?php echo esc_html( $post['readability_score'] ); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <p class="no-data"><?php esc_html_e( 'Great! No posts need immediate attention.', 'geo-ai' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="content-card">
                    <h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Recent Activity', 'geo-ai' ); ?></h3>
                    <?php if ( ! empty( $data['recent_activity'] ) ) : ?>
                    <ul class="content-list">
                        <?php foreach ( $data['recent_activity'] as $post ) : ?>
                        <li>
                            <a href="<?php echo esc_url( get_edit_post_link( $post['ID'] ) ); ?>">
                                <?php echo esc_html( wp_trim_words( $post['post_title'], 8 ) ); ?>
                            </a>
                            <div class="post-meta">
                                <span class="post-date"><?php echo esc_html( human_time_diff( strtotime( $post['post_modified'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'geo-ai' ); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <p class="no-data"><?php esc_html_e( 'No recent activity.', 'geo-ai' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Issues Section -->
            <?php if ( ! empty( $data['issues'] ) ) : ?>
            <div class="geoai-issues-modern">
                <h2><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Issues Requiring Action', 'geo-ai' ); ?></h2>
                <div class="issues-grid">
                    <?php foreach ( $data['issues'] as $issue ) : ?>
                    <div class="issue-modern issue-<?php echo esc_attr( $issue['severity'] ); ?>">
                        <div class="issue-header">
                            <span class="issue-severity"><?php echo esc_html( ucfirst( $issue['severity'] ) ); ?></span>
                            <span class="issue-count"><?php echo esc_html( $issue['count'] ); ?></span>
                        </div>
                        <p class="issue-message"><?php echo esc_html( $issue['message'] ); ?></p>
                        <?php if ( ! empty( $issue['action'] ) ) : ?>
                        <a href="<?php echo esc_url( $issue['action'] ); ?>" class="issue-action">
                            <?php esc_html_e( 'Fix Now', 'geo-ai' ); ?> →
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Optimize Chart.js for performance
            Chart.defaults.animation = false; // Disable animations to reduce CPU/memory
            Chart.defaults.responsive = true;
            Chart.defaults.maintainAspectRatio = false;

            // Score Distribution Chart
            const scoreCtx = document.getElementById('geoai-score-chart');
            if (scoreCtx) {
                new Chart(scoreCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Excellent', 'Good', 'Fair', 'Poor'],
                        datasets: [{
                            label: 'Keyword',
                            data: [
                                <?php echo (int) $score_dist['excellent']['keyword']; ?>,
                                <?php echo (int) $score_dist['good']['keyword']; ?>,
                                <?php echo (int) $score_dist['fair']['keyword']; ?>,
                                <?php echo (int) $score_dist['poor']['keyword']; ?>
                            ],
                            backgroundColor: '#3699e7'
                        }, {
                            label: 'Readability',
                            data: [
                                <?php echo (int) $score_dist['excellent']['readability']; ?>,
                                <?php echo (int) $score_dist['good']['readability']; ?>,
                                <?php echo (int) $score_dist['fair']['readability']; ?>,
                                <?php echo (int) $score_dist['poor']['readability']; ?>
                            ],
                            backgroundColor: '#ff6384'
                        }]
                    },
                    options: {
                        animation: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                enabled: true,
                                mode: 'index'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            // Quality Overview Doughnut Chart
            const qualityCtx = document.getElementById('geoai-quality-chart');
            if (qualityCtx) {
                const totalPosts = <?php echo (int) $data['post_stats']['total_posts']; ?>;
                const withMeta = <?php echo (int) $data['post_stats']['with_meta']; ?>;
                const withKeyword = <?php echo (int) $data['post_stats']['with_keyword']; ?>;
                
                new Chart(qualityCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Both', 'Meta Only', 'Keyword Only', 'Neither'],
                        datasets: [{
                            data: [
                                Math.min(withMeta, withKeyword),
                                Math.max(0, withMeta - withKeyword),
                                Math.max(0, withKeyword - withMeta),
                                totalPosts - withMeta - withKeyword + Math.min(withMeta, withKeyword)
                            ],
                            backgroundColor: ['#4bc0c0', '#3699e7', '#ffce56', '#ff6384']
                        }]
                    },
                    options: {
                        animation: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Add SEO meta boxes to post editor.
     */
    public function add_seo_meta_boxes() {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        
        foreach ( $post_types as $post_type ) {
            // Single unified meta box
            add_meta_box(
                'geoai_seo_analysis',
                __( 'GEO AI SEO Analysis', 'geo-ai' ),
                array( $this, 'render_unified_seo_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render unified SEO meta box with tabs.
     */
    public function render_unified_seo_meta_box( $post ) {
        wp_nonce_field( 'geoai_seo_meta', 'geoai_seo_nonce' );
        wp_nonce_field( 'geoai_meta_box', 'geoai_meta_box_nonce' );
        
        // Get all data
        $focus_keyword = get_post_meta( $post->ID, '_geoai_focus_keyword', true );
        $keyword_score = get_post_meta( $post->ID, '_geoai_keyword_score', true );
        $keyword_data = get_post_meta( $post->ID, '_geoai_keyword_data', true );
        $readability_score = get_post_meta( $post->ID, '_geoai_readability_score', true );
        $readability_data = get_post_meta( $post->ID, '_geoai_readability_data', true );
        
        // Meta data
        $meta_title = get_post_meta( $post->ID, '_geoai_title', true );
        $meta_description = get_post_meta( $post->ID, '_geoai_meta_desc', true );
        $robots = get_post_meta( $post->ID, '_geoai_robots', true );
        
        if ( $keyword_data ) {
            $keyword_data = json_decode( $keyword_data, true );
        }
        if ( $readability_data ) {
            $readability_data = json_decode( $readability_data, true );
        }

        // Get insights and linking data
        $analyzer_insights = new \GeoAI\Analyzers\Content_Insights();
        $insights = $analyzer_insights->analyze( $post->post_content, $post->post_title );
        
        $analyzer_linking = new \GeoAI\Analyzers\Internal_Linking();
        $suggestions = $analyzer_linking->get_suggestions( $post->ID, $post->post_content );
        $link_stats = $analyzer_linking->get_link_stats( $post->post_content );
        
        // Calculate overall score
        $overall_score = 0;
        $score_count = 0;
        if ( $keyword_score ) {
            $overall_score += (int) $keyword_score;
            $score_count++;
        }
        if ( $readability_score ) {
            $overall_score += (int) $readability_score;
            $score_count++;
        }
        $overall_score = $score_count > 0 ? round( $overall_score / $score_count ) : 0;
        ?>
        
        <div class="geoai-unified-seo">
            <!-- Header with Overall Score -->
            <div class="geoai-seo-header">
                <div class="geoai-score-circle <?php echo esc_attr( $this->get_score_class( $overall_score ) ); ?>">
                    <div class="score-value"><?php echo esc_html( (int) $overall_score ); ?></div>
                    <div class="score-label"><?php esc_html_e( 'SEO Score', 'geo-ai' ); ?></div>
                </div>
                <div class="geoai-score-breakdown">
                    <div class="score-item">
                        <span class="score-badge <?php echo esc_attr( $this->get_score_class( $keyword_score ) ); ?>">
                            <?php echo esc_html( $keyword_score ? (int) $keyword_score : '-' ); ?>
                        </span>
                        <span class="score-name"><?php esc_html_e( 'Keyword', 'geo-ai' ); ?></span>
                    </div>
                    <div class="score-item">
                        <span class="score-badge <?php echo esc_attr( $this->get_score_class( $readability_score ) ); ?>">
                            <?php echo esc_html( $readability_score ? (int) $readability_score : '-' ); ?>
                        </span>
                        <span class="score-name"><?php esc_html_e( 'Readability', 'geo-ai' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="geoai-tab-nav">
                <button type="button" class="geoai-tab-btn active" data-tab="meta">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'SEO Meta', 'geo-ai' ); ?>
                </button>
                <button type="button" class="geoai-tab-btn" data-tab="keyword">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Keyword', 'geo-ai' ); ?>
                </button>
                <button type="button" class="geoai-tab-btn" data-tab="readability">
                    <span class="dashicons dashicons-book"></span>
                    <?php esc_html_e( 'Readability', 'geo-ai' ); ?>
                </button>
                <button type="button" class="geoai-tab-btn" data-tab="insights">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Insights', 'geo-ai' ); ?>
                </button>
                <button type="button" class="geoai-tab-btn" data-tab="linking">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Links', 'geo-ai' ); ?>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="geoai-tab-content">
                <!-- SEO Meta Tab -->
                <div class="geoai-tab-pane active" id="geoai-tab-meta">
                    <?php $this->render_meta_tab_content( $post, $meta_title, $meta_description, $robots ); ?>
                </div>

                <!-- Keyword Tab -->
                <div class="geoai-tab-pane" id="geoai-tab-keyword">
                    <?php $this->render_keyword_tab_content( $post, $focus_keyword, $keyword_score, $keyword_data ); ?>
                </div>

                <!-- Readability Tab -->
                <div class="geoai-tab-pane" id="geoai-tab-readability">
                    <?php $this->render_readability_tab_content( $post, $readability_score, $readability_data ); ?>
                </div>

                <!-- Content Insights Tab -->
                <div class="geoai-tab-pane" id="geoai-tab-insights">
                    <?php $this->render_insights_tab_content( $insights ); ?>
                </div>

                <!-- Internal Linking Tab -->
                <div class="geoai-tab-pane" id="geoai-tab-linking">
                    <?php $this->render_linking_tab_content( $suggestions, $link_stats ); ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.geoai-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                
                // Update buttons
                $('.geoai-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Update content
                $('.geoai-tab-pane').removeClass('active');
                $('#geoai-tab-' + tab).addClass('active');
            });
        });
        </script>
        <?php
    }

    /**
     * Render meta tab content (SEO Title, Description, AI Generation).
     */
    private function render_meta_tab_content( $post, $meta_title, $meta_description, $robots ) {
        ?>
        <div class="geoai-tab-section">
            <!-- AI Generation Banner -->
            <div class="geoai-ai-banner">
                <div class="ai-banner-icon">
                    <span class="dashicons dashicons-lightbulb"></span>
                </div>
                <div class="ai-banner-content">
                    <h4><?php esc_html_e( 'AI-Powered Generation', 'geo-ai' ); ?></h4>
                    <p><?php esc_html_e( 'Let Google Gemini AI create optimized meta title and description for you.', 'geo-ai' ); ?></p>
                </div>
                <div class="ai-banner-action">
                    <button type="button" id="geoai-generate-meta-btn" class="button button-primary button-hero" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e( 'Generate with AI', 'geo-ai' ); ?>
                    </button>
                </div>
            </div>
            <div id="geoai-generate-meta-status" class="geoai-status-message" style="display:none;"></div>

            <!-- SEO Title -->
            <div class="geoai-field-group">
                <label for="geoai_title" class="geoai-field-label">
                    <strong><?php esc_html_e( 'SEO Title', 'geo-ai' ); ?></strong>
                    <span class="char-counter" id="title-char-count"><?php echo mb_strlen( $meta_title ); ?> / 70</span>
                </label>
                <input type="text" id="geoai_title" name="geoai_title" value="<?php echo esc_attr( $meta_title ); ?>" class="geoai-input-large" maxlength="70" />
                <p class="geoai-field-description">
                    <?php esc_html_e( 'Optimal length: 50-60 characters. This appears in search results.', 'geo-ai' ); ?>
                </p>
                <div class="geoai-preview-box">
                    <div class="preview-label"><?php esc_html_e( 'Preview:', 'geo-ai' ); ?></div>
                    <div class="preview-title" id="title-preview"><?php echo esc_html( $meta_title ? $meta_title : $post->post_title ); ?></div>
                </div>
            </div>

            <!-- Meta Description -->
            <div class="geoai-field-group">
                <label for="geoai_meta_desc" class="geoai-field-label">
                    <strong><?php esc_html_e( 'Meta Description', 'geo-ai' ); ?></strong>
                    <span class="char-counter" id="desc-char-count"><?php echo mb_strlen( $meta_description ); ?> / 165</span>
                </label>
                <textarea id="geoai_meta_desc" name="geoai_meta_desc" rows="3" class="geoai-textarea-large" maxlength="165"><?php echo esc_textarea( $meta_description ); ?></textarea>
                <p class="geoai-field-description">
                    <?php esc_html_e( 'Optimal length: 150-160 characters. Include a call-to-action.', 'geo-ai' ); ?>
                </p>
                <div class="geoai-preview-box">
                    <div class="preview-label"><?php esc_html_e( 'Preview:', 'geo-ai' ); ?></div>
                    <div class="preview-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
                    <div class="preview-description" id="desc-preview"><?php echo esc_html( $meta_description ? $meta_description : wp_trim_words( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 20 ) ); ?></div>
                </div>
            </div>

            <!-- Robots Meta -->
            <div class="geoai-field-group">
                <label for="geoai_robots" class="geoai-field-label">
                    <strong><?php esc_html_e( 'Robots Meta', 'geo-ai' ); ?></strong>
                </label>
                <select id="geoai_robots" name="geoai_robots" class="geoai-select">
                    <option value="" <?php selected( $robots, '' ); ?>><?php esc_html_e( 'Default (index, follow)', 'geo-ai' ); ?></option>
                    <option value="noindex,follow" <?php selected( $robots, 'noindex,follow' ); ?>><?php esc_html_e( 'No Index, Follow', 'geo-ai' ); ?></option>
                    <option value="index,nofollow" <?php selected( $robots, 'index,nofollow' ); ?>><?php esc_html_e( 'Index, No Follow', 'geo-ai' ); ?></option>
                    <option value="noindex,nofollow" <?php selected( $robots, 'noindex,nofollow' ); ?>><?php esc_html_e( 'No Index, No Follow', 'geo-ai' ); ?></option>
                </select>
                <p class="geoai-field-description">
                    <?php esc_html_e( 'Control how search engines index this page.', 'geo-ai' ); ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Character counters and live preview
            function updateCharCount(inputId, counterId, previewId) {
                var $input = $('#' + inputId);
                var $counter = $('#' + counterId);
                var $preview = $('#' + previewId);
                
                $input.on('input', function() {
                    var length = $(this).val().length;
                    var maxLength = $input.attr('maxlength');
                    $counter.text(length + ' / ' + maxLength);
                    
                    // Update color based on length
                    if (inputId === 'geoai_title') {
                        if (length >= 50 && length <= 60) {
                            $counter.css('color', '#00a32a');
                        } else if (length > 60) {
                            $counter.css('color', '#d63638');
                        } else {
                            $counter.css('color', '#f59e0b');
                        }
                    } else {
                        if (length >= 150 && length <= 160) {
                            $counter.css('color', '#00a32a');
                        } else if (length > 160) {
                            $counter.css('color', '#d63638');
                        } else {
                            $counter.css('color', '#f59e0b');
                        }
                    }
                    
                    // Update preview
                    if ($preview.length) {
                        $preview.text($(this).val() || $preview.data('default'));
                    }
                });
            }
            
            updateCharCount('geoai_title', 'title-char-count', 'title-preview');
            updateCharCount('geoai_meta_desc', 'desc-char-count', 'desc-preview');

            // AI Generation
            $('#geoai-generate-meta-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#geoai-generate-meta-status');
                var postId = $btn.data('post-id');

                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php esc_html_e( 'Generating...', 'geo-ai' ); ?>');
                $status.removeClass('success error').addClass('info').html('<span class="dashicons dashicons-info"></span> <?php esc_html_e( 'AI is analyzing your content... This may take 10-15 seconds.', 'geo-ai' ); ?>').show();

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
                            $status.removeClass('info error').addClass('success').html('<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Generated successfully! Review and edit as needed.', 'geo-ai' ); ?>');
                        } else {
                            $status.removeClass('info success').addClass('error').html('<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Error: ', 'geo-ai' ); ?>' + response.data.message);
                        }
                    },
                    error: function() {
                        $status.removeClass('info success').addClass('error').html('<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Request failed. Please check your API key and try again.', 'geo-ai' ); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Generate with AI', 'geo-ai' ); ?>');
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 5000);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render keyword tab content.
     */
    private function render_keyword_tab_content( $post, $focus_keyword, $keyword_score, $keyword_data ) {
        ?>
        <div class="geoai-tab-section">
            <div class="geoai-input-group">
                <label for="geoai_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'geo-ai' ); ?></label>
                <input type="text" id="geoai_focus_keyword" name="geoai_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" class="geoai-input" placeholder="<?php esc_attr_e( 'Enter your focus keyword...', 'geo-ai' ); ?>" />
                <button type="button" class="button button-primary geoai-analyze-keyword">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Analyze', 'geo-ai' ); ?>
                </button>
            </div>

            <?php $geoai_kw_style = $keyword_score ? '' : 'display:none;'; ?>
            <div id="geoai-keyword-results" <?php if ( $geoai_kw_style ) { echo 'style="' . esc_attr( $geoai_kw_style ) . '"'; } ?>>
                <?php if ( $keyword_score && ! empty( $keyword_data['issues'] ) ) : ?>
                <div class="geoai-analysis-results">
                    <?php foreach ( $keyword_data['issues'] as $issue ) : ?>
                    <div class="geoai-check-item <?php echo esc_attr( $issue['severity'] ); ?>">
                        <span class="check-icon dashicons dashicons-<?php echo esc_attr( $issue['severity'] === 'good' ? 'yes-alt' : ( $issue['severity'] === 'warning' ? 'warning' : 'dismiss' ) ); ?>"></span>
                        <span class="check-text"><?php echo esc_html( $issue['message'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( isset( $keyword_data['density'] ) ) : ?>
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Keyword Density', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( number_format( $keyword_data['density'], 2 ) ); ?>%</div>
                    <div class="stat-detail"><?php echo esc_html( $keyword_data['occurrences'] ); ?> <?php esc_html_e( 'occurrences', 'geo-ai' ); ?></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render readability tab content.
     */
    private function render_readability_tab_content( $post, $readability_score, $readability_data ) {
        ?>
        <div class="geoai-tab-section">
            <button type="button" class="button button-primary geoai-analyze-readability">
                <span class="dashicons dashicons-book"></span>
                <?php esc_html_e( 'Analyze Readability', 'geo-ai' ); ?>
            </button>

            <?php $geoai_read_style = $readability_score ? '' : 'display:none;'; ?>
            <div id="geoai-readability-results" <?php if ( $geoai_read_style ) { echo 'style="' . esc_attr( $geoai_read_style ) . '"'; } ?>>
                <?php if ( $readability_score && ! empty( $readability_data['issues'] ) ) : ?>
                <div class="geoai-analysis-results">
                    <?php foreach ( $readability_data['issues'] as $issue ) : ?>
                    <div class="geoai-check-item <?php echo esc_attr( $issue['severity'] ); ?>">
                        <span class="check-icon dashicons dashicons-<?php echo esc_attr( $issue['severity'] === 'good' ? 'yes-alt' : ( $issue['severity'] === 'warning' ? 'warning' : 'dismiss' ) ); ?>"></span>
                        <span class="check-text"><?php echo esc_html( $issue['message'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="geoai-stats-grid">
                    <?php if ( isset( $readability_data['flesch_score'] ) ) : ?>
                    <div class="geoai-stat-box">
                        <div class="stat-label"><?php esc_html_e( 'Flesch Reading Ease', 'geo-ai' ); ?></div>
                        <div class="stat-value"><?php echo esc_html( number_format( $readability_data['flesch_score'], 1 ) ); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $readability_data['word_count'] ) ) : ?>
                    <div class="geoai-stat-box">
                        <div class="stat-label"><?php esc_html_e( 'Word Count', 'geo-ai' ); ?></div>
                        <div class="stat-value"><?php echo esc_html( number_format( $readability_data['word_count'] ) ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render insights tab content.
     */
    private function render_insights_tab_content( $insights ) {
        ?>
        <div class="geoai-tab-section">
            <div class="geoai-stats-grid">
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Words', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( number_format( $insights['content_metrics']['word_count'] ) ); ?></div>
                </div>
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Reading Time', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( $insights['content_metrics']['reading_time'] ); ?> min</div>
                </div>
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Vocabulary', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( $insights['content_metrics']['lexical_diversity'] ); ?>%</div>
                </div>
            </div>

            <?php if ( ! empty( $insights['recommendations'] ) ) : ?>
            <div class="geoai-recommendations">
                <h4><?php esc_html_e( 'Recommendations', 'geo-ai' ); ?></h4>
                <?php foreach ( $insights['recommendations'] as $rec ) : ?>
                <div class="geoai-check-item <?php echo esc_attr( $rec['type'] ); ?>">
                    <span class="check-icon dashicons dashicons-<?php echo $rec['type'] === 'success' ? 'yes-alt' : ( $rec['type'] === 'warning' ? 'warning' : 'info' ); ?>"></span>
                    <span class="check-text"><?php echo esc_html( $rec['message'] ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="geoai-word-cloud">
                <h4><?php esc_html_e( 'Top Words', 'geo-ai' ); ?></h4>
                <div class="word-tags">
                    <?php foreach ( array_slice( $insights['word_frequency'], 0, 15 ) as $word_data ) : ?>
                    <span class="word-tag">
                        <?php echo esc_html( $word_data['word'] ); ?>
                        <span class="word-count"><?php echo esc_html( $word_data['count'] ); ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render linking tab content.
     */
    private function render_linking_tab_content( $suggestions, $link_stats ) {
        ?>
        <div class="geoai-tab-section">
            <div class="geoai-stats-grid">
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Internal Links', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( $link_stats['internal'] ); ?></div>
                </div>
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'External Links', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( $link_stats['external'] ); ?></div>
                </div>
                <div class="geoai-stat-box">
                    <div class="stat-label"><?php esc_html_e( 'Total Links', 'geo-ai' ); ?></div>
                    <div class="stat-value"><?php echo esc_html( $link_stats['total'] ); ?></div>
                </div>
            </div>

            <?php if ( ! empty( $suggestions ) ) : ?>
            <div class="geoai-link-suggestions">
                <h4><?php esc_html_e( 'Suggested Internal Links', 'geo-ai' ); ?></h4>
                <?php foreach ( array_slice( $suggestions, 0, 5 ) as $suggestion ) : ?>
                <div class="geoai-suggestion-card">
                    <div class="suggestion-header">
                        <strong><?php echo esc_html( $suggestion['title'] ); ?></strong>
                        <span class="relevance-badge <?php echo $suggestion['relevance'] >= 0.7 ? 'high' : ( $suggestion['relevance'] >= 0.5 ? 'medium' : 'low' ); ?>">
                            <?php echo esc_html( round( $suggestion['relevance'] * 100 ) ); ?>%
                        </span>
                    </div>
                    <div class="suggestion-excerpt"><?php echo esc_html( $suggestion['excerpt'] ); ?></div>
                    <button type="button" class="button button-small geoai-copy-link" data-url="<?php echo esc_attr( $suggestion['url'] ); ?>" data-anchor="<?php echo esc_attr( $suggestion['anchor'] ); ?>">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e( 'Copy Link', 'geo-ai' ); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <p class="geoai-empty-state"><?php esc_html_e( 'No internal linking suggestions found.', 'geo-ai' ); ?></p>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.geoai-copy-link').on('click', function() {
                var url = $(this).data('url');
                var anchor = $(this).data('anchor');
                var linkHtml = '<a href="' + url + '">' + anchor + '</a>';
                
                var temp = $('<textarea>');
                $('body').append(temp);
                temp.val(linkHtml).select();
                document.execCommand('copy');
                temp.remove();
                
                var btn = $(this);
                var originalHtml = btn.html();
                btn.html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Copied!', 'geo-ai' ); ?>');
                setTimeout(function() {
                    btn.html(originalHtml);
                }, 2000);
            });
        });
        </script>
        <?php
    }

    /**
     * Render readability analysis meta box.
     */
    public function render_readability_meta_box( $post ) {
        $readability_score = get_post_meta( $post->ID, '_geoai_readability_score', true );
        $readability_data = get_post_meta( $post->ID, '_geoai_readability_data', true );
        
        if ( $readability_data ) {
            $readability_data = json_decode( $readability_data, true );
        }
        ?>
        <div class="geoai-readability-analysis">
            <button type="button" class="button button-secondary geoai-analyze-readability">
                <?php esc_html_e( 'Analyze Readability', 'geo-ai' ); ?>
            </button>

            <div id="geoai-readability-results" <?php echo $readability_score ? '' : 'style="display:none;"'; ?>>
                <?php if ( $readability_score ) : ?>
                <div class="geoai-score-display">
                    <span class="score-label"><?php esc_html_e( 'Readability Score:', 'geo-ai' ); ?></span>
                    <span class="score-value <?php echo esc_attr( $this->get_score_class( $readability_score ) ); ?>">
                        <?php echo esc_html( $readability_score ); ?>/100
                    </span>
                </div>

                <?php if ( ! empty( $readability_data['issues'] ) ) : ?>
                <ul class="geoai-issues-list">
                    <?php foreach ( $readability_data['issues'] as $issue ) : ?>
                    <li class="issue-<?php echo esc_attr( $issue['severity'] ); ?>">
                        <span class="dashicons dashicons-<?php echo $issue['severity'] === 'good' ? 'yes' : 'warning'; ?>"></span>
                        <?php echo esc_html( $issue['message'] ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ( isset( $readability_data['flesch_score'] ) ) : ?>
                <p class="geoai-stat">
                    <strong><?php esc_html_e( 'Flesch Reading Ease:', 'geo-ai' ); ?></strong>
                    <?php echo esc_html( number_format( $readability_data['flesch_score'], 1 ) ); ?>
                </p>
                <?php endif; ?>

                <?php if ( isset( $readability_data['word_count'] ) ) : ?>
                <p class="geoai-stat">
                    <strong><?php esc_html_e( 'Word Count:', 'geo-ai' ); ?></strong>
                    <?php echo esc_html( $readability_data['word_count'] ); ?>
                </p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save SEO meta data.
     */
    public function save_seo_meta( $post_id, $post ) {
        // Check nonce
        if ( ! isset( $_POST['geoai_seo_nonce'] ) || ! wp_verify_nonce( $_POST['geoai_seo_nonce'], 'geoai_seo_meta' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save focus keyword
        if ( isset( $_POST['geoai_focus_keyword'] ) ) {
            $keyword = sanitize_text_field( $_POST['geoai_focus_keyword'] );
            update_post_meta( $post_id, '_geoai_focus_keyword', $keyword );

            // Run keyword analysis if keyword is set
            if ( ! empty( $keyword ) ) {
                $this->run_keyword_analysis( $post_id );
            }
        }

        // Run readability analysis
        $this->run_readability_analysis( $post_id );
    }

    /**
     * Run keyword analysis for a post.
     */
    private function run_keyword_analysis( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $keyword = get_post_meta( $post_id, '_geoai_focus_keyword', true );
        if ( empty( $keyword ) ) {
            return;
        }

        $analyzer = new \GeoAI\Analyzers\Keyword_Analyzer();
        
        $data = array(
            'title'            => $post->post_title,
            'content'          => $post->post_content,
            'excerpt'          => $post->post_excerpt,
            'slug'             => $post->post_name,
            'meta_description' => get_post_meta( $post_id, '_geoai_meta_description', true ),
        );

        $result = $analyzer->analyze( $keyword, $data );

        update_post_meta( $post_id, '_geoai_keyword_score', $result['score'] );
        update_post_meta( $post_id, '_geoai_keyword_data', wp_json_encode( $result ) );
    }

    /**
     * Run readability analysis for a post.
     */
    private function run_readability_analysis( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $analyzer = new \GeoAI\Analyzers\Readability_Analyzer();
        $result = $analyzer->analyze( $post->post_content );

        update_post_meta( $post_id, '_geoai_readability_score', $result['score'] );
        update_post_meta( $post_id, '_geoai_readability_data', wp_json_encode( $result ) );
    }

    /**
     * Render internal linking suggestions meta box.
     */
    public function render_internal_linking_meta_box( $post ) {
        $analyzer = new \GeoAI\Analyzers\Internal_Linking();
        $suggestions = $analyzer->get_suggestions( $post->ID, $post->post_content );
        $link_stats = $analyzer->get_link_stats( $post->post_content );
        ?>
        <div class="geoai-internal-linking">
            <div class="geoai-link-stats" style="background: #f0f6fc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px;"><?php esc_html_e( 'Link Statistics', 'geo-ai' ); ?></h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                    <div>
                        <strong><?php echo esc_html( $link_stats['internal'] ); ?></strong><br>
                        <small><?php esc_html_e( 'Internal Links', 'geo-ai' ); ?></small>
                    </div>
                    <div>
                        <strong><?php echo esc_html( $link_stats['external'] ); ?></strong><br>
                        <small><?php esc_html_e( 'External Links', 'geo-ai' ); ?></small>
                    </div>
                    <div>
                        <strong><?php echo esc_html( $link_stats['total'] ); ?></strong><br>
                        <small><?php esc_html_e( 'Total Links', 'geo-ai' ); ?></small>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $suggestions ) ) : ?>
            <h4><?php esc_html_e( 'Suggested Internal Links', 'geo-ai' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Click to copy the link code to your clipboard:', 'geo-ai' ); ?></p>
            <ul class="geoai-link-suggestions" style="list-style: none; margin: 0; padding: 0;">
                <?php foreach ( $suggestions as $suggestion ) : ?>
                <li style="padding: 12px; margin: 8px 0; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                        <div style="flex: 1;">
                            <strong><?php echo esc_html( $suggestion['title'] ); ?></strong>
                            <div style="font-size: 12px; color: #646970; margin: 5px 0;">
                                <?php echo esc_html( $suggestion['excerpt'] ); ?>
                            </div>
                            <div style="font-size: 11px; color: #2271b1; margin-top: 5px;">
                                <?php
                                if ( ! empty( $suggestion['categories'] ) ) {
                                    echo esc_html( implode( ', ', $suggestion['categories'] ) );
                                }
                                ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <?php $geoai_rel_color = ( $suggestion['relevance'] >= 0.7 ? '#00a32a' : ( $suggestion['relevance'] >= 0.5 ? '#2271b1' : '#dba617' ) ); ?>
                            <span class="relevance-badge" style="display: inline-block; padding: 4px 8px; background: <?php echo esc_attr( $geoai_rel_color ); ?>; color: white; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                <?php echo esc_html( round( $suggestion['relevance'] * 100 ) ); ?>% <?php esc_html_e( 'match', 'geo-ai' ); ?>
                            </span>
                            <br>
                            <button type="button" class="button button-small geoai-copy-link" data-url="<?php echo esc_attr( $suggestion['url'] ); ?>" data-anchor="<?php echo esc_attr( $suggestion['anchor'] ); ?>" style="margin-top: 5px;">
                                <?php esc_html_e( 'Copy Link', 'geo-ai' ); ?>
                            </button>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <p class="description"><?php esc_html_e( 'No internal linking suggestions found. Try adding more content or publish more posts.', 'geo-ai' ); ?></p>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.geoai-copy-link').on('click', function() {
                var url = $(this).data('url');
                var anchor = $(this).data('anchor');
                var linkHtml = '<a href="' + url + '">' + anchor + '</a>';
                
                // Copy to clipboard
                var temp = $('<textarea>');
                $('body').append(temp);
                temp.val(linkHtml).select();
                document.execCommand('copy');
                temp.remove();
                
                // Show feedback
                var btn = $(this);
                var originalText = btn.text();
                btn.text('<?php esc_html_e( 'Copied!', 'geo-ai' ); ?>');
                setTimeout(function() {
                    btn.text(originalText);
                }, 2000);
            });
        });
        </script>
        <?php
    }

    /**
     * Render content insights meta box.
     */
    public function render_content_insights_meta_box( $post ) {
        $analyzer = new \GeoAI\Analyzers\Content_Insights();
        $insights = $analyzer->analyze( $post->post_content, $post->post_title );
        ?>
        <div class="geoai-content-insights">
            <!-- Content Metrics -->
            <div class="insights-metrics" style="background: #f0f6fc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px;"><?php esc_html_e( 'Content Metrics', 'geo-ai' ); ?></h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 13px;">
                    <div>
                        <strong><?php echo esc_html( number_format( $insights['content_metrics']['word_count'] ) ); ?></strong><br>
                        <small><?php esc_html_e( 'Words', 'geo-ai' ); ?></small>
                    </div>
                    <div>
                        <strong><?php echo esc_html( $insights['content_metrics']['sentence_count'] ); ?></strong><br>
                        <small><?php esc_html_e( 'Sentences', 'geo-ai' ); ?></small>
                    </div>
                    <div>
                        <strong><?php echo esc_html( $insights['content_metrics']['reading_time'] ); ?> min</strong><br>
                        <small><?php esc_html_e( 'Reading Time', 'geo-ai' ); ?></small>
                    </div>
                    <div>
                        <strong><?php echo esc_html( $insights['content_metrics']['lexical_diversity'] ); ?>%</strong><br>
                        <small><?php esc_html_e( 'Vocabulary', 'geo-ai' ); ?></small>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <?php if ( ! empty( $insights['recommendations'] ) ) : ?>
            <div class="insights-recommendations" style="margin-bottom: 15px;">
                <h4><?php esc_html_e( 'Recommendations', 'geo-ai' ); ?></h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ( $insights['recommendations'] as $rec ) : ?>
                    <li style="margin: 5px 0; color: <?php echo $rec['type'] === 'warning' ? '#d63638' : ( $rec['type'] === 'success' ? '#00a32a' : '#646970' ); ?>;">
                        <?php echo esc_html( $rec['message'] ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Top Words -->
            <div class="insights-words">
                <h4><?php esc_html_e( 'Top Words', 'geo-ai' ); ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php foreach ( array_slice( $insights['word_frequency'], 0, 15 ) as $word_data ) : ?>
                    <span style="display: inline-block; padding: 5px 10px; background: #f0f0f1; border-radius: 12px; font-size: 12px;">
                        <strong><?php echo esc_html( $word_data['word'] ); ?></strong>
                        <span style="color: #646970;">(<?php echo esc_html( $word_data['count'] ); ?>)</span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Phrases -->
            <?php if ( ! empty( $insights['phrase_frequency'] ) ) : ?>
            <div class="insights-phrases" style="margin-top: 15px;">
                <h4><?php esc_html_e( 'Common Phrases', 'geo-ai' ); ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php foreach ( array_slice( $insights['phrase_frequency'], 0, 10 ) as $phrase_data ) : ?>
                    <span style="display: inline-block; padding: 5px 10px; background: #f0f6fc; border-radius: 12px; font-size: 12px; border: 1px solid #2271b1;">
                        <strong><?php echo esc_html( $phrase_data['phrase'] ); ?></strong>
                        <span style="color: #2271b1;">(<?php echo esc_html( $phrase_data['count'] ); ?>)</span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get CSS class based on score.
     */
    private function get_score_class( $score ) {
        if ( $score >= 80 ) {
            return 'score-good';
        } elseif ( $score >= 60 ) {
            return 'score-ok';
        } elseif ( $score >= 40 ) {
            return 'score-warning';
        } else {
            return 'score-poor';
        }
    }

    /**
     * AJAX: Generate AI-powered meta content
     */
    public function ajax_generate_meta() {
        check_ajax_referer( 'geoai-admin', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'geo-ai' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'geo-ai' ) ) );
        }

        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();
        $result = $analyzer->generate_meta_content( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer( 'geoai-admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'geo-ai' ) ) );
        }

        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();
        $result = $analyzer->test_api_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
