<?php
/**
 * BitCRM core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\BitCRM;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class BitCRM
 *
 * @package SureTriggers\Integrations\BitCRM
 */
class BitCRM extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'BitCRM';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Bit CRM', 'suretriggers' );
		$this->description = __( 'Bit CRM is a WordPress CRM to manage contacts, leads, deals and invoices.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/bit-crm.svg';

		parent::__construct();
	}

	/**
	 * Convert a Bit CRM entity object (as passed to its `bit_crm/*` hooks or
	 * returned from its service `store()`/`update()` calls) into a flat
	 * context array for use in automations.
	 *
	 * Bit CRM entities are ORM model instances (`BitApps\Crm\Deps\BitApps\WPDatabase\Model`)
	 * whose actual column data lives in a protected `$attributes` property, not
	 * as public properties on the object itself — a plain `(array) $entity`
	 * cast instead leaks the model's internal state (query builder, casts,
	 * fillable list, a `timestamps` flag, etc.). Use the model's own `toArray()`
	 * (returns `$attributes`) when available.
	 *
	 * @param object|array|null $entity Bit CRM entity object.
	 * @return array
	 */
	public static function get_entity_context( $entity ) {
		if ( empty( $entity ) ) {
			return [];
		}

		if ( is_object( $entity ) && method_exists( $entity, 'toArray' ) ) {
			return $entity->toArray();
		}

		return (array) $entity;
	}

	/**
	 * Extract the first human-readable message out of a Bit CRM service
	 * `errors` array.
	 *
	 * Bit CRM's own field validator (`Validator::errors()`) returns errors
	 * keyed by field name, each value an array of messages (e.g.
	 * `[ 'systemDefinedFieldsValues.last_name' => [ 'The last name field is required.' ] ]`),
	 * not a sequential array — so `$errors[0]` is always empty for
	 * validator-originated failures. A handful of Bit CRM service methods
	 * (e.g. `TagService::store()`) instead return a plain sequential array
	 * of message strings; this handles both shapes.
	 *
	 * @param array $errors Errors array from a Bit CRM service response.
	 * @return string
	 */
	public static function get_first_error_message( $errors ) {
		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return '';
		}

		$first = reset( $errors );

		if ( is_array( $first ) ) {
			$first = reset( $first );
		}

		return is_scalar( $first ) ? (string) $first : '';
	}

	/**
	 * Is Bit CRM plugin installed and active.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\BitApps\Crm\Plugin' );
	}

}

IntegrationsController::register( BitCRM::class );
