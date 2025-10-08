<?php
/**
 * Internal Linking Suggestions
 *
 * @package GeoAI
 */

namespace GeoAI\Analyzers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggests internal links based on content analysis.
 */
class Internal_Linking {

	/**
	 * Get internal linking suggestions for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Post content.
	 * @return array Suggestions with relevance scores.
	 */
	public function get_suggestions( $post_id, $content ) {
		$suggestions = array();
		
		// Extract keywords from content
		$keywords = $this->extract_keywords( $content );
		
		if ( empty( $keywords ) ) {
			return $suggestions;
		}

		// Find related posts
		$related_posts = $this->find_related_posts( $post_id, $keywords );
		
		// Score and rank suggestions
		foreach ( $related_posts as $related_post ) {
			$relevance = $this->calculate_relevance( $keywords, $related_post );
			
			if ( $relevance > 0.3 ) { // Minimum 30% relevance
				$suggestions[] = array(
					'post_id'    => $related_post['ID'],
					'title'      => $related_post['post_title'],
					'url'        => get_permalink( $related_post['ID'] ),
					'excerpt'    => wp_trim_words( $related_post['post_content'], 20 ),
					'relevance'  => $relevance,
					'anchor'     => $this->suggest_anchor_text( $keywords, $related_post ),
					'post_type'  => $related_post['post_type'],
					'categories' => $this->get_post_categories( $related_post['ID'] ),
				);
			}
		}

		// Sort by relevance
		usort( $suggestions, function( $a, $b ) {
			return $b['relevance'] <=> $a['relevance'];
		});

		// Limit to top 10
		return array_slice( $suggestions, 0, 10 );
	}

	/**
	 * Extract important keywords from content.
	 *
	 * @param string $content Post content.
	 * @return array Keywords with weights.
	 */
	private function extract_keywords( $content ) {
		// Strip HTML and get plain text
		$text = wp_strip_all_tags( $content );
		$text = strtolower( $text );
		
		// Remove common stop words
		$stop_words = array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
			'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
			'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this',
			'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
			'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each',
			'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
			'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
		);

		// Extract words
		preg_match_all( '/\b[a-z]{3,}\b/', $text, $matches );
		$words = $matches[0];

		// Count word frequency
		$word_freq = array_count_values( $words );
		
		// Remove stop words
		foreach ( $stop_words as $stop_word ) {
			unset( $word_freq[ $stop_word ] );
		}

		// Sort by frequency
		arsort( $word_freq );

		// Get top 20 keywords
		$keywords = array_slice( $word_freq, 0, 20, true );

		// Calculate weights (normalize to 0-1)
		$max_freq = max( array_values( $keywords ) );
		foreach ( $keywords as $word => $freq ) {
			$keywords[ $word ] = $freq / $max_freq;
		}

		return $keywords;
	}

	/**
	 * Find related posts based on keywords.
	 *
	 * @param int   $post_id Current post ID.
	 * @param array $keywords Keywords to match.
	 * @return array Related posts.
	 */
	private function find_related_posts( $post_id, $keywords ) {
		global $wpdb;

		$keyword_list = array_keys( $keywords );
		$keyword_pattern = implode( '|', array_map( 'preg_quote', $keyword_list ) );

		// Find posts containing these keywords
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content, post_type, post_date
				FROM {$wpdb->posts}
				WHERE ID != %d
				AND post_status = 'publish'
				AND post_type IN ('post', 'page')
				AND (post_title REGEXP %s OR post_content REGEXP %s)
				ORDER BY post_date DESC
				LIMIT 50",
				$post_id,
				$keyword_pattern,
				$keyword_pattern
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Calculate relevance score between keywords and a post.
	 *
	 * @param array $keywords Source keywords.
	 * @param array $post Post data.
	 * @return float Relevance score 0-1.
	 */
	private function calculate_relevance( $keywords, $post ) {
		$title = strtolower( $post['post_title'] );
		$content = strtolower( wp_strip_all_tags( $post['post_content'] ) );
		
		$score = 0;
		$total_weight = array_sum( $keywords );

		foreach ( $keywords as $keyword => $weight ) {
			// Title matches are worth more
			if ( strpos( $title, $keyword ) !== false ) {
				$score += $weight * 2;
			}
			
			// Content matches
			$content_matches = substr_count( $content, $keyword );
			if ( $content_matches > 0 ) {
				$score += $weight * min( $content_matches / 5, 1 ); // Cap at 5 mentions
			}
		}

		// Normalize to 0-1
		return min( $score / ( $total_weight * 2 ), 1 );
	}

	/**
	 * Suggest anchor text for a link.
	 *
	 * @param array $keywords Source keywords.
	 * @param array $post Target post.
	 * @return string Suggested anchor text.
	 */
	private function suggest_anchor_text( $keywords, $post ) {
		$title = $post['post_title'];
		$title_lower = strtolower( $title );

		// Find matching keywords in title
		foreach ( $keywords as $keyword => $weight ) {
			if ( strpos( $title_lower, $keyword ) !== false ) {
				// Use the title if it contains a keyword
				return $title;
			}
		}

		// Otherwise, use the post title
		return $title;
	}

	/**
	 * Get post categories.
	 *
	 * @param int $post_id Post ID.
	 * @return array Category names.
	 */
	private function get_post_categories( $post_id ) {
		$categories = get_the_category( $post_id );
		return array_map( function( $cat ) {
			return $cat->name;
		}, $categories );
	}

	/**
	 * Check if content already links to a post.
	 *
	 * @param string $content Post content.
	 * @param int    $target_post_id Target post ID.
	 * @return bool True if already linked.
	 */
	public function already_linked( $content, $target_post_id ) {
		$target_url = get_permalink( $target_post_id );
		$target_slug = basename( parse_url( $target_url, PHP_URL_PATH ) );
		
		// Check for permalink or slug in content
		return ( strpos( $content, $target_url ) !== false || 
		         strpos( $content, $target_slug ) !== false );
	}

	/**
	 * Get linking statistics for a post.
	 *
	 * @param string $content Post content.
	 * @return array Statistics.
	 */
	public function get_link_stats( $content ) {
		// Count internal links
		$internal_links = preg_match_all( 
			'/<a[^>]+href=["\'](' . preg_quote( home_url(), '/' ) . '[^"\']*)["\'][^>]*>/i',
			$content,
			$matches
		);

		// Count external links
		$external_links = preg_match_all(
			'/<a[^>]+href=["\']https?:\/\/(?!' . preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' ) . ')[^"\']*["\'][^>]*>/i',
			$content,
			$matches
		);

		// Count total links
		$total_links = preg_match_all( '/<a[^>]+href=["\'][^"\']*["\'][^>]*>/i', $content );

		return array(
			'internal' => $internal_links,
			'external' => $external_links,
			'total'    => $total_links,
		);
	}
}
