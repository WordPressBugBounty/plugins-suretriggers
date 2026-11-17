<?php
/**
 * RecipeSuggestions.
 * php version 5.6
 *
 * @category RecipeSuggestions
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.22
 */

namespace SureTriggers\Admin;

use SureTriggers\Controllers\IntegrationsController;

/**
 * RecipeSuggestions
 *
 * Suggests recipes on OttoKit › Status for sites that have no workflow running yet. Recipes are picked from the
 * plugins installed on the site, using the OttoKit public recipes API (cached in a transient).
 *
 * @category RecipeSuggestions
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.22
 *
 * @phpstan-type Icon array{name: string, label: string}
 * @phpstan-type Recipe array{id: string, title: string, description: string, try_url: string, icons: array<int, Icon>, is_pro: bool}
 * @phpstan-type TieredRecipe array{id: string, title: string, description: string, try_url: string, icons: array<int, Icon>, is_pro: bool, tier: int}
 */
class RecipeSuggestions {

	const TRANSIENT_PREFIX  = 'suretriggers_recipe_suggestions_';
	const CACHE_TTL         = 12 * HOUR_IN_SECONDS;
	const FAILURE_CACHE_TTL = 15 * MINUTE_IN_SECONDS;
	const MAX_RECIPES       = 6;
	const MAX_LOOKUPS       = 5;
	const WORDPRESS         = 'WordPress';
	const RECIPE_URL        = 'https://ottokit.com/automations/';
	const ICON_URL          = 'https://ottokit.s3.us-west-2.amazonaws.com/production/';
	const MAX_ICONS         = 3;
	const ACTIVE_LIMIT      = 12;
	const WORDPRESS_LIMIT   = 8;
	const MAX_PAIRS         = 4;
	const PAIR_LIMIT        = 3;
	const CACHE_VERSION     = '4';
	const TRIGGER_BUTTON    = 'TriggerButton';

	// Priority tiers, best first: 2+ installed plugins, 1 installed plugin + WordPress, 1 installed plugin, the rest.
	const TIER_COMBO     = 0;
	const TIER_PLUGIN_WP = 1;
	const TIER_PLUGIN    = 2;
	const TIER_OTHER     = 3;

	/**
	 * Get recipe suggestions for the plugins installed on this site, from cache when possible.
	 *
	 * @return array<int, Recipe>
	 */
	public static function get_recipes() {
		$active = IntegrationsController::get_activated_integrations();
		$active = is_array( $active ) ? array_values( array_filter( $active, 'is_string' ) ) : [];

		// The version makes sites pick up a change in how recipes are chosen without waiting for the old cache to expire.
		$key    = self::TRANSIENT_PREFIX . md5( self::CACHE_VERSION . '|' . implode( ',', $active ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			/**
			 * Shape is guaranteed by the write below.
			 *
			 * @var array<int, Recipe> $cached
			 */
			return $cached;
		}

		// Plugins OttoKit supports that are not installed here: recipes that need them are skipped.
		$missing = array_values( array_diff( array_keys( (array) IntegrationsController::get_integrations() ), $active, [ self::WORDPRESS ] ) );

		// WordPress and TriggerButton ship with OttoKit, so they say nothing about the plugins this site runs.
		// TriggerButton always reports itself as active; keeping it here would crowd out WordPress recipes on sites with no plugins.
		$installed = array_values( array_diff( $active, [ self::TRIGGER_BUTTON ] ) );

		// With no plugin installed, fall back to the built-in pair so the list still fills up: WordPress recipes lead, TriggerButton ones follow.
		if ( empty( $installed ) ) {
			$installed = [ self::TRIGGER_BUTTON ];
		}

		$recipes = self::interleave( self::fetch_all( self::get_lookups( $installed ), $missing, $installed ) );

		set_transient( $key, $recipes, empty( $recipes ) ? self::FAILURE_CACHE_TTL : self::CACHE_TTL );

		return $recipes;
	}

	/**
	 * Decide what to ask the recipes API, all in one parallel round.
	 *
	 * The API returns only recipes that use every name it is given, so a pair of installed plugins gets exactly the recipes that
	 * combine them: the ones worth showing first. Single lookups follow, for sites with one plugin or too few combos. WordPress
	 * itself is always there, so it gets a lookup too. Pairs are picked so that each of the first four plugins appears early.
	 *
	 * @param array<int, string> $active Integrations installed and active on this site.
	 * @return array<int, array<int, string>> Lists of OttoKit integration names, one API request each.
	 */
	private static function get_lookups( $active ) {
		$lookups = [];
		$head    = array_slice( $active, 0, 4 );
		$count   = count( $head );

		foreach ( [ [ 0, 1 ], [ 2, 3 ], [ 0, 2 ], [ 1, 3 ], [ 0, 3 ], [ 1, 2 ] ] as $pair ) {
			if ( count( $lookups ) >= self::MAX_PAIRS ) {
				break;
			}
			if ( $pair[0] < $count && $pair[1] < $count ) {
				$lookups[] = [ $head[ $pair[0] ], $head[ $pair[1] ] ];
			}
		}

		$singles = array_slice( array_merge( [ self::WORDPRESS ], $active ), 0, self::MAX_LOOKUPS );

		// WordPress and TriggerButton recipes always top the list up, so every site gets a full set even with few or no plugins.
		foreach ( [ self::WORDPRESS, self::TRIGGER_BUTTON ] as $built_in ) {
			if ( ! in_array( $built_in, $singles, true ) ) {
				$singles[] = $built_in;
			}
		}

		foreach ( $singles as $name ) {
			$lookups[] = [ $name ];
		}

		return $lookups;
	}

	/**
	 * Rank a recipe by how well it fits the plugins installed on this site (lower is better).
	 *
	 * @param array<int, string> $names  Integration names used by the recipe.
	 * @param array<int, string> $active Integrations installed and active on this site.
	 * @return int One of the TIER_* constants.
	 */
	private static function get_tier( $names, $active ) {
		$names = array_unique( $names );
		// A site with no supported plugin still has WordPress and TriggerButton, so their recipes rank above ones for apps the site does not run.
		// Once a plugin is installed its own recipes lead and WordPress-only ones fall back to the last tier, as before.
		$known     = array_diff( $active, [ self::TRIGGER_BUTTON ] ) ? $active : [ self::WORDPRESS, self::TRIGGER_BUTTON ];
		$installed = count( array_intersect( $names, $known ) );

		if ( $installed >= 2 ) {
			return self::TIER_COMBO;
		}
		if ( 1 === $installed ) {
			return in_array( self::WORDPRESS, $names, true ) ? self::TIER_PLUGIN_WP : self::TIER_PLUGIN;
		}

		return self::TIER_OTHER;
	}

	/**
	 * Merge recipe lists best tier first, and one recipe at a time within a tier so every integration is represented, without duplicates.
	 *
	 * @param array<int, array<int, TieredRecipe>> $lists Recipes per integration.
	 * @return array<int, Recipe>
	 */
	private static function interleave( $lists ) {
		$recipes = [];

		for ( $tier = self::TIER_COMBO; $tier <= self::TIER_OTHER; $tier++ ) {
			$tiered = [];
			$depth  = 0;
			foreach ( $lists as $list ) {
				$rows = [];
				foreach ( $list as $recipe ) {
					if ( $recipe['tier'] === $tier ) {
						$rows[] = $recipe;
					}
				}
				$depth    = max( $depth, count( $rows ) );
				$tiered[] = $rows;
			}

			for ( $i = 0; $i < $depth; $i++ ) {
				foreach ( $tiered as $rows ) {
					if ( isset( $rows[ $i ] ) && ! isset( $recipes[ $rows[ $i ]['id'] ] ) ) {
						$recipe = $rows[ $i ];
						unset( $recipe['tier'] );
						$recipes[ $recipe['id'] ] = $recipe;
					}
					if ( count( $recipes ) >= self::MAX_RECIPES ) {
						break 3;
					}
				}
			}
		}

		return array_values( $recipes );
	}

	/**
	 * Fetch the published recipes of several integrations from the public recipes API, in parallel.
	 *
	 * @param array<int, array<int, string>> $lookups OttoKit integration names per request, for example [ "WooCommerce", "Mailchimp" ].
	 * @param array<int, string>             $missing Supported plugins that are not installed; recipes using them are skipped.
	 * @param array<int, string>             $active  Integrations installed and active on this site; used to rank recipes.
	 * @return array<int, array<int, TieredRecipe>> Recipes per request, in the same order as $lookups.
	 */
	private static function fetch_all( $lookups, $missing, $active ) {
		/**
		 * Filters the OttoKit API base URL used to fetch recipe suggestions.
		 *
		 * @param string $url API base URL.
		 */
		$base = (string) apply_filters( 'suretriggers_recipe_suggestions_api_url', SURE_TRIGGERS_API_SERVER_URL );

		$requests = [];
		foreach ( $lookups as $index => $names ) {
			if ( count( $names ) > 1 ) {
				$limit = self::PAIR_LIMIT;
			} else {
				$limit = self::WORDPRESS === $names[0] ? self::WORDPRESS_LIMIT : self::ACTIVE_LIMIT;
			}
			$requests[ $index ] = [
				'url'  => add_query_arg(
					[
						'integration_names' => $names,
						'limit'             => $limit,
					],
					$base . '/directory/public/recipes'
				),
				'type' => 'GET',
			];
		}

		$bodies = self::request_all( $requests );

		$lists = [];
		foreach ( array_keys( $lookups ) as $index ) {
			$lists[] = isset( $bodies[ $index ] ) ? self::parse_recipes( $bodies[ $index ], $missing, $active ) : [];
		}

		return $lists;
	}

	/**
	 * Run GET requests in parallel with the Requests library bundled in WordPress, falling back to one by one.
	 *
	 * @param array<int, array{url: string, type: string}> $requests Requests keyed by position.
	 * @return array<int, string> Response bodies of the requests that returned 200, keyed by position.
	 */
	private static function request_all( $requests ) {
		$bodies  = [];
		$options = [
			'timeout'   => 8, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'verify'    => ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/certificates/ca-bundle.crt',
			'useragent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
		];

		// WordPress 6.2+ ships WpOrg\Requests\Requests; older versions ship the global Requests class.
		$class = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : '\Requests';
		if ( class_exists( $class ) && is_callable( [ $class, 'request_multiple' ] ) ) {
			try {
				$responses = call_user_func( [ $class, 'request_multiple' ], $requests, $options );
				if ( is_array( $responses ) ) {
					foreach ( $responses as $index => $response ) {
						if ( is_object( $response ) && isset( $response->status_code, $response->body ) && 200 === $response->status_code && is_string( $response->body ) ) {
							$bodies[ (int) $index ] = $response->body;
						}
					}
					return $bodies;
				}
			} catch ( \Exception $e ) {
				$bodies = [];
			}
		}

		foreach ( $requests as $index => $request ) {
			$response = wp_remote_get( $request['url'], [ 'timeout' => 5 ] ); //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$bodies[ $index ] = wp_remote_retrieve_body( $response );
			}
		}

		return $bodies;
	}

	/**
	 * Turn a public recipes API response into recipe suggestions.
	 *
	 * @param string             $raw     Response body.
	 * @param array<int, string> $missing Supported plugins that are not installed; recipes using them are skipped.
	 * @param array<int, string> $active  Integrations installed and active on this site; used to rank recipes.
	 * @return array<int, TieredRecipe>
	 */
	private static function parse_recipes( $raw, $missing, $active ) {
		$body  = json_decode( $raw, true );
		$items = is_array( $body ) && isset( $body['data']['recipes'] ) && is_array( $body['data']['recipes'] ) ? $body['data']['recipes'] : [];

		$recipes = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || ! is_numeric( $item['id'] ) || empty( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}

			// Trigger first, then the actions, as OttoKit integration names (used for the icon files).
			$icons = [];
			if ( isset( $item['trigger'] ) && is_array( $item['trigger'] ) ) {
				$icons[] = $item['trigger'];
			}
			if ( isset( $item['actions'] ) && is_array( $item['actions'] ) ) {
				foreach ( $item['actions'] as $action ) {
					$icons[] = $action;
				}
			}

			$needs_missing_plugin = false;
			$used_names           = [];
			foreach ( $icons as $icon ) {
				if ( is_array( $icon ) && isset( $icon['integration_name'] ) && is_string( $icon['integration_name'] ) ) {
					// The API may suffix a name with "@..." (a variant of the same integration).
					$parts        = explode( '@', $icon['integration_name'], 2 );
					$used_names[] = $parts[0];
				}
				if ( is_array( $icon ) && isset( $icon['integration_name'] ) && in_array( $icon['integration_name'], $missing, true ) ) {
					$needs_missing_plugin = true;
					break;
				}
			}
			if ( $needs_missing_plugin ) {
				continue;
			}

			$icon_list = [];
			foreach ( $icons as $icon ) {
				if ( ! is_array( $icon ) || empty( $icon['integration_name'] ) || ! is_string( $icon['integration_name'] ) ) {
					continue;
				}
				$name = preg_replace( '/[^A-Za-z0-9_-]/', '', $icon['integration_name'] );
				if ( ! is_string( $name ) || '' === $name ) {
					continue;
				}
				$label = isset( $icon['display_name'] ) && is_string( $icon['display_name'] ) ? $icon['display_name'] : $name;
				if ( isset( $icon_list[ $name ] ) ) {
					continue;
				}
				$icon_list[ $name ] = [
					'name'  => $name,
					'label' => sanitize_text_field( $label ),
				];
			}

			$recipes[] = [
				'id'          => (string) absint( $item['id'] ),
				'title'       => sanitize_text_field( $item['title'] ),
				'description' => isset( $item['description'] ) && is_string( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '',
				'try_url'     => self::RECIPE_URL . absint( $item['id'] ) . '?utm_source=wpplugin&utm_medium=status+page&utm_campaign=recipe+suggestions',
				'icons'       => array_slice( array_values( $icon_list ), 0, self::MAX_ICONS ),
				'is_pro'      => ! empty( $item['is_pro'] ),
				'tier'        => self::get_tier( $used_names, $active ),
			];
		}

		return $recipes;
	}

	/**
	 * Render the empty state for the Status page: a hero and recipe cards picked from installed plugins.
	 *
	 * @return void
	 */
	public static function render() {
		$recipes = current_user_can( 'manage_options' ) ? self::get_recipes() : [];
		self::render_styles();

		echo '<section class="ottokit-recipes" aria-labelledby="ottokit-recipes-title">';
		echo '<h2 id="ottokit-recipes-title" class="ottokit-recipes__title">' . esc_html__( 'No automations are running on this site yet', 'suretriggers' ) . '</h2>';
		echo '<p class="ottokit-recipes__sub">' . esc_html( $recipes ? __( 'We spotted these plugins on your site. Start from a recipe. Most take under 2 minutes.', 'suretriggers' ) : __( 'Start from a ready-made recipe. Most take under 2 minutes.', 'suretriggers' ) ) . '</p>';

		if ( $recipes ) {
			echo '<ul class="ottokit-recipes__list">';
			foreach ( $recipes as $recipe ) {
				self::render_card( $recipe );
			}
			echo '</ul>';
		}

		echo '<div class="ottokit-recipes__foot">';
		echo '<a class="ottokit-recipes__primary" href="' . esc_url( admin_url( 'admin.php?page=suretriggers&redirect_url=/recipes' ) ) . '">' . esc_html__( 'Browse all recipes', 'suretriggers' ) . '</a>';
		echo '</div></section>';

		self::render_script();
	}

	/**
	 * Render one recipe card.
	 *
	 * @param Recipe $recipe Recipe.
	 * @return void
	 */
	private static function render_card( $recipe ) {
		$button_text = __( 'Try now', 'suretriggers' );

		echo '<li class="ottokit-recipes__card">';
		echo '<div class="ottokit-recipes__head"><div class="ottokit-recipes__logos">';
		foreach ( $recipe['icons'] as $icon ) {
			// The initials sit behind the logo and show when the logo cannot be loaded.
			echo '<span class="ottokit-recipes__logo" title="' . esc_attr( $icon['label'] ) . '">' . esc_html( strtoupper( substr( $icon['label'], 0, 2 ) ) );
			echo '<img src="' . esc_url( self::ICON_URL . rawurlencode( $icon['name'] ) . '.png' ) . '" alt="" width="44" height="44" loading="lazy" />';
			echo '</span>';
		}
		echo '</div><div class="ottokit-recipes__cta">';
		if ( $recipe['is_pro'] ) {
			echo '<span class="ottokit-recipes__pro">' . esc_html__( 'PRO', 'suretriggers' ) . '</span>';
		}
		echo '<a class="ottokit-recipes__btn" target="_blank" rel="noopener noreferrer" href="' . esc_url( $recipe['try_url'] ) . '" aria-label="' . esc_attr( $button_text . ': ' . $recipe['title'] ) . '">';
		echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 4l14 8-14 8z"/></svg>';
		echo esc_html( $button_text ) . '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'suretriggers' ) . '</span></a>';
		echo '</div></div>';
		echo '<div class="ottokit-recipes__body"><h3 class="ottokit-recipes__name">' . esc_html( $recipe['title'] ) . '</h3>';
		if ( '' !== $recipe['description'] ) {
			echo '<p class="ottokit-recipes__desc">' . esc_html( $recipe['description'] ) . '</p>';
		}
		echo '</div></li>';
	}

	/**
	 * Print the stylesheet for the recipe suggestions.
	 *
	 * @return void
	 */
	private static function render_styles() {
		echo '<style>
.ottokit-recipes{padding:16px 8px 8px}
.ottokit-recipes__title{margin:0;padding:0;font-size:18px;font-weight:500;line-height:1.4;color:#1d2327}
.ottokit-recipes__sub{margin:4px 0 0;font-size:14px;color:#50575e}
.ottokit-recipes__primary{display:inline-block;margin-left:auto;padding:10px 20px;border-radius:8px;background:#16171b;color:#fff;font-weight:600;text-decoration:none;transition:background-color .15s}
.ottokit-recipes__primary:hover{background:#2c2e35;color:#fff}
.ottokit-recipes__primary:focus-visible,.ottokit-recipes__btn:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
.ottokit-recipes__foot{display:flex;align-items:center;justify-content:space-between;gap:12px 16px;flex-wrap:wrap;margin-top:4px}
.ottokit-recipes__list{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:18px 0;padding:0;list-style:none}
.ottokit-recipes__card{display:flex;flex-direction:column;margin:0;overflow:hidden;background:#fff;border:1px solid #e1e3d8;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:border-color .15s,box-shadow .15s,transform .15s}
.ottokit-recipes__card:hover,.ottokit-recipes__card:focus-within{border-color:#c3c4c7;box-shadow:0 10px 24px rgba(22,23,27,.1)}
.ottokit-recipes__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #eceee6}
.ottokit-recipes__logos{display:inline-flex;overflow:hidden;border:1px solid #e1e3d8;border-radius:10px;background:#fff}
.ottokit-recipes__logo{position:relative;display:grid;place-items:center;width:46px;height:46px;overflow:hidden;color:#50575e;font-size:11px;font-weight:700}
.ottokit-recipes__logo+.ottokit-recipes__logo{border-left:1px solid #e1e3d8}
.ottokit-recipes__logo img{position:absolute;inset:0;width:100%;height:100%;padding:8px;box-sizing:border-box;object-fit:contain;background:#fff}
.ottokit-recipes__cta{display:flex;align-items:center;gap:10px}
.ottokit-recipes__btn{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;color:#3c434a;font-size:14px;font-weight:500;text-decoration:none;transition:border-color .15s,background-color .15s,color .15s}
.ottokit-recipes__btn:hover{border-color:#16171b;background:#16171b;color:#fff}
.ottokit-recipes__pro{padding:2px 9px;border-radius:999px;background:#cfe94e;color:#2f3800;font-size:11px;font-weight:700;letter-spacing:.04em}
.ottokit-recipes__body{padding:16px 16px 20px}
.ottokit-recipes__name{margin:0 0 6px;padding:0;font-size:16px;font-weight:600;line-height:1.4;color:#111}
.ottokit-recipes__desc{display:-webkit-box;margin:0;overflow:hidden;font-size:14px;line-height:1.55;color:#6b7280;-webkit-line-clamp:2;-webkit-box-orient:vertical}
@media (prefers-reduced-motion:no-preference){.ottokit-recipes__card:hover{transform:translateY(-2px)}}
@media (max-width:600px){.ottokit-recipes__list{grid-template-columns:1fr}.ottokit-recipes__primary{width:100%;text-align:center}}
</style>';
	}

	/**
	 * Hide a recipe logo that fails to load, so the initials behind it show instead.
	 *
	 * @return void
	 */
	private static function render_script() {
		echo '<script>document.querySelectorAll(".ottokit-recipes__logo img").forEach(function(i){function h(){i.style.display="none"}if(i.complete&&i.naturalWidth===0){h()}i.addEventListener("error",h)});</script>';
	}
}
