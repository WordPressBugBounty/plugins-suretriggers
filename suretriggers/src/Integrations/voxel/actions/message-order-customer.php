<?php
/**
 * MessageOrderCustomer.
 * php version 5.6
 *
 * @category MessageOrderCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\WordPress\WordPress;
use Exception;

/**
 * MessageOrderCustomer
 *
 * @category MessageOrderCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MessageOrderCustomer extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Voxel';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'voxel_message_order_customer';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Message Order Customer', 'suretriggers' ),
			'action'   => 'voxel_message_order_customer',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Resolves the customer directly from the order, so the automation only
	 * needs an order_id rather than the customer's user ID/email. The message
	 * is sent via Voxel's own direct-messages inbox, from the order's vendor
	 * (falling back to the site's configured admin user when the order has no
	 * vendor).
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @throws Exception Exception.
	 *
	 * @return bool|array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'Voxel\Product_Types\Orders\Order' ) || ! class_exists( 'Voxel\Direct_Messages\Message' ) || ! class_exists( 'Voxel\User' ) ) {
			return false;
		}

		$order_id = absint( isset( $selected_options['order_id'] ) ? $selected_options['order_id'] : 0 );
		$content  = isset( $selected_options['content'] ) && is_string( $selected_options['content'] ) ? sanitize_textarea_field( $selected_options['content'] ) : '';

		if ( ! $order_id ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter a valid order ID.',
			];
		}

		if ( '' === trim( (string) $content ) ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter a message.',
			];
		}

		$order = \Voxel\Product_Types\Orders\Order::get( $order_id );
		if ( ! $order ) {
			return [
				'status'  => 'error',
				'message' => 'Order not found.',
			];
		}

		$receiver = $order->get_customer();
		if ( ! $receiver ) {
			return [
				'status'  => 'error',
				'message' => 'This order has no customer.',
			];
		}

		$sender = null;
		if ( $order->has_vendor() ) {
			$sender = $order->get_vendor();
		} elseif ( function_exists( 'Voxel\get' ) ) {
			$sender = \Voxel\User::get( \Voxel\get( 'settings.notifications.admin_user' ) );
		}

		if ( ! $sender ) {
			return [
				'status'  => 'error',
				'message' => 'Could not determine a sender for this message.',
			];
		}

		$sender_id   = $sender->get_id();
		$receiver_id = $receiver->get_id();

		if ( $sender->get_follow_status( 'user', $receiver_id ) === -1 || $receiver->get_follow_status( 'user', $sender_id ) === -1 ) {
			return [
				'status'  => 'error',
				'message' => 'You cannot message this user.',
			];
		}

		$message = \Voxel\Direct_Messages\Message::create(
			[
				'sender_type'      => 'user',
				'sender_id'        => $sender_id,
				'sender_deleted'   => 0,
				'receiver_type'    => 'user',
				'receiver_id'      => $receiver_id,
				'receiver_deleted' => 0,
				'content'          => $content,
				'seen'             => 0,
			]
		);

		if ( empty( $message ) ) {
			return [
				'status'  => 'error',
				'message' => 'Failed to send the message.',
			];
		}

		$receiver->set_inbox_activity( true );
		$receiver->update_inbox_meta( [ 'unread' => true ] );
		$message->update_chat();

		global $wpdb;

		$has_recently_received_message = ! ! $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}voxel_messages
				WHERE
					sender_type = %s AND sender_id = %d
					AND receiver_type = %s AND receiver_id = %d
					AND created_at > %s
					AND id != %d
				LIMIT 1",
				'user',
				$sender_id,
				'user',
				$receiver_id,
				gmdate( 'Y-m-d H:i:s', time() - ( 15 * MINUTE_IN_SECONDS ) ),
				$message->get_id()
			)
		);

		if ( ! $has_recently_received_message && class_exists( 'Voxel\Events\Direct_Messages\User_Received_Message_Event' ) ) {
			( new \Voxel\Events\Direct_Messages\User_Received_Message_Event() )->dispatch( $message->get_id() );
		}

		return [
			'success'      => true,
			'message'      => esc_attr__( 'Message sent successfully.', 'suretriggers' ),
			'order_id'     => $order->get_id(),
			'sender'       => WordPress::get_user_context( $sender_id ),
			'receiver'     => WordPress::get_user_context( $receiver_id ),
			'chat_message' => [
				'id'      => $message->get_id(),
				'time'    => $message->get_time_for_display(),
				'content' => $message->get_content_for_display(),
			],
		];
	}

}

MessageOrderCustomer::get_instance();
