<?php
/**
 * MediaRestored.
 * php version 5.6
 *
 * @category MediaRestored
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

if ( ! class_exists( 'MediaRestored' ) ) :

	/**
	 * MediaRestored
	 *
	 * @category MediaRestored
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class MediaRestored {

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
		public $trigger = 'fluent_player_media_restored';

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
				'label'         => __( 'Media Restored', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'untrashed_post' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int    $post_id post_id.
		 * @param string $previous_status previous_status.
		 * @return void
		 */
		public function trigger_listener( $post_id, $previous_status = '' ) {
			if ( 'fluent_player_media' !== get_post_type( $post_id ) ) {
				return;
			}
			$context                    = FluentPlayer::get_post_context( absint( $post_id ), 'media' );
			$context['previous_status'] = $previous_status;

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
	MediaRestored::get_instance();

endif;
