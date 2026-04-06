<?php
/**
 * UserRenewsAdvert.
 * php version 5.6
 *
 * @category UserRenewsAdvert
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

if ( ! class_exists( 'UserRenewsAdvert' ) ) :

	/**
	 * UserRenewsAdvert
	 *
	 * @category UserRenewsAdvert
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserRenewsAdvert {

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
		public $trigger = 'wpadverts_user_renews_advert';

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
				'label'         => __( 'User renews an advert', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'adverts_payment_completed',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param WP_Post $payment Payment post object.
		 * @return void
		 */
		public function trigger_listener( $payment ) {
			if ( ! $payment instanceof WP_Post ) {
				return;
			}

			$payment_type = get_post_meta( $payment->ID, '_adverts_payment_type', true );
			if ( 'adverts-renewal' !== $payment_type ) {
				return;
			}

			$object_id = get_post_meta( $payment->ID, '_adverts_object_id', true );
			if ( ! is_numeric( $object_id ) ) {
				return;
			}

			$advert = get_post( absint( $object_id ) );
			if ( ! $advert instanceof WP_Post || 'advert' !== $advert->post_type ) {
				return;
			}

			$user_id = get_post_meta( $payment->ID, '_adverts_user_id', true );
			if ( ! is_numeric( $user_id ) ) {
				$user_id = $advert->post_author;
			}

			$context = array_merge(
				WPAdverts::get_advert_context( absint( $object_id ) ),
				WPAdverts::get_payment_context( $payment ),
				WordPress::get_user_context( absint( $user_id ) )
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
	UserRenewsAdvert::get_instance();

endif;
