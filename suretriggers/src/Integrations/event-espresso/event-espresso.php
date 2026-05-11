<?php
/**
 * Event Espresso integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\EventEspresso;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class EventEspresso
 *
 * @package SureTriggers\Integrations\EventEspresso
 */
class EventEspresso extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'EventEspresso';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Event Espresso', 'suretriggers' );
		$this->description = __( 'Event registration and ticketing for WordPress.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/event-espresso.svg';

		parent::__construct();
	}

	/**
	 * Build context data from an EE_Registration object.
	 *
	 * @param object $registration EE_Registration instance.
	 *
	 * @return array
	 */
	public static function get_registration_context( $registration ) {
		if ( ! is_object( $registration ) || ! method_exists( $registration, 'ID' ) ) {
			return [];
		}

		$context = [
			'registration_id'     => (int) $registration->ID(),
			'registration_code'   => method_exists( $registration, 'reg_code' ) ? (string) $registration->reg_code() : '',
			'registration_status' => method_exists( $registration, 'status_ID' ) ? (string) $registration->status_ID() : '',
			'registration_count'  => method_exists( $registration, 'count' ) ? (int) $registration->count() : 0,
			'registration_date'   => method_exists( $registration, 'date' ) ? (string) $registration->date() : '',
			'price_paid'          => method_exists( $registration, 'price_paid' ) ? (float) $registration->price_paid() : 0,
			'final_price'         => method_exists( $registration, 'final_price' ) ? (float) $registration->final_price() : 0,
			'transaction_id'      => method_exists( $registration, 'transaction_ID' ) ? (int) $registration->transaction_ID() : 0,
			'event_id'            => method_exists( $registration, 'event_ID' ) ? (int) $registration->event_ID() : 0,
			'event_name'          => method_exists( $registration, 'event_name' ) ? (string) $registration->event_name() : '',
			'ticket_id'           => method_exists( $registration, 'ticket_ID' ) ? (int) $registration->ticket_ID() : 0,
			'attendee_id'         => method_exists( $registration, 'attendee_ID' ) ? (int) $registration->attendee_ID() : 0,
		];

		if ( method_exists( $registration, 'ticket' ) ) {
			$ticket = $registration->ticket();
			if ( is_object( $ticket ) && method_exists( $ticket, 'name' ) ) {
				$context['ticket_name'] = (string) $ticket->name();
			}
			if ( is_object( $ticket ) && method_exists( $ticket, 'price' ) ) {
				$context['ticket_price'] = (float) $ticket->price();
			}
		}

		if ( method_exists( $registration, 'attendee' ) ) {
			$attendee = $registration->attendee();
			if ( is_object( $attendee ) ) {
				$context['attendee_first_name'] = method_exists( $attendee, 'fname' ) ? (string) $attendee->fname() : '';
				$context['attendee_last_name']  = method_exists( $attendee, 'lname' ) ? (string) $attendee->lname() : '';
				$context['attendee_full_name']  = method_exists( $attendee, 'full_name' ) ? (string) $attendee->full_name() : '';
				$context['attendee_email']      = method_exists( $attendee, 'email' ) ? (string) $attendee->email() : '';
				$context['attendee_phone']      = method_exists( $attendee, 'phone' ) ? (string) $attendee->phone() : '';
				$context['attendee_address']    = method_exists( $attendee, 'address' ) ? (string) $attendee->address() : '';
				$context['attendee_city']       = method_exists( $attendee, 'city' ) ? (string) $attendee->city() : '';
				$context['attendee_country']    = method_exists( $attendee, 'country_name' ) ? (string) $attendee->country_name() : '';
				$context['wp_user_id']          = method_exists( $attendee, 'wp_user' ) ? (int) $attendee->wp_user() : 0;
			}
		}

		return $context;
	}

	/**
	 * Is Plugin dependent plugin installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'EVENT_ESPRESSO_VERSION' );
	}
}

IntegrationsController::register( EventEspresso::class );
