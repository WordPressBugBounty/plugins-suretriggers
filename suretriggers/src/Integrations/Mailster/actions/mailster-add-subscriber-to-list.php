<?php
/**
 * MailsterAddSubscriberToList.
 * php version 5.6
 *
 * @category MailsterAddSubscriberToList
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
 * MailsterAddSubscriberToList
 *
 * @category MailsterAddSubscriberToList
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterAddSubscriberToList extends AutomateAction {

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
	public $action = 'mailster_add_subscriber_to_list';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Subscriber to List', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Uses `mailster( 'lists' )->assign_subscriber()` (classes/lists.class.php)
	 * which fires `mailster_list_added` on completion.
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

		if ( empty( $selected_options['list_id'] ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Please select a list.', 'suretriggers' ),
			];
		}

		$subscriber = mailster( 'subscribers' )->get_by_mail( $email );

		if ( ! is_object( $subscriber ) || empty( $subscriber->ID ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Subscriber not found with this email.', 'suretriggers' ),
			];
		}

		$list_ids = $this->normalize_ids( $selected_options['list_id'] );

		if ( empty( $list_ids ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'No valid list was selected.', 'suretriggers' ),
			];
		}

		foreach ( $list_ids as $list_id ) {
			mailster( 'lists' )->assign_subscriber( $list_id, absint( $subscriber->ID ) );
		}

		return [
			'subscriber_id' => absint( $subscriber->ID ),
			'email'         => $email,
			'list_ids'      => implode( ',', $list_ids ),
		];
	}

	/**
	 * Normalize a select field's value(s) into an array of integer IDs.
	 *
	 * @param mixed $value Raw selected option value.
	 * @return int[]
	 */
	protected function normalize_ids( $value ) {
		$ids = [];

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) && isset( $item['value'] ) ) {
					$ids[] = absint( $item['value'] );
				} elseif ( is_numeric( $item ) ) {
					$ids[] = absint( $item );
				}
			}
		} elseif ( is_string( $value ) ) {
			foreach ( explode( ',', $value ) as $item ) {
				$item = trim( $item );
				if ( is_numeric( $item ) ) {
					$ids[] = absint( $item );
				}
			}
		} elseif ( is_numeric( $value ) ) {
			$ids[] = absint( $value );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

}

MailsterAddSubscriberToList::get_instance();
