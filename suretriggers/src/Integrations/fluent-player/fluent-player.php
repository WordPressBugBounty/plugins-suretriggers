<?php
/**
 * FluentPlayer integration class file
 *
 * @package  SureTriggers
 * @since 1.0.0
 */

namespace SureTriggers\Integrations\FluentPlayer;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class FluentPlayer
 *
 * @package SureTriggers\Integrations\FluentPlayer
 */
class FluentPlayer extends Integrations {

	use SingletonLoader;

	/**
	 * ID of the integration
	 *
	 * @var string
	 */
	protected $id = 'FluentPlayer';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Fluent Player', 'suretriggers' );
		$this->description = __( 'Video and audio player with email capture, playlists and watch tracking.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/fluent-player.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended on plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'FLUENT_PLAYER_VERSION' ) || class_exists( '\FluentPlayer\App\Models\Media' );
	}

	/**
	 * Build the context for a post-backed Fluent Player object (media or playlist).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $prefix  Key prefix, e.g. "media" or "playlist".
	 * @return array<string,mixed>
	 */
	public static function get_post_context( $post_id, $prefix ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof \WP_Post ) {
			return [ $prefix . '_id' => absint( $post_id ) ];
		}

		return [
			$prefix . '_id'       => $post->ID,
			$prefix . '_title'    => $post->post_title,
			$prefix . '_status'   => $post->post_status,
			$prefix . '_url'      => (string) get_permalink( $post->ID ),
			$prefix . '_author'   => (int) $post->post_author,
			$prefix . '_created'  => $post->post_date,
			$prefix . '_modified' => $post->post_modified,
		];
	}

	/**
	 * Reduce a select / scalar / {value,label} field value to a single positive ID.
	 *
	 * @param mixed $value Raw field value.
	 * @return int
	 */
	public static function normalize_id( $value ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
			if ( is_array( $value ) ) {
				$value = isset( $value['value'] ) ? $value['value'] : 0;
			}
		}
		return is_numeric( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Resolve a valid Fluent Player media ID from action input.
	 *
	 * @param mixed $value Raw field value.
	 * @return int 0 when the ID is missing or is not a Fluent Player media post.
	 */
	public static function get_valid_media_id( $value ) {
		$id = self::normalize_id( $value );
		return ( $id && 'fluent_player_media' === get_post_type( $id ) ) ? $id : 0;
	}

	/**
	 * Look up a layer (lead-capture form, CTA, ad...) on a media item.
	 *
	 * @param int   $media_id Media post ID.
	 * @param mixed $layer_id Layer ID as submitted by the player.
	 * @return array<string,mixed> layer_id / layer_title / layer_type, empty when not found.
	 */
	public static function get_layer_context( $media_id, $layer_id ) {
		if ( ! $media_id || ! is_scalar( $layer_id ) || '' === (string) $layer_id ) {
			return [];
		}

		$settings = get_post_meta( absint( $media_id ), 'settings', true );
		$layers   = is_array( $settings ) && isset( $settings['layers'] ) && is_array( $settings['layers'] ) ? $settings['layers'] : [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) || ! isset( $layer['id'] ) || (string) $layer['id'] !== (string) $layer_id ) {
				continue;
			}
			$type = isset( $layer['type'] ) && is_string( $layer['type'] ) ? $layer['type'] : '';
			if ( 'cta' === $type && isset( $layer['cta_type'] ) && 'email' === $layer['cta_type'] ) {
				$type = 'email';
			}
			return [
				'layer_id'    => (string) $layer_id,
				'layer_title' => isset( $layer['title'] ) && is_string( $layer['title'] ) ? $layer['title'] : '',
				'layer_type'  => $type,
			];
		}

		return [];
	}

	/**
	 * Classify a media source URL the same way Fluent Player does when media is saved from its UI.
	 *
	 * Reuses the plugin's own resolver so provider/media type never drift from what the player expects.
	 *
	 * @param string $url Source URL.
	 * @return array<string,string>|null provider + mediaType, or null when not a playable http(s) URL.
	 */
	public static function classify_source( $url ) {
		$url = esc_url_raw( trim( $url ), [ 'http', 'https' ] );
		if ( '' === $url ) {
			return null;
		}

		if ( class_exists( '\FluentPlayer\App\Services\DynamicMediaSourceResolver' ) ) {
			$result = \FluentPlayer\App\Services\DynamicMediaSourceResolver::classify( $url );
			if ( ! is_array( $result ) || empty( $result['provider'] ) ) {
				return null;
			}
			return [
				'src'       => $url,
				'provider'  => (string) $result['provider'],
				'mediaType' => ! empty( $result['mediaType'] ) ? (string) $result['mediaType'] : 'video',
			];
		}

		return [
			'src'       => $url,
			'provider'  => 'external',
			'mediaType' => 'video',
		];
	}

	/**
	 * Convert a model/object returned by a hook to a plain array.
	 *
	 * Reads the model's raw attributes rather than `toArray()`: Fluent Player's own ORM throws when `toArray()`
	 * re-parses a saved record's `created_at` (ISO output vs. its datetime cast), which must never break the
	 * visitor request that fired the hook.
	 *
	 * @param mixed $item Model or array.
	 * @return array<string,mixed>
	 */
	public static function to_array( $item ) {
		if ( is_object( $item ) ) {
			try {
				if ( method_exists( $item, 'getAttributes' ) ) {
					$item = $item->getAttributes();
				} elseif ( method_exists( $item, 'toArray' ) ) {
					$item = $item->toArray();
				}
			} catch ( \Exception $e ) {
				return [];
			}
		}
		if ( ! is_array( $item ) ) {
			return [];
		}
		if ( isset( $item['meta'] ) && is_string( $item['meta'] ) ) {
			$decoded      = json_decode( $item['meta'], true );
			$item['meta'] = is_array( $decoded ) ? $decoded : [];
		}
		return $item;
	}
}

IntegrationsController::register( FluentPlayer::class );
