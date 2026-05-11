<?php
/**
 * Bookly core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\Bookly;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class Bookly
 *
 * @package SureTriggers\Integrations\Bookly
 */
class Bookly extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'Bookly';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Bookly', 'suretriggers' );
		$this->description = __( 'Bookly is a WordPress appointment booking plugin that helps you manage your business online. It allows customers to book appointments at their convenience and offers a range of features to help you run your business smoothly.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\Bookly\Lib\Plugin' );
	}

}

IntegrationsController::register( Bookly::class );
