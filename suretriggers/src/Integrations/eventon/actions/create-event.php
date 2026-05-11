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

namespace SureTriggers\Integrations\EventOn\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\EventOn\EventOn;
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
	public $integration = 'EventOn';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'eon_create_event';

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
		unset( $user_id, $automation_id, $fields );

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

		$start_time_input = isset( $selected_options['event_start_time'] ) ? sanitize_text_field( $selected_options['event_start_time'] ) : '';
		$end_date_input   = isset( $selected_options['event_end_date'] ) ? sanitize_text_field( $selected_options['event_end_date'] ) : '';
		$end_time_input   = isset( $selected_options['event_end_time'] ) ? sanitize_text_field( $selected_options['event_end_time'] ) : '';

		$start_ts = strtotime( trim( $start_date_input . ' ' . $start_time_input ) );
		if ( false === $start_ts ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid start date or time format.', 'suretriggers' ),
			];
		}

		$end_ts = $start_ts;
		if ( ! empty( $end_date_input ) ) {
			$end_candidate = strtotime( trim( $end_date_input . ' ' . $end_time_input ) );
			if ( false === $end_candidate ) {
				return [
					'status'  => 'error',
					'message' => __( 'Invalid end date or time format.', 'suretriggers' ),
				];
			}
			$end_ts = $end_candidate;
		} elseif ( ! empty( $end_time_input ) ) {
			// Same day, but with an explicit end time.
			$end_candidate = strtotime( trim( $start_date_input . ' ' . $end_time_input ) );
			if ( false !== $end_candidate ) {
				$end_ts = $end_candidate;
			}
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
			'post_type'    => 'ajde_events',
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

		// Core EventOn meta — unix start/end (used everywhere by EventOn).
		update_post_meta( $event_id, 'evcal_srow', (string) $start_ts );
		update_post_meta( $event_id, 'evcal_erow', (string) $end_ts );
		update_post_meta( $event_id, '_unix_start_ev', (string) $start_ts );
		update_post_meta( $event_id, '_unix_end_ev', (string) $end_ts );

		// All day.
		$all_day_value = 'no';
		if ( ! empty( $selected_options['event_all_day'] ) && 'yes' === strtolower( sanitize_text_field( $selected_options['event_all_day'] ) ) ) {
			$all_day_value = 'yes';
		}
		update_post_meta( $event_id, 'evcal_allday', $all_day_value );

		// Featured flag (EventOn stores 'yes'/'no').
		$featured_value = 'no';
		if ( ! empty( $selected_options['event_featured'] ) && 'yes' === strtolower( sanitize_text_field( $selected_options['event_featured'] ) ) ) {
			$featured_value = 'yes';
		}
		update_post_meta( $event_id, '_featured', $featured_value );

		// Language default.
		update_post_meta( $event_id, '_evo_lang', 'L1' );

		// Optional subtitle.
		if ( ! empty( $selected_options['event_subtitle'] ) ) {
			update_post_meta( $event_id, 'evcal_subtitle', sanitize_text_field( $selected_options['event_subtitle'] ) );
		}

		// Optional event color.
		if ( ! empty( $selected_options['event_color'] ) ) {
			$color = sanitize_hex_color( $selected_options['event_color'] );
			if ( is_string( $color ) && '' !== $color ) {
				update_post_meta( $event_id, 'evcal_event_color', $color );
				update_post_meta( $event_id, 'evcal_event_color_n', 1 );
			}
		}

		// Optional external link.
		if ( ! empty( $selected_options['event_exlink'] ) ) {
			update_post_meta( $event_id, 'evcal_exlink', esc_url_raw( $selected_options['event_exlink'] ) );
		}

		// Optional timezone.
		if ( ! empty( $selected_options['event_timezone'] ) ) {
			update_post_meta( $event_id, 'evo_event_timezone', sanitize_text_field( $selected_options['event_timezone'] ) );
		}

		// Location taxonomy (accepts term ID or name).
		if ( ! empty( $selected_options['event_location'] ) && taxonomy_exists( 'event_location' ) ) {
			$this->assign_term( $event_id, 'event_location', $selected_options['event_location'] );
		}

		// Organizer taxonomy (accepts term ID or name).
		if ( ! empty( $selected_options['event_organizer'] ) && taxonomy_exists( 'event_organizer' ) ) {
			$this->assign_term( $event_id, 'event_organizer', $selected_options['event_organizer'] );
		}

		// Event type taxonomy (accepts term ID or name).
		if ( ! empty( $selected_options['event_type'] ) && taxonomy_exists( 'event_type' ) ) {
			$this->assign_term( $event_id, 'event_type', $selected_options['event_type'] );
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

		return EventOn::get_event_context( $event_id );
	}

	/**
	 * Assign a term to an event, creating it if passed a name that does not exist yet.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param mixed  $value    Term ID (numeric) or name.
	 * @return void
	 */
	private function assign_term( $event_id, $taxonomy, $value ) {
		$term_id = 0;

		if ( is_numeric( $value ) ) {
			$candidate = (int) $value;
			if ( $candidate > 0 && term_exists( $candidate, $taxonomy ) ) {
				$term_id = $candidate;
			}
		} else {
			if ( ! is_string( $value ) ) {
				return;
			}
			$name = sanitize_text_field( $value );
			if ( '' === $name ) {
				return;
			}

			$existing = term_exists( $name, $taxonomy );
			if ( is_array( $existing ) && isset( $existing['term_id'] ) ) {
				$term_id = (int) $existing['term_id'];
			} else {
				$created = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$term_id = (int) $created['term_id'];
				}
			}
		}

		if ( $term_id > 0 ) {
			wp_set_object_terms( $event_id, [ $term_id ], $taxonomy, false );
		}
	}
}

CreateEvent::get_instance();
