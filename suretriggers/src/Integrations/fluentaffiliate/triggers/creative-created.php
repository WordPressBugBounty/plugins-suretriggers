<?php
/**
 * CreativeCreated.
 * php version 5.6
 *
 * @category CreativeCreated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentAffiliate\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'CreativeCreated' ) ) :

	/**
	 * CreativeCreated
	 *
	 * @category CreativeCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CreativeCreated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentAffiliate';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fluentaffiliate_creative_created';

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
				'label'         => __( 'Creative Created (PRO)', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_affiliate/after_create_creative',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $creative Creative object.
		 * @return void
		 */
		public function trigger_listener( $creative ) {
			if ( ! is_object( $creative ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $creative,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	CreativeCreated::get_instance();

endif;
