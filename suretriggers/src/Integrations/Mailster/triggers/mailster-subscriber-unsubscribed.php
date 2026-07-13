<?php
/**
 * MailsterSubscriberUnsubscribed trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberUnsubscribed
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
 * MailsterSubscriberUnsubscribed
 *
 * @category MailsterSubscriberUnsubscribed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberUnsubscribed {

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
	public $trigger = 'mailster_subscriber_unsubscribed';

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
			'label'         => __( 'Subscriber Unsubscribed', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_unsubscribe',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 4,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_unsubscribe` action
	 * (classes/subscribers.class.php) when a subscriber unsubscribes,
	 * optionally in the context of a specific campaign.
	 *
	 * @param int      $subscriber_id Subscriber ID.
	 * @param int|null $campaign_id   Campaign ID the unsubscribe originated from, if any.
	 * @param int|null $status        Status the subscriber was set to.
	 * @param int|null $index         Link index, if unsubscribed via a tracked link.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id, $campaign_id = null, $status = null, $index = null ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ) );

		$campaign_id = is_numeric( $campaign_id ) ? absint( $campaign_id ) : 0;

		$context = [
			'subscriber_id'  => absint( $subscriber_id ),
			'email'          => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'campaign_id'    => $campaign_id,
			'campaign_title' => $campaign_id ? get_the_title( $campaign_id ) : '',
			'status'         => is_numeric( $status ) ? absint( $status ) : 2,
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberUnsubscribed::get_instance();
