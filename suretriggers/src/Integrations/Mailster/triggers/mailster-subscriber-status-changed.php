<?php
/**
 * MailsterSubscriberStatusChanged trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberStatusChanged
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
 * MailsterSubscriberStatusChanged
 *
 * @category MailsterSubscriberStatusChanged
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberStatusChanged {

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
	public $trigger = 'mailster_subscriber_status_changed';

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
			'label'         => __( 'Subscriber Status Changed', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_subscriber_change_status',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 3,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_subscriber_change_status` action
	 * (classes/subscribers.class.php) whenever a subscriber's status
	 * transitions, e.g. pending -> subscribed, subscribed -> unsubscribed.
	 *
	 * @param int    $new_status New status code.
	 * @param int    $old_status Previous status code.
	 * @param object $subscriber Subscriber row object.
	 * @return void
	 */
	public function trigger_listener( $new_status, $old_status, $subscriber ) {
		if ( ! is_object( $subscriber ) ) {
			return;
		}

		$context = [
			'subscriber_id'    => isset( $subscriber->ID ) ? absint( $subscriber->ID ) : 0,
			'email'            => isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'old_status'       => absint( $old_status ),
			'old_status_label' => Mailster::get_status_label( $old_status ),
			'new_status'       => absint( $new_status ),
			'new_status_label' => Mailster::get_status_label( $new_status ),
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberStatusChanged::get_instance();
