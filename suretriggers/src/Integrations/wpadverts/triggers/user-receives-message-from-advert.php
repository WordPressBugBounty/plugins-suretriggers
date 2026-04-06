<?php
/**
 * UserReceivesMessageFromAdvert.
 * php version 5.6
 *
 * @category UserReceivesMessageFromAdvert
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

if ( ! class_exists( 'UserReceivesMessageFromAdvert' ) ) :

	/**
	 * UserReceivesMessageFromAdvert
	 *
	 * @category UserReceivesMessageFromAdvert
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserReceivesMessageFromAdvert {

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
		public $trigger = 'wpadverts_user_receives_message_from_advert';

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
				'label'         => __( 'User receives a message from an advert', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'adext_contact_form_send',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int    $post_id Advert post ID.
		 * @param object $form    Adverts_Form object.
		 * @return void
		 */
		public function trigger_listener( $post_id, $form ) {
			if ( empty( $post_id ) ) {
				return;
			}

			$advert = get_post( $post_id );
			if ( ! $advert instanceof \WP_Post ) {
				return;
			}

			$author_id = (int) $advert->post_author;
			if ( empty( $author_id ) ) {
				return;
			}

			$context = WPAdverts::get_advert_context( $post_id );

			if ( is_object( $form ) && method_exists( $form, 'get_value' ) ) {
				$context['message_name']    = sanitize_text_field( (string) $form->get_value( 'message_name' ) );
				$context['message_email']   = sanitize_email( (string) $form->get_value( 'message_email' ) );
				$context['message_subject'] = sanitize_text_field( (string) $form->get_value( 'message_subject' ) );
				$context['message_body']    = wp_kses_post( (string) $form->get_value( 'message_body' ) );
			}

			$context = array_merge(
				$context,
				WordPress::get_user_context( $author_id )
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
	UserReceivesMessageFromAdvert::get_instance();

endif;
