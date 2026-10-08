<?php
/**
 * ContactCreatedBitCRM.
 * php version 5.6
 *
 * @category ContactCreatedBitCRM
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

if ( ! class_exists( 'ContactCreatedBitCRM' ) ) :

	/**
	 * ContactCreatedBitCRM
	 *
	 * @category ContactCreatedBitCRM
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class ContactCreatedBitCRM {

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
		public $trigger = 'contact_created_bit_crm';

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
				'label'         => __( 'Contact Created', 'suretriggers' ),
				'action'        => 'contact_created_bit_crm',
				'common_action' => 'bit_crm/contact_created',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $contact Contact object.
		 *
		 * @return void
		 */
		public function trigger_listener( $contact ) {
			if ( empty( $contact ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => BitCRM::get_entity_context( $contact ),
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	ContactCreatedBitCRM::get_instance();

endif;
