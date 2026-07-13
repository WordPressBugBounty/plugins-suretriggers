<?php
/**
 * Mailster core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\Mailster;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class Mailster
 *
 * @package SureTriggers\Integrations\Mailster
 */
class Mailster extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'Mailster';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Mailster', 'suretriggers' );
		$this->description = __( 'Mailster is a self-hosted email newsletter and marketing automation plugin for WordPress.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/mailster.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'MAILSTER_VERSION' ) && function_exists( 'mailster' );
	}

	/**
	 * Get the human readable label for a Mailster subscriber status code.
	 *
	 * @param int|string $status Mailster subscriber status code.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$statuses = [
			0 => __( 'Pending', 'suretriggers' ),
			1 => __( 'Subscribed', 'suretriggers' ),
			2 => __( 'Unsubscribed', 'suretriggers' ),
			3 => __( 'Hardbounced', 'suretriggers' ),
		];

		$status = (int) $status;

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : __( 'Unknown', 'suretriggers' );
	}

}

IntegrationsController::register( Mailster::class );
