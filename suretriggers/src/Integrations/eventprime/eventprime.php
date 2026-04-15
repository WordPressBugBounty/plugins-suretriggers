<?php
/**
 * EventPrime integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\EventPrime;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class EventPrime
 *
 * @package SureTriggers\Integrations\EventPrime
 */
class EventPrime extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'EventPrime';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'EventPrime', 'suretriggers' );
		$this->description = __( 'Event calendar and booking management for WordPress.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/eventprime.svg';

		parent::__construct();
	}

	/**
	 * Get event context data.
	 *
	 * @param int $event_id Event Post ID.
	 *
	 * @return array
	 */
	public static function get_event_context( $event_id ) {
		$event_post = get_post( $event_id );
		if ( ! $event_post || 'em_event' !== $event_post->post_type ) {
			return [];
		}

		$context = [
			'event_id'          => $event_id,
			'event_title'       => $event_post->post_title,
			'event_description' => $event_post->post_content,
			'event_status'      => $event_post->post_status,
			'event_url'         => get_permalink( $event_id ),
			'featured_image'    => get_the_post_thumbnail_url( $event_id, 'full' ),
		];

		// Date & time.
		$start_date = get_post_meta( $event_id, 'em_start_date', true );
		$end_date   = get_post_meta( $event_id, 'em_end_date', true );
		$start_time = get_post_meta( $event_id, 'em_start_time', true );
		$end_time   = get_post_meta( $event_id, 'em_end_time', true );

		$context['event_start_date'] = is_string( $start_date ) ? $start_date : '';
		$context['event_end_date']   = is_string( $end_date ) ? $end_date : '';
		$context['event_start_time'] = is_string( $start_time ) ? $start_time : '';
		$context['event_end_time']   = is_string( $end_time ) ? $end_time : '';

		$all_day                  = get_post_meta( $event_id, 'em_all_day', true );
		$context['event_all_day'] = ! empty( $all_day ) ? 'yes' : 'no';

		// Venue (taxonomy term).
		$venue_id = get_post_meta( $event_id, 'em_venue', true );
		if ( ! empty( $venue_id ) && is_numeric( $venue_id ) ) {
			$venue_term = get_term( (int) $venue_id, 'em_venue' );
			if ( ! is_wp_error( $venue_term ) && $venue_term ) {
				$context['venue_name'] = $venue_term->name;
				$context['venue_id']   = $venue_term->term_id;
			}
		}

		// Event type (taxonomy term).
		$event_type_id = get_post_meta( $event_id, 'em_event_type', true );
		if ( ! empty( $event_type_id ) && is_numeric( $event_type_id ) ) {
			$type_term = get_term( (int) $event_type_id, 'em_event_type' );
			if ( ! is_wp_error( $type_term ) && $type_term ) {
				$context['event_type_name'] = $type_term->name;
				$context['event_type_id']   = $type_term->term_id;
			}
		}

		// Organizer (taxonomy term).
		$organizer_id = get_post_meta( $event_id, 'em_organizer', true );
		if ( ! empty( $organizer_id ) && is_numeric( $organizer_id ) ) {
			$organizer_term = get_term( (int) $organizer_id, 'em_event_organizer' );
			if ( ! is_wp_error( $organizer_term ) && $organizer_term ) {
				$context['organizer_name'] = $organizer_term->name;
				$context['organizer_id']   = $organizer_term->term_id;
			}
		}

		// Performer(s) (post type).
		$performer_ids = get_post_meta( $event_id, 'em_performer', true );
		if ( ! empty( $performer_ids ) && is_array( $performer_ids ) ) {
			$performer_names = [];
			foreach ( $performer_ids as $pid ) {
				if ( is_numeric( $pid ) ) {
					$performer_post = get_post( (int) $pid );
					if ( $performer_post ) {
						$performer_names[] = $performer_post->post_title;
					}
				}
			}
			$context['performer_names'] = implode( ', ', $performer_names );
		}

		// Fixed price.
		$fixed_price = get_post_meta( $event_id, 'em_fixed_event_price', true );
		if ( is_numeric( $fixed_price ) ) {
			$context['event_fixed_price'] = $fixed_price;
		}

		return $context;
	}

	/**
	 * Get booking context data.
	 *
	 * @param int $booking_id Booking Post ID.
	 *
	 * @return array
	 */
	public static function get_booking_context( $booking_id ) {
		$booking_post = get_post( $booking_id );
		if ( ! $booking_post || 'em_booking' !== $booking_post->post_type ) {
			return [];
		}

		$context = [
			'booking_id' => $booking_id,
		];

		$status                    = get_post_meta( $booking_id, 'em_status', true );
		$context['booking_status'] = is_string( $status ) ? $status : '';

		$event_id            = get_post_meta( $booking_id, 'em_event', true );
		$context['event_id'] = is_numeric( $event_id ) ? (int) $event_id : 0;

		$user_id            = get_post_meta( $booking_id, 'em_user', true );
		$context['user_id'] = is_numeric( $user_id ) ? (int) $user_id : 0;

		$booking_date            = get_post_meta( $booking_id, 'em_date', true );
		$context['booking_date'] = is_numeric( $booking_date ) ? gmdate( 'Y-m-d H:i:s', (int) $booking_date ) : '';

		$payment_method            = get_post_meta( $booking_id, 'em_payment_method', true );
		$context['payment_method'] = is_string( $payment_method ) ? $payment_method : '';

		$event_name            = get_post_meta( $booking_id, 'em_name', true );
		$context['event_name'] = is_string( $event_name ) ? $event_name : '';

		// Order info.
		$order_info = get_post_meta( $booking_id, 'em_order_info', true );
		if ( is_array( $order_info ) ) {
			if ( isset( $order_info['booking_total'] ) ) {
				$context['booking_total'] = $order_info['booking_total'];
			}
			if ( isset( $order_info['event_fixed_price'] ) ) {
				$context['event_fixed_price'] = $order_info['event_fixed_price'];
			}
		}

		// Attendee names.
		$attendees = get_post_meta( $booking_id, 'em_attendee_names', true );
		if ( is_array( $attendees ) && ! empty( $attendees ) ) {
			$context['attendees'] = $attendees;
		}

		return $context;
	}

	/**
	 * Is Plugin depended on plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'EVENTPRIME_VERSION' );
	}
}

IntegrationsController::register( EventPrime::class );
