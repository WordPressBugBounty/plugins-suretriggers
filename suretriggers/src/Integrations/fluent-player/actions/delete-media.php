<?php
/**
 * DeleteMedia.
 * php version 5.6
 *
 * @category DeleteMedia
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
 * DeleteMedia
 *
 * @category DeleteMedia
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteMedia extends AutomateAction {

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
	public $action = 'fluent_player_delete_media';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Media Permanently', 'suretriggers' ),
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

		$context = FluentPlayer::get_post_context( $media_id, 'media' );
		$result  = wp_delete_post( $media_id, true );
		if ( ! $result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Unable to delete the media.', 'suretriggers' ),
			];
		}

		return array_merge( $context, [ 'deleted' => true ] );
	}
}

DeleteMedia::get_instance();
