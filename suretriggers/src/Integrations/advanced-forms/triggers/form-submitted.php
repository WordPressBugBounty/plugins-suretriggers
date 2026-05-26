<?php
/**
 * FormSubmitted.
 * php version 5.6
 *
 * @category FormSubmitted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\AdvancedForms\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'FormSubmitted' ) ) :

	/**
	 * FormSubmitted
	 *
	 * @category FormSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class FormSubmitted {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'AdvancedForms';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'advanced_forms_form_submitted';

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
				'label'         => __( 'Form Submitted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'af/form/submission',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array $form   Form settings array including key and title.
		 * @param array $fields Submitted field data indexed by field key.
		 * @param array $args   Arguments passed to the form render function.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $form, $fields, $args ) {
			if ( empty( $form ) ) {
				return;
			}

			$user_id = get_current_user_id();

			$context               = [];
			$context['form_key']   = isset( $form['key'] ) ? $form['key'] : '';
			$context['form_title'] = isset( $form['title'] ) ? $form['title'] : '';
			$context['post_id']    = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;

			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}

					$label = isset( $field['label'] ) ? $field['label'] : '';
					$key   = isset( $field['name'] ) ? $field['name'] : '';

					if ( '' === $key ) {
						continue;
					}

					$value = isset( $field['value'] ) ? $field['value'] : '';

					if ( '' !== $label ) {
						$context[ $label ] = $value;
					} else {
						$context[ $key ] = $value;
					}
				}
			}
			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger'    => $this->trigger,
					'wp_user_id' => $user_id,
					'context'    => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	FormSubmitted::get_instance();

endif;
