<?php
/**
 * AddUserToCourses.
 * php version 5.6
 *
 * @category AddUserToCourses
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentCommunity\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use FluentCommunity\Modules\Course\Services\CourseHelper;

/**
 * AddUserToCourses
 *
 * Enrols a user into several courses (or every course) in a single action run,
 * so it is counted as one task.
 *
 * @category AddUserToCourses
 * @package  SureTriggers
 * @since    1.0.0
 */
class AddUserToCourses extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentCommunity';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fc_add_user_to_courses';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add User To Courses', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id         User ID.
	 * @param int   $automation_id   Automation ID.
	 * @param array $fields          Fields.
	 * @param array $selected_options Selected options.
	 *
	 * @return array|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$user_email = isset( $selected_options['user_email'] ) && is_string( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';
		$raw        = isset( $selected_options['course_ids'] ) ? $selected_options['course_ids'] : [];

		if ( ! class_exists( 'FluentCommunity\Modules\Course\Services\CourseHelper' ) ) {
			return [
				'status'  => 'error',
				'message' => 'CourseHelper class not found.',
			];
		}

		$user = get_user_by( 'email', $user_email );

		if ( ! $user ) {
			return [
				'status'  => 'error',
				'message' => 'User not found with the provided email.',
			];
		}

		$requested  = $this->normalize_course_ids( $raw );
		$all_toggle = ! empty( $selected_options['all_courses'] ) && 'false' !== $selected_options['all_courses'];

		if ( $all_toggle || in_array( 'all', $requested, true ) ) {
			$course_ids = $this->get_course_ids();
		} else {
			$course_ids = array_values( array_intersect( array_map( 'intval', $requested ), $this->get_course_ids() ) );
		}

		if ( empty( $course_ids ) ) {
			return [
				'status'  => 'error',
				'message' => 'No valid courses selected.',
			];
		}

		$enrolled = [];
		$failed   = [];

		$synced = false;

		foreach ( $course_ids as $course_id ) {
			try {
				// Already-enrolled users are handled by FluentCommunity and count as enrolled.
				// The profile sync is only needed once per user, so skip it after the first success.
				if ( CourseHelper::enrollCourse( $course_id, $user->ID, 'self', $synced ) ) {
					$synced     = true;
					$enrolled[] = $course_id;
				} else {
					$failed[] = $course_id;
				}
			} catch ( Exception $e ) {
				$failed[] = $course_id;
			}
		}

		if ( empty( $enrolled ) ) {
			return [
				'status'         => 'error',
				'message'        => 'Could not add the user to any of the selected courses.',
				'failed_courses' => $failed,
				'user_id'        => $user->ID,
			];
		}

		return [
			'status'           => 'success',
			'message'          => empty( $failed ) ? 'User added to courses successfully' : 'User added to some courses; a few could not be processed',
			'user_id'          => $user->ID,
			'enrolled_courses' => $enrolled,
			'failed_courses'   => $failed,
		];
	}

	/**
	 * Turn the submitted value (array, JSON, comma list, "all") into a flat list.
	 * Numeric entries stay as strings/ints; the "all" keyword is returned as 'all'.
	 *
	 * @param mixed $raw Raw course_ids value.
	 * @return array<int, int|string>
	 */
	private function normalize_course_ids( $raw ) {
		if ( is_string( $raw ) ) {
			$trimmed = trim( $raw );
			$decoded = json_decode( $trimmed, true );
			$raw     = is_array( $decoded ) ? $decoded : explode( ',', $trimmed );
		}

		if ( ! is_array( $raw ) ) {
			$raw = [ $raw ];
		}

		$result = [];
		foreach ( $raw as $item ) {
			if ( is_array( $item ) && isset( $item['value'] ) ) {
				$item = $item['value'];
			}
			if ( is_string( $item ) ) {
				$item = trim( $item );
				if ( 'all' === strtolower( $item ) ) {
					$result[] = 'all';
					continue;
				}
			}
			if ( is_numeric( $item ) && (int) $item > 0 ) {
				$result[] = (int) $item;
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Get the IDs of every FluentCommunity course.
	 *
	 * @return array<int, int>
	 */
	private function get_course_ids() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}fcom_spaces WHERE type = 'course'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map( 'intval', (array) $ids );
	}
}

AddUserToCourses::get_instance();
