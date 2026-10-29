<?php
/**
 * PlaylistRendered.
 * php version 5.6
 *
 * @category PlaylistRendered
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

if ( ! class_exists( 'PlaylistRendered' ) ) :

	/**
	 * PlaylistRendered
	 *
	 * @category PlaylistRendered
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class PlaylistRendered {

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
		public $trigger = 'fluent_player_playlist_rendered';

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
				'label'         => __( 'Playlist Rendered', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'render_block' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener. Hooked on render_block, so the block content must be returned untouched.
		 *
		 * @param string              $block_content Rendered block HTML.
		 * @param array<string,mixed> $block         Parsed block.
		 * @return string
		 */
		public function trigger_listener( $block_content, $block = [] ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) || 'fluent-player/playlist' !== $block['blockName'] ) {
				return $block_content;
			}

			$attrs       = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			$playlist_id = 0;
			foreach ( [ 'playlistId', 'playlist_id', 'id' ] as $key ) {
				if ( ! empty( $attrs[ $key ] ) && is_numeric( $attrs[ $key ] ) ) {
					$playlist_id = absint( $attrs[ $key ] );
					break;
				}
			}

			$context                   = $playlist_id ? FluentPlayer::get_post_context( $playlist_id, 'playlist' ) : [];
			$context['viewer_user_id'] = get_current_user_id();

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);

			return $block_content;
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	PlaylistRendered::get_instance();

endif;
