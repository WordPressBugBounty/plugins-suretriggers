<?php
/**
 * JetEngineCCT core integrations file
 *
 * @since   1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\JetEngineCCT;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\JetEngineCCT
 */
class JetEngineCCT extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'JetEngineCCT';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'JetEngine CCT', 'suretriggers' );
		$this->description = __(
			'JetEngine Custom Content Types - create and manage custom database tables with custom fields.',
			'suretriggers'
		);
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/jetengine.png';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\Jet_Engine' ) && class_exists( '\Jet_Engine\Modules\Custom_Content_Types\Module' );
	}

}

IntegrationsController::register( JetEngineCCT::class );
