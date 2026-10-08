<?php
/**
 * CompanyCreatedBitCRM.
 * php version 5.6
 *
 * @category CompanyCreatedBitCRM
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

if ( ! class_exists( 'CompanyCreatedBitCRM' ) ) :

	/**
	 * CompanyCreatedBitCRM
	 *
	 * @category CompanyCreatedBitCRM
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CompanyCreatedBitCRM {

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
		public $trigger = 'company_created_bit_crm';

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
				'label'         => __( 'Company Created', 'suretriggers' ),
				'action'        => 'company_created_bit_crm',
				'common_action' => 'bit_crm/company_created',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $company Company object.
		 *
		 * @return void
		 */
		public function trigger_listener( $company ) {
			if ( empty( $company ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => BitCRM::get_entity_context( $company ),
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	CompanyCreatedBitCRM::get_instance();

endif;
