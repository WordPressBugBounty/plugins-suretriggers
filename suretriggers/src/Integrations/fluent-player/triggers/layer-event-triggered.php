<?php
/**
 * LayerEventTriggered.
 * php version 5.6
 *
 * @category LayerEventTriggered
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

if ( ! class_exists( 'LayerEventTriggered' ) ) :

	/**
	 * LayerEventTriggered
	 *
	 * @category LayerEventTriggered
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class LayerEventTriggered {

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
		public $trigger = 'fluent_player_layer_event';

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
				'label'         => __( 'Layer Event Triggered', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'fluent_player/layer_event' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener. Requires FluentCRM: Fluent Player only reports layer events for media armed by a FluentCRM automation.
		 *
		 * @param array<string,mixed> $ctx Layer event context (media_id, layer_id, layer_type, layer_title, event, user_id, subscriber_id, email).
		 * @return void
		 */
		public function trigger_listener( $ctx ) {
			if ( ! is_array( $ctx ) || empty( $ctx['media_id'] ) || ! is_numeric( $ctx['media_id'] ) ) {
				return;
			}

			$context                = array_merge( FluentPlayer::get_post_context( absint( $ctx['media_id'] ), 'media' ), $ctx );
			$context['layer_event'] = isset( $ctx['event'] ) ? $ctx['event'] : '';

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
	LayerEventTriggered::get_instance();

endif;
