<?php
/**
 * CustomerCreated.
 * php version 5.6
 *
 * @category CustomerCreated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\HBook\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\HBook\HBook;

if ( ! class_exists( 'CustomerCreated' ) ) :

	/**
	 * CustomerCreated
	 *
	 * @category CustomerCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CustomerCreated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'HBook';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'hbook_customer_created';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
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
				'label'         => __( 'Customer Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'hb_create_customer',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array $info Customer info as saved by HBook.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $info ) {
			if ( empty( $info ) || ! is_array( $info ) ) {
				return;
			}

			$context = [ 'customer_info' => $info ];

			foreach ( $info as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$context[ 'customer_' . $key ] = $value;
				}
			}

			// HBook's hook does not pass the new customer's ID, so resolve it from the email.
			$email = isset( $info['email'] ) && is_string( $info['email'] ) ? $info['email'] : '';
			if ( '' !== $email && class_exists( '\HbDataBaseActions' ) ) {
				$db          = new \HbDataBaseActions();
				$customer_id = $db->get_customer_id( $email );
				if ( $customer_id ) {
					$context = array_merge( $context, HBook::get_customer_context( absint( $customer_id ) ) );
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
	CustomerCreated::get_instance();

endif;
