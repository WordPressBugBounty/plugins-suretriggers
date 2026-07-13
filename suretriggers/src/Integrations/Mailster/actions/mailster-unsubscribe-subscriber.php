<?php
/**
 * MailsterUnsubscribeSubscriber.
 * php version 5.6
 *
 * @category MailsterUnsubscribeSubscriber
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Mailster\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * MailsterUnsubscribeSubscriber
 *
 * @category MailsterUnsubscribeSubscriber
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterUnsubscribeSubscriber extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Mailster';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'mailster_unsubscribe_subscriber';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Unsubscribe Subscriber', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Uses `mailster( 'subscribers' )->unsubscribe()` (classes/subscribers.class.php)
	 * which fires `mailster_unsubscribe` on completion.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 * @return array|void
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'mailster' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Mailster plugin functions not found.', 'suretriggers' ),
			];
		}

		$email = isset( $selected_options['email'] ) ? sanitize_email( (string) $selected_options['email'] ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Email address is invalid.', 'suretriggers' ),
			];
		}

		$subscriber = mailster( 'subscribers' )->get_by_mail( $email );

		if ( ! is_object( $subscriber ) || empty( $subscriber->ID ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Subscriber not found with this email.', 'suretriggers' ),
			];
		}

		mailster( 'subscribers' )->unsubscribe( absint( $subscriber->ID ) );

		return [
			'subscriber_id' => absint( $subscriber->ID ),
			'email'         => $email,
		];
	}

}

MailsterUnsubscribeSubscriber::get_instance();
