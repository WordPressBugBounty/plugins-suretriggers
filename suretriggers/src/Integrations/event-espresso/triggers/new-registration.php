<?php
/**
 * NewRegistration.
 * php version 5.6
 *
 * @category NewRegistration
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

if ( ! class_exists( 'EE_NewRegistration' ) ) :

	/**
	 * EE_NewRegistration
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EE_NewRegistration {

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
		public $trigger = 'ee_new_registration';

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
				'label'         => __( 'New Registration', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'AHEE__EE_Registration_Processor__trigger_registration_update_notifications',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $registration EE_Registration object.
		 * @param array  $additional_details Additional details passed by EE.
		 *
		 * @return void
		 */
		public function trigger_listener( $registration, $additional_details = [] ) {
			$context = EventEspresso::get_registration_context( $registration );
			if ( empty( $context ) ) {
				return;
			}

			$selected_event_id = 0;
			$selected_post_id  = isset( $context['event_id'] ) ? (int) $context['event_id'] : 0;
			if ( $selected_post_id > 0 ) {
				$selected_event_id = $selected_post_id;
			}

			$context['post_id'] = $selected_event_id;

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
	EE_NewRegistration::get_instance();

endif;
