<?php
/**
 * LeadCaptured.
 * php version 5.6
 *
 * @category LeadCaptured
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentPlayer\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\FluentPlayer\FluentPlayer;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'LeadCaptured' ) ) :

	/**
	 * LeadCaptured
	 *
	 * @category LeadCaptured
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class LeadCaptured {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentPlayer';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fluent_player_lead_captured';

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
				'label'         => __( 'Lead Captured', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'fluent_player/email_collected' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 4,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param array<string,mixed> $data data.
		 * @param mixed               $submission submission.
		 * @param bool                $created created.
		 * @param array<string,mixed> $results results.
		 * @return void
		 */
		public function trigger_listener( $data, $submission = null, $created = false, $results = [] ) {
			if ( ! $created ) {
				return;
			}
			$context = FluentPlayer::to_array( $submission );
			if ( is_array( $data ) ) {
				unset( $data['nonce'] );
				$context = array_merge( $data, $context );
			}
			if ( ! empty( $context['media_id'] ) && is_numeric( $context['media_id'] ) ) {
				$context = array_merge( $context, FluentPlayer::get_post_context( absint( $context['media_id'] ), 'media' ) );
			}
			if ( ! empty( $context['media_id'] ) && is_numeric( $context['media_id'] ) && isset( $context['layer_id'] ) ) {
				$context = array_merge( $context, FluentPlayer::get_layer_context( absint( $context['media_id'] ), $context['layer_id'] ) );
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
	LeadCaptured::get_instance();

endif;
