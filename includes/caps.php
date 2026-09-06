<?php
/**
 * Capabilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure custom caps exist in the role system (registration is via activation + init).
 */
function dcp_register_caps() {
	// Caps are assigned on activation; this hook is a stable place for future role helpers.
}

/**
 * Whether the current user may create proposals.
 *
 * @return bool
 */
function dcp_user_can_create_proposals() {
	return current_user_can( 'create_content_proposals' ) || current_user_can( 'edit_posts' );
}

/**
 * Whether the current user may edit a given proposal.
 *
 * @param int $proposal_id Proposal post ID.
 * @return bool
 */
function dcp_user_can_edit_proposal( $proposal_id ) {
	if ( ! dcp_is_proposal( $proposal_id ) ) {
		return false;
	}
	return current_user_can( 'edit_content_proposals' ) || current_user_can( 'edit_post', $proposal_id );
}

/**
 * Whether the current user may apply a proposal to its live source.
 *
 * @param int $proposal_id Proposal post ID.
 * @return bool
 */
function dcp_user_can_apply_proposal( $proposal_id ) {
	if ( ! dcp_is_proposal( $proposal_id ) ) {
		return false;
	}
	$source_id = dcp_get_source_id( $proposal_id );
	if ( ! $source_id ) {
		return false;
	}
	return current_user_can( 'apply_content_proposals' ) && current_user_can( 'edit_post', $source_id );
}
