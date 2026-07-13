<?php
/**
 * BuddyBoss integration class file
 *
 * @package  SureTriggers
 * @since 1.0.0
 */

namespace SureTriggers\Integrations\BuddyBoss;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class BuddyBoss
 *
 * @package SureTriggers\Integrations\BuddyBoss
 */
class BuddyBoss extends Integrations {

	use SingletonLoader;

	/**
	 * ID of the integration
	 *
	 * @var string
	 */
	protected $id = 'BuddyBoss';

	/**
	 * BuddyBoss constructor.
	 */
	public function __construct() {
		$this->name = __( 'BuddyBoss', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Check if content has links.
	 *
	 * @param string $content content.
	 * @return array|string
	 */
	public static function st_content_has_links( $content ) {
		// Define a regular expression pattern to match URLs.
		$pattern = '/<a\b[^>]*href=["\']([^"\'#]+)/i';

		// Use preg_match_all to find all links in the content.
		preg_match_all( $pattern, $content, $matches );
	 
		// Return the array of matched links.
		return $matches[1];
	}

	/**
	 * Attach an uploaded photo or video directly to a BuddyBoss activity via the
	 * native Media/Video components, so it renders on the feed the same way
	 * BuddyBoss's own activity composer does - independent of the content field.
	 *
	 * @param int    $activity_id Activity ID the media should be linked to.
	 * @param string $media_url   Publicly reachable URL of the image or video file.
	 * @param int    $user_id     User the media should be attributed to.
	 * @param int    $group_id    Optional. Group ID when posting to a group's stream.
	 * @return void
	 */
	public static function st_attach_media_to_activity( $activity_id, $media_url, $user_id, $group_id = 0 ) {
		if ( empty( $activity_id ) || empty( $media_url ) ) {
			return;
		}

		if ( ! function_exists( 'bb_media_sideload_attachment' ) || ! function_exists( 'bp_activity_update_meta' ) ) {
			return;
		}

		$attachment_id = bb_media_sideload_attachment( $media_url );

		if ( empty( $attachment_id ) ) {
			return;
		}

		$privacy  = $group_id ? 'grouponly' : 'public';
		$is_video = (bool) preg_match( '/\.mp4([?#].*)?$/i', $media_url );

		if ( $is_video ) {
			if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'video' ) || ! function_exists( 'bp_video_add' ) ) {
				return;
			}

			$video_id = bp_video_add(
				[
					'attachment_id' => $attachment_id,
					'activity_id'   => $activity_id,
					'user_id'       => $user_id,
					'group_id'      => $group_id,
					'privacy'       => $privacy,
				]
			);

			if ( $video_id ) {
				bp_activity_update_meta( $activity_id, 'bp_video_ids', (string) $video_id );
			}
			return;
		}

		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'media' ) || ! function_exists( 'bp_media_add' ) ) {
			return;
		}

		$media_id = bp_media_add(
			[
				'attachment_id' => $attachment_id,
				'activity_id'   => $activity_id,
				'user_id'       => $user_id,
				'group_id'      => $group_id,
				'privacy'       => $privacy,
			]
		);

		if ( $media_id ) {
			bp_activity_update_meta( $activity_id, 'bp_media_ids', (string) $media_id );
		}
	}

	/**
	 * Check plugin is installed.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		if ( function_exists( 'buddypress' ) && isset( buddypress()->buddyboss ) && buddypress()->buddyboss ) {
			return true;
		} else {
			return false;
		}
	}
}

IntegrationsController::register( BuddyBoss::class );
