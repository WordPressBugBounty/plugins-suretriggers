<?php
/**
 * AddTagToContact.
 * php version 5.6
 *
 * @category AddTagToContact
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BitCRM\Actions;

use BitApps\Crm\Deps\BitApps\WPKit\Helpers\Slug;
use BitApps\Crm\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Crm\Model\Contact;
use BitApps\Crm\Model\Tag;
use BitApps\Crm\Model\TagEntity;
use BitApps\Crm\Services\TagService;
use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\BitCRM\BitCRM;
use SureTriggers\Traits\SingletonLoader;

/**
 * AddTagToContact
 *
 * @category AddTagToContact
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AddTagToContact extends AutomateAction {


	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'BitCRM';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'bit_crm_add_tag_to_contact';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Tag to Contact', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selected_options.
	 *
	 * @return array
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\BitApps\Crm\Model\Contact' )
			|| ! class_exists( '\BitApps\Crm\Model\Tag' )
			|| ! class_exists( '\BitApps\Crm\Model\TagEntity' )
			|| ! class_exists( '\BitApps\Crm\Services\TagService' )
			|| ! class_exists( '\BitApps\Crm\Deps\BitApps\WPKit\Helpers\Slug' )
			|| ! class_exists( '\BitApps\Crm\Deps\BitApps\WPKit\Hooks\Hooks' )
		) {
			return [
				'status'  => 'error',
				'message' => 'Bit CRM plugin is not installed or activated.',
			];
		}

		$contact_id = absint( isset( $selected_options['contact_id'] ) ? $selected_options['contact_id'] : 0 );
		$tag_name   = isset( $selected_options['tag_name'] ) && is_string( $selected_options['tag_name'] ) ? sanitize_text_field( $selected_options['tag_name'] ) : '';

		if ( empty( $contact_id ) || '' === $tag_name ) {
			return [
				'status'  => 'error',
				'message' => 'Contact and tag name are required.',
			];
		}

		$contact = Contact::findOne(
			[
				'id'       => $contact_id,
				'is_trash' => 0,
			]
		);

		if ( empty( $contact ) ) {
			return [
				'status'  => 'error',
				'message' => 'Contact not found.',
			];
		}

		$tag = Tag::findOne(
			[
				'module' => Contact::MODULE_NAME,
				'slug'   => Slug::generate( $tag_name ),
			]
		);

		if ( empty( $tag ) ) {
			$result = ( new TagService() )->store(
				[
					'title'  => $tag_name,
					'module' => Contact::MODULE_NAME,
				]
			);

			if ( empty( $result['success'] ) ) {
				$error_message = BitCRM::get_first_error_message( isset( $result['errors'] ) ? $result['errors'] : [] );

				return [
					'status'  => 'error',
					'message' => '' !== $error_message ? $error_message : 'Failed to create tag.',
				];
			}

			$tag = $result['data'];
		}

		$tag_entity = [
			'module'    => Contact::MODULE_NAME,
			'tag_id'    => $tag->id,
			'entity_id' => $contact_id,
		];

		if ( ! TagEntity::findOne( $tag_entity ) ) {
			if ( ! TagEntity::insert( $tag_entity ) ) {
				return [
					'status'  => 'error',
					'message' => 'Failed to add tag to contact.',
				];
			}

			Hooks::doAction( 'bit_crm/tag_attached_to_contact', $tag, $contact_id );
		}

		return array_merge(
			[
				'tag_id'   => $tag->id,
				'tag_name' => $tag->title,
				'tag_slug' => $tag->slug,
			],
			BitCRM::get_entity_context( $contact )
		);
	}

}

AddTagToContact::get_instance();
