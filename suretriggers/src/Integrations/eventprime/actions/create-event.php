<?php
/**
 * CreateEvent.
 * php version 5.6
 *
 * @category CreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventPrime\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\EventPrime\EventPrime;
use SureTriggers\Traits\SingletonLoader;

/**
 * CreateEvent
 *
 * @category CreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateEvent extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'EventPrime';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'ep_create_event';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create an Event', 'suretriggers' ),
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
	 *
	 * @return array|mixed
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		$event_title = isset( $selected_options['event_title'] ) ? sanitize_text_field( $selected_options['event_title'] ) : '';

		if ( empty( $event_title ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Event title is required.', 'suretriggers' ),
			];
		}

		$start_date_input = isset( $selected_options['event_start_date'] ) ? sanitize_text_field( $selected_options['event_start_date'] ) : '';

		if ( empty( $start_date_input ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Start date is required.', 'suretriggers' ),
			];
		}

		// Determine post status.
		$post_status      = 'publish';
		$allowed_statuses = [ 'publish', 'draft', 'pending', 'private' ];
		if ( ! empty( $selected_options['event_status'] ) ) {
			$status = sanitize_text_field( $selected_options['event_status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$post_status = $status;
			}
		}

		// Create the event post.
		$event_args = [
			'post_title'   => $event_title,
			'post_type'    => 'em_event',
			'post_status'  => $post_status,
			'post_content' => '',
		];

		if ( ! empty( $selected_options['event_description'] ) ) {
			$event_args['post_content'] = wp_kses_post( $selected_options['event_description'] );
		}

		$event_id = wp_insert_post( $event_args, true );

		if ( is_wp_error( $event_id ) ) {
			return [
				'status'  => 'error',
				'message' => $event_id->get_error_message(),
			];
		}

		// Core meta.
		update_post_meta( $event_id, 'em_id', $event_id );
		update_post_meta( $event_id, 'em_name', $event_title );

		// Parse dates and times.
		$start_ts = strtotime( $start_date_input );
		if ( false === $start_ts ) {
			wp_delete_post( $event_id, true );
			return [
				'status'  => 'error',
				'message' => __( 'Invalid start date format.', 'suretriggers' ),
			];
		}

		$end_date_input = isset( $selected_options['event_end_date'] ) ? sanitize_text_field( $selected_options['event_end_date'] ) : '';
		$end_ts         = $start_ts;
		if ( ! empty( $end_date_input ) ) {
			$end_ts = strtotime( $end_date_input );
			if ( false === $end_ts ) {
				wp_delete_post( $event_id, true );
				return [
					'status'  => 'error',
					'message' => __( 'Invalid end date format.', 'suretriggers' ),
				];
			}
		}

		// Start time / end time (12h format expected by EP, e.g. "10:00 AM").
		$start_time = ! empty( $selected_options['event_start_time'] ) ? sanitize_text_field( $selected_options['event_start_time'] ) : '';
		$end_time   = ! empty( $selected_options['event_end_time'] ) ? sanitize_text_field( $selected_options['event_end_time'] ) : '';

		// Use EP helper if available for timestamp conversion, fallback to strtotime.
		$ep_functions = null;
		if ( class_exists( 'Eventprime_Basic_Functions' ) ) {
			$ep_functions = new \Eventprime_Basic_Functions();
		}

		$start_date_str = gmdate( 'Y-m-d', $start_ts );
		$end_date_str   = gmdate( 'Y-m-d', $end_ts );

		if ( $ep_functions && method_exists( $ep_functions, 'ep_date_to_timestamp' ) ) {
			$em_start_date = (int) $ep_functions->ep_date_to_timestamp( $start_date_str, 'Y-m-d', 1 );
			$em_end_date   = (int) $ep_functions->ep_date_to_timestamp( $end_date_str, 'Y-m-d', 1 );
		} else {
			$em_start_date = (int) strtotime( $start_date_str . ' 00:00:00' );
			$em_end_date   = (int) strtotime( $end_date_str . ' 00:00:00' );
		}

		update_post_meta( $event_id, 'em_start_date', $em_start_date );
		update_post_meta( $event_id, 'em_end_date', $em_end_date );
		update_post_meta( $event_id, 'em_start_time', $start_time );
		update_post_meta( $event_id, 'em_end_time', $end_time );

		// Combined date_time timestamps.
		if ( ! empty( $start_time ) ) {
			$start_dt_str = $start_date_str . ' ' . $start_time;
			if ( $ep_functions && method_exists( $ep_functions, 'ep_datetime_to_timestamp' ) ) {
				$em_start_dt = (int) $ep_functions->ep_datetime_to_timestamp( $start_dt_str, 'Y-m-d', '', 0, 1 );
			} else {
				$em_start_dt = (int) strtotime( $start_dt_str );
			}
			update_post_meta( $event_id, 'em_start_date_time', $em_start_dt );
		}

		if ( ! empty( $end_time ) ) {
			$end_dt_str = $end_date_str . ' ' . $end_time;
			if ( $ep_functions && method_exists( $ep_functions, 'ep_datetime_to_timestamp' ) ) {
				$em_end_dt = (int) $ep_functions->ep_datetime_to_timestamp( $end_dt_str, 'Y-m-d', '', 0, 1 );
			} else {
				$em_end_dt = (int) strtotime( $end_dt_str );
			}
			update_post_meta( $event_id, 'em_end_date_time', $em_end_dt );
		}

		// All day.
		$all_day = 0;
		if ( ! empty( $selected_options['event_all_day'] ) && 'yes' === strtolower( sanitize_text_field( $selected_options['event_all_day'] ) ) ) {
			$all_day = 1;
		}
		update_post_meta( $event_id, 'em_all_day', $all_day );

		// Enable booking.
		$enable_booking = 'bookings_off';
		if ( ! empty( $selected_options['event_enable_booking'] ) && 'yes' === strtolower( sanitize_text_field( $selected_options['event_enable_booking'] ) ) ) {
			$enable_booking = 'bookings_on';
		}
		update_post_meta( $event_id, 'em_enable_booking', $enable_booking );

		// Fixed event price.
		if ( isset( $selected_options['event_fixed_price'] ) && '' !== $selected_options['event_fixed_price'] && is_numeric( $selected_options['event_fixed_price'] ) ) {
			update_post_meta( $event_id, 'em_fixed_event_price', (string) floatval( $selected_options['event_fixed_price'] ) );
		}

		// Venue (taxonomy term ID).
		if ( ! empty( $selected_options['event_venue_id'] ) ) {
			$venue_id = absint( $selected_options['event_venue_id'] );
			if ( $venue_id > 0 && term_exists( $venue_id, 'em_venue' ) ) {
				update_post_meta( $event_id, 'em_venue', $venue_id );
				wp_set_object_terms( $event_id, [ $venue_id ], 'em_venue', false );
			}
		}

		// Event Type (taxonomy term ID).
		if ( ! empty( $selected_options['event_type_id'] ) ) {
			$type_id = absint( $selected_options['event_type_id'] );
			if ( $type_id > 0 && term_exists( $type_id, 'em_event_type' ) ) {
				update_post_meta( $event_id, 'em_event_type', $type_id );
				wp_set_object_terms( $event_id, [ $type_id ], 'em_event_type', false );
			}
		}

		// Organizer (taxonomy term ID).
		if ( ! empty( $selected_options['event_organizer_id'] ) ) {
			$organizer_id = absint( $selected_options['event_organizer_id'] );
			if ( $organizer_id > 0 && term_exists( $organizer_id, 'em_event_organizer' ) ) {
				update_post_meta( $event_id, 'em_organizer', $organizer_id );
				wp_set_object_terms( $event_id, [ $organizer_id ], 'em_event_organizer', false );
			}
		}

		// Performer(s) (post IDs, comma-separated).
		if ( ! empty( $selected_options['event_performer_ids'] ) ) {
			$raw_performers = sanitize_text_field( $selected_options['event_performer_ids'] );
			$performer_ids  = array_filter( array_map( 'absint', explode( ',', $raw_performers ) ) );
			$valid_ids      = [];
			foreach ( $performer_ids as $pid ) {
				if ( 'em_performer' === get_post_type( $pid ) ) {
					$valid_ids[] = $pid;
				}
			}
			if ( ! empty( $valid_ids ) ) {
				update_post_meta( $event_id, 'em_performer', $valid_ids );
			}
		}

		// Featured image.
		if ( ! empty( $selected_options['event_featured_image'] ) ) {
			$image_url = esc_url_raw( $selected_options['event_featured_image'] );
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$attachment_id = media_sideload_image( $image_url, $event_id, $event_title, 'id' );
			if ( ! is_wp_error( $attachment_id ) && is_int( $attachment_id ) ) {
				set_post_thumbnail( $event_id, $attachment_id );
			}
		}

		return EventPrime::get_event_context( $event_id );
	}
}

CreateEvent::get_instance();
