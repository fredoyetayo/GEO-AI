<?php
/**
 * Plugin Name: GEO AI (AI SEO)
 * Plugin URI: https://fredoyetayo.com
 * Description: Modern SEO plugin optimized for AI answer engines (Google AI Overviews, Perplexity, ChatGPT) with classic SEO essentials.
 * Version: 1.3.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Fred Oyetayo
 * Author URI: https://fredoyetayo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geo-ai
 * Domain Path: /languages
 *
 * @package GeoAI
 */

namespace GeoAI;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'GEOAI_VERSION', '1.3.0' );
define( 'GEOAI_PLUGIN_FILE', __FILE__ );
define( 'GEOAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GEOAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class GeoAI_Plugin {
    /**
     * Plugin instance.
     *
     * @var GeoAI_Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return GeoAI_Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files.
     */
    private function load_dependencies() {
        // Load Action Scheduler.
        require_once GEOAI_PLUGIN_DIR . 'vendor/action-scheduler/action-scheduler.php';

        // Load traits.
        require_once GEOAI_PLUGIN_DIR . 'includes/traits/trait-encryption.php';

        // Load analyzers.
        require_once GEOAI_PLUGIN_DIR . 'includes/analyzers/class-keyword-analyzer.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/analyzers/class-readability-analyzer.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/analyzers/class-seo-dashboard.php';

        // Load core classes.
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-admin.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-rest.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-analyzer.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-compat.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-schema.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-sitemaps.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-meta.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-social.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-breadcrumbs.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-redirects.php';
        require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-404.php';

        // Load WP-CLI if available.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once GEOAI_PLUGIN_DIR . 'includes/class-geoai-cli.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialize plugin components.
     */
    public function init() {
        // Initialize components.
        Admin\GeoAI_Admin::get_instance();
        REST\GeoAI_REST::get_instance();
        Core\GeoAI_Compat::get_instance();
        Core\GeoAI_Schema::get_instance();
        Core\GeoAI_Sitemaps::get_instance();
        Core\GeoAI_Meta::get_instance();
        Core\GeoAI_Social::get_instance();
        Core\GeoAI_Breadcrumbs::get_instance();
        Core\GeoAI_Redirects::get_instance();
        Core\GeoAI_404::get_instance();

        // Register Answer Card block.
        add_action( 'init', array( $this, 'register_blocks' ) );

        do_action( 'geoai_init' );
    }

    /**
     * Register Gutenberg blocks.
     */
    public function register_blocks() {
        register_block_type( GEOAI_PLUGIN_DIR . 'blocks/answer-card' );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'geo-ai',
            false,
            dirname( GEOAI_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Set default options.
        $this->set_default_options();

        // Add custom role capabilities.
        $this->add_capabilities();

        // Create 404 log table.
        $this->create_404_table();

        // Flush rewrite rules.
        flush_rewrite_rules();

        do_action( 'geoai_activated' );
    }

    /**
     * Create 404 log table.
     */
    private function create_404_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'geoai_404_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            referrer varchar(255) DEFAULT '',
            ip varchar(50) DEFAULT '',
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY url (url),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear scheduled actions.
        as_unschedule_all_actions( 'geoai_audit_batch' );

        // Flush rewrite rules.
        flush_rewrite_rules();

        do_action( 'geoai_deactivated' );
    }

    /**
     * Set default plugin options.
     */
    private function set_default_options() {
        $defaults = array(
            'geoai_api_key'             => '',
            'geoai_autorun_on_save'     => false,
            'geoai_compat_mode'         => 'standalone',
            'geoai_titles_templates'    => $this->get_default_title_templates(),
            'geoai_social_defaults'     => array(
                'og_image'   => '',
                'og_title'   => '',
                'og_desc'    => '',
                'tw_card'    => 'summary_large_image',
                'tw_site'    => '',
                'tw_creator' => '',
            ),
            'geoai_schema_defaults'     => array(
                'article'        => true,
                'faq'            => false,
                'howto'          => false,
                'product'        => false,
                'localbusiness'  => false,
                'organization'   => true,
                'website'        => true,
            ),
            'geoai_sitemaps'            => array(
                'enabled'       => true,
                'post_types'    => array( 'post', 'page' ),
                'taxonomies'    => array( 'category', 'post_tag' ),
                'images'        => true,
                'ping_google'   => false,
                'ping_bing'     => false,
            ),
            'geoai_crawler_prefs'       => array(
                'block_perplexity' => false,
                'block_gptbot'     => false,
                'block_ccbot'      => false,
                'block_anthropic'  => false,
            ),
            'geoai_redirects'           => array(),
            'geoai_404_settings'        => array(
                'enabled'   => false,
                'retention' => 30,
                'max_logs'  => 1000,
            ),
            'geoai_roles_caps'          => array(
                'administrator' => array( 'manage_geoai', 'edit_geoai_meta' ),
            ),
            'geoai_debug_mode'          => false,
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Get default title templates.
     *
     * @return array
     */
    private function get_default_title_templates() {
        return array(
            'post'     => '%%title%% %%sep%% %%sitename%%',
            'page'     => '%%title%% %%sep%% %%sitename%%',
            'archive'  => '%%archive_title%% %%sep%% %%sitename%%',
            'search'   => 'Search Results for "%%searchphrase%%" %%sep%% %%sitename%%',
            'notfound' => 'Page not found %%sep%% %%sitename%%',
            'home'     => '%%sitename%% %%sep%% %%sitedesc%%',
        );
    }

    /**
     * Add custom capabilities to roles.
     */
    private function add_capabilities() {
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( 'manage_geoai' );
            $admin_role->add_cap( 'edit_geoai_meta' );
        }

        do_action( 'geoai_add_capabilities' );
    }
}

/**
 * Initialize the plugin.
 */
function geoai_init() {
    return GeoAI_Plugin::get_instance();
}

// Start the plugin.
geoai_init();
