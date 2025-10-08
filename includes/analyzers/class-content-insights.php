<?php
/**
 * Content Insights Analyzer
 *
 * @package GeoAI
 */

namespace GeoAI\Analyzers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyzes content for word frequency and insights.
 */
class Content_Insights {

	/**
	 * Analyze content and generate insights.
	 *
	 * @param string $content Post content.
	 * @param string $title Post title.
	 * @return array Insights data.
	 */
	public function analyze( $content, $title = '' ) {
		$text = wp_strip_all_tags( $content );
		
		return array(
			'word_frequency'   => $this->get_word_frequency( $text ),
			'phrase_frequency' => $this->get_phrase_frequency( $text ),
			'content_metrics'  => $this->get_content_metrics( $text, $title ),
			'prominent_words'  => $this->get_prominent_words( $text, $title ),
			'recommendations'  => $this->get_recommendations( $text, $title ),
		);
	}

	/**
	 * Get word frequency analysis.
	 *
	 * @param string $text Content text.
	 * @return array Top words with counts.
	 */
	private function get_word_frequency( $text ) {
		$text = strtolower( $text );
		
		// Extract words (3+ characters)
		preg_match_all( '/\b[a-z]{3,}\b/', $text, $matches );
		$words = $matches[0];

		// Remove stop words
		$stop_words = $this->get_stop_words();
		$words = array_diff( $words, $stop_words );

		// Count frequency
		$word_freq = array_count_values( $words );
		arsort( $word_freq );

		// Get top 30
		$top_words = array_slice( $word_freq, 0, 30, true );

		// Calculate percentages
		$total_words = count( $words );
		$result = array();
		foreach ( $top_words as $word => $count ) {
			$result[] = array(
				'word'       => $word,
				'count'      => $count,
				'percentage' => round( ( $count / $total_words ) * 100, 2 ),
			);
		}

		return $result;
	}

	/**
	 * Get phrase frequency (2-3 word phrases).
	 *
	 * @param string $text Content text.
	 * @return array Top phrases with counts.
	 */
	private function get_phrase_frequency( $text ) {
		$text = strtolower( $text );
		
		// Extract words
		preg_match_all( '/\b[a-z]{3,}\b/', $text, $matches );
		$words = $matches[0];

		// Remove stop words
		$stop_words = $this->get_stop_words();
		
		$phrases = array();

		// Extract 2-word phrases
		for ( $i = 0; $i < count( $words ) - 1; $i++ ) {
			if ( ! in_array( $words[ $i ], $stop_words ) || ! in_array( $words[ $i + 1 ], $stop_words ) ) {
				$phrase = $words[ $i ] . ' ' . $words[ $i + 1 ];
				if ( ! isset( $phrases[ $phrase ] ) ) {
					$phrases[ $phrase ] = 0;
				}
				$phrases[ $phrase ]++;
			}
		}

		// Extract 3-word phrases
		for ( $i = 0; $i < count( $words ) - 2; $i++ ) {
			$phrase = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
			if ( ! isset( $phrases[ $phrase ] ) ) {
				$phrases[ $phrase ] = 0;
			}
			$phrases[ $phrase ]++;
		}

		// Sort by frequency
		arsort( $phrases );

		// Get top 15 phrases that appear at least twice
		$result = array();
		foreach ( $phrases as $phrase => $count ) {
			if ( $count >= 2 && count( $result ) < 15 ) {
				$result[] = array(
					'phrase' => $phrase,
					'count'  => $count,
				);
			}
		}

		return $result;
	}

	/**
	 * Get content metrics.
	 *
	 * @param string $text Content text.
	 * @param string $title Post title.
	 * @return array Metrics.
	 */
	private function get_content_metrics( $text, $title ) {
		$words = str_word_count( $text );
		$sentences = $this->count_sentences( $text );
		$paragraphs = substr_count( $text, "\n\n" ) + 1;
		$characters = strlen( $text );

		// Calculate reading time (average 200 words per minute)
		$reading_time = ceil( $words / 200 );

		// Calculate speaking time (average 150 words per minute)
		$speaking_time = ceil( $words / 150 );

		// Unique words
		preg_match_all( '/\b[a-z]{3,}\b/i', strtolower( $text ), $matches );
		$all_words = $matches[0];
		$unique_words = count( array_unique( $all_words ) );

		// Lexical diversity (unique words / total words)
		$lexical_diversity = $words > 0 ? round( ( $unique_words / $words ) * 100, 1 ) : 0;

		return array(
			'word_count'        => $words,
			'sentence_count'    => $sentences,
			'paragraph_count'   => $paragraphs,
			'character_count'   => $characters,
			'unique_words'      => $unique_words,
			'lexical_diversity' => $lexical_diversity,
			'reading_time'      => $reading_time,
			'speaking_time'     => $speaking_time,
			'avg_words_per_sentence' => $sentences > 0 ? round( $words / $sentences, 1 ) : 0,
			'avg_words_per_paragraph' => $paragraphs > 0 ? round( $words / $paragraphs, 1 ) : 0,
		);
	}

	/**
	 * Get prominent words (excluding stop words, sorted by frequency).
	 *
	 * @param string $text Content text.
	 * @param string $title Post title.
	 * @return array Prominent words.
	 */
	private function get_prominent_words( $text, $title ) {
		$combined = $title . ' ' . $text;
		$combined = strtolower( $combined );
		
		preg_match_all( '/\b[a-z]{4,}\b/', $combined, $matches );
		$words = $matches[0];

		$stop_words = $this->get_stop_words();
		$words = array_diff( $words, $stop_words );

		$word_freq = array_count_values( $words );
		arsort( $word_freq );

		// Get top 10
		$top_words = array_slice( array_keys( $word_freq ), 0, 10 );

		return $top_words;
	}

	/**
	 * Get content recommendations.
	 *
	 * @param string $text Content text.
	 * @param string $title Post title.
	 * @return array Recommendations.
	 */
	private function get_recommendations( $text, $title ) {
		$recommendations = array();
		$metrics = $this->get_content_metrics( $text, $title );

		// Word count recommendations
		if ( $metrics['word_count'] < 300 ) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %d: current word count */
					__( 'Content is short (%d words). Aim for at least 300 words for better SEO.', 'geo-ai' ),
					$metrics['word_count']
				),
			);
		} elseif ( $metrics['word_count'] >= 1500 ) {
			$recommendations[] = array(
				'type'    => 'success',
				'message' => __( 'Excellent! Long-form content (1500+ words) tends to rank better.', 'geo-ai' ),
			);
		}

		// Lexical diversity
		if ( $metrics['lexical_diversity'] < 40 ) {
			$recommendations[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s: lexical diversity percentage */
					__( 'Low vocabulary diversity (%s%%). Try using more varied words.', 'geo-ai' ),
					$metrics['lexical_diversity']
				),
			);
		}

		// Sentence length
		if ( $metrics['avg_words_per_sentence'] > 25 ) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => __( 'Sentences are quite long. Break them up for better readability.', 'geo-ai' ),
			);
		}

		// Paragraph length
		if ( $metrics['avg_words_per_paragraph'] > 150 ) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => __( 'Paragraphs are too long. Aim for 100-150 words per paragraph.', 'geo-ai' ),
			);
		}

		// Reading time
		if ( $metrics['reading_time'] > 10 ) {
			$recommendations[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %d: reading time in minutes */
					__( 'Long read (%d min). Consider adding a table of contents.', 'geo-ai' ),
					$metrics['reading_time']
				),
			);
		}

		return $recommendations;
	}

	/**
	 * Count sentences in text.
	 *
	 * @param string $text Content text.
	 * @return int Sentence count.
	 */
	private function count_sentences( $text ) {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return count( $sentences );
	}

	/**
	 * Get list of stop words.
	 *
	 * @return array Stop words.
	 */
	private function get_stop_words() {
		return array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
			'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
			'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this',
			'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
			'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each',
			'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
			'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
			'just', 'about', 'into', 'through', 'during', 'before', 'after',
			'above', 'below', 'between', 'under', 'again', 'further', 'then',
			'once', 'here', 'there', 'all', 'any', 'both', 'each', 'more', 'most',
			'other', 'some', 'such', 'only', 'own', 'same', 'than', 'too', 'very',
		);
	}
}
