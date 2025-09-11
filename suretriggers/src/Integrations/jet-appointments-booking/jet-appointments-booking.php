<?php
/**
 * Jet Appointments Booking core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class JetAppointmentsBooking
 *
 * @package SureTriggers\Integrations\JetAppointmentsBooking
 */
class JetAppointmentsBooking extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'JetAppointmentsBooking';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Jet Appointments Booking', 'suretriggers' );
		$this->description = __( 'A WordPress plugin for creating appointment booking forms and managing appointments with JetEngine.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/jet-appointments-booking.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\JET_APB\Plugin' );
	}

}

IntegrationsController::register( JetAppointmentsBooking::class );
