<?php
/**
 * Proposal lifecycle: create, update, list, apply, guards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param int $post_id Post ID.
 * @return bool
 */
function dcp_is_proposal( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, DCP_META_IS_PROPOSAL, true );
}

/**
 * @param int $proposal_id Proposal post ID.
 * @return int Source post ID or 0.
 */
function dcp_get_source_id( $proposal_id ) {
	return (int) get_post_meta( (int) $proposal_id, DCP_META_SOURCE, true );
}

/**
 * Open proposal for a published source, if any.
 *
 * @param int $source_id Source post ID.
 * @return int Proposal ID or 0.
 */

/**
 * Whether a human has approved this proposal for Publish Live.
 *
 * @param int $proposal_id Proposal ID.
 * @return bool
 */
function dcp_is_approved( $proposal_id ) {
	return (bool) get_post_meta( (int) $proposal_id, DCP_META_APPROVED, true );
}

/**
 * Mark proposal approved by current user (human gate before agent Publish Live).
 *
 * @param int $proposal_id Proposal ID.
 * @return true|WP_Error
 */
function dcp_approve_proposal( $proposal_id ) {
	$proposal_id = (int) $proposal_id;
	if ( ! dcp_is_proposal( $proposal_id ) ) {
		return new WP_Error( 'dcp_not_proposal', __( 'Not a proposal.', 'draft-changes' ) );
	}
	if ( ! dcp_user_can_apply_proposal( $proposal_id ) ) {
		return new WP_Error( 'dcp_forbidden', __( 'You cannot approve this proposal.', 'draft-changes' ) );
	}
	update_post_meta( $proposal_id, DCP_META_APPROVED, 1 );
	update_post_meta( $proposal_id, DCP_META_APPROVED_BY, get_current_user_id() );
	update_post_meta( $proposal_id, DCP_META_APPROVED_AT, gmdate( 'c' ) );
	return true;
}

/**
 * Clear human approval (e.g. after content changes).
 *
 * @param int $proposal_id Proposal ID.
 */
function dcp_clear_approval( $proposal_id ) {
	delete_post_meta( (int) $proposal_id, DCP_META_APPROVED );
	delete_post_meta( (int) $proposal_id, DCP_META_APPROVED_BY );
	delete_post_meta( (int) $proposal_id, DCP_META_APPROVED_AT );
}

function dcp_get_open_proposal_id( $source_id ) {
	$existing = (int) get_post_meta( (int) $source_id, DCP_META_OPEN_PROPOSAL, true );
	if ( $existing && dcp_is_proposal( $existing ) && get_post( $existing ) ) {
		$status = get_post_status( $existing );
		if ( in_array( $status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return $existing;
		}
	}
	return 0;
}

/**
 * Create a proposal draft from a published post/page.
 *
 * @param int $source_id Source post ID.
 * @return int|WP_Error Proposal ID.
 */
function dcp_create_proposal( $source_id ) {
	$source_id = (int) $source_id;
	$source    = get_post( $source_id );

	if ( ! $source || 'publish' !== $source->post_status ) {
		return new WP_Error( 'dcp_invalid_source', __( 'Source must be a published post or page.', 'draft-changes' ) );
	}

	if ( ! in_array( $source->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error( 'dcp_unsupported_type', __( 'Only posts and pages are supported in v1.', 'draft-changes' ) );
	}

	if ( dcp_is_proposal( $source_id ) ) {
		return new WP_Error( 'dcp_source_is_proposal', __( 'Cannot create a proposal from another proposal.', 'draft-changes' ) );
	}

	$open = dcp_get_open_proposal_id( $source_id );
	if ( $open ) {
		return new WP_Error(
			'dcp_proposal_exists',
			__( 'An open proposal already exists for this content.', 'draft-changes' ),
			array( 'proposal_id' => $open )
		);
	}

	$proposal_id = wp_insert_post(
		array(
			'post_type'    => $source->post_type,
			'post_status'  => 'draft',
			'post_title'   => $source->post_title,
			'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt,
			'post_author'  => get_current_user_id() ? get_current_user_id() : $source->post_author,
			'post_parent'  => $source->post_parent,
			'menu_order'   => $source->menu_order,
			'post_name'    => '', // Avoid slug collision with live URL.
		),
		true
	);

	if ( is_wp_error( $proposal_id ) ) {
		return $proposal_id;
	}

	update_post_meta( $proposal_id, DCP_META_IS_PROPOSAL, 1 );
	update_post_meta( $proposal_id, DCP_META_SOURCE, $source_id );
	update_post_meta( $source_id, DCP_META_OPEN_PROPOSAL, $proposal_id );

	$thumb = get_post_thumbnail_id( $source_id );
	if ( $thumb ) {
		set_post_thumbnail( $proposal_id, $thumb );
	}

	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $source->post_type, $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $proposal_id, $terms, $taxonomy );
		}
	}

	return $proposal_id;
}

/**
 * Update proposal fields.
 *
 * @param int   $proposal_id Proposal ID.
 * @param array $fields      Keys: title, content, excerpt.
 * @return true|WP_Error
 */
function dcp_update_proposal( $proposal_id, $fields ) {
	$proposal_id = (int) $proposal_id;
	if ( ! dcp_is_proposal( $proposal_id ) ) {
		return new WP_Error( 'dcp_not_proposal', __( 'Not a proposal.', 'draft-changes' ) );
	}

	$update = array( 'ID' => $proposal_id );
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

	dcp_clear_approval( $proposal_id );
	return true;
}

/**
 * Apply proposal onto its published source, then permanently delete the proposal.
 *
 * @param int $proposal_id Proposal ID.
 * @return array|WP_Error { source_post_id, applied }
 */
function dcp_apply_proposal( $proposal_id ) {
	$proposal_id = (int) $proposal_id;
	$proposal    = get_post( $proposal_id );

	if ( ! $proposal || ! dcp_is_proposal( $proposal_id ) ) {
		return new WP_Error( 'dcp_not_proposal', __( 'Not a proposal.', 'draft-changes' ) );
	}

	$source_id = dcp_get_source_id( $proposal_id );
	$source    = get_post( $source_id );
	if ( ! $source || 'publish' !== $source->post_status ) {
		return new WP_Error( 'dcp_invalid_source', __( 'Source is missing or not published.', 'draft-changes' ) );
	}

	// Native revision of current live content for undo.
	wp_save_post_revision( $source_id );

	$result = wp_update_post(
		array(
			'ID'           => $source_id,
			'post_title'   => $proposal->post_title,
			'post_content' => $proposal->post_content,
			'post_excerpt' => $proposal->post_excerpt,
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$thumb = get_post_thumbnail_id( $proposal_id );
	if ( $thumb ) {
		set_post_thumbnail( $source_id, $thumb );
	} else {
		delete_post_thumbnail( $source_id );
	}

	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $source->post_type, $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_object_terms( $proposal_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $source_id, $terms, $taxonomy );
		}
	}

	delete_post_meta( $source_id, DCP_META_OPEN_PROPOSAL );
	wp_delete_post( $proposal_id, true );

	return array(
		'source_post_id' => $source_id,
		'applied'        => true,
	);
}

/**
 * List open proposals.
 *
 * @param array $args Optional. source_post_id, status, per_page, page.
 * @return array { items, total }
 */
function dcp_list_proposals( $args = array() ) {
	$query_args = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => isset( $args['status'] ) ? $args['status'] : array( 'draft', 'pending' ),
		'posts_per_page' => isset( $args['per_page'] ) ? (int) $args['per_page'] : 20,
		'paged'          => isset( $args['page'] ) ? (int) $args['page'] : 1,
		'meta_query'     => array(
			array(
				'key'   => DCP_META_IS_PROPOSAL,
				'value' => '1',
			),
		),
		'fields'         => 'ids',
		'no_found_rows'  => false,
	);

	if ( ! empty( $args['source_post_id'] ) ) {
		$query_args['meta_query'][] = array(
			'key'   => DCP_META_SOURCE,
			'value' => (int) $args['source_post_id'],
		);
	}

	$q = new WP_Query( $query_args );
	$items = array();
	foreach ( $q->posts as $id ) {
		$post = get_post( $id );
		$items[] = array(
			'proposal_id'    => (int) $id,
			'source_post_id' => dcp_get_source_id( $id ),
			'title'          => $post ? $post->post_title : '',
			'status'         => $post ? $post->post_status : '',
			'modified_gmt'   => $post ? $post->post_modified_gmt : '',
		);
	}

	return array(
		'items' => $items,
		'total' => (int) $q->found_posts,
	);
}

/**
 * Publishing a proposal means Apply to live (merge onto the source URL).
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post object.
 */
function dcp_on_proposal_publish( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	if ( ! dcp_is_proposal( $post->ID ) ) {
		return;
	}
	if ( ! empty( $GLOBALS['dcp_applying_proposal'] ) ) {
		return;
	}

	$GLOBALS['dcp_applying_proposal'] = true;
	remove_action( 'transition_post_status', 'dcp_on_proposal_publish', 10 );

	// Never leave the proposal as a public URL — apply merges onto the source.
	wp_update_post(
		array(
			'ID'          => $post->ID,
			'post_status' => 'draft',
		)
	);

	$result = dcp_apply_proposal( $post->ID );

	add_action( 'transition_post_status', 'dcp_on_proposal_publish', 10, 3 );
	$GLOBALS['dcp_applying_proposal'] = false;

	if ( is_wp_error( $result ) ) {
		return;
	}

	$redirect = get_edit_post_link( $result['source_post_id'], 'raw' );
	$redirect = add_query_arg( 'dcp_applied', '1', $redirect );

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$GLOBALS['dcp_apply_redirect'] = $redirect;
		return;
	}

	if ( is_admin() && ! wp_doing_ajax() ) {
		wp_safe_redirect( $redirect );
		exit;
	}
}
add_action( 'transition_post_status', 'dcp_on_proposal_publish', 10, 3 );

/**
 * After Gutenberg publishes a proposal, point the client at the live source.
 *
 * @param WP_REST_Response $response Response.
 * @return WP_REST_Response
 */
function dcp_rest_apply_redirect( $response ) {
	if ( empty( $GLOBALS['dcp_apply_redirect'] ) || ! ( $response instanceof WP_REST_Response ) ) {
		return $response;
	}
	$response->header( 'X-DCP-Redirect', esc_url_raw( $GLOBALS['dcp_apply_redirect'] ) );
	$data = $response->get_data();
	if ( is_array( $data ) ) {
		$data['dcp_applied']           = true;
		$data['dcp_redirect']          = $GLOBALS['dcp_apply_redirect'];
		$data['dcp_source_edit_link']  = $GLOBALS['dcp_apply_redirect'];
		$response->set_data( $data );
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'dcp_rest_apply_redirect' );
