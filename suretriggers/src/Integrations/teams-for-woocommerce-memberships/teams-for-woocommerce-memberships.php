<?php
/**
 * TeamsForWoocommerceMemberships core integrations file
 *
 * @since   1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\TeamsForWoocommerceMemberships;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class TeamsForWoocommerceMemberships
 *
 * @package SureTriggers\Integrations\TeamsForWoocommerceMemberships
 */
class TeamsForWoocommerceMemberships extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'TeamsForWoocommerceMemberships';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Teams for WooCommerce Memberships', 'suretriggers' );
		$this->description = __( 'Sell memberships to teams, companies, groups, or families.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/teamsforwoocommercememberships.svg';
		parent::__construct();
	}

	/**
	 * Get team context data.
	 *
	 * @param int|object $team_or_id Team object or ID.
	 * @return array
	 */
	public static function get_team_context( $team_or_id ) {
		if ( ! function_exists( 'wc_memberships_for_teams_get_team' ) ) {
			return [];
		}

		$team = is_object( $team_or_id ) ? $team_or_id : wc_memberships_for_teams_get_team( $team_or_id );

		if ( ! $team || ! is_object( $team ) ) {
			return [];
		}

		$context = [];

		if ( method_exists( $team, 'get_id' ) ) {
			$context['team_id'] = $team->get_id();
		}

		if ( method_exists( $team, 'get_name' ) ) {
			$context['team_name'] = $team->get_name();
		}

		if ( method_exists( $team, 'get_owner_id' ) ) {
			$context['team_owner_id'] = $team->get_owner_id();
		}

		if ( method_exists( $team, 'get_seat_count' ) ) {
			$context['team_seat_count'] = $team->get_seat_count();
		}

		if ( method_exists( $team, 'get_used_seat_count' ) ) {
			$context['team_used_seats'] = $team->get_used_seat_count();
		}

		if ( method_exists( $team, 'get_member_count' ) ) {
			$context['team_member_count'] = $team->get_member_count();
		}

		if ( method_exists( $team, 'get_plan' ) ) {
			$plan = $team->get_plan();
			if ( is_object( $plan ) ) {
				if ( method_exists( $plan, 'get_id' ) ) {
					$context['membership_plan_id'] = $plan->get_id();
				}
				if ( method_exists( $plan, 'get_name' ) ) {
					$context['membership_plan_name'] = $plan->get_name();
				}
			}
		}

		$team_post_id = isset( $context['team_id'] ) ? $context['team_id'] : 0;

		if ( $team_post_id ) {
			$product_id = get_post_meta( $team_post_id, '_product_id', true );
			if ( $product_id ) {
				$context['product_id'] = $product_id;
			}

			$order_id = get_post_meta( $team_post_id, '_order_id', true );
			if ( $order_id ) {
				$context['order_id'] = $order_id;
			}

			$end_date = get_post_meta( $team_post_id, '_membership_end_date', true );
			if ( $end_date ) {
				$context['membership_end_date'] = $end_date;
			}
		}

		return $context;
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'WooCommerce' )
			&& class_exists( 'WC_Memberships_Loader' )
			&& class_exists( 'WC_Memberships_For_Teams_Loader' );
	}

}

IntegrationsController::register( TeamsForWoocommerceMemberships::class );
