<?php
/**
 * StudiocartPurchaseProduct.
 * php version 5.6
 *
 * @category StudiocartPurchaseProduct
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.21
 */

namespace SureTriggers\Integrations\Studiocart\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\Studiocart\Studiocart;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'StudiocartPurchaseProduct' ) ) :

	/**
	 * StudiocartPurchaseProduct
	 *
	 * @category StudiocartPurchaseProduct
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.21
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class StudiocartPurchaseProduct {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'Studiocart';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'studiocart_purchase_product';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.1.21
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register trigger.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Product Purchased', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'sc_order_complete',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param string $status     Top level order status passed by Studiocart.
		 * @param array  $order_data Order data.
		 * @param string $order_type Order type, e.g. "main" or a bump/upsell slug.
		 * @since 1.1.21
		 *
		 * @return void
		 */
		public function trigger_listener( $status, $order_data, $order_type ) {
			if ( ! is_array( $order_data ) || 'paid' !== $status ) {
				return;
			}

			$context = Studiocart::get_order_context( $order_data, $order_type );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	StudiocartPurchaseProduct::get_instance();

endif;
