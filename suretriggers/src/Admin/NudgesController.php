<?php
/**
 * NudgesController — usage nudges for free users inside wp-admin.
 * php version 5.6
 *
 * @category NudgesController
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.22
 */

namespace SureTriggers\Admin;

use SureTriggers\Models\SaasApiToken;
use SureTriggers\Traits\SingletonLoader;

/**
 * NudgesController
 *
 * Fetches a slim usage summary from the OttoKit SaaS (cached in a transient) and uses it for:
 *  - a WordPress Dashboard widget (tasks used, 80% warning)
 *  - a usage badge on the OttoKit admin menu once usage passes 80%
 *
 * The Pro card is always visible to free users; the 80% warning and menu badge wait until the first workflow has been published.
 *
 * @category NudgesController
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.22
 *
 * @phpstan-type UsageSummary array{plan_slug: string, is_free: bool, has_published_automation: bool, task_allocated: int, task_consumed: int, task_percent: int, remaining_days: int, workflows_allocated: int, workflows_consumed: int, tasks_lifetime: int, upgrade: array{plan_name: string, price: int, tasks: int}|null}
 */
class NudgesController {

	use SingletonLoader;

	const TRANSIENT_KEY     = 'suretriggers_usage_summary_v2';
	const CACHE_TTL         = 6 * HOUR_IN_SECONDS;
	const FAILURE_CACHE_TTL = 15 * MINUTE_IN_SECONDS;
	const WARNING_THRESHOLD = 80;
	const PRICING_URL       = 'https://ottokit.com/pricing/?utm_source=wpplugin&utm_medium=dashboard+widget&utm_campaign=usage+nudge';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_badge' ], 999 );
		add_filter( 'get_user_option_meta-box-order_dashboard', [ $this, 'prepend_widget_to_saved_order' ] );
	}

	/**
	 * Get the usage summary, from cache when possible.
	 *
	 * @return UsageSummary|null Null when not connected or the SaaS is unreachable.
	 */
	public static function get_usage_summary() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			if ( empty( $cached ) ) {
				return null;
			}
			/**
			 * Shape is guaranteed by the write below.
			 *
			 * @var UsageSummary $cached
			 */
			return $cached;
		}
		if ( false !== $cached ) {
			return null;
		}

		$token = SaasApiToken::get();
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		$response = wp_remote_post(
			SURE_TRIGGERS_API_SERVER_URL . '/connection/wordpress/usage',
			[
				'body'    => [
					'saas-token' => $token,
					'base_url'   => str_replace( '/wp-json/', '', get_rest_url() ),
				],
				'timeout' => 4, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache the failure briefly so wp-admin pages don't retry on every load.
			set_transient( self::TRANSIENT_KEY, [], self::FAILURE_CACHE_TTL );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : null;
		if ( ! $data ) {
			set_transient( self::TRANSIENT_KEY, [], self::FAILURE_CACHE_TTL );
			return null;
		}

		$summary = [
			'plan_slug'                => isset( $data['plan_slug'] ) ? sanitize_text_field( $data['plan_slug'] ) : '',
			'is_free'                  => ! empty( $data['is_free'] ),
			'has_published_automation' => ! empty( $data['has_published_automation'] ),
			'task_allocated'           => self::to_int( isset( $data['task_allocated'] ) ? $data['task_allocated'] : 0 ),
			'task_consumed'            => self::to_int( isset( $data['task_consumed'] ) ? $data['task_consumed'] : 0 ),
			'task_percent'             => self::to_int( isset( $data['task_consumed_percent'] ) ? $data['task_consumed_percent'] : 0 ),
			'remaining_days'           => self::to_int( isset( $data['remaining_days'] ) ? $data['remaining_days'] : 0 ),
			'workflows_allocated'      => isset( $data['workflows_allocated'] ) && is_numeric( $data['workflows_allocated'] ) ? (int) $data['workflows_allocated'] : 0,
			'workflows_consumed'       => isset( $data['workflows_consumed'] ) && is_numeric( $data['workflows_consumed'] ) ? (int) $data['workflows_consumed'] : 0,
			'tasks_lifetime'           => isset( $data['tasks_lifetime'] ) && is_numeric( $data['tasks_lifetime'] ) ? (int) $data['tasks_lifetime'] : 0,
			'upgrade'                  => null,
		];

		if ( isset( $data['upgrade'] ) && is_array( $data['upgrade'] ) && isset( $data['upgrade']['plan_name'], $data['upgrade']['price'], $data['upgrade']['tasks'] ) ) {
			$summary['upgrade'] = [
				'plan_name' => sanitize_text_field( (string) $data['upgrade']['plan_name'] ),
				'price'     => self::to_int( $data['upgrade']['price'] ),
				'tasks'     => self::to_int( $data['upgrade']['tasks'] ),
			];
		}

		set_transient( self::TRANSIENT_KEY, $summary, self::CACHE_TTL );

		return $summary;
	}

	/**
	 * Convert a formatted number ("1,250", "80%") to an int.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function to_int( $value ) {
		return (int) preg_replace( '/[^0-9]/', '', (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Whether the current user may see the nudges.
	 *
	 * @return bool
	 */
	private function can_view() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register the Dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! $this->can_view() || ! get_option( 'suretrigger_options' ) ) {
			return;
		}
		// The widget exists to show free users what they get with a paid plan, so paid plans never see it.
		$usage = self::get_usage_summary();
		if ( ! $usage || ! $usage['is_free'] ) {
			return;
		}
		$label = __( 'OttoKit · Free plan', 'suretriggers' );
		wp_add_dashboard_widget(
			'suretriggers_usage_widget',
			'<span style="display:inline-flex;align-items:center"><span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;margin-right:8px;border-radius:6px;background:#cfe94e"><span style="width:8px;height:8px;border:3px solid #16171b;border-radius:50%"></span></span>' . esc_html( $label ) . '</span>',
			[ $this, 'render_dashboard_widget' ]
		);

		// Show the widget first in the left column (users can still reorder it).
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['suretriggers_usage_widget'] ) ) {
			$widget = $wp_meta_boxes['dashboard']['normal']['core']['suretriggers_usage_widget'];
			unset( $wp_meta_boxes['dashboard']['normal']['core']['suretriggers_usage_widget'] );
			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( [ 'suretriggers_usage_widget' => $widget ], $wp_meta_boxes['dashboard']['normal']['core'] ); //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Put the widget first for users who already saved a Dashboard order, until they move it themselves.
	 *
	 * @param mixed $order Saved order: column => comma-separated widget IDs.
	 * @return mixed
	 */
	public function prepend_widget_to_saved_order( $order ) {
		if ( ! is_array( $order ) || ! isset( $order['normal'] ) || ! is_string( $order['normal'] ) ) {
			return $order;
		}
		foreach ( $order as $ids ) {
			if ( is_string( $ids ) && false !== strpos( $ids, 'suretriggers_usage_widget' ) ) {
				return $order;
			}
		}
		$order['normal'] = '' === $order['normal'] ? 'suretriggers_usage_widget' : 'suretriggers_usage_widget,' . $order['normal'];
		return $order;
	}

	/**
	 * Render a labelled usage bar.
	 *
	 * @param string $label   Row label.
	 * @param string $value   Right-aligned value, already formatted.
	 * @param int    $percent Fill percentage.
	 * @param string $color   Bar colour.
	 * @return void
	 */
	private function render_bar( $label, $value, $percent, $color ) {
		echo '<div><div style="display:flex;justify-content:space-between;font-size:13px"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		echo '<div style="height:8px;margin:4px 0 0;background:#e8eae3;border-radius:4px;overflow:hidden"><div style="height:100%;width:' . (int) $percent . '%;background:' . esc_attr( $color ) . '"></div></div></div>';
	}

	/**
	 * Render a stat tile.
	 *
	 * @param string $value Big number.
	 * @param string $label Caption.
	 * @return void
	 */
	private function render_tile( $value, $label ) {
		echo '<div style="flex:1;min-width:0;padding:10px 12px;background:#f3f4ee;border-radius:8px"><strong style="display:block;font-size:18px;line-height:1.25">' . esc_html( $value ) . '</strong><span style="font-size:12.5px;color:#50575e">' . esc_html( $label ) . '</span></div>';
	}

	/**
	 * Render the Dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$usage = self::get_usage_summary();
		if ( ! $usage ) {
			echo '<p>' . esc_html__( 'Usage is unavailable right now. Please check again later.', 'suretriggers' ) . '</p>';
			return;
		}

		$warn    = $this->should_warn( $usage );
		$percent = min( 100, max( 0, $usage['task_percent'] ) );
		$color   = $warn ? ( $usage['task_percent'] >= 100 ? '#d63638' : '#dba617' ) : '#7c9a1f';
		$tasks   = max( $usage['task_consumed'], $usage['tasks_lifetime'] );

		echo '<div style="display:flex;flex-direction:column;gap:12px">';

		// Usage ring with the tasks used this month.
		echo '<div style="display:flex;align-items:center;gap:14px">';
		if ( $usage['task_allocated'] > 0 ) {
			$circ   = 163.36;
			$offset = round( $circ * ( 1 - $percent / 100 ), 1 );
			echo '<svg width="64" height="64" viewBox="0 0 64 64" role="img" aria-label="' . esc_attr( $percent . '%' ) . '" style="flex:none">';
			echo '<circle cx="32" cy="32" r="26" fill="none" stroke="#e8eae3" stroke-width="8"/>';
			echo '<circle cx="32" cy="32" r="26" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="8" stroke-linecap="round" stroke-dasharray="' . esc_attr( (string) $circ ) . '" stroke-dashoffset="' . esc_attr( (string) $offset ) . '" transform="rotate(-90 32 32)"/>';
			echo '<text x="32" y="36" text-anchor="middle" font-size="13" font-weight="700" fill="#1d2327">' . esc_html( $percent . '%' ) . '</text></svg>';
			echo '<div><strong style="display:block;font-size:20px;line-height:1.1">' . esc_html( number_format_i18n( $usage['task_consumed'] ) . ' / ' . number_format_i18n( $usage['task_allocated'] ) ) . ' ' . esc_html__( 'tasks', 'suretriggers' ) . '</strong>';
		} else {
			echo '<div><strong style="display:block;font-size:20px;line-height:1.1">' . esc_html( number_format_i18n( $usage['task_consumed'] ) ) . ' ' . esc_html__( 'tasks', 'suretriggers' ) . '</strong>';
		}
		$reset = date_i18n( 'M j', time() + $usage['remaining_days'] * DAY_IN_SECONDS );
		/* translators: %s: date tasks reset. */
		echo '<span style="color:#50575e;font-size:12.5px">' . esc_html( sprintf( __( 'used this month · resets %s', 'suretriggers' ), $reset ) ) . '</span></div></div>';

		if ( $usage['workflows_allocated'] > 0 ) {
			$wf_percent = min( 100, (int) round( $usage['workflows_consumed'] / $usage['workflows_allocated'] * 100 ) );
			$this->render_bar(
				__( 'Workflows', 'suretriggers' ),
				number_format_i18n( $usage['workflows_consumed'] ) . ' / ' . number_format_i18n( $usage['workflows_allocated'] ),
				$wf_percent,
				$wf_percent >= 100 ? '#d63638' : ( $wf_percent >= 90 ? '#dba617' : '#7c9a1f' )
			);
		}

		// About 30 seconds of manual work saved per task.
		$hours = $tasks * 30 / 3600;
		echo '<div style="display:flex;gap:8px">';
		if ( $tasks <= 0 ) {
			$saved = __( '0 min', 'suretriggers' );
		} elseif ( $hours < 1 ) {
			/* translators: %s: estimated minutes saved. */
			$saved = sprintf( __( '~%s min', 'suretriggers' ), number_format_i18n( max( 1, (int) round( $hours * 60 ) ) ) );
		} else {
			/* translators: %s: estimated hours saved. */
			$saved = sprintf( __( '~%s hrs', 'suretriggers' ), number_format_i18n( round( $hours, $hours < 10 ? 1 : 0 ), $hours < 10 ? 1 : 0 ) );
		}
		$this->render_tile( $saved, __( 'saved by automations', 'suretriggers' ) );
		$this->render_tile( number_format_i18n( $tasks ), __( 'tasks run since install', 'suretriggers' ) );
		echo '</div>';

		if ( $warn ) {
			$this->render_warning( $usage );
		}

		// The Pro card shows in every state for free users, so the paid plan is always visible.
		// Collapsed to one line by default; expanded near the limit.
		if ( $usage['is_free'] ) {
			$this->render_pro_card( $usage['upgrade'], $usage['task_allocated'], $warn );
		}

		echo '</div>';
	}

	/**
	 * Render the usage warning notice.
	 *
	 * @param UsageSummary $usage Usage summary.
	 * @return void
	 */
	private function render_warning( $usage ) {
		$limit = $usage['task_percent'] >= 100;
		if ( $limit ) {
			$lead = __( 'You have reached your task limit.', 'suretriggers' );
			$rest = __( 'Workflows stay paused until tasks reset or you upgrade.', 'suretriggers' );
		} else {
			/* translators: %d: percent of tasks used. */
			$lead = sprintf( __( '%d%% of your monthly tasks used.', 'suretriggers' ), $usage['task_percent'] );
			/* translators: %s: tasks allowed per month. */
			$rest = sprintf( __( 'Workflows pause when you hit %s.', 'suretriggers' ), number_format_i18n( $usage['task_allocated'] ) );
		}
		$style = $limit ? 'background:#fdecea;border:1px solid #f1b5ae;color:#7a1a12' : 'background:#fffaea;border:1px solid #ead590;color:#3c2e00';
		echo '<div style="padding:10px 12px;border-radius:6px;font-size:13px;line-height:1.5;' . esc_attr( $style ) . '"><strong>' . esc_html( $lead ) . '</strong> ' . esc_html( $rest ) . '</div>';
	}

	/**
	 * Render the Pro card: collapsed to one line by default, expandable on click, open near the limit.
	 *
	 * @param array{plan_name: string, price: int, tasks: int}|null $upgrade   Upgrade plan, when the SaaS returned one.
	 * @param int                                                   $allocated Tasks allowed per month on the current plan.
	 * @param bool                                                  $open      Whether the card starts expanded.
	 * @return void
	 */
	private function render_pro_card( $upgrade, $allocated, $open = false ) {
		$li   = 'display:flex;align-items:center;gap:8px;margin:0 0 8px;font-size:13px';
		$dot  = '<span style="width:6px;height:6px;border-radius:50%;background:#7c9a1f;flex:none"></span>';
		$end  = 'margin-left:auto;color:#50575e;font-size:12.5px;white-space:nowrap';
		$pill = 'margin-left:auto;padding:1px 9px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap;';

		$multiple = $upgrade && $upgrade['tasks'] > 0 && $allocated > 0 ? (int) round( $upgrade['tasks'] / $allocated ) : 0;
		if ( $upgrade && $upgrade['tasks'] > 0 ) {
			/* translators: %d: how many times more tasks Pro includes. */
			$label = $multiple > 1 ? sprintf( __( '%d× more tasks', 'suretriggers' ), $multiple ) : __( 'More tasks every month', 'suretriggers' );
		} else {
			$label = __( 'Unlock Pro features', 'suretriggers' );
		}

		echo '<style>#suretriggers-pro-card summary{list-style:none;cursor:pointer}#suretriggers-pro-card summary::-webkit-details-marker{display:none}#suretriggers-pro-card .st-tog{margin-left:auto;display:inline-flex;align-items:center;gap:4px;padding:4px 8px 4px 10px;border-radius:999px;border:1px solid #cdd6a8;background:#fff;color:#1d2327;font-size:12.5px;font-weight:600;white-space:nowrap;transition:background .15s,border-color .15s}#suretriggers-pro-card summary:hover .st-tog{background:#16171b;border-color:#16171b;color:#fff}#suretriggers-pro-card .st-chev{display:block;transition:transform .15s}#suretriggers-pro-card[open] .st-chev{transform:rotate(180deg)}#suretriggers-pro-card .st-hide,#suretriggers-pro-card[open] .st-show{display:none}#suretriggers-pro-card[open] .st-hide{display:inline}#suretriggers-pro-card:hover{border-color:#b9c48a!important}#suretriggers-pro-card summary{outline:none}#suretriggers-pro-card.st-kbd summary:focus-visible .st-tog{box-shadow:0 0 0 2px #f5f8e6,0 0 0 4px #2271b1}</style>';
		echo '<details id="suretriggers-pro-card" style="padding:12px 14px;border:1px solid #d6dcc0;border-radius:8px;background:#f5f8e6"' . ( $open ? ' open' : '' ) . '>';
		echo '<summary style="display:flex;align-items:center;flex-wrap:wrap;gap:6px 10px"><span style="padding:2px 8px;border-radius:999px;background:#16171b;color:#cfe94e;font-size:11.5px;font-weight:700;letter-spacing:.05em">PRO</span>';
		echo '<span style="font-size:13.5px;font-weight:600;color:#1d2327">' . esc_html( $label ) . '</span>';
		if ( $upgrade && $upgrade['price'] > 0 ) {
			/* translators: %s: one-time price in USD. */
			echo '<span style="font-size:12.5px;color:#50575e">' . esc_html( sprintf( __( 'One-time $%s', 'suretriggers' ), number_format_i18n( $upgrade['price'] ) ) ) . '</span>';
		}
		echo '<span class="st-tog"><span class="st-show">' . esc_html__( 'See what you get', 'suretriggers' ) . '</span><span class="st-hide">' . esc_html__( 'Hide details', 'suretriggers' ) . '</span><svg class="st-chev" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M3 5.25 7 9.25l4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></summary>';
		echo '<ul style="margin:14px 0 12px;padding:12px 0 0;list-style:none;border-top:1px solid #dfe5c6">';

		if ( $upgrade && $upgrade['tasks'] > 0 ) {
			/* translators: 1: tasks per month on the current plan, 2: tasks per month on the upgrade plan. */
			$value = $allocated > 0 ? sprintf( __( '%1$s → %2$s / mo', 'suretriggers' ), number_format_i18n( $allocated ), number_format_i18n( $upgrade['tasks'] ) ) : sprintf( __( '%s / mo', 'suretriggers' ), number_format_i18n( $upgrade['tasks'] ) );
			echo '<li style="' . esc_attr( $li ) . '">' . $dot . '<span>' . esc_html__( 'Monthly tasks', 'suretriggers' ) . '</span><span style="' . esc_attr( $end ) . '">' . esc_html( $value ) . '</span></li>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<li style="' . esc_attr( $li ) . '">' . $dot . '<span>' . esc_html__( 'Schedule and Loop steps', 'suretriggers' ) . '</span><span style="' . esc_attr( $pill . 'background:#e6f0bd;color:#3d4a05' ) . '">' . esc_html__( 'Pro', 'suretriggers' ) . '</span></li>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<li style="' . esc_attr( $li ) . '">' . $dot . '<span>' . esc_html__( 'Paths, branching logic', 'suretriggers' ) . '</span><span style="' . esc_attr( $pill . 'background:#e4e7ee;color:#2c3338' ) . '">' . esc_html__( 'Business', 'suretriggers' ) . '</span></li>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</ul>';

		echo '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px"><a style="display:inline-block;padding:9px 16px;background:#16171b;color:#fff;border-radius:6px;font-weight:600;font-size:13px;text-decoration:none" target="_blank" rel="noopener noreferrer" href="' . esc_url( self::PRICING_URL ) . '">' . esc_html__( 'See Pro plans', 'suretriggers' ) . '</a>';
		echo '<span style="color:#50575e;font-size:12.5px">' . esc_html__( '14-day money-back guarantee', 'suretriggers' ) . '</span></div>';
		echo '</details>';
		// Show the focus ring only for keyboard users, not after a mouse click.
		echo '<script>(function(){var c=document.getElementById("suretriggers-pro-card");if(!c){return;}document.addEventListener("keydown",function(e){if(e.key==="Tab"){c.classList.add("st-kbd");}});c.addEventListener("mousedown",function(){c.classList.remove("st-kbd");});})();</script>';
	}

	/**
	 * Add a usage badge to the OttoKit menu once usage passes the warning threshold.
	 *
	 * @return void
	 */
	public function add_menu_badge() {
		global $menu;
		if ( ! $this->can_view() || ! is_array( $menu ) ) {
			return;
		}

		$usage = self::get_usage_summary();
		if ( ! $usage || ! $this->should_warn( $usage ) ) {
			return;
		}

		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'suretriggers' === $item[2] ) {
				$menu[ $key ][0] .= ' <span class="awaiting-mod" style="background:#d63638;color:#fff">' . esc_html( $usage['task_percent'] . '%' ) . '</span>'; //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}
	}

	/**
	 * Whether to show the 80% warning: free plan, limited tasks, past the threshold, and value already seen.
	 *
	 * @param UsageSummary $usage Usage summary.
	 * @return bool
	 */
	private function should_warn( $usage ) {
		return $usage['is_free']
			&& $usage['has_published_automation']
			&& $usage['task_allocated'] > 0
			&& $usage['task_percent'] >= self::WARNING_THRESHOLD;
	}
}
