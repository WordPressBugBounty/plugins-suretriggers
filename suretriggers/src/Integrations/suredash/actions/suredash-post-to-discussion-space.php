<?php
/**
 * SureDashPostToDiscussionSpace.
 * php version 5.6
 *
 * @category SureDashPostToDiscussionSpace
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
 * SureDashPostToDiscussionSpace
 *
 * @category SureDashPostToDiscussionSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashPostToDiscussionSpace extends AutomateAction {

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
	public $action = 'suredash_post_to_discussion_space';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Post in Specific Space', 'suretriggers' ),
			'action'   => 'suredash_post_to_discussion_space',
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
	 * @return array|bool|object
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDASHBOARD_FEED_POST_TYPE' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		$post_data = [];

		if ( ! empty( $selected_options['post_title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $selected_options['post_title'] );
		}

		if ( ! empty( $selected_options['post_content'] ) ) {
			$html_content              = $selected_options['post_content'];
			$patterns                  = [
				'/<head\b[^>]*>.*?<\/head>/is',
				'/<script\b[^>]*>.*?<\/script>/is',
				'/<style\b[^>]*>.*?<\/style>/is',
			];
			$post_data['post_content'] = preg_replace( $patterns, '', $html_content );
		}

		if ( ! empty( $selected_options['post_excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $selected_options['post_excerpt'] );
		}

		$meta_array = [];
		if ( ! empty( $selected_options['post_meta'] ) && is_array( $selected_options['post_meta'] ) ) {
			foreach ( $selected_options['post_meta'] as $meta ) {
				if ( isset( $meta['metaKey'] ) && isset( $meta['metaValue'] ) ) {
					$meta_key                = sanitize_key( $meta['metaKey'] );
					$meta_value              = sanitize_text_field( $meta['metaValue'] );
					$meta_array[ $meta_key ] = $meta_value;
				}
			}
			if ( ! empty( $meta_array ) ) {
				$post_data['meta_input'] = $meta_array;
			}
		}

		$post_data['post_type']   = SUREDASHBOARD_FEED_POST_TYPE;
		$post_data['post_status'] = ! empty( $selected_options['post_status'] ) ? $selected_options['post_status'] : 'publish';

		if ( ! empty( $selected_options['post_author'] ) ) {
			$post_data['post_author'] = absint( $selected_options['post_author'] );
		} elseif ( $user_id ) {
			$post_data['post_author'] = $user_id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}

		if ( empty( $post_data['post_title'] ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Post title is required.', 'suretriggers' ),
			];
		}

		/**
		 * Post ID.
		 *
		 * @var int|\WP_Error $post_id
		 */
		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			$this->set_error(
				[
					'post_data' => $post_data,
					'msg'       => __( 'Failed to create discussion post!', 'suretriggers' ),
				]
			);
			return false;
		}

		if ( ! empty( $selected_options['space_id'] ) ) {
			$space_id   = absint( $selected_options['space_id'] );
			$space_post = get_post( $space_id );
			
			if ( $space_post ) {
				$taxonomy = defined( 'SUREDASHBOARD_FEED_TAXONOMY' ) ? SUREDASHBOARD_FEED_TAXONOMY : 'community-forum';
				
				if ( taxonomy_exists( $taxonomy ) ) {
					$terms = get_terms(
						[
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
						] 
					);

					if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
						$matching_term = null;
						
						foreach ( $terms as $term ) {
							if ( $space_post->post_title === $term->name || 
								sanitize_title( $space_post->post_title ) === $term->slug ||
								false !== strpos( $term->name, $space_post->post_title ) ||
								false !== strpos( $space_post->post_title, str_replace( 'Forum: ', '', $term->name ) )
							) {
								$matching_term = $term;
								break;
							}
						}
						
						if ( $matching_term ) {
							wp_set_object_terms( $post_id, [ $matching_term->term_id ], $taxonomy );
						}
					}
				}
			}
		}

		if ( ! empty( $selected_options['featured_image'] ) ) {
			$image_url = $selected_options['featured_image'];
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				set_post_thumbnail( $post_id, (int) $attachment_id );
			}
		}

		$post_response = get_post( $post_id );

		if ( ! $post_response ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to retrieve created post.', 'suretriggers' ),
			];
		}

		$response_data = [
			'post_id'        => $post_response->ID,
			'post_title'     => $post_response->post_title,
			'post_content'   => $post_response->post_content,
			'post_excerpt'   => $post_response->post_excerpt,
			'post_status'    => $post_response->post_status,
			'post_author'    => $post_response->post_author,
			'post_date'      => $post_response->post_date,
			'post_modified'  => $post_response->post_modified,
			'post_type'      => $post_response->post_type,
			'post_permalink' => get_permalink( $post_id ),
		];

		if ( ! empty( $selected_options['space_id'] ) ) {
			$response_data['space_id'] = $selected_options['space_id'];
		}

		$featured_image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $featured_image_url ) {
			$response_data['featured_image_url'] = $featured_image_url;
		}

		$response_data['status']  = 'success';
		$response_data['message'] = __( 'Discussion post created successfully.', 'suretriggers' );

		return $response_data;
	}
}

SureDashPostToDiscussionSpace::get_instance();
