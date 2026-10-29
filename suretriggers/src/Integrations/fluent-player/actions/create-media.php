<?php
/**
 * CreateMedia.
 * php version 5.6
 *
 * @category CreateMedia
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
 * CreateMedia
 *
 * @category CreateMedia
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateMedia extends AutomateAction {

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
	public $action = 'fluent_player_create_media';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Media', 'suretriggers' ),
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
		if ( ! class_exists( '\FluentPlayer\App\Models\Media' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Fluent Player is not active.', 'suretriggers' ),
			];
		}

		$title = isset( $selected_options['title'] ) && is_string( $selected_options['title'] ) ? sanitize_text_field( $selected_options['title'] ) : '';
		$src   = isset( $selected_options['src'] ) && is_string( $selected_options['src'] ) ? $selected_options['src'] : '';
		if ( '' === $title || '' === $src ) {
			return [
				'status'  => 'error',
				'message' => __( 'Media title and source URL are required.', 'suretriggers' ),
			];
		}

		$source = FluentPlayer::classify_source( $src );
		if ( ! $source ) {
			return [
				'status'  => 'error',
				'message' => __( 'Source URL must be a valid http(s) media URL.', 'suretriggers' ),
			];
		}

		$media_type = isset( $selected_options['media_type'] ) ? $this->first_scalar( $selected_options['media_type'] ) : 'auto';
		if ( ! in_array( $media_type, [ 'video', 'audio' ], true ) ) {
			$media_type = $source['mediaType'];
		}

		$status = isset( $selected_options['post_status'] ) ? $this->first_scalar( $selected_options['post_status'] ) : 'draft';
		$status = in_array( $status, [ 'publish', 'draft', 'private' ], true ) ? $status : 'draft';

		try {
			$media              = new \FluentPlayer\App\Models\Media();
			$media->title       = $title;
			$media->post_status = $status;
			$media->settings    = [
				'title'             => $title,
				'src'               => $source['src'],
				'provider'          => $source['provider'],
				'mediaType'         => $media_type,
				'viewType'          => $media_type,
				'streamType'        => 'on-demand',
				'loadStrategy'      => 'visible',
				'chapters'          => [],
				'overlays'          => [],
				'language_mappings' => [],
			];
			$saved              = $media->save();
		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}

		$media_id = is_object( $saved ) && isset( $saved->ID ) ? absint( $saved->ID ) : absint( $media->id );
		if ( ! $media_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Unable to create the media.', 'suretriggers' ),
			];
		}

		return array_merge(
			FluentPlayer::get_post_context( $media_id, 'media' ),
			[
				'media_source'   => $source['src'],
				'media_provider' => $source['provider'],
				'media_type'     => $media_type,
			]
		);
	}

	/**
	 * Reduce a select / scalar / {value,label} value to a string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function first_scalar( $value ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
			if ( is_array( $value ) ) {
				$value = isset( $value['value'] ) ? $value['value'] : '';
			}
		}
		return is_string( $value ) ? sanitize_key( $value ) : '';
	}
}

CreateMedia::get_instance();
