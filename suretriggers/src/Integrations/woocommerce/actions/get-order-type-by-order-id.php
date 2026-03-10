<?php
/**
 * GetOrderTypeByOrderId.
 * php version 5.6
 *
 * @category GetOrderTypeByOrderId
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WooCommerce\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * GetOrderTypeByOrderId
 *
 * @category GetOrderTypeByOrderId
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetOrderTypeByOrderId extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WooCommerce';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wc_get_order_type_by_order_id';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Order Type by Order ID', 'suretriggers' ),
			'action'   => 'wc_get_order_type_by_order_id',
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|null
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$order_id = $selected_options['order_id'];

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Abstract_Order ) {
			return [
				'status'  => 'error',
				'message' => 'There is no order associated with this Order ID.',
			];
		}

		return [
			'order_id'     => $order_id,
			'order_type'   => $order->get_type(),
			'order_status' => $order->get_status(),
		];
	}
}

GetOrderTypeByOrderId::get_instance();
