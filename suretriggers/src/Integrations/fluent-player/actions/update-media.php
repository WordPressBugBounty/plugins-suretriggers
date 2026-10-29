<?php
/**
 * UpdateMedia.
 * php version 5.6
 *
 * @category UpdateMedia
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentPlayer\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\FluentPlayer\FluentPlayer;
use SureTriggers\Traits\SingletonLoader;

/**
 * UpdateMedia
 *
 * @category UpdateMedia
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateMedia extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentPlayer';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluent_player_update_media';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Media', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int                 $user_id user_id.
	 * @param int                 $automation_id automation_id.
	 * @param array               $fields fields.
	 * @param array<string,mixed> $selected_options selectedOptions.
	 *
	 * @return array<string,mixed>
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$media_id = FluentPlayer::get_valid_media_id( isset( $selected_options['media_id'] ) ? $selected_options['media_id'] : 0 );
		if ( ! $media_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid Fluent Player media ID.', 'suretriggers' ),
			];
		}

		$post_data = [ 'ID' => $media_id ];
		$title     = isset( $selected_options['title'] ) && is_string( $selected_options['title'] ) ? sanitize_text_field( $selected_options['title'] ) : '';
		if ( '' !== $title ) {
			$post_data['post_title'] = $title;
		}

		$status = isset( $selected_options['post_status'] ) ? $selected_options['post_status'] : '';
		if ( is_array( $status ) ) {
			$status = reset( $status );
			$status = is_array( $status ) && isset( $status['value'] ) ? $status['value'] : '';
		}
		if ( is_string( $status ) && in_array( $status, [ 'publish', 'draft', 'private' ], true ) ) {
			$post_data['post_status'] = $status;
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return [
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
			}
		}

		// The player reads title/source from the `settings` post meta, so keep it in sync.
		$settings = get_post_meta( $media_id, 'settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$changed  = false;
		if ( '' !== $title ) {
			$settings['title'] = $title;
			$changed           = true;
		}
		$src = isset( $selected_options['src'] ) && is_string( $selected_options['src'] ) ? trim( $selected_options['src'] ) : '';
		if ( '' !== $src ) {
			$source = FluentPlayer::classify_source( $src );
			if ( ! $source ) {
				return [
					'status'  => 'error',
					'message' => __( 'Source URL must be a valid http(s) media URL.', 'suretriggers' ),
				];
			}
			// A new source invalidates the old provider / library attachment, so reset them with it.
			unset( $settings['attachment_id'] );
			$settings['src']       = $source['src'];
			$settings['provider']  = $source['provider'];
			$settings['mediaType'] = $source['mediaType'];
			$settings['viewType']  = $source['mediaType'];
			$changed               = true;
		}
		if ( $changed ) {
			update_post_meta( $media_id, 'settings', $settings );
		}

		return FluentPlayer::get_post_context( $media_id, 'media' );
	}
}

UpdateMedia::get_instance();
