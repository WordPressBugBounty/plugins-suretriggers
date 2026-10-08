<?php
/**
 * UpdateOrderNotes.
 * php version 5.6
 *
 * @category UpdateOrderNotes
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * UpdateOrderNotes
 *
 * @category UpdateOrderNotes
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateOrderNotes extends AutomateAction {

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
	public $action = 'voxel_update_order_notes';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Order Notes', 'suretriggers' ),
			'action'   => 'voxel_update_order_notes',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Note: Voxel stores order notes as a single string field (the note shown
	 * to the customer/vendor on the order), not as a multi-entry note thread.
	 * This action overwrites that field.
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
		if ( ! class_exists( 'Voxel\Product_Types\Orders\Order' ) ) {
			return false;
		}

		$order_id = absint( isset( $selected_options['order_id'] ) ? $selected_options['order_id'] : 0 );
		$notes    = isset( $selected_options['order_notes'] ) && is_string( $selected_options['order_notes'] ) ? sanitize_textarea_field( $selected_options['order_notes'] ) : '';

		if ( ! $order_id ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter a valid order ID.',
			];
		}

		$order = \Voxel\Product_Types\Orders\Order::get( $order_id );
		if ( ! $order ) {
			return [
				'status'  => 'error',
				'message' => 'Order not found.',
			];
		}

		$order->set_details( 'order_notes', $notes );
		$order->save();

		return [
			'success'     => true,
			'message'     => esc_attr__( 'Order notes updated successfully.', 'suretriggers' ),
			'order_id'    => $order->get_id(),
			'order_notes' => $order->get_order_notes(),
		];
	}

}

UpdateOrderNotes::get_instance();
