<?php
/**
 * MailsterSubscriberBounced trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberBounced
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
 * MailsterSubscriberBounced
 *
 * @category MailsterSubscriberBounced
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberBounced {

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
	public $trigger = 'mailster_subscriber_bounced';

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
			'label'         => __( 'Subscriber Bounced', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_bounce',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 5,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_bounce` action (classes/subscribers.class.php)
	 * when a campaign to this subscriber bounces.
	 *
	 * @param int      $subscriber_id Subscriber ID.
	 * @param int|null $campaign_id   Campaign ID that bounced.
	 * @param bool     $is_hardbounce Whether this was a hard bounce.
	 * @param int|null $status        Status the subscriber was set to.
	 * @param int|null $index         Bounce index.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id, $campaign_id = null, $is_hardbounce = false, $status = null, $index = null ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber  = mailster( 'subscribers' )->get( absint( $subscriber_id ) );
		$campaign_id = is_numeric( $campaign_id ) ? absint( $campaign_id ) : 0;

		$context = [
			'subscriber_id'  => absint( $subscriber_id ),
			'email'          => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'campaign_id'    => $campaign_id,
			'campaign_title' => $campaign_id ? get_the_title( $campaign_id ) : '',
			'is_hardbounce'  => (bool) $is_hardbounce,
			'status'         => is_numeric( $status ) ? absint( $status ) : 3,
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberBounced::get_instance();
