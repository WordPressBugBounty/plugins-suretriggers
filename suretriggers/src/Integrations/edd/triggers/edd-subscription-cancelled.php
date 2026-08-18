<?php
/**
 * EDDSubscriptionCancelled.
 * php version 5.6
 *
 * @category EDDSubscriptionCancelled
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EDD\Triggers;

use EDD_Subscription;
use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\EDD\EDD;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'EDDSubscriptionCancelled' ) ) :

	/**
	 * EDDSubscriptionCancelled
	 *
	 * @category EDDSubscriptionCancelled
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EDDSubscriptionCancelled {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EDD';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'edd_subscription_cancelled';

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
		 * @param array $triggers Trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Subscription Cancelled', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'edd_subscription_cancelled',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int                          $subscription_id The subscription ID.
		 * @param EDD_Subscription|object|null $subscription    The subscription object.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function trigger_listener( $subscription_id, $subscription ) {
			if ( ! class_exists( 'EDD_Subscription' ) || ! ( $subscription instanceof EDD_Subscription ) ) {
				return;
			}

			$context = EDD::get_subscription_context( $subscription );

			if ( empty( $context ) ) {
				return;
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
	EDDSubscriptionCancelled::get_instance();

endif;
