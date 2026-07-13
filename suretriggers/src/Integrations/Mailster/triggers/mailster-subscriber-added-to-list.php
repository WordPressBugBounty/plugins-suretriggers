<?php
/**
 * MailsterSubscriberAddedToList trigger.
 * php version 5.6
 *
 * @category MailsterSubscriberAddedToList
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
 * MailsterSubscriberAddedToList
 *
 * @category MailsterSubscriberAddedToList
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterSubscriberAddedToList {

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
	public $trigger = 'mailster_subscriber_added_to_list';

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
			'label'         => __( 'Subscriber Added to List', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_list_added',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 3,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_list_added` action (classes/lists.class.php)
	 * when a subscriber is assigned to a list.
	 *
	 * @param int      $list_id       List ID.
	 * @param int      $subscriber_id Subscriber ID.
	 * @param int|null $added         Timestamp the subscriber was added to the list.
	 * @return void
	 */
	public function trigger_listener( $list_id, $subscriber_id, $added = null ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ) );
		$list       = mailster( 'lists' )->get( absint( $list_id ) );

		$context = [
			'list_id'       => absint( $list_id ),
			'list_name'     => is_object( $list ) && isset( $list->name ) ? sanitize_text_field( (string) $list->name ) : '',
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'added'         => is_numeric( $added ) ? absint( $added ) : 0,
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterSubscriberAddedToList::get_instance();
