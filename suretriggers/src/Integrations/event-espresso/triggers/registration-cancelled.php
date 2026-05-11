<?php
/**
 * RegistrationCancelled.
 * php version 5.6
 *
 * @category RegistrationCancelled
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventEspresso\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\EventEspresso\EventEspresso;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'EE_RegistrationCancelled' ) ) :

	/**
	 * EE_RegistrationCancelled
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EE_RegistrationCancelled {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EventEspresso';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'ee_registration_cancelled';

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
				'label'         => __( 'Registration Cancelled or Declined', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'AHEE__EE_Registration__set_status__canceled_or_declined',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 4,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $registration EE_Registration object.
		 * @param string $old_status Previous status ID.
		 * @param string $new_status New status ID.
		 * @param mixed  $reg_context Registration context.
		 *
		 * @return void
		 */
		public function trigger_listener( $registration, $old_status = '', $new_status = '', $reg_context = null ) {
			$context = EventEspresso::get_registration_context( $registration );
			if ( empty( $context ) ) {
				return;
			}

			$context['previous_status'] = is_string( $old_status ) ? $old_status : '';
			$context['post_id']         = isset( $context['event_id'] ) ? (int) $context['event_id'] : 0;

			$wp_user_id = isset( $context['wp_user_id'] ) ? (int) $context['wp_user_id'] : 0;
			if ( $wp_user_id > 0 ) {
				$context = array_merge( WordPress::get_user_context( $wp_user_id ), $context );
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
	EE_RegistrationCancelled::get_instance();

endif;
