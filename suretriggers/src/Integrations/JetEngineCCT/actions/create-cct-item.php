<?php
/**
 * CreateCCTItem.
 * php version 5.6
 *
 * @category CreateCCTItem
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
 * CreateCCTItem
 *
 * @category CreateCCTItem
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateCCTItem extends AutomateAction {

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
	public $action = 'jet_engine_create_cct_item';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create CCT Item', 'suretriggers' ),
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
		$status   = isset( $selected_options['cct_status'] ) ? sanitize_text_field( $selected_options['cct_status'] ) : 'publish';

		if ( empty( $cct_slug ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'CCT slug is required.', 'suretriggers' ),
			];
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

		$item_data               = [];
		$item_data['cct_status'] = $status;

		if ( ! empty( $selected_options['cct_fields'] ) && is_array( $selected_options['cct_fields'] ) ) {
			foreach ( $selected_options['cct_fields'] as $field ) {
				$field_name  = isset( $field['field_name'] ) ? sanitize_text_field( $field['field_name'] ) : '';
				$field_value = isset( $field['field_value'] ) ? $field['field_value'] : '';

				if ( ! empty( $field_name ) ) {
					$item_data[ $field_name ] = $field_value;
				}
			}
		}

		$handler = $factory->get_item_handler();

		if ( ! $handler ) {
			return [
				'status'  => 'error',
				'message' => __( 'Unable to get item handler for CCT.', 'suretriggers' ),
			];
		}

		$item_id = $handler->update_item( $item_data );

		if ( is_wp_error( $item_id ) ) {
			return [
				'status'  => 'error',
				'message' => $item_id->get_error_message(),
			];
		}

		if ( empty( $item_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create CCT item.', 'suretriggers' ),
			];
		}

		$created_item = $factory->db->get_item( $item_id );

		$context = [
			'cct_slug'    => $cct_slug,
			'cct_item_id' => $item_id,
		];

		if ( ! empty( $created_item ) ) {
			$item_array = is_object( $created_item ) ? (array) $created_item : $created_item;

			if ( is_array( $item_array ) ) {
				foreach ( $item_array as $key => $value ) {
					$context[ $key ] = maybe_unserialize( $value );
				}
			}
		}

		return $context;
	}
}

CreateCCTItem::get_instance();
