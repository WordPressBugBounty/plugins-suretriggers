<?php
/**
 * EventCalendarCreateEvent.
 * php version 5.6
 *
 * @category EventCalendarCreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * EventCalendarCreateEvent
 *
 * @category EventCalendarCreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class EventCalendarCreateEvent extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'TheEventCalendar';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'event_calendar_create_event';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create an event', 'suretriggers' ),
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
	 * @throws Exception Exception.
	 *
	 * @return array|bool
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		if ( ! class_exists( 'Tribe__Events__Main' ) || ! function_exists( 'tribe_create_event' ) ) {
			return [
				'status'  => 'error',
				'message' => 'The Events Calendar plugin is not installed or activated.',
			];
		}

		$event_title = isset( $selected_options['event_title'] ) ? sanitize_text_field( $selected_options['event_title'] ) : '';

		if ( empty( $event_title ) ) {
			return [
				'status'  => 'error',
				'message' => 'Event title is required.',
			];
		}

		$start_date = isset( $selected_options['event_start_date'] ) ? sanitize_text_field( $selected_options['event_start_date'] ) : '';
		$end_date   = isset( $selected_options['event_end_date'] ) ? sanitize_text_field( $selected_options['event_end_date'] ) : '';

		if ( empty( $start_date ) ) {
			return [
				'status'  => 'error',
				'message' => 'Event start date is required.',
			];
		}

		$args = [
			'post_title'  => $event_title,
			'post_status' => 'publish',
			'EventAllDay' => false,
		];

		// Description.
		if ( ! empty( $selected_options['event_description'] ) ) {
			$args['post_content'] = wp_kses_post( $selected_options['event_description'] );
		}

		// Start date/time — expects format "Y-m-d H:i:s" or "Y-m-d".
		$args['EventStartDate'] = $start_date;

		if ( ! empty( $selected_options['event_start_time'] ) ) {
			$args['EventStartDate'] = $start_date . ' ' . sanitize_text_field( $selected_options['event_start_time'] );
		}

		// End date/time.
		if ( ! empty( $end_date ) ) {
			$args['EventEndDate'] = $end_date;

			if ( ! empty( $selected_options['event_end_time'] ) ) {
				$args['EventEndDate'] = $end_date . ' ' . sanitize_text_field( $selected_options['event_end_time'] );
			}
		}

		// All day event.
		if ( ! empty( $selected_options['event_all_day'] ) && 'yes' === strtolower( sanitize_text_field( $selected_options['event_all_day'] ) ) ) {
			$args['EventAllDay'] = true;
		}

		// Event URL / website.
		if ( ! empty( $selected_options['event_url'] ) ) {
			$args['EventURL'] = esc_url_raw( $selected_options['event_url'] );
		}

		// Event cost.
		if ( isset( $selected_options['event_cost'] ) && '' !== $selected_options['event_cost'] ) {
			$args['EventCost'] = sanitize_text_field( $selected_options['event_cost'] );
		}

		if ( ! empty( $selected_options['event_currency_symbol'] ) ) {
			$args['EventCurrencySymbol'] = sanitize_text_field( $selected_options['event_currency_symbol'] );
		}

		// Post status override.
		if ( ! empty( $selected_options['event_status'] ) ) {
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private' ];
			$status           = sanitize_text_field( $selected_options['event_status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$args['post_status'] = $status;
			}
		}

		$event_id = tribe_create_event( $args );

		if ( ! $event_id || is_wp_error( $event_id ) ) {
			return [
				'status'  => 'error',
				'message' => 'Failed to create the event.',
			];
		}

		// Venue — set after creation via post meta.
		if ( ! empty( $selected_options['event_venue_id'] ) ) {
			$venue_id = absint( $selected_options['event_venue_id'] );
			if ( $venue_id > 0 && 'tribe_venue' === get_post_type( $venue_id ) ) {
				update_post_meta( $event_id, '_EventVenueID', $venue_id );
			}
		}

		// Organizer — set after creation via post meta.
		if ( ! empty( $selected_options['event_organizer_id'] ) ) {
			$organizer_id = absint( $selected_options['event_organizer_id'] );
			if ( $organizer_id > 0 && 'tribe_organizer' === get_post_type( $organizer_id ) ) {
				update_post_meta( $event_id, '_EventOrganizerID', $organizer_id );
			}
		}

		// Category — set after creation via wp_set_object_terms.
		if ( ! empty( $selected_options['event_category_id'] ) ) {
			$raw_category = $selected_options['event_category_id'];
			// Extract value from select field array format.
			if ( is_array( $raw_category ) ) {
				$first = reset( $raw_category );
				if ( is_array( $first ) && isset( $first['value'] ) ) {
					$raw_category = $first['value'];
				} elseif ( is_object( $first ) && isset( $first->value ) ) {
					$raw_category = $first->value;
				} else {
					$raw_category = $first;
				}
			}
			$category_id = absint( $raw_category );
			if ( $category_id > 0 && term_exists( $category_id, 'tribe_events_cat' ) ) {
				wp_set_object_terms( $event_id, [ $category_id ], 'tribe_events_cat' );
			}
		}

		// Featured image — download from URL and set as post thumbnail.
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

		$context = [
			'event_id'         => $event_id,
			'event_title'      => $event_title,
			'event_url'        => get_permalink( $event_id ),
			'event_start_date' => $start_date,
			'event_end_date'   => $end_date,
		];

		if ( ! empty( $selected_options['event_description'] ) ) {
			$context['event_description'] = $selected_options['event_description'];
		}

		// Read back venue/organizer/category from DB to confirm they were saved.
		$saved_venue = get_post_meta( $event_id, '_EventVenueID', true );
		if ( ! empty( $saved_venue ) && is_numeric( $saved_venue ) ) {
			$context['event_venue_id']   = $saved_venue;
			$context['event_venue_name'] = get_the_title( (int) $saved_venue );
		}

		$saved_organizer = get_post_meta( $event_id, '_EventOrganizerID', true );
		if ( ! empty( $saved_organizer ) && is_numeric( $saved_organizer ) ) {
			$context['event_organizer_id']   = $saved_organizer;
			$context['event_organizer_name'] = get_the_title( (int) $saved_organizer );
		}

		$saved_terms = wp_get_object_terms( $event_id, 'tribe_events_cat' );
		if ( ! is_wp_error( $saved_terms ) && ! empty( $saved_terms ) ) {
			$context['event_category_id']   = $saved_terms[0]->term_id;
			$context['event_category_name'] = $saved_terms[0]->name;
		}

		$context['event_featured_image_id']  = get_post_meta( $event_id, '_thumbnail_id', true );
		$context['event_featured_image_url'] = get_the_post_thumbnail_url( $event_id );

		return $context;
	}
}

EventCalendarCreateEvent::get_instance();
