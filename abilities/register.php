<?php
/**
 * Abilities API registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register ability category.
 */
function dcp_register_ability_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'content-proposals',
		array(
			'label'       => __( 'Content Proposals', 'draft-changes' ),
			'description' => __( 'Propose edits to published content without changing the live version.', 'draft-changes' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'dcp_register_ability_category' );

/**
 * Register Abilities.
 */
function dcp_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'draft-changes/create-proposed-revision',
		array(
			'label'               => __( 'Create proposed revision', 'draft-changes' ),
			'description'         => __( 'Create a draft proposal of an already-published post or page. The live version stays published. Use this when asked to edit published content without publishing. Returns a proposal_id and edit_url. Does not publish.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'source_post_id' => array(
						'type'        => 'integer',
						'description' => 'ID of the published post or page to propose changes for.',
						'minimum'     => 1,
					),
				),
				'required'             => array( 'source_post_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'proposal_id'    => array( 'type' => 'integer' ),
					'source_post_id' => array( 'type' => 'integer' ),
					'edit_url'       => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'dcp_ability_create_proposed_revision',
			'permission_callback' => 'dcp_ability_can_create_proposed_revision',
			'meta'                => array(
				'show_in_rest' => true,
				'public'       => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	wp_register_ability(
		'draft-changes/update-proposed-revision',
		array(
			'label'               => __( 'Update proposed revision', 'draft-changes' ),
			'description'         => __( 'Update the title, content, and/or excerpt of an existing draft proposal. Does not affect the live published post. Does not publish.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'proposal_id' => array(
						'type'        => 'integer',
						'description' => 'ID of the proposal draft to update.',
						'minimum'     => 1,
					),
					'title'       => array(
						'type'        => 'string',
						'description' => 'New post title.',
					),
					'content'     => array(
						'type'        => 'string',
						'description' => 'New post content (block markup or HTML).',
					),
					'excerpt'     => array(
						'type'        => 'string',
						'description' => 'New post excerpt.',
					),
				),
				'required'             => array( 'proposal_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'proposal_id'  => array( 'type' => 'integer' ),
					'modified_gmt' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'dcp_ability_update_proposed_revision',
			'permission_callback' => 'dcp_ability_can_update_proposed_revision',
			'meta'                => array(
				'show_in_rest' => true,
				'public'       => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'draft-changes/list-proposals',
		array(
			'label'               => __( 'List proposals', 'draft-changes' ),
			'description'         => __( 'List open draft proposals for published content. Optionally filter by source_post_id.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'source_post_id' => array(
						'type'        => 'integer',
						'description' => 'Limit to proposals for this published post or page.',
						'minimum'     => 1,
					),
					'status'         => array(
						'type' => 'string',
						'enum' => array( 'draft', 'pending' ),
					),
					'per_page'       => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'           => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'items' => array( 'type' => 'array' ),
					'total' => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => 'dcp_ability_list_proposals',
			'permission_callback' => 'dcp_ability_can_list_proposals',
			'meta'                => array(
				'show_in_rest' => true,
				'public'       => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
				),
			),
		)
	);

	wp_register_ability(
		'draft-changes/get-proposal',
		array(
			'label'               => __( 'Get proposal', 'draft-changes' ),
			'description'         => __( 'Get a proposal draft including its source_post_id, status, title, content, and edit_url.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'proposal_id' => array(
						'type'        => 'integer',
						'description' => 'Proposal draft ID.',
						'minimum'     => 1,
					),
				),
				'required'             => array( 'proposal_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'proposal_id'    => array( 'type' => 'integer' ),
					'source_post_id' => array( 'type' => 'integer' ),
					'title'          => array( 'type' => 'string' ),
					'content'        => array( 'type' => 'string' ),
					'excerpt'        => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string' ),
					'edit_url'       => array( 'type' => 'string' ),
					'approved'       => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'dcp_ability_get_proposal',
			'permission_callback' => 'dcp_ability_can_get_proposal',
			'meta'                => array(
				'show_in_rest' => true,
				'public'       => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
				),
			),
		)
	);

	wp_register_ability(
		'draft-changes/publish-live',
		array(
			'label'               => __( 'Publish Live', 'draft-changes' ),
			'description'         => __( 'Apply an approved proposal onto the live published post or page (same URL), save a native revision for undo, then permanently delete the proposal. Requires a human to have approved the proposal first via draft-changes/approve-proposal (or Approve in the editor). Fails with dcp_not_approved otherwise. Prefer this after human review when asked to publish the proposal live.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'proposal_id' => array(
						'type'        => 'integer',
						'description' => 'ID of the approved proposal to publish live.',
						'minimum'     => 1,
					),
				),
				'required'             => array( 'proposal_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'source_post_id' => array( 'type' => 'integer' ),
					'applied'        => array( 'type' => 'boolean' ),
					'view_url'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'dcp_ability_publish_live',
			'permission_callback' => 'dcp_ability_can_publish_live',
			'meta'                => array(
				'show_in_rest' => true,
				'public'       => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'dcp_register_abilities' );

/**
 * @param array $input Input.
 * @return bool|WP_Error
 */
function dcp_ability_can_create_proposed_revision( $input ) {
	if ( ! dcp_user_can_create_proposals() ) {
		return false;
	}
	$source_id = isset( $input['source_post_id'] ) ? (int) $input['source_post_id'] : 0;
	if ( ! $source_id || ! current_user_can( 'edit_post', $source_id ) ) {
		return false;
	}
	return true;
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_create_proposed_revision( $input ) {
	$proposal_id = dcp_create_proposal( (int) $input['source_post_id'] );
	if ( is_wp_error( $proposal_id ) ) {
		return $proposal_id;
	}

	return array(
		'proposal_id'    => $proposal_id,
		'source_post_id' => (int) $input['source_post_id'],
		'edit_url'       => get_edit_post_link( $proposal_id, 'raw' ),
		'status'         => 'draft',
	);
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_update_proposed_revision( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_edit_proposal( $proposal_id );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_update_proposed_revision( $input ) {
	$proposal_id = (int) $input['proposal_id'];
	$fields      = array();
	foreach ( array( 'title', 'content', 'excerpt' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$fields[ $key ] = $input[ $key ];
		}
	}

	$result = dcp_update_proposal( $proposal_id, $fields );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post = get_post( $proposal_id );
	return array(
		'proposal_id'  => $proposal_id,
		'modified_gmt' => $post ? $post->post_modified_gmt : '',
	);
}

/**
 * @param array|null $input Input.
 * @return bool
 */
function dcp_ability_can_list_proposals( $input = null ) {
	return dcp_user_can_create_proposals() || current_user_can( 'edit_posts' );
}

/**
 * @param array|null $input Input.
 * @return array
 */
function dcp_ability_list_proposals( $input = null ) {
	$input = is_array( $input ) ? $input : array();
	return dcp_list_proposals( $input );
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_get_proposal( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_edit_proposal( $proposal_id );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_get_proposal( $input ) {
	$proposal_id = (int) $input['proposal_id'];
	$post        = get_post( $proposal_id );
	if ( ! $post || ! dcp_is_proposal( $proposal_id ) ) {
		return new WP_Error( 'dcp_not_proposal', __( 'Not a proposal.', 'draft-changes' ) );
	}

	return array(
		'proposal_id'    => $proposal_id,
		'source_post_id' => dcp_get_source_id( $proposal_id ),
		'title'          => $post->post_title,
		'content'        => $post->post_content,
		'excerpt'        => $post->post_excerpt,
		'status'         => $post->post_status,
		'approved'       => dcp_is_approved( $proposal_id ),
		'edit_url'       => get_edit_post_link( $proposal_id, 'raw' ),
	);
}

/**
 * @param array $input Input.
 * @return bool
 */

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_approve_proposal( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_apply_proposal( $proposal_id );
}

/**
 * Human approval gate for agent Publish Live.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_approve_proposal( $input ) {
	$proposal_id = (int) $input['proposal_id'];
	$result      = dcp_approve_proposal( $proposal_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'proposal_id'    => $proposal_id,
		'approved'       => true,
		'source_post_id' => dcp_get_source_id( $proposal_id ),
		'edit_url'       => get_edit_post_link( $proposal_id, 'raw' ),
	);
}

function dcp_ability_can_publish_live( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_apply_proposal( $proposal_id );
}

/**
 * Agent Publish Live — requires prior human approval.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_publish_live( $input ) {
	$proposal_id = (int) $input['proposal_id'];
	if ( ! dcp_is_approved( $proposal_id ) ) {
		return new WP_Error(
			'dcp_not_approved',
			__( 'A human must Approve this proposal in the editor before Publish Live.', 'draft-changes' ),
			array( 'proposal_id' => $proposal_id, 'edit_url' => get_edit_post_link( $proposal_id, 'raw' ) )
		);
	}

	$result = dcp_apply_proposal( $proposal_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$result['view_url'] = get_permalink( $result['source_post_id'] );
	return $result;
}
