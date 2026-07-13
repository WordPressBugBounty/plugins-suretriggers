<?php
/**
 * MailsterTagRemovedFromSubscriber trigger.
 * php version 5.6
 *
 * @category MailsterTagRemovedFromSubscriber
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
 * MailsterTagRemovedFromSubscriber
 *
 * @category MailsterTagRemovedFromSubscriber
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterTagRemovedFromSubscriber {

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
	public $trigger = 'mailster_tag_removed_from_subscriber';

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
			'label'         => __( 'Tag Removed from Subscriber', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_tag_removed',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 3,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_tag_removed` action (classes/tags.class.php)
	 * when a tag is unassigned from a subscriber.
	 *
	 * @param int    $tag_id        Tag ID.
	 * @param int    $subscriber_id Subscriber ID.
	 * @param string $name          Tag name.
	 * @return void
	 */
	public function trigger_listener( $tag_id, $subscriber_id, $name = '' ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ) );

		$context = [
			'tag_id'        => absint( $tag_id ),
			'tag_name'      => is_string( $name ) ? sanitize_text_field( $name ) : '',
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterTagRemovedFromSubscriber::get_instance();
