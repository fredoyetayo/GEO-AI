<?php
/**
 * Focus Keyword Analysis
 *
 * @package GeoAI
 */

namespace GeoAI\Analyzers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyzes content for focus keyword optimization.
 */
class Keyword_Analyzer {

	/**
	 * Analyze content for focus keyword.
	 *
	 * @param string $keyword Focus keyword.
	 * @param array  $data Content data (title, content, excerpt, slug, meta_description).
	 * @return array Analysis results.
	 */
	public function analyze( $keyword, $data ) {
		if ( empty( $keyword ) ) {
			return array(
				'score'  => 0,
				'issues' => array(
					array(
						'id'       => 'no_keyword',
						'severity' => 'error',
						'message'  => __( 'No focus keyword set.', 'geo-ai' ),
					),
				),
			);
		}

		$keyword = strtolower( trim( $keyword ) );
		$issues  = array();
		$score   = 0;
		$max_score = 100;

		// 1. Keyword in title (20 points)
		$title_result = $this->check_keyword_in_title( $keyword, $data['title'] ?? '' );
		$score += $title_result['score'];
		if ( ! empty( $title_result['issue'] ) ) {
			$issues[] = $title_result['issue'];
		}

		// 2. Keyword in meta description (15 points)
		$meta_result = $this->check_keyword_in_meta( $keyword, $data['meta_description'] ?? '' );
		$score += $meta_result['score'];
		if ( ! empty( $meta_result['issue'] ) ) {
			$issues[] = $meta_result['issue'];
		}

		// 3. Keyword in URL/slug (10 points)
		$slug_result = $this->check_keyword_in_slug( $keyword, $data['slug'] ?? '' );
		$score += $slug_result['score'];
		if ( ! empty( $slug_result['issue'] ) ) {
			$issues[] = $slug_result['issue'];
		}

		// 4. Keyword in first paragraph (15 points)
		$first_para_result = $this->check_keyword_in_first_paragraph( $keyword, $data['content'] ?? '' );
		$score += $first_para_result['score'];
		if ( ! empty( $first_para_result['issue'] ) ) {
			$issues[] = $first_para_result['issue'];
		}

		// 5. Keyword density (20 points)
		$density_result = $this->check_keyword_density( $keyword, $data['content'] ?? '' );
		$score += $density_result['score'];
		if ( ! empty( $density_result['issue'] ) ) {
			$issues[] = $density_result['issue'];
		}

		// 6. Keyword distribution (20 points)
		$distribution_result = $this->check_keyword_distribution( $keyword, $data['content'] ?? '' );
		$score += $distribution_result['score'];
		if ( ! empty( $distribution_result['issue'] ) ) {
			$issues[] = $distribution_result['issue'];
		}

		return array(
			'score'         => round( ( $score / $max_score ) * 100 ),
			'issues'        => $issues,
			'keyword'       => $keyword,
			'density'       => $density_result['density'] ?? 0,
			'occurrences'   => $density_result['count'] ?? 0,
		);
	}

	/**
	 * Check if keyword is in title.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $title Post title.
	 * @return array Score and issue.
	 */
	private function check_keyword_in_title( $keyword, $title ) {
		$title_lower = strtolower( $title );
		$position = strpos( $title_lower, $keyword );

		if ( false === $position ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'keyword_not_in_title',
					'severity' => 'error',
					'message'  => sprintf(
						/* translators: %s: focus keyword */
						__( 'Focus keyword "%s" not found in title.', 'geo-ai' ),
						$keyword
					),
				),
			);
		}

		// Bonus if keyword is at the beginning
		if ( $position < 10 ) {
			return array(
				'score' => 20,
				'issue' => array(
					'id'       => 'keyword_in_title_start',
					'severity' => 'good',
					'message'  => __( 'Focus keyword appears at the beginning of title. Great!', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'keyword_in_title',
				'severity' => 'good',
				'message'  => __( 'Focus keyword found in title.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check if keyword is in meta description.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $meta Meta description.
	 * @return array Score and issue.
	 */
	private function check_keyword_in_meta( $keyword, $meta ) {
		if ( empty( $meta ) ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'no_meta_description',
					'severity' => 'warning',
					'message'  => __( 'No meta description set.', 'geo-ai' ),
				),
			);
		}

		$meta_lower = strtolower( $meta );
		if ( false === strpos( $meta_lower, $keyword ) ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'keyword_not_in_meta',
					'severity' => 'warning',
					'message'  => __( 'Focus keyword not found in meta description.', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'keyword_in_meta',
				'severity' => 'good',
				'message'  => __( 'Focus keyword found in meta description.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check if keyword is in URL slug.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $slug Post slug.
	 * @return array Score and issue.
	 */
	private function check_keyword_in_slug( $keyword, $slug ) {
		$slug_lower = strtolower( $slug );
		$keyword_slug = sanitize_title( $keyword );

		if ( false === strpos( $slug_lower, $keyword_slug ) ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'keyword_not_in_slug',
					'severity' => 'warning',
					'message'  => __( 'Focus keyword not found in URL.', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 10,
			'issue' => array(
				'id'       => 'keyword_in_slug',
				'severity' => 'good',
				'message'  => __( 'Focus keyword found in URL.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check if keyword is in first paragraph.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $content Post content.
	 * @return array Score and issue.
	 */
	private function check_keyword_in_first_paragraph( $keyword, $content ) {
		$text = wp_strip_all_tags( $content );
		$paragraphs = preg_split( '/\n\n+/', $text );
		
		if ( empty( $paragraphs[0] ) ) {
			return array( 'score' => 0, 'issue' => array() );
		}

		$first_para = strtolower( $paragraphs[0] );
		if ( false === strpos( $first_para, $keyword ) ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'keyword_not_in_first_para',
					'severity' => 'warning',
					'message'  => __( 'Focus keyword not found in first paragraph.', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'keyword_in_first_para',
				'severity' => 'good',
				'message'  => __( 'Focus keyword found in first paragraph.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check keyword density.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $content Post content.
	 * @return array Score, issue, density, and count.
	 */
	private function check_keyword_density( $keyword, $content ) {
		$text = wp_strip_all_tags( $content );
		$text_lower = strtolower( $text );
		
		$word_count = str_word_count( $text );
		if ( $word_count === 0 ) {
			return array( 'score' => 0, 'density' => 0, 'count' => 0 );
		}

		$keyword_count = substr_count( $text_lower, $keyword );
		$density = ( $keyword_count / $word_count ) * 100;

		// Ideal density: 0.5% - 2.5%
		if ( $density < 0.3 ) {
			return array(
				'score'   => 5,
				'density' => $density,
				'count'   => $keyword_count,
				'issue'   => array(
					'id'       => 'keyword_density_low',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: density percentage */
						__( 'Keyword density is too low (%.2f%%). Aim for 0.5-2.5%%.', 'geo-ai' ),
						$density
					),
				),
			);
		}

		if ( $density > 3.0 ) {
			return array(
				'score'   => 5,
				'density' => $density,
				'count'   => $keyword_count,
				'issue'   => array(
					'id'       => 'keyword_density_high',
					'severity' => 'error',
					'message'  => sprintf(
						/* translators: %s: density percentage */
						__( 'Keyword density is too high (%.2f%%). This may be seen as keyword stuffing.', 'geo-ai' ),
						$density
					),
				),
			);
		}

		return array(
			'score'   => 20,
			'density' => $density,
			'count'   => $keyword_count,
			'issue'   => array(
				'id'       => 'keyword_density_good',
				'severity' => 'good',
				'message'  => sprintf(
					/* translators: %s: density percentage */
					__( 'Keyword density is optimal (%.2f%%).', 'geo-ai' ),
					$density
				),
			),
		);
	}

	/**
	 * Check keyword distribution throughout content.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $content Post content.
	 * @return array Score and issue.
	 */
	private function check_keyword_distribution( $keyword, $content ) {
		$text = wp_strip_all_tags( $content );
		$text_lower = strtolower( $text );
		
		$length = strlen( $text );
		if ( $length === 0 ) {
			return array( 'score' => 0 );
		}

		// Divide content into 3 sections
		$section_size = floor( $length / 3 );
		$sections = array(
			substr( $text_lower, 0, $section_size ),
			substr( $text_lower, $section_size, $section_size ),
			substr( $text_lower, $section_size * 2 ),
		);

		$sections_with_keyword = 0;
		foreach ( $sections as $section ) {
			if ( false !== strpos( $section, $keyword ) ) {
				$sections_with_keyword++;
			}
		}

		if ( $sections_with_keyword === 0 ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'keyword_not_distributed',
					'severity' => 'error',
					'message'  => __( 'Focus keyword not found in content.', 'geo-ai' ),
				),
			);
		}

		if ( $sections_with_keyword === 1 ) {
			return array(
				'score' => 7,
				'issue' => array(
					'id'       => 'keyword_poorly_distributed',
					'severity' => 'warning',
					'message'  => __( 'Focus keyword only appears in one section. Distribute it throughout the content.', 'geo-ai' ),
				),
			);
		}

		if ( $sections_with_keyword === 2 ) {
			return array(
				'score' => 15,
				'issue' => array(
					'id'       => 'keyword_fairly_distributed',
					'severity' => 'ok',
					'message'  => __( 'Focus keyword appears in 2 out of 3 sections.', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 20,
			'issue' => array(
				'id'       => 'keyword_well_distributed',
				'severity' => 'good',
				'message'  => __( 'Focus keyword is well distributed throughout the content.', 'geo-ai' ),
			),
		);
	}
}
