<?php
/**
 * MediaCreatedOrUpdated.
 * php version 5.6
 *
 * @category MediaCreatedOrUpdated
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

if ( ! class_exists( 'MediaCreatedOrUpdated' ) ) :

	/**
	 * MediaCreatedOrUpdated
	 *
	 * @category MediaCreatedOrUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class MediaCreatedOrUpdated {

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
		public $trigger = 'fluent_player_media_created_or_updated';

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
				'label'         => __( 'Media Created or Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'fluent_player/after_save_media' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int                 $media_id media_id.
		 * @param array<string,mixed> $payload payload.
		 * @return void
		 */
		public function trigger_listener( $media_id, $payload = [] ) {
			if ( empty( $media_id ) ) {
				return;
			}
			$context              = FluentPlayer::get_post_context( absint( $media_id ), 'media' );
			$context['is_update'] = is_array( $payload ) && ! empty( $payload['id'] );

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
	MediaCreatedOrUpdated::get_instance();

endif;
