<?php
/**
 * SecureCustomFields core integrations file
 *
 * @since   1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\SecureCustomFields;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\SecureCustomFields
 */
class SecureCustomFields extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'SecureCustomFields';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Secure Custom Fields', 'suretriggers' );
		$this->description = __( 'Secure Custom Fields (SCF) is a free, open-source custom fields plugin for WordPress, forked from Advanced Custom Fields.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/SecureCustomFields.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'SCF_Schema_Builder' );
	}

}

IntegrationsController::register( SecureCustomFields::class );
