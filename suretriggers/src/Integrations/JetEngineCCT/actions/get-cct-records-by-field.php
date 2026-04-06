<?php
/**
 * GetCCTRecordsByField.
 * php version 5.6
 *
 * @category GetCCTRecordsByField
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
 * GetCCTRecordsByField
 *
 * @category GetCCTRecordsByField
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetCCTRecordsByField extends AutomateAction {

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
	public $action = 'jet_engine_get_cct_records_by_field';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get CCT Records by Field', 'suretriggers' ),
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

		$cct_slug    = isset( $selected_options['cct_slug'] ) ? sanitize_text_field( $selected_options['cct_slug'] ) : '';
		$field_name  = isset( $selected_options['field_name'] ) ? sanitize_text_field( $selected_options['field_name'] ) : '';
		$field_value = isset( $selected_options['field_value'] ) ? $selected_options['field_value'] : '';
		$operator    = isset( $selected_options['operator'] ) ? sanitize_text_field( $selected_options['operator'] ) : '=';
		$limit       = isset( $selected_options['limit'] ) ? absint( $selected_options['limit'] ) : 20;

		if ( empty( $cct_slug ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'CCT slug is required.', 'suretriggers' ),
			];
		}

		if ( empty( $field_name ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Field name is required.', 'suretriggers' ),
			];
		}

		$allowed_operators = [ '=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN' ];
		if ( false === array_search( $operator, $allowed_operators, true ) ) {
			$operator = '=';
		}

		$content_types = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager->get_content_types();
		$factory       = isset( $content_types[ $cct_slug ] ) ? $content_types[ $cct_slug ] : false;

		if ( ! $factory ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: CCT slug */
					__( 'Custom Content Type "%s" not found.', 'suretriggers' ),
					$cct_slug
				),
			];
		}

		$query_args = [
			[
				'field'    => $field_name,
				'operator' => $operator,
				'value'    => $field_value,
			],
		];

		$items = $factory->db->query( $query_args, $limit, 0 );

		$records = [];

		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {
				$record = is_object( $item ) ? (array) $item : $item;

				if ( is_array( $record ) ) {
					foreach ( $record as $key => $value ) {
						$record[ $key ] = maybe_unserialize( $value );
					}
				}

				$records[] = $record;
			}
		}

		$total_count = $factory->db->count( $query_args );

		return [
			'cct_slug'     => $cct_slug,
			'field_name'   => $field_name,
			'field_value'  => $field_value,
			'operator'     => $operator,
			'records'      => $records,
			'record_count' => count( $records ),
			'total_count'  => is_numeric( $total_count ) ? (int) $total_count : 0,
		];
	}
}

GetCCTRecordsByField::get_instance();
