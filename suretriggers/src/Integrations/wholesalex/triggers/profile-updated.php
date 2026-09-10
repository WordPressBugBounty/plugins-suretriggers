<?php
/**
 * ProfileUpdated
 *
 * @package  SureTriggers
 * @category Integration
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.38
 */

namespace SureTriggers\Integrations\WholesaleX\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WholesaleX\WholesaleX;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'ProfileUpdated' ) ) :

	/**
	 * ProfileUpdated
	 *
	 * @category ProfileUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.38
	 */
	class ProfileUpdated {

		use SingletonLoader;

		/**
		 * Integration name.
		 *
		 * @var string
		 */
		public $integration = 'WholesaleX';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wholesalex_profile_updated';

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register trigger.
		 *
		 * @param array $triggers Registered triggers.
		 * @return array Modified triggers.
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'User Profile Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wholesalex_user_profile_update_notify',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int   $user_id        Updated user ID.
		 * @param array $updated_fields Human-readable labels of the sections that
		 *                              changed (e.g. "Discounts", "Profile Settings").
		 * @return void
		 */
		public function trigger_listener( $user_id, $updated_fields = [] ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				return;
			}

			$context = WholesaleX::get_user_context( $user_id );
			if ( empty( $context ) ) {
				return;
			}

			$context['updated_fields'] = is_array( $updated_fields )
				? implode( ', ', array_map( 'sanitize_text_field', $updated_fields ) )
				: sanitize_text_field( (string) $updated_fields );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	ProfileUpdated::get_instance();

endif;
