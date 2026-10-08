<?php
/**
 * DealCreatedBitCRM.
 * php version 5.6
 *
 * @category DealCreatedBitCRM
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BitCRM\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\BitCRM\BitCRM;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'DealCreatedBitCRM' ) ) :

	/**
	 * DealCreatedBitCRM
	 *
	 * @category DealCreatedBitCRM
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class DealCreatedBitCRM {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'BitCRM';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'deal_created_bit_crm';

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
				'label'         => __( 'Deal Created', 'suretriggers' ),
				'action'        => 'deal_created_bit_crm',
				'common_action' => 'bit_crm/deal_created',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $deal Deal object.
		 *
		 * @return void
		 */
		public function trigger_listener( $deal ) {
			if ( empty( $deal ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => BitCRM::get_entity_context( $deal ),
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	DealCreatedBitCRM::get_instance();

endif;
