<?php
/**
 * MailsterDeleteSubscriber.
 * php version 5.6
 *
 * @category MailsterDeleteSubscriber
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
 * MailsterDeleteSubscriber
 *
 * @category MailsterDeleteSubscriber
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterDeleteSubscriber extends AutomateAction {

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
	public $action = 'mailster_delete_subscriber';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Subscriber', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Uses `mailster( 'subscribers' )->remove()` (classes/subscribers.class.php)
	 * which fires `mailster_subscriber_delete` on completion.
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

		$removed = mailster( 'subscribers' )->remove( [ absint( $subscriber->ID ) ] );

		if ( ! $removed ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to delete the subscriber.', 'suretriggers' ),
			];
		}

		return [
			'subscriber_id' => absint( $subscriber->ID ),
			'email'         => $email,
		];
	}

}

MailsterDeleteSubscriber::get_instance();
