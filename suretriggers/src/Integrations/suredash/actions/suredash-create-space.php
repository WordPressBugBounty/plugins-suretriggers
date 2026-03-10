<?php
/**
 * SureDashCreateSpace.
 *
 * @category SureDashCreateSpace
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
 * SureDashCreateSpace
 *
 * @category SureDashCreateSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashCreateSpace extends AutomateAction {

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
	public $action = 'suredash_create_space';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Space', 'suretriggers' ),
			'action'   => 'suredash_create_space',
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
		if ( ! defined( 'SUREDASHBOARD_VER' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		if ( ! function_exists( 'sd_wp_insert_post' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash version does not support programmatic space creation.', 'suretriggers' ),
			];
		}

		$space_title = ! empty( $selected_options['space_title'] ) ? sanitize_text_field( $selected_options['space_title'] ) : '';
		$space_type  = ! empty( $selected_options['space_type'] ) ? sanitize_text_field( $selected_options['space_type'] ) : 'posts_discussion';
		$space_group = ! empty( $selected_options['space_group'] ) ? sanitize_text_field( $selected_options['space_group'] ) : '';

		if ( empty( $space_title ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Space title is required.', 'suretriggers' ),
			];
		}

		$allowed_types = [ 'single_post', 'posts_discussion', 'link', 'resource_library', 'course', 'collection', 'events' ];
		if ( ! in_array( $space_type, $allowed_types, true ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid space type. Allowed types: single_post, posts_discussion, link, resource_library, course, collection, events.', 'suretriggers' ),
			];
		}

		$allowed_statuses = [ 'publish', 'draft', 'private', 'pending' ];
		$post_status      = isset( $selected_options['space_status'] ) && in_array( $selected_options['space_status'], $allowed_statuses, true )
			? $selected_options['space_status']
			: 'publish';

		$taxonomy = defined( 'SUREDASHBOARD_TAXONOMY' ) ? SUREDASHBOARD_TAXONOMY : 'portal_group';
		$term_id  = 0;

		// Resolve or create the space group.
		if ( ! empty( $space_group ) ) {
			if ( is_numeric( $space_group ) ) {
				$term = get_term( absint( $space_group ), $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$term_id = $term->term_id;
				} else {
					return [
						'status'  => 'error',
						'message' => __( 'Space group not found with the provided ID.', 'suretriggers' ),
					];
				}
			} else {
				$term = term_exists( $space_group, $taxonomy );
				if ( is_array( $term ) ) {
					$term_id = (int) $term['term_id'];
				} else {
					// Create the space group.
					$new_term = wp_insert_term( $space_group, $taxonomy );
					if ( ! is_wp_error( $new_term ) ) {
						$term_id = (int) $new_term['term_id'];
					}
				}
			}
		} else {
			// Default to "Uncategorized" group. Note: on non-English sites SureDash may
			// name the default group differently; this will create a new term if not found.
			$term = term_exists( 'Uncategorized', $taxonomy );
			if ( is_array( $term ) ) {
				$term_id = (int) $term['term_id'];
			} else {
				$new_term = wp_insert_term( 'Uncategorized', $taxonomy );
				if ( ! is_wp_error( $new_term ) ) {
					$term_id = (int) $new_term['term_id'];
				}
			}
		}

		if ( 0 === $term_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to resolve or create the space group.', 'suretriggers' ),
			];
		}

		$post_type = defined( 'SUREDASHBOARD_POST_TYPE' ) ? SUREDASHBOARD_POST_TYPE : 'portal';

		$post_author = $user_id ? $user_id : get_current_user_id();
		if ( ! empty( $selected_options['space_author'] ) ) {
			$post_author = absint( $selected_options['space_author'] );
		}

		$post_attr = [
			'post_title'  => $space_title,
			'post_name'   => sanitize_title( $space_title ),
			'post_type'   => $post_type,
			'post_status' => $post_status,
			'post_author' => $post_author,
		];

		if ( ! empty( $selected_options['space_description'] ) ) {
			$post_attr['post_content'] = wp_kses_post( $selected_options['space_description'] );
		}

		$space_id = sd_wp_insert_post( $post_attr );

		if ( is_wp_error( $space_id ) || 0 === $space_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create the space.', 'suretriggers' ),
			];
		}

		// Assign the space to the group.
		$terms_result = wp_set_post_terms( $space_id, [ $term_id ], $taxonomy );
		if ( is_wp_error( $terms_result ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Space created but group assignment failed.', 'suretriggers' ),
			];
		}

		// Set the integration/space type meta.
		if ( function_exists( 'sd_update_post_meta' ) ) {
			sd_update_post_meta( $space_id, 'integration', $space_type );
		} else {
			update_post_meta( $space_id, 'integration', $space_type );
		}

		// For discussion spaces, create a forum category linked to the space.
		if ( 'posts_discussion' === $space_type ) {
			$feed_taxonomy = defined( 'SUREDASHBOARD_FEED_TAXONOMY' ) ? SUREDASHBOARD_FEED_TAXONOMY : 'community-forum';
			$forum_term    = term_exists( $space_title, $feed_taxonomy );

			if ( is_array( $forum_term ) ) {
				$feed_group_id = (int) $forum_term['term_id'];
			} else {
				$new_forum_term = wp_insert_term( $space_title, $feed_taxonomy );
				$feed_group_id  = ! is_wp_error( $new_forum_term ) ? (int) $new_forum_term['term_id'] : 0;
			}

			if ( $feed_group_id > 0 ) {
				if ( function_exists( 'sd_update_post_meta' ) ) {
					sd_update_post_meta( $space_id, 'feed_group_id', $feed_group_id );
				} else {
					update_post_meta( $space_id, 'feed_group_id', $feed_group_id );
				}
			}
		}

		// Set featured image if provided — validate URL to prevent SSRF.
		if ( ! empty( $selected_options['featured_image'] ) ) {
			$image_url = esc_url_raw( $selected_options['featured_image'] );
			$parsed    = wp_parse_url( $image_url );

			if ( ! empty( $image_url ) && isset( $parsed['scheme'] ) && in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$attachment_id = media_sideload_image( $image_url, $space_id, null, 'id' );

				if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
					set_post_thumbnail( $space_id, (int) $attachment_id );
				}
			}
		}

		// Set custom meta fields if provided.
		if ( ! empty( $selected_options['space_meta'] ) && is_array( $selected_options['space_meta'] ) ) {
			foreach ( $selected_options['space_meta'] as $meta ) {
				if ( isset( $meta['metaKey'] ) && isset( $meta['metaValue'] ) ) {
					$meta_key   = sanitize_key( $meta['metaKey'] );
					$meta_value = sanitize_text_field( $meta['metaValue'] );
					if ( function_exists( 'sd_update_post_meta' ) ) {
						sd_update_post_meta( $space_id, $meta_key, $meta_value );
					} else {
						update_post_meta( $space_id, $meta_key, $meta_value );
					}
				}
			}
		}

		$space_post = get_post( $space_id );

		if ( ! $space_post ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to retrieve created space.', 'suretriggers' ),
			];
		}

		$response_data = [
			'status'          => 'success',
			'message'         => __( 'Space created successfully.', 'suretriggers' ),
			'space_id'        => $space_post->ID,
			'space_title'     => $space_post->post_title,
			'space_type'      => $space_type,
			'space_status'    => $space_post->post_status,
			'space_author'    => $space_post->post_author,
			'space_date'      => $space_post->post_date,
			'space_permalink' => get_permalink( $space_id ),
			'space_group_id'  => $term_id,
		];

		$group_term = get_term( $term_id, $taxonomy );
		if ( $group_term && ! is_wp_error( $group_term ) ) {
			$response_data['space_group_name'] = $group_term->name;
		}

		$featured_image_url = get_the_post_thumbnail_url( $space_id, 'full' );
		if ( $featured_image_url ) {
			$response_data['featured_image_url'] = $featured_image_url;
		}

		return $response_data;
	}
}

SureDashCreateSpace::get_instance();
