<?php
/**
 * AffiliateStatusChangedToPending.
 * php version 5.6
 *
 * @category AffiliateStatusChangedToPending
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentAffiliate\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'AffiliateStatusChangedToPending' ) ) :

	/**
	 * AffiliateStatusChangedToPending
	 *
	 * @category AffiliateStatusChangedToPending
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class AffiliateStatusChangedToPending {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentAffiliate';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fluentaffiliate_affiliate_status_changed_to_pending';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Affiliate Status Changed to Pending', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_affiliate/affiliate_status_to_pending',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $affiliate Affiliate object.
		 * @param string $old_status Old status.
		 * @return void
		 */
		public function trigger_listener( $affiliate, $old_status ) {
			if ( ! is_object( $affiliate ) ) {
				return;
			}
			$context = [
				'affiliate'  => $affiliate,
				'old_status' => $old_status,
				'new_status' => 'pending',
			];
			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	AffiliateStatusChangedToPending::get_instance();

endif;
