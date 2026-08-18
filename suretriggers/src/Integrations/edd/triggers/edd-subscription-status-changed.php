<?php
/**
 * EDDSubscriptionStatusChanged.
 * php version 5.6
 *
 * @category EDDSubscriptionStatusChanged
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

if ( ! class_exists( 'EDDSubscriptionStatusChanged' ) ) :

	/**
	 * EDDSubscriptionStatusChanged
	 *
	 * @category EDDSubscriptionStatusChanged
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EDDSubscriptionStatusChanged {

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
		public $trigger = 'edd_subscription_status_changed';

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
				'event_name'    => 'edd_subscription_status_change',
				'label'         => __( 'Subscription Status Changed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'edd_subscription_status_change',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param string                       $from_status  The old subscription status.
		 * @param string                       $to_status    The new subscription status.
		 * @param EDD_Subscription|object|null $subscription The subscription object.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function trigger_listener( $from_status, $to_status, $subscription ) {
			if ( ! class_exists( 'EDD_Subscription' ) || ! ( $subscription instanceof EDD_Subscription ) ) {
				return;
			}

			// Only fire when the status has actually changed.
			if ( strtolower( (string) $from_status ) === strtolower( (string) $to_status ) ) {
				return;
			}

			$context = EDD::get_subscription_context( $subscription );

			if ( empty( $context ) ) {
				return;
			}

			$context['from_status'] = $from_status;
			$context['to_status']   = $to_status;

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
	EDDSubscriptionStatusChanged::get_instance();

endif;
