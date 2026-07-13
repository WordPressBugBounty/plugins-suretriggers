<?php
/**
 * MailsterSubscriberDeleted trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberDeleted
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
 * MailsterSubscriberDeleted
 *
 * @category MailsterSubscriberDeleted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberDeleted {

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
	public $trigger = 'mailster_subscriber_deleted';

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
			'label'         => __( 'Subscriber Deleted', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_subscriber_delete',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 2,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_subscriber_delete` action
	 * (classes/subscribers.class.php) right after a subscriber row is removed.
	 *
	 * @param int    $subscriber_id Subscriber ID that was deleted.
	 * @param string $email         Email address of the deleted subscriber.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id, $email = '' ) {
		if ( empty( $subscriber_id ) ) {
			return;
		}

		$context = [
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => is_string( $email ) ? sanitize_email( $email ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberDeleted::get_instance();
