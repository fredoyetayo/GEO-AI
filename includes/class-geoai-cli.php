<?php
/**
 * WP-CLI commands.
 *
 * @package GeoAI
 */

namespace GeoAI\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * GEO AI WP-CLI commands.
 */
class GeoAI_CLI {
    /**
     * Audit a post or all posts.
     *
     * ## OPTIONS
     *
     * <post|all>
     * : Post ID or 'all' to audit all posts.
     *
     * [--min-score=<score>]
     * : Minimum score threshold for 'all' mode.
     *
     * ## EXAMPLES
     *
     *     wp geoai audit 123
     *     wp geoai audit all --min-score=80
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function audit( $args, $assoc_args ) {
        $target    = $args[0] ?? 'all';
        $min_score = $assoc_args['min-score'] ?? 0;

        if ( 'all' === $target ) {
            $this->audit_all( $min_score );
        } else {
            $this->audit_post( absint( $target ) );
        }
    }

    private function audit_post( $post_id ) {
        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();
        $result   = $analyzer->analyze_post( $post_id );

        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }

        \WP_CLI::success(
            sprintf(
                'Post #%d audited. Score: %d/100',
                $post_id,
                $result['scores']['total']
            )
        );

        \WP_CLI::line( 'Issues:' );
        foreach ( $result['issues'] as $issue ) {
            \WP_CLI::line( sprintf( '  - [%s] %s', $issue['severity'], $issue['msg'] ) );
        }
    }

    private function audit_all( $min_score ) {
        $posts = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            )
        );

        $progress = \WP_CLI\Utils\make_progress_bar( 'Auditing posts', count( $posts ) );

        $analyzer = \GeoAI\Core\GeoAI_Analyzer::get_instance();

        foreach ( $posts as $post ) {
            $result = $analyzer->analyze_post( $post->ID );

            if ( ! is_wp_error( $result ) && $result['scores']['total'] >= $min_score ) {
                \WP_CLI::log(
                    sprintf(
                        'Post #%d "%s": %d/100',
                        $post->ID,
                        get_the_title( $post ),
                        $result['scores']['total']
                    )
                );
            }

            $progress->tick();
        }

        $progress->finish();
        \WP_CLI::success( 'Audit complete.' );
    }
}

\WP_CLI::add_command( 'geoai', __NAMESPACE__ . '\\GeoAI_CLI' );
