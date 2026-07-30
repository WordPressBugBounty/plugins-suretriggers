<?php
/**
 * Directories Pro core integrations file
 *
 * @since 1.1.26
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\DirectoriesPro;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class DirectoriesPro
 *
 * @package SureTriggers\Integrations\DirectoriesPro
 */
class DirectoriesPro extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'DirectoriesPro';

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return function_exists( 'drts' );
	}

	/**
	 * Get the Directories Pro bundle for a post type, if any.
	 *
	 * @param string $post_type Post type / bundle name.
	 * @return object|false
	 */
	public static function get_bundle( $post_type ) {
		if ( ! function_exists( 'drts' ) ) {
			return false;
		}

		return drts()->Entity_Bundle( $post_type );
	}

	/**
	 * Whether the given bundle is a Directories Pro listing bundle.
	 *
	 * @param mixed $bundle Bundle object.
	 * @return bool
	 */
	public static function is_listing_bundle( $bundle ) {
		if ( ! is_object( $bundle ) ) {
			return false;
		}

		// Note: Bundle::__get() has no matching __isset(), so isset( $bundle->type )
		// always evaluates to false even though $bundle->type itself resolves fine.
		return 'directory__listing' === $bundle->type && empty( $bundle->info['is_taxonomy'] ); // @phpstan-ignore-line
	}

	/**
	 * Whether the given bundle is a Directories Pro review bundle.
	 *
	 * @param mixed $bundle Bundle object.
	 * @return bool
	 */
	public static function is_review_bundle( $bundle ) {
		if ( ! is_object( $bundle ) ) {
			return false;
		}

		return 'review_review' === $bundle->type; // @phpstan-ignore-line
	}

	/**
	 * Build a common trigger context array for a Directories Pro entity post.
	 *
	 * @param \WP_Post|null $post   The entity post.
	 * @param mixed         $bundle The resolved bundle for the post's post type.
	 * @return array
	 */
	public static function build_entity_context( $post, $bundle ) {
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$user = WordPress::get_user_context( absint( $post->post_author ) );

		return array_merge(
			$user,
			[
				'listing_id'    => $post->ID,
				'listing_title' => $post->post_title,
				'description'   => $post->post_content,
				'listing_type'  => $post->post_type,
				'bundle_type'   => is_object( $bundle ) ? $bundle->type : '', // @phpstan-ignore-line
				'listing_url'   => get_permalink( $post->ID ),
				'status'        => $post->post_status,
				'created'       => $post->post_date,
				'modified'      => $post->post_modified,
			]
		);
	}

}

IntegrationsController::register( DirectoriesPro::class );
