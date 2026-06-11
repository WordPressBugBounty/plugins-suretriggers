<?php
/**
 * MembershipCreated.
 * php version 5.6
 *
 * @category MembershipCreated
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

if ( ! class_exists( 'MembershipCreated' ) ) :

	/**
	 * MembershipCreated
	 *
	 * @category MembershipCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class MembershipCreated {


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
		public $trigger = 'mepr-event-member-signup-completed';

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
				'label'         => __( 'Membership Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;

		}


		/**
		 * Trigger listener
		 * This will trigger only for initial member signup completion, not recurring payments.
		 *
		 * @param object $event Event data.
		 *
		 * @return void
		 */
		public function trigger_listener( $event ) {
			if ( ! class_exists( 'MeprEvent' ) || ! $event instanceof \MeprEvent ) {
				return;
			}

			$user_id = $event->evt_id;
			if ( empty( $user_id ) ) {
				return;
			}

			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				return;
			}

			if ( ! class_exists( 'MeprTransaction' ) ) {
				return;
			}

			$membership_id      = 0;
			$membership_context = null;
			$transaction_id     = 0;

			// MemberPress records the triggering transaction as JSON in $event->args.
			// For offline/free/lifetime one-time payments the txn has status='complete'.
			// For Stripe subscriptions, record_create_sub() fires this event BEFORE
			// record_sub_payment() runs, so the event's txn is a subscription_confirmation
			// (status='confirmed'). In that case we pull financial data from the subscription.
			$txn_args = ! empty( $event->args ) ? json_decode( $event->args ) : null;
			$txn_id   = ( $txn_args instanceof \stdClass && isset( $txn_args->id ) && is_numeric( $txn_args->id ) )
				? absint( $txn_args->id )
				: 0;

			if ( $txn_id ) {
				$txn = new \MeprTransaction( $txn_id );

				if ( $txn->id ) {
					if ( \MeprTransaction::$complete_str === $txn->status ) {
						// One-time / offline / free payment: transaction is already complete.
						$membership_id      = (int) $txn->product_id;
						$membership_context = MemberPress::get_membership_context( $txn );
						$transaction_id     = (int) $txn->id;
					} elseif (
						\MeprTransaction::$confirmed_str === $txn->status
						&& ! empty( $txn->subscription_id )
						&& class_exists( 'MeprSubscription' )
					) {
						// Stripe subscription: the confirmation txn exists but the payment
						// txn is created later. Pull financial data from the subscription.
						$sub = new \MeprSubscription( (int) $txn->subscription_id );
						if ( $sub instanceof \MeprSubscription && $sub->id ) {
							$membership_id             = (int) $txn->product_id;
							$sub_ctx                   = MemberPress::get_subscription_context( $sub );
							$sub_ctx['trans_num']      = $txn->trans_num;
							$sub_ctx['transaction_id'] = $txn->id;
							$membership_context        = $sub_ctx;
							$transaction_id            = (int) $txn->id;
						}
					}
				}
			}

			// Fallback for any other payment flow: scan all user transactions.
			if ( null === $membership_context ) {
				$all_txns = \MeprTransaction::get_all_by_user_id( $user_id );
				foreach ( (array) $all_txns as $raw_txn ) {
					if ( \MeprTransaction::$complete_str === $raw_txn->status ) {
						$txn_obj            = new \MeprTransaction( (int) $raw_txn->id );
						$membership_id      = (int) $txn_obj->product_id;
						$membership_context = MemberPress::get_membership_context( $txn_obj );
						$transaction_id     = (int) $txn_obj->id;
						break;
					}
				}
			}

			if ( null === $membership_context || empty( $membership_id ) ) {
				return;
			}

			if ( ! get_post( $membership_id ) ) {
				return;
			}

			// Collect MemberPress custom/account field values for this user.
			$custom_fields_context = [];
			if ( class_exists( 'MeprUser' ) ) {
				$mepr_user = new \MeprUser( $user_id );
				if ( method_exists( $mepr_user, 'custom_profile_values' ) ) {
					$custom_field_values = $mepr_user->custom_profile_values( true );
					if ( is_array( $custom_field_values ) ) {
						foreach ( $custom_field_values as $field_key => $field_value ) {
							$custom_fields_context[ sanitize_key( $field_key ) ] = $field_value;
						}
					}
				}
			}

			$membership_context['transaction_id'] = $transaction_id;

			$context                  = array_merge(
				WordPress::get_user_context( $user_id ),
				$membership_context,
				$custom_fields_context,
				[ 'signup_date' => $event->created_at ]
			);
			$context['membership_id'] = $membership_id;

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
	MembershipCreated::get_instance();

endif;
