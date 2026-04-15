<?php
/**
 * EverestFormsFormSubmitted.
 * php version 5.6
 *
 * @category EverestFormsFormSubmitted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EverestForms\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'EverestFormsFormSubmitted' ) ) :

	/**
	 * EverestFormsFormSubmitted
	 *
	 * @category EverestFormsFormSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EverestFormsFormSubmitted {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EverestForms';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'everest_forms_form_submitted';

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
				'common_action' => 'everest_forms_process_complete',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 4,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array $form_fields Sanitized field values/properties.
		 * @param array $entry Form submission raw data.
		 * @param array $form_data Form settings/data.
		 * @param int   $entry_id Entry ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $form_fields, $entry, $form_data, $entry_id ) {
			if ( empty( $form_data ) ) {
				return;
			}

			$user_id = ap_get_current_user_id();
			$context = [];

			$context['form_id']    = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
			$context['form_title'] = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';
			$context['entry_id']   = (int) $entry_id;

			if ( is_array( $form_fields ) ) {
				foreach ( $form_fields as $field_id => $field ) {
					$field_label             = isset( $field['name'] ) ? $field['name'] : 'field_' . $field_id;
					$field_value             = isset( $field['value'] ) ? $field['value'] : '';
					$context[ $field_label ] = $field_value;
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
	EverestFormsFormSubmitted::get_instance();

endif;
