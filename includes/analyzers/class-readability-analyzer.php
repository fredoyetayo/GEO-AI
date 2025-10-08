<?php
/**
 * Readability Analysis
 *
 * @package GeoAI
 */

namespace GeoAI\Analyzers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyzes content readability using various metrics.
 */
class Readability_Analyzer {

	/**
	 * Analyze content readability.
	 *
	 * @param string $content Post content.
	 * @return array Analysis results.
	 */
	public function analyze( $content ) {
		$text = wp_strip_all_tags( $content );
		
		if ( empty( $text ) ) {
			return array(
				'score'  => 0,
				'issues' => array(
					array(
						'id'       => 'no_content',
						'severity' => 'error',
						'message'  => __( 'No content to analyze.', 'geo-ai' ),
					),
				),
			);
		}

		$issues = array();
		$score  = 0;
		$max_score = 100;

		// 1. Flesch Reading Ease (25 points)
		$flesch_result = $this->calculate_flesch_reading_ease( $text );
		$score += $flesch_result['score'];
		if ( ! empty( $flesch_result['issue'] ) ) {
			$issues[] = $flesch_result['issue'];
		}

		// 2. Sentence length (20 points)
		$sentence_result = $this->check_sentence_length( $text );
		$score += $sentence_result['score'];
		if ( ! empty( $sentence_result['issue'] ) ) {
			$issues[] = $sentence_result['issue'];
		}

		// 3. Paragraph length (15 points)
		$paragraph_result = $this->check_paragraph_length( $content );
		$score += $paragraph_result['score'];
		if ( ! empty( $paragraph_result['issue'] ) ) {
			$issues[] = $paragraph_result['issue'];
		}

		// 4. Subheading distribution (15 points)
		$subheading_result = $this->check_subheadings( $content );
		$score += $subheading_result['score'];
		if ( ! empty( $subheading_result['issue'] ) ) {
			$issues[] = $subheading_result['issue'];
		}

		// 5. Passive voice (15 points)
		$passive_result = $this->check_passive_voice( $text );
		$score += $passive_result['score'];
		if ( ! empty( $passive_result['issue'] ) ) {
			$issues[] = $passive_result['issue'];
		}

		// 6. Transition words (10 points)
		$transition_result = $this->check_transition_words( $text );
		$score += $transition_result['score'];
		if ( ! empty( $transition_result['issue'] ) ) {
			$issues[] = $transition_result['issue'];
		}

		return array(
			'score'              => round( ( $score / $max_score ) * 100 ),
			'issues'             => $issues,
			'flesch_score'       => $flesch_result['flesch_score'] ?? 0,
			'avg_sentence_length' => $sentence_result['avg_length'] ?? 0,
			'word_count'         => str_word_count( $text ),
		);
	}

	/**
	 * Calculate Flesch Reading Ease score.
	 *
	 * @param string $text Content text.
	 * @return array Score and issue.
	 */
	private function calculate_flesch_reading_ease( $text ) {
		$sentences = $this->count_sentences( $text );
		$words = str_word_count( $text );
		$syllables = $this->count_syllables( $text );

		if ( $sentences === 0 || $words === 0 ) {
			return array( 'score' => 0, 'flesch_score' => 0 );
		}

		// Flesch Reading Ease formula
		$flesch = 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words );
		$flesch = max( 0, min( 100, $flesch ) );

		// Score interpretation
		// 90-100: Very Easy (5th grade)
		// 80-89: Easy (6th grade)
		// 70-79: Fairly Easy (7th grade)
		// 60-69: Standard (8th-9th grade)
		// 50-59: Fairly Difficult (10th-12th grade)
		// 30-49: Difficult (College)
		// 0-29: Very Difficult (College graduate)

		if ( $flesch >= 60 ) {
			return array(
				'score'        => 25,
				'flesch_score' => $flesch,
				'issue'        => array(
					'id'       => 'flesch_good',
					'severity' => 'good',
					'message'  => sprintf(
						/* translators: %s: Flesch score */
						__( 'Readability is good (Flesch score: %.1f).', 'geo-ai' ),
						$flesch
					),
				),
			);
		}

		if ( $flesch >= 50 ) {
			return array(
				'score'        => 18,
				'flesch_score' => $flesch,
				'issue'        => array(
					'id'       => 'flesch_ok',
					'severity' => 'ok',
					'message'  => sprintf(
						/* translators: %s: Flesch score */
						__( 'Readability is acceptable (Flesch score: %.1f).', 'geo-ai' ),
						$flesch
					),
				),
			);
		}

		return array(
			'score'        => 10,
			'flesch_score' => $flesch,
			'issue'        => array(
				'id'       => 'flesch_difficult',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %s: Flesch score */
					__( 'Content is difficult to read (Flesch score: %.1f). Aim for 60+.', 'geo-ai' ),
					$flesch
				),
			),
		);
	}

	/**
	 * Check sentence length.
	 *
	 * @param string $text Content text.
	 * @return array Score and issue.
	 */
	private function check_sentence_length( $text ) {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$total_words = 0;
		$long_sentences = 0;

		foreach ( $sentences as $sentence ) {
			$words = str_word_count( trim( $sentence ) );
			$total_words += $words;
			if ( $words > 20 ) {
				$long_sentences++;
			}
		}

		$sentence_count = count( $sentences );
		if ( $sentence_count === 0 ) {
			return array( 'score' => 0, 'avg_length' => 0 );
		}

		$avg_length = $total_words / $sentence_count;
		$long_percentage = ( $long_sentences / $sentence_count ) * 100;

		if ( $long_percentage > 25 ) {
			return array(
				'score'      => 10,
				'avg_length' => $avg_length,
				'issue'      => array(
					'id'       => 'sentences_too_long',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage of long sentences */
						__( '%.0f%% of sentences are too long (>20 words). Try to keep them shorter.', 'geo-ai' ),
						$long_percentage
					),
				),
			);
		}

		return array(
			'score'      => 20,
			'avg_length' => $avg_length,
			'issue'      => array(
				'id'       => 'sentences_good',
				'severity' => 'good',
				'message'  => sprintf(
					/* translators: %s: average sentence length */
					__( 'Sentence length is good (avg: %.1f words).', 'geo-ai' ),
					$avg_length
				),
			),
		);
	}

	/**
	 * Check paragraph length.
	 *
	 * @param string $content HTML content.
	 * @return array Score and issue.
	 */
	private function check_paragraph_length( $content ) {
		// Extract paragraphs (look for <p> tags or double line breaks)
		$paragraphs = preg_split( '/<\/p>|<br\s*\/?>\s*<br\s*\/?>|\n\n+/i', $content );
		$long_paragraphs = 0;
		$total_paragraphs = 0;

		foreach ( $paragraphs as $para ) {
			$text = wp_strip_all_tags( $para );
			$words = str_word_count( $text );
			
			if ( $words > 10 ) {
				$total_paragraphs++;
				if ( $words > 150 ) {
					$long_paragraphs++;
				}
			}
		}

		if ( $total_paragraphs === 0 ) {
			return array( 'score' => 0 );
		}

		$long_percentage = ( $long_paragraphs / $total_paragraphs ) * 100;

		if ( $long_percentage > 30 ) {
			return array(
				'score' => 8,
				'issue' => array(
					'id'       => 'paragraphs_too_long',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage of long paragraphs */
						__( '%.0f%% of paragraphs are too long (>150 words). Break them up for better readability.', 'geo-ai' ),
						$long_percentage
					),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'paragraphs_good',
				'severity' => 'good',
				'message'  => __( 'Paragraph length is good.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check subheading distribution.
	 *
	 * @param string $content HTML content.
	 * @return array Score and issue.
	 */
	private function check_subheadings( $content ) {
		$text = wp_strip_all_tags( $content );
		$word_count = str_word_count( $text );

		// Count H2-H6 tags
		preg_match_all( '/<h[2-6][^>]*>.*?<\/h[2-6]>/is', $content, $matches );
		$subheading_count = count( $matches[0] );

		if ( $word_count < 300 ) {
			// Short content doesn't need many subheadings
			return array(
				'score' => 15,
				'issue' => array(
					'id'       => 'content_short',
					'severity' => 'ok',
					'message'  => __( 'Content is short, subheadings are optional.', 'geo-ai' ),
				),
			);
		}

		$words_per_subheading = $subheading_count > 0 ? $word_count / $subheading_count : $word_count;

		if ( $subheading_count === 0 ) {
			return array(
				'score' => 0,
				'issue' => array(
					'id'       => 'no_subheadings',
					'severity' => 'error',
					'message'  => __( 'No subheadings found. Add H2-H6 tags to improve structure.', 'geo-ai' ),
				),
			);
		}

		if ( $words_per_subheading > 300 ) {
			return array(
				'score' => 8,
				'issue' => array(
					'id'       => 'few_subheadings',
					'severity' => 'warning',
					'message'  => __( 'Add more subheadings. Aim for one every 250-300 words.', 'geo-ai' ),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'subheadings_good',
				'severity' => 'good',
				'message'  => sprintf(
					/* translators: %d: number of subheadings */
					__( 'Good use of subheadings (%d found).', 'geo-ai' ),
					$subheading_count
				),
			),
		);
	}

	/**
	 * Check passive voice usage.
	 *
	 * @param string $text Content text.
	 * @return array Score and issue.
	 */
	private function check_passive_voice( $text ) {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$passive_count = 0;

		// Common passive voice indicators
		$passive_indicators = array(
			'/\b(is|are|was|were|be|been|being)\s+\w+ed\b/i',
			'/\b(is|are|was|were|be|been|being)\s+\w+en\b/i',
		);

		foreach ( $sentences as $sentence ) {
			foreach ( $passive_indicators as $pattern ) {
				if ( preg_match( $pattern, $sentence ) ) {
					$passive_count++;
					break;
				}
			}
		}

		$sentence_count = count( $sentences );
		if ( $sentence_count === 0 ) {
			return array( 'score' => 0 );
		}

		$passive_percentage = ( $passive_count / $sentence_count ) * 100;

		if ( $passive_percentage > 20 ) {
			return array(
				'score' => 8,
				'issue' => array(
					'id'       => 'passive_voice_high',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage of passive voice */
						__( '%.0f%% of sentences use passive voice. Try to use active voice more.', 'geo-ai' ),
						$passive_percentage
					),
				),
			);
		}

		return array(
			'score' => 15,
			'issue' => array(
				'id'       => 'passive_voice_good',
				'severity' => 'good',
				'message'  => __( 'Good use of active voice.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Check transition words usage.
	 *
	 * @param string $text Content text.
	 * @return array Score and issue.
	 */
	private function check_transition_words( $text ) {
		$transition_words = array(
			'however', 'therefore', 'furthermore', 'moreover', 'consequently',
			'nevertheless', 'meanwhile', 'additionally', 'similarly', 'likewise',
			'in addition', 'for example', 'for instance', 'in fact', 'as a result',
			'on the other hand', 'in contrast', 'in conclusion', 'finally', 'first',
			'second', 'third', 'next', 'then', 'also', 'besides', 'indeed',
		);

		$text_lower = strtolower( $text );
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences_with_transitions = 0;

		foreach ( $sentences as $sentence ) {
			$sentence_lower = strtolower( $sentence );
			foreach ( $transition_words as $word ) {
				if ( strpos( $sentence_lower, $word ) !== false ) {
					$sentences_with_transitions++;
					break;
				}
			}
		}

		$sentence_count = count( $sentences );
		if ( $sentence_count === 0 ) {
			return array( 'score' => 0 );
		}

		$transition_percentage = ( $sentences_with_transitions / $sentence_count ) * 100;

		if ( $transition_percentage < 20 ) {
			return array(
				'score' => 5,
				'issue' => array(
					'id'       => 'few_transitions',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage of sentences with transitions */
						__( 'Only %.0f%% of sentences contain transition words. Aim for 30%% or more.', 'geo-ai' ),
						$transition_percentage
					),
				),
			);
		}

		return array(
			'score' => 10,
			'issue' => array(
				'id'       => 'transitions_good',
				'severity' => 'good',
				'message'  => __( 'Good use of transition words.', 'geo-ai' ),
			),
		);
	}

	/**
	 * Count sentences in text.
	 *
	 * @param string $text Content text.
	 * @return int Number of sentences.
	 */
	private function count_sentences( $text ) {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return count( $sentences );
	}

	/**
	 * Count syllables in text (approximation).
	 *
	 * @param string $text Content text.
	 * @return int Number of syllables.
	 */
	private function count_syllables( $text ) {
		$words = str_word_count( strtolower( $text ), 1 );
		$syllable_count = 0;

		foreach ( $words as $word ) {
			$syllable_count += $this->count_syllables_in_word( $word );
		}

		return $syllable_count;
	}

	/**
	 * Count syllables in a single word.
	 *
	 * @param string $word Single word.
	 * @return int Number of syllables.
	 */
	private function count_syllables_in_word( $word ) {
		$word = strtolower( trim( $word ) );
		$word = preg_replace( '/[^a-z]/', '', $word );

		if ( strlen( $word ) <= 3 ) {
			return 1;
		}

		$word = preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word );
		$word = preg_replace( '/^y/', '', $word );
		$matches = preg_match_all( '/[aeiouy]{1,2}/', $word );

		return max( 1, $matches );
	}
}
