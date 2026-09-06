<?php
/**
 * Changeset architecture: staging session for site edits.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register CPT dcp_changeset.
 */
function dcp_register_changeset_cpt() {
	register_post_type(
		'dcp_changeset',
		array(
			'labels'              => array(
				'name'               => __( 'Changesets', 'draft-changes' ),
				'singular_name'      => __( 'Changeset', 'draft-changes' ),
				'add_new'            => __( 'Add New', 'draft-changes' ),
				'add_new_item'       => __( 'Add New Changeset', 'draft-changes' ),
				'edit_item'          => __( 'Edit Changeset', 'draft-changes' ),
				'new_item'           => __( 'New Changeset', 'draft-changes' ),
				'view_item'          => __( 'View Changeset', 'draft-changes' ),
				'search_items'       => __( 'Search Changesets', 'draft-changes' ),
				'not_found'          => __( 'No changesets found', 'draft-changes' ),
				'not_found_in_trash' => __( 'No changesets found in Trash', 'draft-changes' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-clipboard',
			'show_in_rest'        => true,
		)
	);
}
add_action( 'init', 'dcp_register_changeset_cpt' );

/**
 * Get or create the current open changeset.
 *
 * @param string|null $title Optional title for new changeset.
 * @return int|WP_Error Changeset ID.
 */
function dcp_get_open_changeset( $title = null ) {
	$existing = get_posts(
		array(
			'post_type'      => 'dcp_changeset',
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_dcp_changeset_status',
					'value' => 'open',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( $existing ) {
		return $existing[0]->ID;
	}

	return dcp_create_changeset( $title );
}

/**
 * Create a new changeset.
 *
 * @param string|null $title Optional title.
 * @return int|WP_Error Changeset ID.
 */
function dcp_create_changeset( $title = null ) {
	if ( ! $title ) {
		$title = sprintf(
			/* translators: %s: date */
			__( 'Changeset %s', 'draft-changes' ),
			gmdate( 'Y-m-d H:i' )
		);
	}

	$changeset_id = wp_insert_post(
		array(
			'post_type'   => 'dcp_changeset',
			'post_status' => 'draft',
			'post_title'  => $title,
		),
		true
	);

	if ( is_wp_error( $changeset_id ) ) {
		return $changeset_id;
	}

	$uuid = wp_generate_uuid4();
	update_post_meta( $changeset_id, '_dcp_changeset_uuid', $uuid );
	update_post_meta( $changeset_id, '_dcp_changeset_status', 'open' );

	return $changeset_id;
}

/**
 * Get changeset by ID or UUID.
 *
 * @param int|string $changeset_id_or_uuid Changeset ID or UUID.
 * @return WP_Post|null
 */
function dcp_get_changeset( $changeset_id_or_uuid ) {
	if ( is_numeric( $changeset_id_or_uuid ) ) {
		$post = get_post( (int) $changeset_id_or_uuid );
		if ( $post && 'dcp_changeset' === $post->post_type ) {
			return $post;
		}
		return null;
	}

	$changesets = get_posts(
		array(
			'post_type'      => 'dcp_changeset',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_dcp_changeset_uuid',
					'value' => $changeset_id_or_uuid,
				),
			),
		)
	);

	return $changesets ? $changesets[0] : null;
}

/**
 * Get changeset UUID.
 *
 * @param int $changeset_id Changeset ID.
 * @return string
 */
function dcp_get_changeset_uuid( $changeset_id ) {
	return get_post_meta( (int) $changeset_id, '_dcp_changeset_uuid', true );
}

/**
 * Get changeset status.
 *
 * @param int $changeset_id Changeset ID.
 * @return string open|approved|published|discarded
 */
function dcp_get_changeset_status( $changeset_id ) {
	$status = get_post_meta( (int) $changeset_id, '_dcp_changeset_status', true );
	return $status ? $status : 'open';
}

/**
 * Whether changeset is approved.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_is_changeset_approved( $changeset_id ) {
	return 'approved' === dcp_get_changeset_status( $changeset_id );
}

/**
 * Approve changeset (human gate).
 *
 * @param int $changeset_id Changeset ID.
 * @return true|WP_Error
 */
function dcp_approve_changeset( $changeset_id ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_not_changeset', __( 'Not a changeset.', 'draft-changes' ) );
	}

	if ( ! dcp_user_can_approve_changeset( $changeset_id ) ) {
		return new WP_Error( 'dcp_forbidden', __( 'You cannot approve this changeset.', 'draft-changes' ) );
	}

	update_post_meta( $changeset_id, '_dcp_changeset_status', 'approved' );
	update_post_meta( $changeset_id, '_dcp_approved_by', get_current_user_id() );
	update_post_meta( $changeset_id, '_dcp_approved_at', gmdate( 'c' ) );
	wp_update_post( array( 'ID' => $changeset_id, 'post_status' => 'pending' ) );

	return true;
}

/**
 * Whether staged entity draft.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function dcp_is_staged( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, '_dcp_is_staged', true );
}

/**
 * Get source post ID from staged draft.
 *
 * @param int $staged_id Staged draft ID.
 * @return int Source post ID or 0.
 */
function dcp_get_staged_source_id( $staged_id ) {
	return (int) get_post_meta( (int) $staged_id, DCP_META_SOURCE, true );
}

/**
 * Get changeset ID from staged draft.
 *
 * @param int $staged_id Staged draft ID.
 * @return int
 */
function dcp_get_staged_changeset_id( $staged_id ) {
	return (int) get_post_meta( (int) $staged_id, '_dcp_changeset_id', true );
}

/**
 * Stage content: clone a published post/page into changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @param int $source_id    Source post ID.
 * @return int|WP_Error Staged draft ID.
 */
function dcp_stage_content( $changeset_id, $source_id ) {
	$changeset_id = (int) $changeset_id;
	$source_id    = (int) $source_id;

	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	$source = get_post( $source_id );
	if ( ! $source || 'publish' !== $source->post_status ) {
		return new WP_Error( 'dcp_invalid_source', __( 'Source must be a published post or page.', 'draft-changes' ) );
	}

	if ( ! in_array( $source->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error( 'dcp_unsupported_type', __( 'Only posts and pages are supported in v1.', 'draft-changes' ) );
	}

	if ( dcp_is_staged( $source_id ) ) {
		return new WP_Error( 'dcp_source_is_staged', __( 'Cannot stage another staged draft.', 'draft-changes' ) );
	}

	$existing = dcp_get_staged_draft_for_source( $changeset_id, $source_id );
	if ( $existing ) {
		return new WP_Error(
			'dcp_already_staged',
			__( 'This content is already staged in this changeset.', 'draft-changes' ),
			array( 'staged_id' => $existing )
		);
	}

	$staged_id = wp_insert_post(
		array(
			'post_type'    => $source->post_type,
			'post_status'  => 'draft',
			'post_title'   => $source->post_title,
			'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt,
			'post_author'  => get_current_user_id() ? get_current_user_id() : $source->post_author,
			'post_parent'  => $source->post_parent,
			'menu_order'   => $source->menu_order,
			'post_name'    => '',
		),
		true
	);

	if ( is_wp_error( $staged_id ) ) {
		return $staged_id;
	}

	update_post_meta( $staged_id, '_dcp_is_staged', 1 );
	update_post_meta( $staged_id, DCP_META_SOURCE, $source_id );
	update_post_meta( $staged_id, '_dcp_changeset_id', $changeset_id );

	$thumb = get_post_thumbnail_id( $source_id );
	if ( $thumb ) {
		set_post_thumbnail( $staged_id, $thumb );
	}

	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $source->post_type, $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $staged_id, $terms, $taxonomy );
		}
	}

	return $staged_id;
}

/**
 * Get staged draft for a source in a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @param int $source_id    Source post ID.
 * @return int Staged draft ID or 0.
 */
function dcp_get_staged_draft_for_source( $changeset_id, $source_id ) {
	$staged = get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_dcp_changeset_id',
					'value' => (int) $changeset_id,
				),
				array(
					'key'   => DCP_META_SOURCE,
					'value' => (int) $source_id,
				),
				array(
					'key'   => '_dcp_is_staged',
					'value' => '1',
				),
			),
			'fields'         => 'ids',
		)
	);

	return $staged ? $staged[0] : 0;
}

/**
 * Get all staged drafts in a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return array Array of staged draft IDs.
 */
function dcp_get_staged_drafts( $changeset_id ) {
	return get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_dcp_changeset_id',
					'value' => (int) $changeset_id,
				),
				array(
					'key'   => '_dcp_is_staged',
					'value' => '1',
				),
			),
			'fields'         => 'ids',
		)
	);
}

/**
 * Update staged content.
 *
 * @param int   $staged_id Staged draft ID.
 * @param array $fields    Keys: title, content, excerpt.
 * @return true|WP_Error
 */
function dcp_update_staged_content( $staged_id, $fields ) {
	$staged_id = (int) $staged_id;
	if ( ! dcp_is_staged( $staged_id ) ) {
		return new WP_Error( 'dcp_not_staged', __( 'Not a staged draft.', 'draft-changes' ) );
	}

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

	if ( count( $update ) === 1 ) {
		return new WP_Error( 'dcp_no_fields', __( 'No fields to update.', 'draft-changes' ) );
	}

	$result = wp_update_post( $update, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return true;
}

/**
 * Publish changeset: apply all staged drafts to live, then close.
 *
 * @param int $changeset_id Changeset ID.
 * @return array|WP_Error { applied_count, source_ids }
 */
function dcp_publish_changeset( $changeset_id ) {
	$changeset_id = (int) $changeset_id;
	$changeset    = dcp_get_changeset( $changeset_id );

	if ( ! $changeset ) {
		return new WP_Error( 'dcp_not_changeset', __( 'Not a changeset.', 'draft-changes' ) );
	}

	$staged_ids = dcp_get_staged_drafts( $changeset_id );
	$source_ids = array();

	foreach ( $staged_ids as $staged_id ) {
		$staged    = get_post( $staged_id );
		$source_id = dcp_get_staged_source_id( $staged_id );
		$source    = get_post( $source_id );

		if ( ! $source || 'publish' !== $source->post_status ) {
			continue;
		}

		wp_save_post_revision( $source_id );

		wp_update_post(
			array(
				'ID'           => $source_id,
				'post_title'   => $staged->post_title,
				'post_content' => $staged->post_content,
				'post_excerpt' => $staged->post_excerpt,
			),
			true
		);

		$thumb = get_post_thumbnail_id( $staged_id );
		if ( $thumb ) {
			set_post_thumbnail( $source_id, $thumb );
		} else {
			delete_post_thumbnail( $source_id );
		}

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $source->post_type, $taxonomy ) ) {
				continue;
			}
			$terms = wp_get_object_terms( $staged_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $source_id, $terms, $taxonomy );
			}
		}

		wp_delete_post( $staged_id, true );
		$source_ids[] = $source_id;
	}

	update_post_meta( $changeset_id, '_dcp_changeset_status', 'published' );
	wp_update_post( array( 'ID' => $changeset_id, 'post_status' => 'publish' ) );

	dcp_clear_preview_cookie();

	return array(
		'applied_count' => count( $source_ids ),
		'source_ids'    => $source_ids,
	);
}

/**
 * List changesets.
 *
 * @param array $args Optional. status, per_page, page.
 * @return array { items, total }
 */
function dcp_list_changesets( $args = array() ) {
	$meta_status = array( 'open', 'approved' );
	if ( isset( $args['status'] ) ) {
		$meta_status = (array) $args['status'];
	}

	$query_args = array(
		'post_type'      => 'dcp_changeset',
		'post_status'    => 'any',
		'posts_per_page' => isset( $args['per_page'] ) ? (int) $args['per_page'] : 20,
		'paged'          => isset( $args['page'] ) ? (int) $args['page'] : 1,
		'meta_query'     => array(
			array(
				'key'     => '_dcp_changeset_status',
				'value'   => $meta_status,
				'compare' => 'IN',
			),
		),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$q = new WP_Query( $query_args );
	$items = array();
	foreach ( $q->posts as $post ) {
		$uuid         = dcp_get_changeset_uuid( $post->ID );
		$status       = dcp_get_changeset_status( $post->ID );
		$staged_count = count( dcp_get_staged_drafts( $post->ID ) );

		$items[] = array(
			'changeset_id' => (int) $post->ID,
			'uuid'         => $uuid,
			'title'        => $post->post_title,
			'status'       => $status,
			'staged_count' => $staged_count,
			'created_gmt'  => $post->post_date_gmt,
		);
	}

	return array(
		'items' => $items,
		'total' => (int) $q->found_posts,
	);
}

/**
 * Hard-block public publish of staged drafts.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post object.
 */
function dcp_prevent_staged_publish( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	if ( ! dcp_is_staged( $post->ID ) ) {
		return;
	}

	remove_action( 'transition_post_status', 'dcp_prevent_staged_publish', 10 );
	wp_update_post(
		array(
			'ID'          => $post->ID,
			'post_status' => 'draft',
		)
	);
	add_action( 'transition_post_status', 'dcp_prevent_staged_publish', 10, 3 );
}
add_action( 'transition_post_status', 'dcp_prevent_staged_publish', 10, 3 );

/**
 * Get preview URL for a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return string
 */
function dcp_get_preview_url( $changeset_id ) {
	$uuid = dcp_get_changeset_uuid( $changeset_id );
	return add_query_arg( 'dcp_changeset', $uuid, home_url( '/' ) );
}

/**
 * Set preview cookie.
 *
 * @param string $uuid Changeset UUID.
 */
function dcp_set_preview_cookie( $uuid ) {
	if ( ! headers_sent() ) {
		setcookie( 'dcp_changeset', $uuid, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}
}

/**
 * Clear preview cookie.
 */
function dcp_clear_preview_cookie() {
	if ( ! headers_sent() ) {
		setcookie( 'dcp_changeset', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}
}

/**
 * Get active preview changeset UUID from query or cookie.
 *
 * @return string|null
 */
function dcp_get_active_preview_uuid() {
	if ( isset( $_GET['dcp_changeset'] ) && $_GET['dcp_changeset'] ) {
		return sanitize_text_field( wp_unslash( $_GET['dcp_changeset'] ) );
	}
	if ( isset( $_COOKIE['dcp_changeset'] ) && $_COOKIE['dcp_changeset'] ) {
		return sanitize_text_field( wp_unslash( $_COOKIE['dcp_changeset'] ) );
	}
	return null;
}

/**
 * Initialize preview mode: set cookie from query param.
 */
function dcp_init_preview() {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return;
	}

	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset ) {
		dcp_clear_preview_cookie();
		return;
	}

	if ( isset( $_GET['dcp_changeset'] ) ) {
		dcp_set_preview_cookie( $uuid );
	}

	if ( isset( $_GET['dcp_exit_preview'] ) ) {
		dcp_clear_preview_cookie();
		wp_safe_redirect( remove_query_arg( array( 'dcp_exit_preview', 'dcp_changeset' ) ) );
		exit;
	}
}
add_action( 'init', 'dcp_init_preview' );

/**
 * Filter queries to overlay staged content over live in preview mode.
 *
 * @param WP_Query $query Query.
 */
function dcp_preview_filter_posts( $query ) {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return;
	}

	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset ) {
		return;
	}

	if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	add_filter( 'posts_results', 'dcp_overlay_staged_content', 10, 2 );
}
add_action( 'pre_get_posts', 'dcp_preview_filter_posts' );

/**
 * Overlay staged drafts over live posts in preview mode.
 *
 * @param array    $posts Posts.
 * @param WP_Query $query Query.
 * @return array
 */
function dcp_overlay_staged_content( $posts, $query ) {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return $posts;
	}

	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset ) {
		return $posts;
	}

	$staged_map = array();
	foreach ( dcp_get_staged_drafts( $changeset->ID ) as $staged_id ) {
		$source_id = dcp_get_staged_source_id( $staged_id );
		if ( $source_id ) {
			$staged_map[ $source_id ] = $staged_id;
		}
	}

	foreach ( $posts as $i => $post ) {
		if ( isset( $staged_map[ $post->ID ] ) ) {
			$staged = get_post( $staged_map[ $post->ID ] );
			if ( $staged ) {
				$staged->ID        = $post->ID;
				$staged->post_name = $post->post_name;
				$posts[ $i ]       = $staged;
			}
		}
	}

	return $posts;
}

/**
 * Whether the current user can approve a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_user_can_approve_changeset( $changeset_id ) {
	return current_user_can( 'apply_content_proposals' ) || current_user_can( 'publish_posts' ) || current_user_can( 'publish_pages' );
}

/**
 * Whether the current user can publish a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_user_can_publish_changeset( $changeset_id ) {
	return dcp_user_can_approve_changeset( $changeset_id );
}
