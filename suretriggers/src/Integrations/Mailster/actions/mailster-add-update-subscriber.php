<?php
/**
 * MailsterAddUpdateSubscriber.
 * php version 5.6
 *
 * @category MailsterAddUpdateSubscriber
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
 * MailsterAddUpdateSubscriber
 *
 * @category MailsterAddUpdateSubscriber
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterAddUpdateSubscriber extends AutomateAction {

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
	public $action = 'mailster_add_update_subscriber';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add/Update Subscriber', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Uses `mailster( 'subscribers' )->add()` (classes/subscribers.class.php)
	 * which upserts by email when `$overwrite` is true.
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

		$entry = [
			'email' => $email,
		];

		if ( ! empty( $selected_options['first_name'] ) ) {
			$entry['firstname'] = sanitize_text_field( (string) $selected_options['first_name'] );
		}

		if ( ! empty( $selected_options['last_name'] ) ) {
			$entry['lastname'] = sanitize_text_field( (string) $selected_options['last_name'] );
		}

		if ( isset( $selected_options['status'] ) && is_numeric( $selected_options['status'] ) ) {
			$entry['status'] = absint( $selected_options['status'] );
		}

		$send_notification = isset( $selected_options['send_notification'] )
			&& in_array( $selected_options['send_notification'], [ true, 1, '1', 'true' ], true );

		$subscriber_id = mailster( 'subscribers' )->add( $entry, true, false, $send_notification );

		if ( is_wp_error( $subscriber_id ) ) {
			return [
				'status'  => 'error',
				'message' => $subscriber_id->get_error_message(),
			];
		}

		if ( ! empty( $selected_options['list_id'] ) ) {
			$list_ids = $this->normalize_ids( $selected_options['list_id'] );

			if ( ! empty( $list_ids ) ) {
				mailster( 'subscribers' )->assign_lists( [ $subscriber_id ], $list_ids );
			}
		}

		$subscriber = mailster( 'subscribers' )->get( $subscriber_id, true );

		$context = [
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => $email,
			'first_name'    => is_object( $subscriber ) && isset( $subscriber->firstname ) ? $subscriber->firstname : '',
			'last_name'     => is_object( $subscriber ) && isset( $subscriber->lastname ) ? $subscriber->lastname : '',
			'status'        => is_object( $subscriber ) && isset( $subscriber->status ) ? absint( $subscriber->status ) : 0,
			'status_label'  => Mailster::get_status_label( is_object( $subscriber ) && isset( $subscriber->status ) ? $subscriber->status : 0 ),
		];

		return $context;
	}

	/**
	 * Normalize a select field's value(s) into an array of integer IDs.
	 *
	 * Supports a single ID, a comma-separated string of IDs, or an array
	 * of `{ value, label }` options as produced by multi-select fields.
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

MailsterAddUpdateSubscriber::get_instance();
