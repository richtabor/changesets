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
			'label'       => __( 'Changesets', 'draft-changes' ),
			'description' => __( 'Accumulate site edits in a staging session, preview without touching live, then publish after approval.', 'draft-changes' ),
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

	wp_register_ability_category(
		'content-proposals',
		array(
			'label'       => __( 'Changesets', 'draft-changes' ),
			'description' => __( 'Accumulate site edits in a staging session, preview without touching live, then publish after approval.', 'draft-changes' ),
		)
	);

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

	wp_register_ability(
	'draft-changes/create-changeset',
	array(
		'label'               => __( 'Create changeset', 'draft-changes' ),
		'description'         => __( 'Create a new changeset staging session for site edits. Returns changeset_id, uuid, and preview_url. All staged edits accumulate in this session until Publish Changeset.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'title' => array(
					'type'        => 'string',
					'description' => 'Human-readable title for this changeset (e.g. "Add Contact", "Home copy pass").',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'changeset_id' => array( 'type' => 'integer' ),
				'uuid'         => array( 'type' => 'string' ),
				'preview_url'  => array( 'type' => 'string' ),
				'status'       => array( 'type' => 'string' ),
			),
		),
		'execute_callback'    => 'dcp_ability_create_changeset',
		'permission_callback' => 'dcp_ability_can_create_changeset',
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
		'draft-changes/update-changeset',
		array(
			'label'               => __( 'Update changeset', 'draft-changes' ),
			'description'         => __( 'Update the changeset session label (title) as the bag of work grows. Does not change staged content.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id' => array(
						'type'        => 'integer',
						'description' => 'Changeset ID.',
						'minimum'     => 1,
					),
					'title'        => array(
						'type'        => 'string',
						'description' => 'New human-readable session label.',
					),
				),
				'required'             => array( 'changeset_id', 'title' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'changeset_id' => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'preview_url'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'dcp_ability_update_changeset',
			'permission_callback' => 'dcp_ability_can_update_changeset',
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
	'draft-changes/get-changeset',
	array(
		'label'               => __( 'Get changeset', 'draft-changes' ),
		'description'         => __( 'Get changeset details including all staged entity drafts. Returns changeset metadata and list of staged items.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'changeset_id' => array(
					'type'        => 'integer',
					'description' => 'Changeset ID.',
					'minimum'     => 1,
				),
			),
			'required'             => array( 'changeset_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'changeset_id' => array( 'type' => 'integer' ),
				'uuid'         => array( 'type' => 'string' ),
				'title'        => array( 'type' => 'string' ),
				'status'       => array( 'type' => 'string' ),
				'preview_url'  => array( 'type' => 'string' ),
				'staged_items' => array( 'type' => 'array' ),
			),
		),
		'execute_callback'    => 'dcp_ability_get_changeset',
		'permission_callback' => 'dcp_ability_can_get_changeset',
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
	'draft-changes/list-changesets',
	array(
		'label'               => __( 'List changesets', 'draft-changes' ),
		'description'         => __( 'List open or approved changesets. Returns paginated list with metadata.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'status'   => array(
					'type' => 'string',
					'enum' => array( 'open', 'approved' ),
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				),
				'page'     => array(
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
		'execute_callback'    => 'dcp_ability_list_changesets',
		'permission_callback' => 'dcp_ability_can_list_changesets',
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
		'draft-changes/stage-global-styles',
		array(
			'label'               => __( 'Stage global styles', 'draft-changes' ),
			'description'         => __( 'Stage global styles (settings/styles theme.json patch, or full user styles) into a changeset. Live styles stay unchanged until Publish Changeset. Prefer this for colors, typography, spacing; use stage-style-variation to apply a whole theme variation.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id' => array(
						'type'        => 'integer',
						'description' => 'Changeset ID.',
						'minimum'     => 1,
					),
					'settings'     => array(
						'type'        => 'object',
						'description' => 'Partial theme.json settings to merge.',
					),
					'styles'       => array(
						'type'        => 'object',
						'description' => 'Partial theme.json styles to merge.',
					),
					'theme_json'   => array(
						'type'        => 'object',
						'description' => 'Optional full/partial theme.json object (merged).',
					),
				),
				'required'             => array( 'changeset_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'changeset_id' => array( 'type' => 'integer' ),
					'staged'       => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'dcp_ability_stage_global_styles',
			'permission_callback' => 'dcp_ability_can_stage_global_styles',
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
		'draft-changes/stage-style-variation',
		array(
			'label'               => __( 'Stage style variation', 'draft-changes' ),
			'description'         => __( 'Stage a theme style variation (e.g. Twilight, Midnight) into a changeset. Live styles stay unchanged until Publish Changeset.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id' => array(
						'type'        => 'integer',
						'description' => 'Changeset ID.',
						'minimum'     => 1,
					),
					'variation'    => array(
						'type'        => 'string',
						'description' => 'Style variation title or file stem (e.g. "Twilight" or "05-twilight").',
					),
				),
				'required'             => array( 'changeset_id', 'variation' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'changeset_id' => array( 'type' => 'integer' ),
					'stem'         => array( 'type' => 'string' ),
					'title'        => array( 'type' => 'string' ),
					'staged'       => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'dcp_ability_stage_style_variation',
			'permission_callback' => 'dcp_ability_can_stage_style_variation',
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
		'draft-changes/stage-setting',
		array(
			'label'               => __( 'Stage setting', 'draft-changes' ),
			'description'         => __( 'Stage a site setting into a changeset (Reading homepage, site title, etc.). Live options stay unchanged until Publish Changeset.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id' => array(
						'type'        => 'integer',
						'description' => 'Changeset ID.',
						'minimum'     => 1,
					),
					'key'          => array(
						'type'        => 'string',
						'description' => 'Option key: show_on_front, page_on_front, page_for_posts, blogname, blogdescription.',
					),
					'value'        => array(
						'description' => 'Option value (string or integer).',
					),
				),
				'required'             => array( 'changeset_id', 'key', 'value' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'changeset_id' => array( 'type' => 'integer' ),
					'key'          => array( 'type' => 'string' ),
					'value'        => array(),
					'staged'       => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'dcp_ability_stage_setting',
			'permission_callback' => 'dcp_ability_can_stage_setting',
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
		'draft-changes/create-staged-page',
		array(
			'label'               => __( 'Create staged page', 'draft-changes' ),
			'description'         => __( 'Create a brand-new page that lives only inside a changeset (not published until Publish Changeset). Use for Contact, About, etc. Returns staged_id, edit_url, and preview_path.', 'draft-changes' ),
			'category'            => 'content-proposals',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id' => array(
						'type'        => 'integer',
						'description' => 'Changeset ID to stage the page in.',
						'minimum'     => 1,
					),
					'title'        => array(
						'type'        => 'string',
						'description' => 'Page title (required).',
					),
					'content'      => array(
						'type'        => 'string',
						'description' => 'Optional page content (block markup or HTML).',
					),
					'slug'         => array(
						'type'        => 'string',
						'description' => 'Optional page slug (defaults to sanitized title).',
					),
				),
				'required'             => array( 'changeset_id', 'title' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'staged_id'    => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'slug'         => array( 'type' => 'string' ),
					'edit_url'     => array( 'type' => 'string' ),
					'preview_path' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'dcp_ability_create_staged_page',
			'permission_callback' => 'dcp_ability_can_create_staged_page',
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
	'draft-changes/stage-content',
	array(
		'label'               => __( 'Stage content', 'draft-changes' ),
		'description'         => __( 'Clone a published page or post into the changeset for editing. Returns staged_id and edit_url. Does not change live content.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'changeset_id'   => array(
					'type'        => 'integer',
					'description' => 'ID of the changeset to stage content into.',
					'minimum'     => 1,
				),
				'source_post_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the published post or page to stage.',
					'minimum'     => 1,
				),
			),
			'required'             => array( 'changeset_id', 'source_post_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'staged_id'      => array( 'type' => 'integer' ),
				'source_post_id' => array( 'type' => 'integer' ),
				'edit_url'       => array( 'type' => 'string' ),
			),
		),
		'execute_callback'    => 'dcp_ability_stage_content',
		'permission_callback' => 'dcp_ability_can_stage_content',
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
	'draft-changes/update-staged-content',
	array(
		'label'               => __( 'Update staged content', 'draft-changes' ),
		'description'         => __( 'Update title, content, and/or excerpt of a staged draft in a changeset. Does not affect live content. Does not publish.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'staged_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the staged draft to update.',
					'minimum'     => 1,
				),
				'title'     => array(
					'type'        => 'string',
					'description' => 'New post title.',
				),
				'content'   => array(
					'type'        => 'string',
					'description' => 'New post content (block markup or HTML).',
				),
				'excerpt'   => array(
					'type'        => 'string',
					'description' => 'New post excerpt.',
				),
			),
			'required'             => array( 'staged_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'staged_id'    => array( 'type' => 'integer' ),
				'modified_gmt' => array( 'type' => 'string' ),
			),
		),
		'execute_callback'    => 'dcp_ability_update_staged_content',
		'permission_callback' => 'dcp_ability_can_update_staged_content',
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
	'draft-changes/approve-changeset',
	array(
		'label'               => __( 'Approve changeset', 'draft-changes' ),
		'description'         => __( 'Human approval gate. Mark changeset as approved so an agent can Publish Changeset. Prefer this after human review.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'changeset_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the changeset to approve.',
					'minimum'     => 1,
				),
			),
			'required'             => array( 'changeset_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'changeset_id' => array( 'type' => 'integer' ),
				'approved'     => array( 'type' => 'boolean' ),
				'preview_url'  => array( 'type' => 'string' ),
			),
		),
		'execute_callback'    => 'dcp_ability_approve_changeset',
		'permission_callback' => 'dcp_ability_can_approve_changeset',
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
	'draft-changes/publish-changeset',
	array(
		'label'               => __( 'Publish changeset', 'draft-changes' ),
		'description'         => __( 'Apply all staged edits in the changeset to live content, save native revisions for undo, then close the changeset. Requires human approval first via draft-changes/approve-changeset. Fails with dcp_not_approved otherwise.', 'draft-changes' ),
		'category'            => 'content-proposals',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'changeset_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the approved changeset to publish.',
					'minimum'     => 1,
				),
			),
			'required'             => array( 'changeset_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'applied_count' => array( 'type' => 'integer' ),
				'source_ids'    => array( 'type' => 'array' ),
			),
		),
		'execute_callback'    => 'dcp_ability_publish_changeset',
		'permission_callback' => 'dcp_ability_can_publish_changeset',
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

function dcp_ability_can_update_proposed_revision( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_edit_proposal( $proposal_id );
}

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

function dcp_ability_can_list_proposals( $input = null ) {
	return dcp_user_can_create_proposals() || current_user_can( 'edit_posts' );
}

function dcp_ability_list_proposals( $input = null ) {
	$input = is_array( $input ) ? $input : array();
	return dcp_list_proposals( $input );
}

function dcp_ability_can_get_proposal( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_edit_proposal( $proposal_id );
}

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

function dcp_ability_can_approve_proposal( $input ) {
	$proposal_id = isset( $input['proposal_id'] ) ? (int) $input['proposal_id'] : 0;
	return $proposal_id && dcp_user_can_apply_proposal( $proposal_id );
}

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

function dcp_ability_can_create_changeset( $input ) {
	return dcp_user_can_create_proposals();
}

function dcp_ability_create_changeset( $input ) {
	$title = isset( $input['title'] ) ? $input['title'] : null;
	$changeset_id = dcp_create_changeset( $title );
	if ( is_wp_error( $changeset_id ) ) {
		return $changeset_id;
	}

	return array(
		'changeset_id' => $changeset_id,
		'uuid'         => dcp_get_changeset_uuid( $changeset_id ),
		'preview_url'  => dcp_get_preview_url( $changeset_id ),
		'status'       => dcp_get_changeset_status( $changeset_id ),
	);
}

function dcp_ability_can_get_changeset( $input ) {
	return dcp_user_can_create_proposals();
}

function dcp_ability_get_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$changeset    = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_not_changeset', __( 'Not a changeset.', 'draft-changes' ) );
	}

	$staged_ids = dcp_get_staged_drafts( $changeset_id );
	$staged_items = array();
	foreach ( $staged_ids as $staged_id ) {
		$post      = get_post( $staged_id );
		$source_id = dcp_get_staged_source_id( $staged_id );
		$source    = get_post( $source_id );

		$staged_items[] = array(
			'staged_id'      => (int) $staged_id,
			'source_post_id' => $source_id,
			'source_title'   => $source ? $source->post_title : '',
			'title'          => $post ? $post->post_title : '',
			'post_type'      => $post ? $post->post_type : '',
			'edit_url'       => get_edit_post_link( $staged_id, 'raw' ),
		);
	}

	return array(
		'changeset_id' => $changeset_id,
		'uuid'         => dcp_get_changeset_uuid( $changeset_id ),
		'title'        => $changeset->post_title,
		'status'       => dcp_get_changeset_status( $changeset_id ),
		'preview_url'  => dcp_get_preview_url( $changeset_id ),
		'staged_items' => $staged_items,
	);
}

function dcp_ability_can_list_changesets( $input ) {
	return dcp_user_can_create_proposals();
}

function dcp_ability_list_changesets( $input ) {
	$input = is_array( $input ) ? $input : array();
	return dcp_list_changesets( $input );
}

function dcp_ability_can_stage_content( $input ) {
	$source_id = isset( $input['source_post_id'] ) ? (int) $input['source_post_id'] : 0;
	return dcp_user_can_create_proposals() && $source_id && current_user_can( 'edit_post', $source_id );
}

function dcp_ability_stage_content( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$source_id    = (int) $input['source_post_id'];
	$staged_id    = dcp_stage_content( $changeset_id, $source_id );

	if ( is_wp_error( $staged_id ) ) {
		return $staged_id;
	}

	return array(
		'staged_id'      => $staged_id,
		'source_post_id' => $source_id,
		'edit_url'       => get_edit_post_link( $staged_id, 'raw' ),
	);
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_create_staged_page( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && function_exists( 'dcp_user_can_create_proposals' ) && dcp_user_can_create_proposals();
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_create_staged_page( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$title        = isset( $input['title'] ) ? $input['title'] : '';
	$content      = isset( $input['content'] ) ? $input['content'] : '';
	$slug         = isset( $input['slug'] ) ? $input['slug'] : '';

	$staged_id = dcp_create_staged_page( $changeset_id, $title, $content, $slug );
	if ( is_wp_error( $staged_id ) ) {
		return $staged_id;
	}

	$staged = get_post( $staged_id );
	$uuid   = dcp_get_changeset_uuid( $changeset_id );

	return array(
		'staged_id'    => $staged_id,
		'title'        => $staged->post_title,
		'slug'         => $staged->post_name,
		'edit_url'     => get_edit_post_link( $staged_id, 'raw' ),
		'preview_path' => '/' . $staged->post_name . '/?dcp_changeset=' . $uuid,
	);
}



function dcp_ability_can_update_staged_content( $input ) {
	$staged_id = isset( $input['staged_id'] ) ? (int) $input['staged_id'] : 0;
	return $staged_id && current_user_can( 'edit_post', $staged_id );
}

function dcp_ability_update_staged_content( $input ) {
	$staged_id = (int) $input['staged_id'];
	$fields    = array();
	foreach ( array( 'title', 'content', 'excerpt' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$fields[ $key ] = $input[ $key ];
		}
	}

	$result = dcp_update_staged_content( $staged_id, $fields );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post = get_post( $staged_id );
	return array(
		'staged_id'    => $staged_id,
		'modified_gmt' => $post ? $post->post_modified_gmt : '',
	);
}

function dcp_ability_can_approve_changeset( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && dcp_user_can_approve_changeset( $changeset_id );
}

function dcp_ability_approve_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$result       = dcp_approve_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'changeset_id' => $changeset_id,
		'approved'     => true,
		'preview_url'  => dcp_get_preview_url( $changeset_id ),
	);
}

function dcp_ability_can_publish_changeset( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && dcp_user_can_publish_changeset( $changeset_id );
}

function dcp_ability_publish_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	if ( ! dcp_is_changeset_approved( $changeset_id ) ) {
		return new WP_Error(
			'dcp_not_approved',
			__( 'A human must Approve Changeset before Publish Changeset.', 'draft-changes' ),
			array( 'changeset_id' => $changeset_id, 'preview_url' => dcp_get_preview_url( $changeset_id ) )
		);
	}

	$result = dcp_publish_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result;
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_stage_setting( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && current_user_can( 'manage_options' );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_stage_setting( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$key          = isset( $input['key'] ) ? (string) $input['key'] : '';
	$value        = isset( $input['value'] ) ? $input['value'] : null;

	$result = dcp_stage_option( $changeset_id, $key, $value );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$bag = dcp_get_staged_options( $changeset_id );
	return array(
		'changeset_id' => $changeset_id,
		'key'          => $key,
		'value'        => $bag[ $key ],
		'staged'       => true,
	);
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_update_changeset( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && ( current_user_can( 'edit_pages' ) || current_user_can( 'publish_pages' ) );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_update_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$title        = isset( $input['title'] ) ? $input['title'] : '';

	$result = dcp_update_changeset_title( $changeset_id, $title );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$changeset = dcp_get_changeset( $changeset_id );
	return array(
		'changeset_id' => $changeset_id,
		'title'        => $changeset->post_title,
		'preview_url'  => dcp_get_preview_url( $changeset_id ),
	);
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_stage_style_variation( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && current_user_can( 'edit_theme_options' );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_stage_style_variation( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$variation    = isset( $input['variation'] ) ? (string) $input['variation'] : '';
	return dcp_stage_style_variation( $changeset_id, $variation );
}

/**
 * @param array $input Input.
 * @return bool
 */
function dcp_ability_can_stage_global_styles( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && current_user_can( 'edit_theme_options' );
}

/**
 * @param array $input Input.
 * @return array|WP_Error
 */
function dcp_ability_stage_global_styles( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$patch        = array();
	if ( ! empty( $input['theme_json'] ) && is_array( $input['theme_json'] ) ) {
		$patch = $input['theme_json'];
	}
	if ( ! empty( $input['settings'] ) && is_array( $input['settings'] ) ) {
		$patch['settings'] = $input['settings'];
	}
	if ( ! empty( $input['styles'] ) && is_array( $input['styles'] ) ) {
		$patch['styles'] = $input['styles'];
	}
	if ( ! $patch ) {
		return new WP_Error( 'dcp_empty_styles', __( 'Provide settings, styles, and/or theme_json.', 'draft-changes' ) );
	}
	$result = dcp_stage_global_styles( $changeset_id, $patch );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return array(
		'changeset_id' => $changeset_id,
		'staged'       => true,
	);
}
