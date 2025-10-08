<?php
/**
 * Primary Category Selector
 *
 * @package GeoAI
 */

namespace GeoAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles primary category selection for posts.
 */
class Primary_Category {

	/**
	 * Instance of this class.
	 *
	 * @var Primary_Category
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Primary_Category
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
		add_action( 'add_meta_boxes', array( $this, 'add_primary_category_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_primary_category' ), 10, 2 );
		add_filter( 'post_link_category', array( $this, 'filter_post_link_category' ), 10, 3 );
		add_filter( 'get_the_archive_title', array( $this, 'filter_archive_title' ), 10, 1 );
	}

	/**
	 * Add primary category meta box.
	 */
	public function add_primary_category_meta_box() {
		$post_types = get_post_types_by_support( 'category' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'geoai_primary_category',
				__( 'Primary Category', 'geo-ai' ),
				array( $this, 'render_primary_category_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render primary category meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_primary_category_meta_box( $post ) {
		wp_nonce_field( 'geoai_primary_category', 'geoai_primary_category_nonce' );
		
		$primary_category = get_post_meta( $post->ID, '_geoai_primary_category', true );
		$categories = get_the_category( $post->ID );
		
		if ( empty( $categories ) ) {
			?>
			<p class="description">
				<?php esc_html_e( 'Please assign at least one category to this post first.', 'geo-ai' ); ?>
			</p>
			<?php
			return;
		}
		?>
		<div class="geoai-primary-category-selector">
			<p class="description" style="margin-bottom: 10px;">
				<?php esc_html_e( 'Select the main category for this post. This will be used in breadcrumbs and permalinks.', 'geo-ai' ); ?>
			</p>
			
			<select name="geoai_primary_category" id="geoai_primary_category" class="widefat">
				<option value=""><?php esc_html_e( '— Select Primary Category —', 'geo-ai' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $primary_category, $category->term_id ); ?>>
						<?php echo esc_html( $category->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! empty( $primary_category ) ) : ?>
			<div class="geoai-primary-badge" style="margin-top: 10px; padding: 8px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 3px;">
				<strong><?php esc_html_e( 'Current Primary:', 'geo-ai' ); ?></strong>
				<?php
				$cat = get_category( $primary_category );
				if ( $cat ) {
					echo esc_html( $cat->name );
				}
				?>
			</div>
			<?php endif; ?>

			<p class="description" style="margin-top: 10px;">
				<strong><?php esc_html_e( 'Benefits:', 'geo-ai' ); ?></strong><br>
				• <?php esc_html_e( 'Better URL structure', 'geo-ai' ); ?><br>
				• <?php esc_html_e( 'Clearer breadcrumb navigation', 'geo-ai' ); ?><br>
				• <?php esc_html_e( 'Improved content organization', 'geo-ai' ); ?>
			</p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Update primary category when categories change
			$('#categorychecklist input[type="checkbox"]').on('change', function() {
				var primarySelect = $('#geoai_primary_category');
				var currentPrimary = primarySelect.val();
				var checkedCategories = $('#categorychecklist input[type="checkbox"]:checked');
				
				// Rebuild options
				primarySelect.find('option:not(:first)').remove();
				
				checkedCategories.each(function() {
					var catId = $(this).val();
					var catName = $(this).parent().text().trim();
					var option = $('<option></option>')
						.attr('value', catId)
						.text(catName);
					
					if (catId == currentPrimary) {
						option.attr('selected', 'selected');
					}
					
					primarySelect.append(option);
				});

				// If current primary is no longer in categories, clear it
				if (checkedCategories.length > 0 && !primarySelect.find('option[value="' + currentPrimary + '"]').length) {
					primarySelect.val('');
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Save primary category.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function save_primary_category( $post_id, $post ) {
		// Check nonce
		if ( ! isset( $_POST['geoai_primary_category_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['geoai_primary_category_nonce'], 'geoai_primary_category' ) ) {
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

		// Save primary category
		if ( isset( $_POST['geoai_primary_category'] ) ) {
			$primary_category = intval( $_POST['geoai_primary_category'] );
			
			// Verify the category is assigned to the post
			$categories = wp_get_post_categories( $post_id );
			
			if ( $primary_category && in_array( $primary_category, $categories ) ) {
				update_post_meta( $post_id, '_geoai_primary_category', $primary_category );
			} else {
				delete_post_meta( $post_id, '_geoai_primary_category' );
			}
		}
	}

	/**
	 * Get primary category for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Term|null Primary category or null.
	 */
	public function get_primary_category( $post_id ) {
		$primary_id = get_post_meta( $post_id, '_geoai_primary_category', true );
		
		if ( $primary_id ) {
			$category = get_category( $primary_id );
			if ( $category && ! is_wp_error( $category ) ) {
				return $category;
			}
		}

		// Fallback to first category
		$categories = get_the_category( $post_id );
		return ! empty( $categories ) ? $categories[0] : null;
	}

	/**
	 * Filter post link category to use primary category.
	 *
	 * @param \WP_Term $cat Category object.
	 * @param array    $cats Array of categories.
	 * @param \WP_Post $post Post object.
	 * @return \WP_Term Modified category.
	 */
	public function filter_post_link_category( $cat, $cats, $post ) {
		$primary = $this->get_primary_category( $post->ID );
		return $primary ? $primary : $cat;
	}

	/**
	 * Filter archive title to show primary category.
	 *
	 * @param string $title Archive title.
	 * @return string Modified title.
	 */
	public function filter_archive_title( $title ) {
		if ( is_single() ) {
			$post_id = get_the_ID();
			$primary = $this->get_primary_category( $post_id );
			
			if ( $primary ) {
				return $primary->name;
			}
		}
		
		return $title;
	}
}
