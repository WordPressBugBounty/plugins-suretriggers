<?php
/**
 * CouponCodeRedeemed.
 * php version 5.6
 *
 * @category CouponCodeRedeemed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\MemberPress\Triggers;

use MeprTransaction;
use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\MemberPress\MemberPress;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'CouponCodeRedeemed' ) ) :

	/**
	 * CouponCodeRedeemed
	 *
	 * @category CouponCodeRedeemed
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class CouponCodeRedeemed {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'MemberPress';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'mepr-coupon-code-redeemed';

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
				'label'         => __( 'Coupon Code Redeemed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [
					'mepr-event-transaction-completed',
					'mepr_txn_transition_status',
				],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;

		}


		/**
		 * Trigger listener
		 * This will trigger for both recurring and non-recurring transactions.
		 *
		 * Handles two WordPress hooks with different signatures:
		 * - `mepr-event-transaction-completed` ($event) fires for transactions that are
		 *   charged and completed immediately.
		 * - `mepr_txn_transition_status` ($old_status, $new_status, $txn) is also needed
		 *   because a coupon that grants a free trial (0 due today) never reaches the
		 *   "complete" status at signup - MemberPress only marks it "confirmed" and the
		 *   "transaction-completed" event isn't recorded until the trial converts to a
		 *   paid charge days later. Watching the status transition catches the coupon
		 *   redemption at the moment it actually happens.
		 *
		 * @param object      $event_or_old_status MeprEvent instance, or the previous transaction status string.
		 * @param string|null $new_status          The new transaction status (only set for the status-transition hook).
		 * @param object|null $txn                  The MeprTransaction instance (only set for the status-transition hook).
		 *
		 * @return void
		 */
		public function trigger_listener( $event_or_old_status, $new_status = null, $txn = null ) {
			if ( ! class_exists( 'MeprTransaction' ) ) {
				return;
			}

			if ( class_exists( 'MeprEvent' ) && $event_or_old_status instanceof \MeprEvent ) {
				$transaction = $event_or_old_status->get_data();
			} elseif ( $txn instanceof MeprTransaction ) {
				$old_status = $event_or_old_status;
				// Only act the moment the transaction first becomes "confirmed".
				if ( MeprTransaction::$confirmed_str !== $new_status || MeprTransaction::$confirmed_str === $old_status ) {
					return;
				}

				// "confirmed" is also used as a generic placeholder status for every new
				// subscription, trial or not - a non-trial signup gets a *separate*
				// transaction row with status "complete" in the same request, which the
				// other hook above already handles. Only continue here for a genuine
				// zero-cost trial, since that is the one case where "complete" never
				// fires at signup time (it only fires days later when the trial converts
				// to a real charge). This keeps the two hooks from firing for the same
				// coupon redemption.
				$sub = $txn->subscription();
				if (
					! $sub
					|| ! isset( $sub->trial, $sub->trial_amount )
					|| ! $sub->trial
					|| (float) $sub->trial_amount > 0.00
				) {
					return;
				}

				$transaction = $txn;
			} else {
				return;
			}

			if ( ! ( $transaction instanceof MeprTransaction ) || empty( $transaction->coupon() ) ) {
				return;
			}

			// Defensive dedupe in case something in a customized checkout flow ends up
			// calling this for the same transaction more than once.
			$dedupe_key = 'st_mepr_coupon_redeemed_' . $transaction->id;
			if ( get_transient( $dedupe_key ) ) {
				return;
			}
			set_transient( $dedupe_key, 1, DAY_IN_SECONDS );

			$context              = array_merge(
				WordPress::get_user_context( $transaction->user_id ),
				MemberPress::get_membership_context( $transaction )
			);
			$context['coupon_id'] = $transaction->coupon()->ID;
			$context['coupon']    = get_post( $transaction->coupon()->ID );
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
	CouponCodeRedeemed::get_instance();

endif;
