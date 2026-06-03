<?php
/**
 * BricksforgeFormSubmitted.
 * php version 5.6
 *
 * @category BricksforgeFormSubmitted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Bricksforge\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'BricksforgeFormSubmitted' ) ) :

	/**
	 * BricksforgeFormSubmitted
	 *
	 * @category BricksforgeFormSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BricksforgeFormSubmitted {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'Bricksforge';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'bricksforge_form_submitted';

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
				'action'        => 'bricksforge_form_submitted',
				'common_action' => 'bricksforge/pro_forms/after_submit',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * Fires after a Bricksforge Pro Form is submitted successfully.
		 * $form_data is the Pro Forms handler object; calling get_fields() returns
		 * an array keyed by 'formId' and 'form-field-{field_id}'.
		 *
		 * @param mixed $form_data Bricksforge Pro Forms handler object or form data array.
		 * @param mixed $results   Results of all form actions after submission.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $form_data, $results ) {
			$fields = [];

			if ( is_object( $form_data ) && method_exists( $form_data, 'get_fields' ) ) {
				$raw = $form_data->get_fields();
				if ( is_array( $raw ) ) {
					$fields = $raw;
				}
			} elseif ( is_array( $form_data ) ) {
				$fields = $form_data;
			}

			if ( empty( $fields ) ) {
				return;
			}

			$context = [];

			// Extract form ID — Bricksforge uses 'formId' (camelCase) in get_fields().
			if ( isset( $fields['formId'] ) && ( is_string( $fields['formId'] ) || is_numeric( $fields['formId'] ) ) ) {
				$context['form_id'] = sanitize_text_field( (string) $fields['formId'] );
			} elseif ( isset( $fields['form_id'] ) && ( is_string( $fields['form_id'] ) || is_numeric( $fields['form_id'] ) ) ) {
				$context['form_id'] = sanitize_text_field( (string) $fields['form_id'] );
			}

			// Extract submitted field values.
			// Bricksforge prefixes field keys with 'form-field-'; strip this prefix
			// so the context key matches the Field ID the user configured.
			foreach ( $fields as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}

				if ( 'formId' === $key || 'form_id' === $key ) {
					// Already handled above.
					continue;
				}

				if ( strpos( $key, 'form-field-' ) === 0 ) {
					$field_key             = substr( $key, 11 ); // strip 'form-field-' (11 chars).
					$context[ $field_key ] = $value;
				} else {
					$context[ $key ] = $value;
				}
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
	BricksforgeFormSubmitted::get_instance();

endif;
