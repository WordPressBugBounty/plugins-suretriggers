<?php
/**
 * SureDashFetchPost.
 * php version 5.6
 *
 * @category SureDashFetchPost
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDash\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * SureDashFetchPost
 *
 * @category SureDashFetchPost
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashFetchPost extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDash';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'suredash_fetch_post';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Fetch Post', 'suretriggers' ),
			'action'   => 'suredash_fetch_post',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|bool
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDASHBOARD_FEED_POST_TYPE' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		$post_id = ! empty( $selected_options['post_id'] ) ? absint( $selected_options['post_id'] ) : 0;

		if ( empty( $post_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Post ID is required.', 'suretriggers' ),
			];
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return [
				'status'  => 'error',
				'message' => __( 'Post not found with the provided ID.', 'suretriggers' ),
			];
		}

		$response = [
			'status'         => 'success',
			'message'        => __( 'Post fetched successfully.', 'suretriggers' ),
			'post_id'        => $post->ID,
			'post_title'     => $post->post_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post->post_status,
			'post_author'    => $post->post_author,
			'post_date'      => $post->post_date,
			'post_modified'  => $post->post_modified,
			'post_type'      => $post->post_type,
			'post_permalink' => get_permalink( $post_id ),
		];

		// Include author details.
		$author = get_user_by( 'id', $post->post_author );
		if ( $author ) {
			$response['author_display_name'] = $author->display_name;
			$response['author_email']        = $author->user_email;
		}

		// Include post meta.
		if ( function_exists( 'sd_get_post_meta' ) ) {
			$post_meta = sd_get_post_meta( $post_id );
		} else {
			$post_meta = get_post_meta( $post_id );
		}

		if ( is_array( $post_meta ) ) {
			$response['post_metas'] = $post_meta;
		}

		// Include taxonomies.
		$current_taxonomies = get_object_taxonomies( $post, 'objects' );
		$taxonomies         = [];
		foreach ( $current_taxonomies as $tax_title => $tax ) {
			$terms = get_the_terms( $post, $tax_title );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$taxonomies[ $tax_title ][] = [
						'name'    => $term->name,
						'slug'    => $term->slug,
						'term_id' => $term->term_id,
					];
				}
			}
		}

		if ( ! empty( $taxonomies ) ) {
			$response['taxonomies'] = $taxonomies;
		}

		// Include featured image.
		$featured_image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $featured_image_url ) {
			$response['featured_image_url'] = $featured_image_url;
			$response['featured_image_id']  = get_post_thumbnail_id( $post_id );
		}

		// Include comment count.
		$response['comment_count'] = (int) $post->comment_count;

		return $response;
	}
}

SureDashFetchPost::get_instance();
