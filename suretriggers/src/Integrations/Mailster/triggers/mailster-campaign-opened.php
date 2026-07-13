<?php
/**
 * MailsterCampaignOpened trigger.
 * php version 5.6
 *
 * @category MailsterCampaignOpened
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
 * MailsterCampaignOpened
 *
 * @category MailsterCampaignOpened
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class MailsterCampaignOpened {

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
	public $trigger = 'mailster_campaign_opened';

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
			'label'         => __( 'Campaign Opened', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'mailster_open',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 3,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * Fires from Mailster's `mailster_open` action (classes/frontpage.class.php)
	 * when a subscriber opens a campaign with open tracking enabled.
	 *
	 * @param int      $subscriber_id  Subscriber ID.
	 * @param int      $campaign_id    Campaign ID.
	 * @param int|null $campaign_index Send index for this campaign/subscriber pair.
	 * @return void
	 */
	public function trigger_listener( $subscriber_id, $campaign_id, $campaign_index = null ) {
		if ( empty( $subscriber_id ) || ! function_exists( 'mailster' ) ) {
			return;
		}

		$subscriber = mailster( 'subscribers' )->get( absint( $subscriber_id ) );

		$context = [
			'subscriber_id'  => absint( $subscriber_id ),
			'email'          => is_object( $subscriber ) && isset( $subscriber->email ) ? sanitize_email( (string) $subscriber->email ) : '',
			'campaign_id'    => absint( $campaign_id ),
			'campaign_title' => get_the_title( absint( $campaign_id ) ),
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

MailsterCampaignOpened::get_instance();
