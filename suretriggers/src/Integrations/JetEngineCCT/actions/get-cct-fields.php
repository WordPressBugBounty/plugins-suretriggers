<?php
/**
 * GetCCTFields.
 * php version 5.6
 *
 * @category GetCCTFields
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetEngineCCT\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * GetCCTFields
 *
 * @category GetCCTFields
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetCCTFields extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'JetEngineCCT';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'jet_engine_get_cct_fields';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get CCT Fields', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|void
	 *
	 * @throws \Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\Jet_Engine\Modules\Custom_Content_Types\Module' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'JetEngine Custom Content Types module is not active.', 'suretriggers' ),
			];
		}

		$cct_slug = isset( $selected_options['cct_slug'] ) ? sanitize_text_field( $selected_options['cct_slug'] ) : '';

		if ( empty( $cct_slug ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'CCT slug is required.', 'suretriggers' ),
			];
		}

		$content_types = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager->get_content_types();

		$factory = isset( $content_types[ $cct_slug ] ) ? $content_types[ $cct_slug ] : false;

		if ( ! $factory || ! is_object( $factory ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: CCT slug */
					__( 'Custom Content Type "%s" not found.', 'suretriggers' ),
					$cct_slug
				),
			];
		}

		$formatted_fields = method_exists( $factory, 'get_formatted_fields' ) ? $factory->get_formatted_fields() : [];

		$fields_data = [];

		if ( ! empty( $formatted_fields ) && is_array( $formatted_fields ) ) {
			foreach ( $formatted_fields as $field_name => $field_info ) {
				$field_entry = [
					'name' => $field_name,
				];

				if ( is_array( $field_info ) ) {
					if ( isset( $field_info['type'] ) ) {
						$field_entry['type'] = $field_info['type'];
					}
					if ( isset( $field_info['sql_type'] ) ) {
						$field_entry['sql_type'] = $field_info['sql_type'];
					}
					if ( isset( $field_info['title'] ) ) {
						$field_entry['title'] = $field_info['title'];
					}
				}

				$fields_data[] = $field_entry;
			}
		}

		$cct_name = method_exists( $factory, 'get_arg' ) ? $factory->get_arg( 'name' ) : $cct_slug;

		return [
			'cct_slug'    => $cct_slug,
			'cct_name'    => $cct_name,
			'fields'      => $fields_data,
			'field_count' => count( $fields_data ),
		];
	}
}

GetCCTFields::get_instance();
