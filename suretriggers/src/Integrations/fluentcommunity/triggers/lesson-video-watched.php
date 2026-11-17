<?php
/**
 * LessonVideoWatched.
 * php version 5.6
 *
 * @category LessonVideoWatched
 * @package  SureTriggers
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.21
 */

namespace SureTriggers\Integrations\FluentCommunity\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\WordPress\WordPress;

if ( ! class_exists( 'LessonVideoWatched' ) ) :

	/**
	 * LessonVideoWatched
	 *
	 * Fires when a student has watched a Fluent Player video in a Fluent Community lesson
	 * (the lesson's video-completion threshold was reached, or the video ended).
	 * Does not require FluentCRM.
	 *
	 * @category LessonVideoWatched
	 * @package  SureTriggers
	 * @since    1.1.21
	 */
	class LessonVideoWatched {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentCommunity';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fc_lesson_video_watched';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since 1.1.21
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register the trigger.
		 *
		 * @param array $triggers Existing triggers.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Lesson Video Watched', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_community/lesson/video_watched',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $lesson  The lesson model.
		 * @param int    $user_id The user ID.
		 * @return void
		 */
		public function trigger_listener( $lesson, $user_id ) {
			if ( ! is_object( $lesson ) || empty( $user_id ) || ! is_numeric( $user_id ) ) {
				return;
			}

			$user_id = absint( $user_id );
			$meta    = isset( $lesson->meta ) && is_array( $lesson->meta ) ? $lesson->meta : [];
			$media   = isset( $meta['media'] ) && is_array( $meta['media'] ) ? $meta['media'] : [];
			$user    = get_userdata( $user_id );

			$course_title = '';
			$space_id     = isset( $lesson->space_id ) ? absint( $lesson->space_id ) : 0;
			if ( $space_id && class_exists( '\FluentCommunity\App\Models\BaseSpace' ) ) {
				$course = \FluentCommunity\App\Models\BaseSpace::find( $space_id );
				if ( is_object( $course ) && isset( $course->title ) && is_string( $course->title ) ) {
					$course_title = $course->title;
				}
			}

			$context = [
				'user_id'      => $user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'media_id'     => isset( $media['media_id'] ) && is_scalar( $media['media_id'] ) ? $media['media_id'] : '',
				'media_title'  => isset( $media['title'] ) && is_string( $media['title'] ) ? $media['title'] : '',
				'lesson_id'    => isset( $lesson->id ) ? absint( $lesson->id ) : 0,
				'lesson_title' => isset( $lesson->title ) && is_string( $lesson->title ) ? $lesson->title : '',
				'course_id'    => $space_id,
				'course_title' => $course_title,
				'user'         => WordPress::get_user_context( $user_id ),
			];

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	// Initialize the class.
	LessonVideoWatched::get_instance();

endif;
