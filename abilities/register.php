<?php
/**
 * Abilities API registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Permission helper: Can user manage changesets?
 *
 * @return bool
 */
function cs_user_can_manage_changesets() {
	return current_user_can( 'manage_changesets' ) || current_user_can( 'edit_posts' );
}

/**
 * Register ability category.
 */
function cs_register_ability_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'changesets',
		array(
			'label'       => __( 'Changesets', 'changesets' ),
			'description' => __( 'Accumulate site edits in a staging session, preview without touching live, then publish after approval.', 'changesets' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'cs_register_ability_category' );

/**
 * Register Abilities.
 */
function cs_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'changesets/create',
		array(
			'label'               => __( 'Create changeset', 'changesets' ),
			'description'         => __( 'Create a new changeset staging session for site edits. Returns changeset_id, uuid, and preview_url. All staged edits accumulate in this session until Publish Changeset.', 'changesets' ),
			'category'            => 'changesets',
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
			'execute_callback'    => 'cs_ability_create_changeset',
			'permission_callback' => 'cs_ability_can_create_changeset',
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
		'changesets/get',
		array(
			'label'               => __( 'Get changeset', 'changesets' ),
			'description'         => __( 'Get changeset details including all staged entity drafts. Returns changeset metadata and list of staged items.', 'changesets' ),
			'category'            => 'changesets',
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
			'execute_callback'    => 'cs_ability_get_changeset',
			'permission_callback' => 'cs_ability_can_get_changeset',
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
		'changesets/list',
		array(
			'label'               => __( 'List changesets', 'changesets' ),
			'description'         => __( 'List open or approved changesets. Returns paginated list with metadata.', 'changesets' ),
			'category'            => 'changesets',
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
			'execute_callback'    => 'cs_ability_list_changesets',
			'permission_callback' => 'cs_ability_can_list_changesets',
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
		'changesets/stage',
		array(
			'label'               => __( 'Stage page', 'changesets' ),
			'description'         => __( 'Stage a page or post into a changeset. If source_post_id is provided, clones that published page/post (or updates existing staged draft for that source in this changeset). If no source, creates a new staged page (title required). Optional title, content, excerpt, slug apply in the same call (create or update). Returns staged_id, preview hints, etc.', 'changesets' ),
			'category'            => 'changesets',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'changeset_id'   => array(
						'type'        => 'integer',
						'description' => 'Changeset ID to stage the page in.',
						'minimum'     => 1,
					),
					'source_post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional: ID of published post or page to stage. Omit to create new page.',
						'minimum'     => 1,
					),
					'title'          => array(
						'type'        => 'string',
						'description' => 'Page title (required when creating new page).',
					),
					'content'        => array(
						'type'        => 'string',
						'description' => 'Optional page content (block markup or HTML).',
					),
					'excerpt'        => array(
						'type'        => 'string',
						'description' => 'Optional page excerpt.',
					),
					'slug'           => array(
						'type'        => 'string',
						'description' => 'Optional page slug.',
					),
				),
				'required'             => array( 'changeset_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'staged_id'      => array( 'type' => 'integer' ),
					'source_post_id' => array( 'type' => 'integer' ),
					'title'          => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'edit_url'       => array( 'type' => 'string' ),
					'preview_path'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'cs_ability_stage_page',
			'permission_callback' => 'cs_ability_can_stage_page',
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
		'changesets/approve',
		array(
			'label'               => __( 'Approve changeset', 'changesets' ),
			'description'         => __( 'Human approval gate. Mark changeset as approved so an agent can Publish Changeset. Prefer this after human review.', 'changesets' ),
			'category'            => 'changesets',
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
			'execute_callback'    => 'cs_ability_approve_changeset',
			'permission_callback' => 'cs_ability_can_approve_changeset',
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
		'changesets/publish',
		array(
			'label'               => __( 'Publish changeset', 'changesets' ),
			'description'         => __( 'Apply all staged edits in the changeset to live content, save native revisions for undo, then close the changeset. Requires human approval first via changesets/approve. Fails with cs_not_approved otherwise.', 'changesets' ),
			'category'            => 'changesets',
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
			'execute_callback'    => 'cs_ability_publish_changeset',
			'permission_callback' => 'cs_ability_can_publish_changeset',
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
add_action( 'wp_abilities_api_init', 'cs_register_abilities' );


// ============================================
// Ability callbacks
// ============================================

function cs_ability_can_create_changeset( $input ) {
	return cs_user_can_manage_changesets();
}

function cs_ability_create_changeset( $input ) {
	$title = isset( $input['title'] ) ? $input['title'] : null;
	$changeset_id = cs_create_changeset( $title );
	if ( is_wp_error( $changeset_id ) ) {
		return $changeset_id;
	}

	return array(
		'changeset_id' => $changeset_id,
		'uuid'         => cs_get_changeset_uuid( $changeset_id ),
		'preview_url'  => cs_get_preview_url( $changeset_id ),
		'status'       => cs_get_changeset_status( $changeset_id ),
	);
}

function cs_ability_can_get_changeset( $input ) {
	return cs_user_can_manage_changesets();
}

function cs_ability_get_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$changeset    = cs_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'cs_not_changeset', __( 'Not a changeset.', 'changesets' ) );
	}

	$staged_ids = cs_get_staged_drafts( $changeset_id );
	$staged_items = array();
	foreach ( $staged_ids as $staged_id ) {
		$post      = get_post( $staged_id );
		$source_id = cs_get_staged_source_id( $staged_id );
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
		'uuid'         => cs_get_changeset_uuid( $changeset_id ),
		'title'        => $changeset->post_title,
		'status'       => cs_get_changeset_status( $changeset_id ),
		'preview_url'  => cs_get_preview_url( $changeset_id ),
		'staged_items' => $staged_items,
	);
}

function cs_ability_can_list_changesets( $input ) {
	return cs_user_can_manage_changesets();
}

function cs_ability_list_changesets( $input ) {
	$input = is_array( $input ) ? $input : array();
	return cs_list_changesets( $input );
}

function cs_ability_can_stage_page( $input ) {
	$source_id = isset( $input['source_post_id'] ) ? (int) $input['source_post_id'] : 0;
	if ( $source_id && ! current_user_can( 'edit_post', $source_id ) ) {
		return false;
	}
	return cs_user_can_manage_changesets();
}

/**
 * Stage page: unified ability for staging existing content or creating new pages.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function cs_ability_stage_page( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$source_id    = isset( $input['source_post_id'] ) ? (int) $input['source_post_id'] : 0;

	if ( $source_id > 0 ) {
		$existing_staged = cs_get_staged_draft_for_source( $changeset_id, $source_id );
		if ( $existing_staged ) {
			$staged_id = $existing_staged;
		} else {
			$staged_id = cs_stage_content( $changeset_id, $source_id );
			if ( is_wp_error( $staged_id ) ) {
				return $staged_id;
			}
		}
	} else {
		$title   = isset( $input['title'] ) ? $input['title'] : '';
		$content = isset( $input['content'] ) ? $input['content'] : '';
		$slug    = isset( $input['slug'] ) ? $input['slug'] : '';

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'cs_missing_title', __( 'Title is required when creating a new page.', 'changesets' ) );
		}

		$staged_id = cs_create_staged_page( $changeset_id, $title, $content, $slug );
		if ( is_wp_error( $staged_id ) ) {
			return $staged_id;
		}
	}

	$fields = array();
	foreach ( array( 'title', 'content', 'excerpt' ) as $key ) {
		if ( array_key_exists( $key, $input ) && '' !== $input[ $key ] ) {
			$fields[ $key ] = $input[ $key ];
		}
	}
	if ( isset( $input['slug'] ) && '' !== $input['slug'] ) {
		$fields['slug'] = $input['slug'];
	}

	if ( ! empty( $fields ) ) {
		$update = array( 'ID' => $staged_id );
		if ( isset( $fields['title'] ) ) {
			$update['post_title'] = $fields['title'];
		}
		if ( isset( $fields['content'] ) ) {
			$update['post_content'] = $fields['content'];
		}
		if ( isset( $fields['excerpt'] ) ) {
			$update['post_excerpt'] = $fields['excerpt'];
		}
		if ( isset( $fields['slug'] ) ) {
			$update['post_name'] = sanitize_title( $fields['slug'] );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$staged = get_post( $staged_id );
	$uuid   = cs_get_changeset_uuid( $changeset_id );

	return array(
		'staged_id'      => $staged_id,
		'source_post_id' => $source_id,
		'title'          => $staged->post_title,
		'slug'           => $staged->post_name,
		'edit_url'       => get_edit_post_link( $staged_id, 'raw' ),
		'preview_path'   => '/' . $staged->post_name . '/?changeset=' . $uuid,
	);
}

function cs_ability_can_approve_changeset( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && cs_user_can_approve_changeset( $changeset_id );
}

function cs_ability_approve_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	$result       = cs_approve_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'changeset_id' => $changeset_id,
		'approved'     => true,
		'preview_url'  => cs_get_preview_url( $changeset_id ),
	);
}

function cs_ability_can_publish_changeset( $input ) {
	$changeset_id = isset( $input['changeset_id'] ) ? (int) $input['changeset_id'] : 0;
	return $changeset_id && cs_user_can_publish_changeset( $changeset_id );
}

function cs_ability_publish_changeset( $input ) {
	$changeset_id = (int) $input['changeset_id'];
	if ( ! cs_is_changeset_approved( $changeset_id ) ) {
		return new WP_Error(
			'cs_not_approved',
			__( 'A human must Approve Changeset before Publish Changeset.', 'changesets' ),
			array( 'changeset_id' => $changeset_id, 'preview_url' => cs_get_preview_url( $changeset_id ) )
		);
	}

	$result = cs_publish_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result;
}
