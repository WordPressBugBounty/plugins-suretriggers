<?php
/**
 * WpfOrderBumpAccepted.
 * php version 5.6
 *
 * @category WpfOrderBumpAccepted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPFunnels\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'WpfOrderBumpAccepted' ) ) :

	/**
	 * WpfOrderBumpAccepted
	 *
	 * @category WpfOrderBumpAccepted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class WpfOrderBumpAccepted {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WPFunnels';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wpf_order_bump_accepted';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Order Bump Accepted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wpfunnels/order_bump_accepted',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int $funnel_id The funnel ID.
		 * @param int $step_id The step ID.
		 * @param int $order_id The WooCommerce order ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $funnel_id, $step_id, $order_id ) {
			$context                = [];
			$context['funnel_id']   = $funnel_id;
			$context['funnel']      = $funnel_id;
			$context['funnel_name'] = get_the_title( $funnel_id );
			$context['step_id']     = $step_id;
			$context['step_name']   = get_the_title( $step_id );
			$context['order_id']    = $order_id;

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order instanceof \WC_Order ) {
					$context['order_total']    = $order->get_total();
					$context['order_currency'] = $order->get_currency();
					$context['order_status']   = $order->get_status();
					$context['billing_email']  = $order->get_billing_email();

					$user_id = $order->get_customer_id();
					if ( $user_id ) {
						$context = array_merge( $context, WordPress::get_user_context( $user_id ) );
					}
				}
			}

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
	WpfOrderBumpAccepted::get_instance();

endif;
