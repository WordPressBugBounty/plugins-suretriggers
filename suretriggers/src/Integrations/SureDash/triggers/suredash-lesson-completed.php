<?php
/**
 * SureDashLessonComplete trigger for handling lesson completion events.
 * php version 5.6
 *
 * @category SureDashLessonComplete
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDash\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * SureDashLessonComplete
 *
 * @category SureDashLessonComplete
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashLessonComplete {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDash';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'suredash_lesson_completed';

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
	 * Register a trigger.
	 *
	 * @param array $triggers triggers.
	 * @return array
	 */
	public function register( $triggers ) {
		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( 'User Completes Lesson', 'suretriggers' ),
			'action'        => 'update_user_meta',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 4,
		];
		return $triggers;
	}

	/**
	 * Trigger listener for lesson completion events.
	 *
	 * Fires on update_user_meta. Detects when a new lesson ID is added
	 * to the portal_course_{course_id}_completed_lessons meta array.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value (array of completed lesson IDs).
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function trigger_listener( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( ! preg_match( '/^portal_course_(\d+)_completed_lessons$/', $meta_key, $matches ) ) {
			return;
		}

		$course_id = (int) $matches[1];

		// Get the lessons that were completed before this update.
		$old_lessons = get_user_meta( $user_id, $meta_key, true );
		$old_lessons = is_array( $old_lessons ) ? $old_lessons : [];
		$new_lessons = is_array( $meta_value ) ? $meta_value : [];

		// Find lesson IDs just added in this update.
		$added_lessons = array_diff( $new_lessons, $old_lessons );

		if ( empty( $added_lessons ) ) {
			return;
		}

		$course = get_post( $course_id );

		foreach ( $added_lessons as $lesson_id ) {
			$lesson_id = (int) $lesson_id;
			if ( ! $lesson_id ) {
				continue;
			}

			$context                     = WordPress::get_user_context( $user_id );
			$context['lesson_id']        = $lesson_id;
			$context['course_id']        = $course_id;
			$context['suredash_courses'] = $course_id;

			$lesson = get_post( $lesson_id );
			if ( $lesson instanceof \WP_Post ) {
				$context['lesson_title'] = $lesson->post_title;
			}

			if ( $course instanceof \WP_Post ) {
				$context['course_title'] = $course->post_title;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}
}

SureDashLessonComplete::get_instance();
