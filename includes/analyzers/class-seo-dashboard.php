<?php
/**
 * SEO Analysis Dashboard
 *
 * @package GeoAI
 */

namespace GeoAI\Analyzers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides site-wide SEO health analysis.
 */
class SEO_Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Clear cache when posts are saved
		add_action( 'save_post', array( $this, 'clear_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_cache' ) );
	}

	/**
	 * Clear dashboard cache.
	 */
	public function clear_cache() {
		delete_transient( 'geoai_dashboard_data' );
	}

	/**
	 * Get site-wide SEO health data.
	 *
	 * @return array Dashboard data.
	 */
	public function get_dashboard_data() {
		// Check cache first (5 minute expiration)
		$cache_key = 'geoai_dashboard_data';
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Generate fresh data
		$data = array(
			'overall_score'      => $this->calculate_overall_score(),
			'issues'             => $this->get_site_issues(),
			'post_stats'         => $this->get_post_statistics(),
			'recommendations'    => $this->get_recommendations(),
			'score_distribution' => $this->get_score_distribution(),
			'recent_activity'    => $this->get_recent_activity(),
			'top_performers'     => $this->get_top_performers(),
			'needs_attention'    => $this->get_needs_attention(),
		);

		// Cache for 5 minutes
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Calculate overall site SEO score.
	 *
	 * @return int Score 0-100.
	 */
	private function calculate_overall_score() {
		$issues = $this->get_site_issues();
		$total_issues = count( $issues );
		
		// Start with 100 and deduct points
		$score = 100;
		
		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ) {
				case 'critical':
					$score -= 15;
					break;
				case 'high':
					$score -= 10;
					break;
				case 'medium':
					$score -= 5;
					break;
				case 'low':
					$score -= 2;
					break;
			}
		}

		return max( 0, $score );
	}

	/**
	 * Get site-wide SEO issues.
	 *
	 * @return array List of issues.
	 */
	private function get_site_issues() {
		$issues = array();

		// Check for posts without meta descriptions
		$no_meta = $this->count_posts_without_meta();
		if ( $no_meta > 0 ) {
			$issues[] = array(
				'id'       => 'posts_no_meta',
				'severity' => 'high',
				'count'    => $no_meta,
				'message'  => sprintf(
					/* translators: %d: number of posts */
					_n(
						'%d post is missing a meta description.',
						'%d posts are missing meta descriptions.',
						$no_meta,
						'geo-ai'
					),
					$no_meta
				),
				'action'   => admin_url( 'admin.php?page=geoai-bulk-editor' ),
			);
		}

		// Check for duplicate titles
		$duplicate_titles = $this->find_duplicate_titles();
		if ( ! empty( $duplicate_titles ) ) {
			$issues[] = array(
				'id'       => 'duplicate_titles',
				'severity' => 'medium',
				'count'    => count( $duplicate_titles ),
				'message'  => sprintf(
					/* translators: %d: number of duplicate titles */
					__( '%d posts have duplicate titles.', 'geo-ai' ),
					count( $duplicate_titles )
				),
				'action'   => admin_url( 'admin.php?page=geoai-bulk-editor' ),
			);
		}

		// Check for low word count pages
		$low_word_count = $this->count_low_word_count_posts();
		if ( $low_word_count > 0 ) {
			$issues[] = array(
				'id'       => 'low_word_count',
				'severity' => 'medium',
				'count'    => $low_word_count,
				'message'  => sprintf(
					/* translators: %d: number of posts */
					_n(
						'%d post has less than 300 words.',
						'%d posts have less than 300 words.',
						$low_word_count,
						'geo-ai'
					),
					$low_word_count
				),
			);
		}

		// Check for posts without focus keywords
		$no_keyword = $this->count_posts_without_keyword();
		if ( $no_keyword > 0 ) {
			$issues[] = array(
				'id'       => 'no_focus_keyword',
				'severity' => 'low',
				'count'    => $no_keyword,
				'message'  => sprintf(
					/* translators: %d: number of posts */
					_n(
						'%d post is missing a focus keyword.',
						'%d posts are missing focus keywords.',
						$no_keyword,
						'geo-ai'
					),
					$no_keyword
				),
			);
		}

		// Check for posts with readability issues
		$readability_issues = $this->count_readability_issues();
		if ( $readability_issues > 0 ) {
			$issues[] = array(
				'id'       => 'readability_issues',
				'severity' => 'low',
				'count'    => $readability_issues,
				'message'  => sprintf(
					/* translators: %d: number of posts */
					_n(
						'%d post has readability issues.',
						'%d posts have readability issues.',
						$readability_issues,
						'geo-ai'
					),
					$readability_issues
				),
			);
		}

		return $issues;
	}

	/**
	 * Get post statistics.
	 *
	 * @return array Statistics.
	 */
	private function get_post_statistics() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// Total published posts
		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_status = 'publish' 
			AND post_type IN ({$post_types_str})"
		);

		// Posts with SEO meta
		$with_meta = $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key = '_geoai_meta_description'
			AND pm.meta_value != ''"
		);

		// Posts with focus keyword
		$with_keyword = $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key = '_geoai_focus_keyword'
			AND pm.meta_value != ''"
		);

		return array(
			'total_posts'      => (int) $total,
			'with_meta'        => (int) $with_meta,
			'with_keyword'     => (int) $with_keyword,
			'meta_percentage'  => $total > 0 ? round( ( $with_meta / $total ) * 100 ) : 0,
			'keyword_percentage' => $total > 0 ? round( ( $with_keyword / $total ) * 100 ) : 0,
		);
	}

	/**
	 * Get SEO recommendations.
	 *
	 * @return array Recommendations.
	 */
	private function get_recommendations() {
		$recommendations = array();
		$issues = $this->get_site_issues();

		foreach ( $issues as $issue ) {
			switch ( $issue['id'] ) {
				case 'posts_no_meta':
					$recommendations[] = array(
						'title'   => __( 'Add Meta Descriptions', 'geo-ai' ),
						'message' => __( 'Meta descriptions improve click-through rates from search results. Use the Bulk Editor to add them quickly.', 'geo-ai' ),
						'action'  => $issue['action'] ?? '',
					);
					break;

				case 'duplicate_titles':
					$recommendations[] = array(
						'title'   => __( 'Fix Duplicate Titles', 'geo-ai' ),
						'message' => __( 'Duplicate titles confuse search engines. Make each title unique and descriptive.', 'geo-ai' ),
						'action'  => $issue['action'] ?? '',
					);
					break;

				case 'low_word_count':
					$recommendations[] = array(
						'title'   => __( 'Expand Thin Content', 'geo-ai' ),
						'message' => __( 'Posts with less than 300 words may not rank well. Add more valuable content or consolidate pages.', 'geo-ai' ),
					);
					break;

				case 'no_focus_keyword':
					$recommendations[] = array(
						'title'   => __( 'Set Focus Keywords', 'geo-ai' ),
						'message' => __( 'Focus keywords help you optimize content for specific search terms. Add them in the post editor.', 'geo-ai' ),
					);
					break;
			}
		}

		return $recommendations;
	}

	/**
	 * Count posts without meta descriptions.
	 *
	 * @return int Count.
	 */
	private function count_posts_without_meta() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_geoai_meta_description'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);
	}

	/**
	 * Find posts with duplicate titles.
	 *
	 * @return array Post IDs with duplicate titles.
	 */
	private function find_duplicate_titles() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$duplicates = $wpdb->get_results(
			"SELECT post_title, COUNT(*) as count 
			FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND post_type IN ({$post_types_str})
			GROUP BY post_title
			HAVING count > 1",
			ARRAY_A
		);

		return $duplicates;
	}

	/**
	 * Count posts with low word count.
	 *
	 * @return int Count.
	 */
	private function count_low_word_count_posts() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// Use SQL to estimate word count (much faster than PHP)
		// Approximate: LENGTH(content) / 5 (avg word length + space)
		$low_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND post_type IN ({$post_types_str})
			AND CHAR_LENGTH(REPLACE(post_content, ' ', '')) / 5 < 300"
		);

		return (int) $low_count;
	}

	/**
	 * Count posts without focus keyword.
	 *
	 * @return int Count.
	 */
	private function count_posts_without_keyword() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_geoai_focus_keyword'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);
	}

	/**
	 * Count posts with readability issues.
	 *
	 * @return int Count.
	 */
	private function count_readability_issues() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// Count posts with readability score < 60
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key = '_geoai_readability_score'
			AND CAST(pm.meta_value AS UNSIGNED) < 60"
		);
	}

	/**
	 * Get score distribution for chart.
	 *
	 * @return array Score ranges and counts.
	 */
	private function get_score_distribution() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// Use SQL aggregation instead of PHP loops (much faster)
		$keyword_dist = $wpdb->get_row(
			"SELECT 
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) >= 80 THEN 1 ELSE 0 END) as excellent,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as good,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) BETWEEN 40 AND 59 THEN 1 ELSE 0 END) as fair,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) < 40 THEN 1 ELSE 0 END) as poor
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key = '_geoai_keyword_score'
			AND pm.meta_value != ''",
			ARRAY_A
		);

		$readability_dist = $wpdb->get_row(
			"SELECT 
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) >= 80 THEN 1 ELSE 0 END) as excellent,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as good,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) BETWEEN 40 AND 59 THEN 1 ELSE 0 END) as fair,
				SUM(CASE WHEN CAST(pm.meta_value AS UNSIGNED) < 40 THEN 1 ELSE 0 END) as poor
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key = '_geoai_readability_score'
			AND pm.meta_value != ''",
			ARRAY_A
		);

		// Build ranges array
		return array(
			'excellent' => array(
				'min'         => 80,
				'max'         => 100,
				'keyword'     => (int) ( $keyword_dist['excellent'] ?? 0 ),
				'readability' => (int) ( $readability_dist['excellent'] ?? 0 ),
			),
			'good' => array(
				'min'         => 60,
				'max'         => 79,
				'keyword'     => (int) ( $keyword_dist['good'] ?? 0 ),
				'readability' => (int) ( $readability_dist['good'] ?? 0 ),
			),
			'fair' => array(
				'min'         => 40,
				'max'         => 59,
				'keyword'     => (int) ( $keyword_dist['fair'] ?? 0 ),
				'readability' => (int) ( $readability_dist['fair'] ?? 0 ),
			),
			'poor' => array(
				'min'         => 0,
				'max'         => 39,
				'keyword'     => (int) ( $keyword_dist['poor'] ?? 0 ),
				'readability' => (int) ( $readability_dist['poor'] ?? 0 ),
			),
		);
	}

	/**
	 * Get recent activity.
	 *
	 * @return array Recent posts analyzed.
	 */
	private function get_recent_activity() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$results = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_modified,
			MAX(CASE WHEN pm.meta_key = '_geoai_keyword_score' THEN pm.meta_value END) as keyword_score,
			MAX(CASE WHEN pm.meta_key = '_geoai_readability_score' THEN pm.meta_value END) as readability_score
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key IN ('_geoai_keyword_score', '_geoai_readability_score')
			GROUP BY p.ID
			ORDER BY p.post_modified DESC
			LIMIT 5",
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get top performing posts.
	 *
	 * @return array Top posts by score.
	 */
	private function get_top_performers() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$results = $wpdb->get_results(
			"SELECT p.ID, p.post_title,
			MAX(CASE WHEN pm.meta_key = '_geoai_keyword_score' THEN CAST(pm.meta_value AS UNSIGNED) END) as keyword_score,
			MAX(CASE WHEN pm.meta_key = '_geoai_readability_score' THEN CAST(pm.meta_value AS UNSIGNED) END) as readability_score
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key IN ('_geoai_keyword_score', '_geoai_readability_score')
			GROUP BY p.ID
			HAVING keyword_score >= 80 OR readability_score >= 80
			ORDER BY (COALESCE(keyword_score, 0) + COALESCE(readability_score, 0)) DESC
			LIMIT 5",
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get posts needing attention.
	 *
	 * @return array Posts with low scores.
	 */
	private function get_needs_attention() {
		global $wpdb;

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$results = $wpdb->get_results(
			"SELECT p.ID, p.post_title,
			MAX(CASE WHEN pm.meta_key = '_geoai_keyword_score' THEN CAST(pm.meta_value AS UNSIGNED) END) as keyword_score,
			MAX(CASE WHEN pm.meta_key = '_geoai_readability_score' THEN CAST(pm.meta_value AS UNSIGNED) END) as readability_score
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ({$post_types_str})
			AND pm.meta_key IN ('_geoai_keyword_score', '_geoai_readability_score')
			GROUP BY p.ID
			HAVING keyword_score < 60 OR readability_score < 60
			ORDER BY (COALESCE(keyword_score, 0) + COALESCE(readability_score, 0)) ASC
			LIMIT 5",
			ARRAY_A
		);

		return $results;
	}
}
