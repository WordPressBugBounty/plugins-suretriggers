<?php
/**
 * MailsterGetSubscriberByEmail.
 * php version 5.6
 *
 * @category MailsterGetSubscriberByEmail
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Mailster\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\Mailster\Mailster;
use SureTriggers\Traits\SingletonLoader;

/**
 * MailsterGetSubscriberByEmail
 *
 * @category MailsterGetSubscriberByEmail
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterGetSubscriberByEmail extends AutomateAction {

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
	public $action = 'mailster_get_subscriber_by_email';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Subscriber by Email', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Uses `mailster( 'subscribers' )->get_by_mail()` (classes/subscribers.class.php)
	 * to look up a subscriber's details, lists and tags for use in later
	 * automation steps.
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

		$subscriber = mailster( 'subscribers' )->get_by_mail( $email, true );

		if ( ! is_object( $subscriber ) || empty( $subscriber->ID ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Subscriber not found with this email.', 'suretriggers' ),
			];
		}

		$lists = mailster( 'subscribers' )->get_lists( absint( $subscriber->ID ), true );
		$tags  = mailster( 'subscribers' )->get_tags( absint( $subscriber->ID ), true );

		return [
			'subscriber_id' => absint( $subscriber->ID ),
			'email'         => isset( $subscriber->email ) ? (string) $subscriber->email : $email,
			'first_name'    => isset( $subscriber->firstname ) ? (string) $subscriber->firstname : '',
			'last_name'     => isset( $subscriber->lastname ) ? (string) $subscriber->lastname : '',
			'status'        => isset( $subscriber->status ) ? absint( $subscriber->status ) : 0,
			'status_label'  => Mailster::get_status_label( isset( $subscriber->status ) ? $subscriber->status : 0 ),
			'wp_id'         => isset( $subscriber->wp_id ) ? absint( $subscriber->wp_id ) : 0,
			'list_ids'      => is_array( $lists ) ? implode( ',', $lists ) : '',
			'tag_ids'       => is_array( $tags ) ? implode( ',', $tags ) : '',
		];
	}

}

MailsterGetSubscriberByEmail::get_instance();
