<?php
/**
 * MailsterNewSubscriberAdded trigger.
 * php version 5.6
 *
 * @category MailsterNewSubscriberAdded
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Mailster\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\Mailster\Mailster;
use SureTriggers\Traits\SingletonLoader;

/**
 * MailsterNewSubscriberAdded
 *
 * @category MailsterNewSubscriberAdded
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterNewSubscriberAdded {

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
	public $trigger = 'mailster_new_subscriber_added';

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
			'label'         => __( 'New Subscriber Added', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_add_subscriber',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 1,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_add_subscriber` action after a
	 * subscriber entry has been inserted (classes/subscribers.class.php).
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
			'status'        => isset( $subscriber->status ) ? absint( $subscriber->status ) : 0,
			'status_label'  => Mailster::get_status_label( isset( $subscriber->status ) ? $subscriber->status : 0 ),
			'wp_id'         => isset( $subscriber->wp_id ) ? absint( $subscriber->wp_id ) : 0,
			'signup_date'   => isset( $subscriber->signup ) ? sanitize_text_field( (string) $subscriber->signup ) : '',
			'referer'       => isset( $subscriber->referer ) ? esc_url_raw( (string) $subscriber->referer ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterNewSubscriberAdded::get_instance();
