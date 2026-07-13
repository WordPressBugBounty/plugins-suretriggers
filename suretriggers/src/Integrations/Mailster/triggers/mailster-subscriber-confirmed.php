<?php
/**
 * MailsterSubscriberConfirmed trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberConfirmed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Mailster\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

/**
 * MailsterSubscriberConfirmed
 *
 * @category MailsterSubscriberConfirmed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberConfirmed {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Mailster';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'mailster_subscriber_confirmed';

	use SingletonLoader;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
	}

	/**
	 * Register a trigger.
	 *
	 * @param array $triggers triggers.
	 * @return array
	 */
	public function register( $triggers ) {
		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( 'Subscriber Confirmed (Double Opt-in)', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_subscriber_subscribed',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 1,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_subscriber_subscribed` action, run
	 * after the subscriber confirms their subscription (classes/frontpage.class.php).
	 *
	 * @param int $subscriber_id Subscriber ID.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ), true );

		if ( ! is_object( $subscriber ) ) {
			return;
		}

		$context = [
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'first_name'    => isset( $subscriber->firstname ) ? sanitize_text_field( (string) $subscriber->firstname ) : '',
			'last_name'     => isset( $subscriber->lastname ) ? sanitize_text_field( (string) $subscriber->lastname ) : '',
			'confirm_date'  => isset( $subscriber->confirm ) ? sanitize_text_field( (string) $subscriber->confirm ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberConfirmed::get_instance();
