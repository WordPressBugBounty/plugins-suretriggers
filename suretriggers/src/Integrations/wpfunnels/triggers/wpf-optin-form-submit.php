<?php
/**
 * WpfOptinFormSubmit.
 * php version 5.6
 *
 * @category WpfOptinFormSubmit
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPFunnels\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'WpfOptinFormSubmit' ) ) :

	/**
	 * WpfOptinFormSubmit
	 *
	 * @category WpfOptinFormSubmit
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class WpfOptinFormSubmit {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WPFunnels';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wpf_optin_form_submit';

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
				'label'         => __( 'Optin Form Submitted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wpfunnels/after_optin_submit',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int   $funnel_id The funnel ID.
		 * @param array $optin_data The optin form data.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $funnel_id, $optin_data ) {
			$context                = [];
			$context['funnel_id']   = $funnel_id;
			$context['funnel']      = $funnel_id;
			$context['funnel_name'] = get_the_title( $funnel_id );

			if ( is_array( $optin_data ) ) {
				$context['email']      = isset( $optin_data['email'] ) ? sanitize_email( $optin_data['email'] ) : '';
				$context['first_name'] = isset( $optin_data['first_name'] ) ? sanitize_text_field( $optin_data['first_name'] ) : '';
				$context['last_name']  = isset( $optin_data['last_name'] ) ? sanitize_text_field( $optin_data['last_name'] ) : '';
				$context['phone']      = isset( $optin_data['phone'] ) ? sanitize_text_field( $optin_data['phone'] ) : '';
			}

			$user_id = get_current_user_id();
			if ( $user_id ) {
				$context = array_merge( $context, WordPress::get_user_context( $user_id ) );
			}

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
	WpfOptinFormSubmit::get_instance();

endif;
