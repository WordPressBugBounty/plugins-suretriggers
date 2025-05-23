<?php
/**
 * Bricks Builder core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\BricksBuilder;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\BricksBuilder
 */
class BricksBuilder extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'BricksBuilder';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Bricks', 'suretriggers' );
		$this->description = __( 'Visual Site Builder for WordPress', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/bricksbuilder.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		$bricks_theme = wp_get_theme( 'bricks' );
		return $bricks_theme->exists();
	}

}

IntegrationsController::register( BricksBuilder::class );
