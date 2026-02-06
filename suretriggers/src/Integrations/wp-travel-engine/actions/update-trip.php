<?php
/**
 * UpdateTrip.
 * php version 5.6
 *
 * @category UpdateTrip
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
 * UpdateTrip
 *
 * @category UpdateTrip
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateTrip extends AutomateAction {

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
	public $action = 'wpte_update_trip';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update an Existing Trip', 'suretriggers' ),
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
		$existing_trip  = get_post( $trip_id );

		if ( empty( $existing_trip ) || $trip_post_type !== $existing_trip->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid trip ID. Trip not found.', 'suretriggers' ),
			];
		}

		// Update post fields if provided.
		$post_update = [];

		if ( ! empty( $selected_options['trip_name'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $selected_options['trip_name'] );
		}

		if ( isset( $selected_options['trip_description'] ) && '' !== $selected_options['trip_description'] ) {
			$post_update['post_content'] = sanitize_textarea_field( $selected_options['trip_description'] );
		}

		if ( ! empty( $selected_options['status'] ) && in_array( $selected_options['status'], [ 'publish', 'draft' ], true ) ) {
			$post_update['post_status'] = $selected_options['status'];
		}

		if ( ! empty( $post_update ) ) {
			$post_update['ID'] = $trip_id;
			$result            = wp_update_post( $post_update, true );
			if ( is_wp_error( $result ) ) {
				return [
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
			}
		}

		// Update trip meta settings.
		$existing_settings = get_post_meta( $trip_id, 'wp_travel_engine_setting', true );
		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = [];
		}

		$meta_updated = false;

		if ( isset( $selected_options['trip_duration'] ) && '' !== $selected_options['trip_duration'] ) {
			$trip_duration                      = intval( $selected_options['trip_duration'] );
			$existing_settings['trip_duration'] = $trip_duration;
			update_post_meta( $trip_id, 'wp_travel_engine_setting_trip_duration', $trip_duration );

			$trip_duration_unit                      = isset( $selected_options['trip_duration_unit'] ) ? sanitize_text_field( $selected_options['trip_duration_unit'] ) : 'days';
			$existing_settings['trip_duration_unit'] = $trip_duration_unit;
			update_post_meta( $trip_id, '_s_duration', $trip_duration * ( 'days' === $trip_duration_unit ? 24 : 1 ) );
			$meta_updated = true;
		}

		if ( isset( $selected_options['trip_duration_night'] ) && '' !== $selected_options['trip_duration_night'] ) {
			$existing_settings['trip_duration_nights'] = intval( $selected_options['trip_duration_night'] );
			$meta_updated                              = true;
		}

		if ( isset( $selected_options['enable_cutoff_time'] ) && '' !== $selected_options['enable_cutoff_time'] ) {
			$existing_settings['trip_cutoff_enable'] = filter_var( $selected_options['enable_cutoff_time'], FILTER_VALIDATE_BOOLEAN );
			$meta_updated                            = true;
		}

		if ( isset( $selected_options['cutoff_time_value'] ) && '' !== $selected_options['cutoff_time_value'] ) {
			$existing_settings['trip_cut_off_time'] = intval( $selected_options['cutoff_time_value'] );
			$meta_updated                           = true;
		}

		if ( isset( $selected_options['cutoff_time_unit'] ) && '' !== $selected_options['cutoff_time_unit'] ) {
			$existing_settings['trip_cut_off_unit'] = sanitize_text_field( $selected_options['cutoff_time_unit'] );
			$meta_updated                           = true;
		}

		if ( isset( $selected_options['set_minimum_maximum_age'] ) && '' !== $selected_options['set_minimum_maximum_age'] ) {
			$existing_settings['min_max_age_enable'] = filter_var( $selected_options['set_minimum_maximum_age'], FILTER_VALIDATE_BOOLEAN );
			$meta_updated                            = true;
		}

		if ( isset( $selected_options['min_age'] ) && '' !== $selected_options['min_age'] ) {
			$min_age = intval( $selected_options['min_age'] );
			update_post_meta( $trip_id, 'wp_travel_engine_trip_min_age', $min_age );
			$meta_updated = true;
		}

		if ( isset( $selected_options['max_age'] ) && '' !== $selected_options['max_age'] ) {
			$max_age = intval( $selected_options['max_age'] );
			update_post_meta( $trip_id, 'wp_travel_engine_trip_max_age', $max_age );
			$meta_updated = true;
		}

		if ( isset( $selected_options['set_minimum_maximum_participants'] ) && '' !== $selected_options['set_minimum_maximum_participants'] ) {
			$existing_settings['minmax_pax_enable'] = filter_var( $selected_options['set_minimum_maximum_participants'], FILTER_VALIDATE_BOOLEAN );
			$meta_updated                           = true;
		}

		if ( isset( $selected_options['min_participants'] ) && '' !== $selected_options['min_participants'] ) {
			$existing_settings['trip_minimum_pax'] = intval( $selected_options['min_participants'] );
			$meta_updated                          = true;
		}

		if ( isset( $selected_options['max_participants'] ) && '' !== $selected_options['max_participants'] ) {
			$existing_settings['trip_maximum_pax'] = intval( $selected_options['max_participants'] );
			$meta_updated                          = true;
		}

		if ( isset( $selected_options['trip_highlights'] ) && '' !== $selected_options['trip_highlights'] ) {
			$trip_highlights                      = array_filter( array_map( 'sanitize_text_field', explode( "\n", $selected_options['trip_highlights'] ) ) );
			$existing_settings['trip_highlights'] = array_map(
				function( $h ) {
					return [ 'highlight_text' => $h ];
				},
				$trip_highlights
			);
			$meta_updated                         = true;
		}

		if ( isset( $selected_options['trip_includes'] ) && '' !== $selected_options['trip_includes'] ) {
			if ( ! isset( $existing_settings['cost'] ) || ! is_array( $existing_settings['cost'] ) ) {
				$existing_settings['cost'] = [];
			}
			$existing_settings['cost']['cost_includes'] = sanitize_textarea_field( $selected_options['trip_includes'] );
			$meta_updated                               = true;
		}

		if ( isset( $selected_options['trip_excludes'] ) && '' !== $selected_options['trip_excludes'] ) {
			if ( ! isset( $existing_settings['cost'] ) || ! is_array( $existing_settings['cost'] ) ) {
				$existing_settings['cost'] = [];
			}
			$existing_settings['cost']['cost_excludes'] = sanitize_textarea_field( $selected_options['trip_excludes'] );
			$meta_updated                               = true;
		}

		// Update trip code.
		if ( isset( $selected_options['trip_code'] ) && '' !== $selected_options['trip_code'] ) {
			$existing_settings['trip_code'] = sanitize_text_field( $selected_options['trip_code'] );
			$meta_updated                   = true;
		}

		// Update total travellers seats.
		if ( isset( $selected_options['total_seats'] ) && '' !== $selected_options['total_seats'] ) {
			$existing_settings['trip_maximum_pax']  = intval( $selected_options['total_seats'] );
			$existing_settings['minmax_pax_enable'] = true;
			$meta_updated                           = true;
		}

		// Update trip expiry date.
		if ( isset( $selected_options['trip_expiry_date'] ) && '' !== $selected_options['trip_expiry_date'] ) {
			$existing_settings['trip_expiry_date'] = sanitize_text_field( $selected_options['trip_expiry_date'] );
			$meta_updated                          = true;
		}

		// Update itinerary.
		if ( isset( $selected_options['itinerary'] ) && '' !== $selected_options['itinerary'] ) {
			$itinerary_input = $selected_options['itinerary'];

			// Handle both array and JSON string input.
			if ( is_array( $itinerary_input ) ) {
				$itinerary_data = $itinerary_input;
			} else {
				$itinerary_data = json_decode( $itinerary_input, true );

				// If json_decode fails, try with stripslashes (handles escaped quotes like \").
				if ( null === $itinerary_data ) {
					$itinerary_data = json_decode( stripslashes( $itinerary_input ), true );
				}
			}

			if ( is_array( $itinerary_data ) && ! empty( $itinerary_data ) ) {
				$itinerary_titles  = [];
				$itinerary_content = [];

				foreach ( $itinerary_data as $index => $item ) {
					$itinerary_titles[ $index ]  = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
					$itinerary_content[ $index ] = isset( $item['content'] ) ? sanitize_textarea_field( $item['content'] ) : '';
				}

				$existing_settings['itinerary'] = [
					'itinerary_title'   => $itinerary_titles,
					'itinerary_content' => $itinerary_content,
				];
				$meta_updated                   = true;
			}
		}

		if ( $meta_updated ) {
			update_post_meta( $trip_id, 'wp_travel_engine_setting', $existing_settings );
		}

		// Update destination taxonomy.
		if ( ! empty( $selected_options['destination'] ) ) {
			$destination = sanitize_text_field( $selected_options['destination'] );
			$term        = term_exists( $destination, 'destination' );
			if ( ! $term ) {
				$term = wp_insert_term( $destination, 'destination' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) && isset( $term['term_id'] ) ? intval( $term['term_id'] ) : 0;
				if ( $term_id ) {
					wp_set_object_terms( $trip_id, $term_id, 'destination' );
				}
			}
		}

		// Update activities taxonomy.
		if ( ! empty( $selected_options['activities'] ) ) {
			$activities = array_filter( array_map( 'sanitize_text_field', explode( ',', $selected_options['activities'] ) ) );
			$term_ids   = [];
			foreach ( $activities as $activity ) {
				$term = term_exists( trim( $activity ), 'activities' );
				if ( ! $term ) {
					$term = wp_insert_term( trim( $activity ), 'activities' );
				}
				if ( ! is_wp_error( $term ) && is_array( $term ) && isset( $term['term_id'] ) ) {
					$term_ids[] = intval( $term['term_id'] );
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $trip_id, $term_ids, 'activities' );
			}
		}

		// Update trip tags taxonomy.
		if ( ! empty( $selected_options['trip_tag'] ) ) {
			$tags    = array_filter( array_map( 'sanitize_text_field', explode( ',', $selected_options['trip_tag'] ) ) );
			$tag_ids = [];
			foreach ( $tags as $tag ) {
				$term = term_exists( trim( $tag ), 'trip_tag' );
				if ( ! $term ) {
					$term = wp_insert_term( trim( $tag ), 'trip_tag' );
				}
				if ( ! is_wp_error( $term ) && is_array( $term ) && isset( $term['term_id'] ) ) {
					$tag_ids[] = intval( $term['term_id'] );
				}
			}
			if ( ! empty( $tag_ids ) ) {
				wp_set_object_terms( $trip_id, $tag_ids, 'trip_tag' );
			}
		}

		// Update featured image.
		if ( ! empty( $selected_options['featured_image_url'] ) ) {
			$featured_image_url = esc_url_raw( $selected_options['featured_image_url'] );
			$this->set_featured_image_from_url( $trip_id, $featured_image_url );
		}

		$updated_trip = get_post( $trip_id );

		return [
			'status'      => 'success',
			'message'     => sprintf(
				/* translators: %d: trip ID */
				__( 'Trip updated successfully. Trip ID: %d', 'suretriggers' ),
				$trip_id
			),
			'trip_id'     => $trip_id,
			'trip_title'  => $updated_trip instanceof \WP_Post ? $updated_trip->post_title : '',
			'trip_status' => $updated_trip instanceof \WP_Post ? $updated_trip->post_status : '',
		];
	}

	/**
	 * Download image from URL and set as featured image.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $image_url Image URL.
	 * @return void
	 */
	private function set_featured_image_from_url( $post_id, $image_url ) {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		$abspath = realpath( ABSPATH );
		if ( false === $abspath ) {
			return;
		}

		require_once $abspath . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'file.php';
		require_once $abspath . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'media.php';
		require_once $abspath . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'image.php';

		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return;
		}

		preg_match( '/[^\?]+\.(jpg|jpeg|png|gif)/i', $image_url, $matches );
		$file_array = [
			'name'     => ! empty( $matches ) ? basename( $matches[0] ) : 'featured-image.jpg',
			'tmp_name' => $tmp,
		];

		$attach_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attach_id ) ) {
			$tmp_file    = $file_array['tmp_name'];
			$uploads_dir = wp_upload_dir();

			$tmp_file_realpath     = realpath( $tmp_file );
			$uploads_base_realpath = realpath( $uploads_dir['basedir'] );

			if (
				file_exists( $tmp_file ) &&
				false !== $tmp_file_realpath &&
				false !== $uploads_base_realpath &&
				0 === strpos( $tmp_file_realpath, $uploads_base_realpath )
			) {
				wp_delete_file( $tmp_file );
			}
			return;
		}

		set_post_thumbnail( $post_id, $attach_id );
	}
}

UpdateTrip::get_instance();
