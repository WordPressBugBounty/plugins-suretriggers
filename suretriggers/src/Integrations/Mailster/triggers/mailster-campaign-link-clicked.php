<?php
/**
 * MailsterCampaignLinkClicked trigger.
 * php version 5.6
 *
 * @category MailsterCampaignLinkClicked
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Mailster\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

/**
 * MailsterCampaignLinkClicked
 *
 * @category MailsterCampaignLinkClicked
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterCampaignLinkClicked {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Mailster';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'mailster_campaign_link_clicked';

	use SingletonLoader;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
	}

	/**
	 * Register a trigger.
	 *
	 * @param array $triggers triggers.
	 * @return array
	 */
	public function register( $triggers ) {
		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( 'Campaign Link Clicked', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_click',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 5,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_click` action (classes/frontpage.class.php)
	 * when a subscriber clicks a tracked link inside a campaign.
	 *
	 * @param int      $subscriber_id  Subscriber ID.
	 * @param int      $campaign_id    Campaign ID.
	 * @param string   $target         The clicked link's destination URL.
	 * @param int|null $index          Link index within the campaign.
	 * @param int|null $campaign_index Send index for this campaign/subscriber pair.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id, $campaign_id, $target = '', $index = null, $campaign_index = null ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ) );

		$context = [
			'subscriber_id'  => absint( $subscriber_id ),
			'email'          => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'campaign_id'    => absint( $campaign_id ),
			'campaign_title' => get_the_title( absint( $campaign_id ) ),
			'link'           => is_string( $target ) ? esc_url_raw( $target ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterCampaignLinkClicked::get_instance();
