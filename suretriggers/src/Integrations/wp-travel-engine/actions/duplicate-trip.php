<?php
/**
 * DuplicateTrip.
 * php version 5.6
 *
 * @category DuplicateTrip
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPTravelEngine\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * DuplicateTrip
 *
 * @category DuplicateTrip
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DuplicateTrip extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WPTravelEngine';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wpte_duplicate_trip';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Duplicate an Existing Trip', 'suretriggers' ),
			'action'   => $this->action,
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		if ( ! function_exists( 'wp_travel_engine_get_settings' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'WP Travel Engine plugin is not active.', 'suretriggers' ),
			];
		}

		$trip_id = isset( $selected_options['trip_id'] ) ? absint( $selected_options['trip_id'] ) : 0;

		if ( empty( $trip_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Trip ID is required.', 'suretriggers' ),
			];
		}

		$trip_post_type = defined( 'WP_TRAVEL_ENGINE_POST_TYPE' ) ? WP_TRAVEL_ENGINE_POST_TYPE : 'trip';
		$original_trip  = get_post( $trip_id );

		if ( empty( $original_trip ) || $trip_post_type !== $original_trip->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid trip ID. Trip not found.', 'suretriggers' ),
			];
		}

		$new_title  = isset( $selected_options['new_title'] ) ? sanitize_text_field( $selected_options['new_title'] ) : '';
		$new_status = ( isset( $selected_options['status'] ) && in_array( $selected_options['status'], [ 'publish', 'draft' ], true ) ) ? $selected_options['status'] : 'draft';

		if ( empty( $new_title ) ) {
			/* translators: %s: original trip title */
			$new_title = sprintf( __( '%s (Copy)', 'suretriggers' ), $original_trip->post_title );
		}

		$new_post_id = wp_insert_post(
			[
				'post_title'   => $new_title,
				'post_content' => $original_trip->post_content,
				'post_excerpt' => $original_trip->post_excerpt,
				'post_status'  => $new_status,
				'post_type'    => $trip_post_type,
				'post_author'  => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return [
				'status'  => 'error',
				'message' => $new_post_id->get_error_message(),
			];
		}

		if ( empty( $new_post_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to duplicate trip.', 'suretriggers' ),
			];
		}

		// Copy all post meta.
		$post_meta = get_post_meta( $trip_id );
		if ( is_array( $post_meta ) && ! empty( $post_meta ) ) {
			foreach ( $post_meta as $meta_key => $meta_values ) {
				if ( ! is_string( $meta_key ) || '_wp_old_slug' === $meta_key ) {
					continue;
				}
				if ( ! is_array( $meta_values ) ) {
					continue;
				}
				foreach ( $meta_values as $meta_value ) {
					if ( is_string( $meta_value ) ) {
						$meta_value = maybe_unserialize( $meta_value );
					}
					update_post_meta( $new_post_id, $meta_key, $meta_value );
				}
			}
		}

		// Copy taxonomies.
		$taxonomies = get_object_taxonomies( $trip_post_type );
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_object_terms( $trip_id, $taxonomy, [ 'fields' => 'ids' ] );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					wp_set_object_terms( $new_post_id, $terms, $taxonomy );
				}
			}
		}

		// Copy featured image.
		$thumbnail_id = get_post_thumbnail_id( $trip_id );
		if ( ! empty( $thumbnail_id ) ) {
			set_post_thumbnail( $new_post_id, $thumbnail_id );
		}

		return [
			'status'           => 'success',
			'message'          => sprintf(
				__( 'Trip duplicated successfully. New trip ID: %1$d (duplicated from ID: %2$d)', 'suretriggers' ),
				$new_post_id,
				$trip_id
			),
			'new_trip_id'      => $new_post_id,
			'original_trip_id' => $trip_id,
			'new_trip_title'   => $new_title,
			'new_trip_status'  => $new_status,
		];
	}
}

DuplicateTrip::get_instance();
