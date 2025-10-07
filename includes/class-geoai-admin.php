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
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'GEO AI Settings', 'geo-ai' ),
            __( 'GEO AI', 'geo-ai' ),
            'manage_options',
            'geoai-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-search',
            80
        );
    }

    public function register_settings() {
        $settings = array(
            'geoai_api_key',
            'geoai_autorun_on_save',
            'geoai_compat_mode',
            'geoai_titles_templates',
            'geoai_social_defaults',
            'geoai_schema_defaults',
            'geoai_sitemaps',
            'geoai_crawler_prefs',
            'geoai_redirects',
            'geoai_404_settings',
            'geoai_roles_caps',
            'geoai_debug_mode',
        );

        foreach ( $settings as $setting ) {
            register_setting( 'geoai_settings', $setting );
        }
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_geoai-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'geoai-admin',
            GEOAI_PLUGIN_URL . 'assets/admin.css',
            array(),
            GEOAI_VERSION
        );

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
        $asset_file = GEOAI_PLUGIN_DIR . 'build/editor.asset.php';
        
        if ( file_exists( $asset_file ) ) {
            $asset = include $asset_file;
            
            wp_enqueue_script(
                'geoai-editor',
                GEOAI_PLUGIN_URL . 'build/editor.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
        } else {
            // Fallback if build file doesn't exist
            wp_enqueue_script(
                'geoai-editor',
                GEOAI_PLUGIN_URL . 'build/editor.js',
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
        $decrypted_key    = ! empty( $api_key ) ? $this->decrypt( $api_key ) : '';
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
                    <p class="description">
                        <?php esc_html_e( 'Your API key is encrypted when stored. Get one from Google AI Studio.', 'geo-ai' ); ?>
                    </p>
                </td>
            </tr>
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
        // Handle API key encryption on save
        if ( isset( $_POST['geoai_api_key'] ) && check_admin_referer( 'geoai_settings-options' ) ) {
            $submitted_key = sanitize_text_field( wp_unslash( $_POST['geoai_api_key'] ) );
            if ( ! empty( $submitted_key ) && $submitted_key !== $decrypted_key ) {
                update_option( 'geoai_api_key', $this->encrypt( $submitted_key ) );
            }
        }
    }

    private function render_titles_tab() {
        $templates = get_option( 'geoai_titles_templates', array() );
        ?>
        <h2><?php esc_html_e( 'Title & Meta Description Templates', 'geo-ai' ); ?></h2>
        <p><?php esc_html_e( 'Available variables:', 'geo-ai' ); ?> <code>%%title%%</code>, <code>%%sitename%%</code>, <code>%%sep%%</code>, <code>%%excerpt%%</code>, <code>%%category%%</code>, <code>%%tag%%</code>, <code>%%date%%</code>, <code>%%modified%%</code>, <code>%%id%%</code>, <code>%%author%%</code></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Post Title', 'geo-ai' ); ?></label></th>
                <td><input type="text" name="geoai_titles_templates[post]" value="<?php echo esc_attr( $templates['post'] ?? '%%title%% %%sep%% %%sitename%%' ); ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Page Title', 'geo-ai' ); ?></label></th>
                <td><input type="text" name="geoai_titles_templates[page]" value="<?php echo esc_attr( $templates['page'] ?? '%%title%% %%sep%% %%sitename%%' ); ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Archive Title', 'geo-ai' ); ?></label></th>
                <td><input type="text" name="geoai_titles_templates[archive]" value="<?php echo esc_attr( $templates['archive'] ?? '%%archive_title%% %%sep%% %%sitename%%' ); ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Homepage Title', 'geo-ai' ); ?></label></th>
                <td><input type="text" name="geoai_titles_templates[home]" value="<?php echo esc_attr( $templates['home'] ?? '%%sitename%% %%sep%% %%sitedesc%%' ); ?>" class="large-text" /></td>
            </tr>
        </table>
        <?php
    }

    private function render_social_tab() {
        $defaults = get_option( 'geoai_social_defaults', array() );
        ?>
        <h2><?php esc_html_e( 'OpenGraph & Twitter Cards', 'geo-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default OG Image', 'geo-ai' ); ?></label></th>
                <td><input type="url" name="geoai_social_defaults[og_image]" value="<?php echo esc_url( $defaults['og_image'] ?? '' ); ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Twitter Card Type', 'geo-ai' ); ?></label></th>
                <td>
                    <select name="geoai_social_defaults[tw_card]">
                        <option value="summary" <?php selected( $defaults['tw_card'] ?? 'summary_large_image', 'summary' ); ?>><?php esc_html_e( 'Summary', 'geo-ai' ); ?></option>
                        <option value="summary_large_image" <?php selected( $defaults['tw_card'] ?? 'summary_large_image', 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image', 'geo-ai' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Twitter Site Handle', 'geo-ai' ); ?></label></th>
                <td><input type="text" name="geoai_social_defaults[tw_site]" value="<?php echo esc_attr( $defaults['tw_site'] ?? '' ); ?>" placeholder="@yoursite" class="regular-text" /></td>
            </tr>
        </table>
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
                                    <strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" target="_blank"><?php the_title(); ?></a></strong>
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
                    echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
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
                esc_html( _n( '%d post updated.', '%d posts updated.', $updated, 'geo-ai' ) ),
                $updated
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
            $updated,
            $skipped
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
            $deleted
        );
        echo '</p></div>';
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
}
