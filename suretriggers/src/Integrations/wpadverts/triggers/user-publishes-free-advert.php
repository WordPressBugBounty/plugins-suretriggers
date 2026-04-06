<?php
/**
 * UserPublishesFreeAdvert.
 * php version 5.6
 *
 * @category UserPublishesFreeAdvert
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPAdverts\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WPAdverts\WPAdverts;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;
use WP_Post;

if ( ! class_exists( 'UserPublishesFreeAdvert' ) ) :

	/**
	 * UserPublishesFreeAdvert
	 *
	 * @category UserPublishesFreeAdvert
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserPublishesFreeAdvert {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WPAdverts';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wpadverts_user_publishes_free_advert';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
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
				'label'         => __( 'User publishes a free advert', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'transition_post_status',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 * @return void
		 */
		public function trigger_listener( $new_status, $old_status, $post ) {
			if ( ! $post instanceof WP_Post ) {
				return;
			}

			if ( 'advert' !== $post->post_type ) {
				return;
			}

			if ( 'publish' !== $new_status ) {
				return;
			}

			if ( 'publish' === $old_status ) {
				return;
			}

			if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
				return;
			}

			// Check if this advert has a paid listing type.
			$listing_id = get_post_meta( $post->ID, 'payments_listing_type', true );
			if ( is_numeric( $listing_id ) ) {
				$listing = get_post( absint( $listing_id ) );
				if ( $listing instanceof WP_Post ) {
					$price = get_post_meta( $listing->ID, 'adverts_price', true );
					if ( is_numeric( $price ) && (float) $price > 0 ) {
						return;
					}
				}
			}

			$user_id = (int) $post->post_author;
			$context = array_merge(
				WPAdverts::get_advert_context( $post->ID ),
				WordPress::get_user_context( $user_id )
			);

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	UserPublishesFreeAdvert::get_instance();

endif;
