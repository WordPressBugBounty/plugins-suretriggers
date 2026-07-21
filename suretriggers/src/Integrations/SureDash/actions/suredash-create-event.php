<?php
/**
 * SureDashCreateEvent.
 * php version 5.6
 *
 * @category SureDashCreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDash\Actions;

use SureDashboard\Core\Routers\Backend as SureDashBackendRouter;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use WP_REST_Request;
use Exception;

/**
 * SureDashCreateEvent
 *
 * @category SureDashCreateEvent
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashCreateEvent extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDash';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'suredash_create_event';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Event in Space', 'suretriggers' ),
			'action'   => 'suredash_create_event',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|bool
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDASHBOARD_VER' ) || ! defined( 'SUREDASHBOARD_SUB_CONTENT_POST_TYPE' ) || ! class_exists( SureDashBackendRouter::class ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		$event_title = ! empty( $selected_options['post_title'] ) ? sanitize_text_field( $selected_options['post_title'] ) : '';
		$space_id    = ! empty( $selected_options['space_id'] ) ? absint( $selected_options['space_id'] ) : 0;

		if ( empty( $event_title ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Event title is required.', 'suretriggers' ),
			];
		}

		if ( empty( $space_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Event/Calendar space is required.', 'suretriggers' ),
			];
		}

		$space_post_type = defined( 'SUREDASHBOARD_POST_TYPE' ) ? SUREDASHBOARD_POST_TYPE : 'portal';
		$space           = get_post( $space_id );

		if ( ! $space || $space_post_type !== $space->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Selected space could not be found.', 'suretriggers' ),
			];
		}

		if ( 'events' !== get_post_meta( $space_id, 'integration', true ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Selected space is not an Event/Calendar space.', 'suretriggers' ),
			];
		}

		$post_status = ! empty( $selected_options['post_status'] ) && in_array( $selected_options['post_status'], [ 'publish', 'draft' ], true )
			? $selected_options['post_status']
			: 'publish';

		$post_data = [
			'post_title'  => $event_title,
			'post_status' => $post_status,
			'space_id'    => $space_id,
			'context'     => 'events',
			'space_type'  => 'events',
		];

		if ( ! empty( $selected_options['post_slug'] ) ) {
			$post_data['post_slug'] = sanitize_title( $selected_options['post_slug'] );
		}

		if ( ! empty( $selected_options['comment_status'] ) && in_array( $selected_options['comment_status'], [ 'open', 'closed' ], true ) ) {
			$post_data['comment_status'] = $selected_options['comment_status'];
		}

		if ( ! empty( $selected_options['event_date'] ) ) {
			$post_data['event_date'] = sanitize_text_field( $selected_options['event_date'] );
		}

		if ( ! empty( $selected_options['event_start_time'] ) ) {
			$post_data['event_start_time'] = sanitize_text_field( $selected_options['event_start_time'] );
		}

		if ( ! empty( $selected_options['event_duration'] ) ) {
			$post_data['event_duration'] = sanitize_text_field( $selected_options['event_duration'] );
		}

		if ( ! empty( $selected_options['event_timezone'] ) ) {
			$post_data['event_timezone'] = sanitize_text_field( $selected_options['event_timezone'] );
		}

		if ( ! empty( $selected_options['rsvp_link'] ) ) {
			$post_data['rsvp_link'] = esc_url_raw( $selected_options['rsvp_link'] );
		}

		if ( ! empty( $selected_options['event_joining_link'] ) ) {
			$post_data['event_joining_link'] = esc_url_raw( $selected_options['event_joining_link'] );
		}

		if ( ! empty( $selected_options['recorded_video_link'] ) ) {
			$post_data['recorded_video_link'] = esc_url_raw( $selected_options['recorded_video_link'] );
		}

		if ( ! empty( $selected_options['visibility_scope'] ) ) {
			$visibility_scope = $selected_options['visibility_scope'];
			if ( ! is_array( $visibility_scope ) ) {
				$visibility_scope = explode( ',', $visibility_scope );
			}
			$post_data['visibility_scope'] = implode(
				',',
				array_values( array_filter( array_map( 'sanitize_text_field', $visibility_scope ) ) )
			);
		}

		if ( ! empty( $selected_options['custom_post_cover_image'] ) ) {
			$post_data['custom_post_cover_image'] = esc_url_raw( $selected_options['custom_post_cover_image'] );
		}

		if ( ! empty( $selected_options['custom_post_embed_media'] ) ) {
			$post_data['custom_post_embed_media'] = esc_url_raw( $selected_options['custom_post_embed_media'] );
		}

		// Set the current user context so the event is attributed to the automation's user.
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		foreach ( $post_data as $key => $value ) {
			$_POST[ $key ] = $value; //phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside create_post_for_space().
		}

		try {
			$result = SureDashBackendRouter::get_instance()->create_post_for_space( $request );
		} finally {
			foreach ( array_keys( $post_data ) as $key ) {
				unset( $_POST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		if ( empty( $result['success'] ) ) {
			$this->set_error(
				[
					'post_data' => $post_data,
					'msg'       => ! empty( $result['data']['message'] ) ? $result['data']['message'] : __( 'Failed to create event.', 'suretriggers' ),
				]
			);
			return false;
		}

		$response_data            = $result['data'];
		$response_data['status']  = 'success';
		$response_data['message'] = __( 'Event created successfully.', 'suretriggers' );

		return $response_data;
	}
}

SureDashCreateEvent::get_instance();
