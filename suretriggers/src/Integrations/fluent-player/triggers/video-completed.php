<?php
/**
 * VideoCompleted.
 * php version 5.6
 *
 * @category VideoCompleted
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

if ( ! class_exists( 'VideoCompleted' ) ) :

	/**
	 * VideoCompleted
	 *
	 * @category VideoCompleted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class VideoCompleted {

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
		public $trigger = 'fluent_player_video_completed';

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
				'label'         => __( 'Video Completed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'fluent_player/watch_recorded', 'fluent_player/media_milestone' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * Handles two sources: `fluent_player/watch_recorded` (media_id, user_id, watch) from LMS progress tracking, and
		 * `fluent_player/media_milestone` (a single context array) when a "completed" milestone is reached via FluentCRM.
		 *
		 * @param int|array<string,mixed> $media_id media_id, or the milestone context.
		 * @param int                     $user_id user_id.
		 * @param array<string,mixed>     $watch watch.
		 * @return void
		 */
		public function trigger_listener( $media_id, $user_id = 0, $watch = [] ) {
			if ( is_array( $media_id ) ) {
				$milestone = $media_id;
				if ( empty( $milestone['media_id'] ) || ! is_numeric( $milestone['media_id'] ) || ! isset( $milestone['milestone'] ) || 'completed' !== $milestone['milestone'] ) {
					return;
				}
				$media_id = absint( $milestone['media_id'] );
				$user_id  = isset( $milestone['user_id'] ) && is_numeric( $milestone['user_id'] ) ? absint( $milestone['user_id'] ) : 0;
				$watch    = [
					'verdict'  => [
						'complete' => true,
						'reason'   => 'milestone',
					],
					'coverage' => isset( $milestone['coverage'] ) ? $milestone['coverage'] : 0,
				];
			}

			$verdict = isset( $watch['verdict'] ) && is_array( $watch['verdict'] ) ? $watch['verdict'] : [];
			if ( empty( $verdict['complete'] ) ) {
				return;
			}
			$context                 = FluentPlayer::get_post_context( absint( $media_id ), 'media' );
			$context['user_id']      = absint( $user_id );
			$context['completed_by'] = isset( $verdict['reason'] ) ? $verdict['reason'] : '';
			$context['duration']     = isset( $watch['duration'] ) ? $watch['duration'] : 0;
			$context['coverage']     = isset( $watch['coverage'] ) ? $watch['coverage'] : 0;
			$user                    = $user_id ? get_userdata( absint( $user_id ) ) : false;
			$context['user_email']   = $user ? $user->user_email : '';
			$context['user_name']    = $user ? $user->display_name : '';

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
	VideoCompleted::get_instance();

endif;
