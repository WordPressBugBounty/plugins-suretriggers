<?php
/**
 * CustomerApprovesFinalDelivery.
 * php version 5.6
 *
 * @category CustomerApprovesFinalDelivery
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\ServicesForSureCart\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'CustomerApprovesFinalDelivery' ) ) :

	/**
	 * CustomerApprovesFinalDelivery
	 *
	 * @category CustomerApprovesFinalDelivery
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CustomerApprovesFinalDelivery {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'ServicesForSureCart';


		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'ss_customer_approves_final_delivery';

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
				'label'         => __( 'Customer Approves Final Delivery', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'surelywp_services_customer_approve_delivery',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;

		}

		/**
		 * Trigger listener
		 *
		 * @param int $service_id Service ID.
		 * @param int $message_id Message ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $service_id, $message_id ) {
			global $wpdb;

			$result    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}surelywp_sv_messages WHERE service_id = %d AND message_id = %d", $service_id, $message_id ), ARRAY_A );
			$user_data = WordPress::get_user_context( $result['user_id'] );
			unset( $result['user_id'] );
			$context = array_merge( $result, $user_data );
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
	CustomerApprovesFinalDelivery::get_instance();

endif;
