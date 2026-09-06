<?php
/**
 * Minimal human UI: banners + Apply to live (works with block editor).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice / banner on proposal and source editors.
 */
function dcp_admin_notices() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id ) {
		return;
	}

	if ( dcp_is_proposal( $post_id ) ) {
		$source_id = dcp_get_source_id( $post_id );
		$source    = get_post( $source_id );
		$title     = $source ? $source->post_title : __( '(missing)', 'draft-changes' );
		$edit      = $source_id ? get_edit_post_link( $source_id ) : '';

		echo '<div class="notice notice-info" style="padding:12px 16px"><p style="margin:0 0 8px">';
		echo esc_html(
			sprintf(
				/* translators: %s: source post title */
				__( 'This is a proposal. Live content stays published until you Apply to live. Source: %s', 'draft-changes' ),
				$title
			)
		);
		if ( $edit ) {
			echo ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Open live post', 'draft-changes' ) . '</a>';
		}
		echo '</p>';

		if ( dcp_user_can_apply_proposal( $post_id ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dcp_apply&post_id=' . $post_id ),
				'dcp_apply_' . $post_id
			);
			echo '<p style="margin:0"><a class="button button-primary button-hero" href="' . esc_url( $url ) . '">' . esc_html__( 'Apply to live', 'draft-changes' ) . '</a></p>';
		} else {
			echo '<p style="margin:0">' . esc_html__( 'You do not have permission to apply this proposal.', 'draft-changes' ) . '</p>';
		}
		echo '</div>';
		return;
	}

	$open = dcp_get_open_proposal_id( $post_id );
	if ( $open ) {
		$url = get_edit_post_link( $open );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'There is an open proposal for this published content.', 'draft-changes' );
		if ( $url ) {
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Review proposal', 'draft-changes' ) . '</a>';
		}
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'dcp_admin_notices' );

/**
 * Also show Apply in the block editor document settings via a side meta box
 * that only contains a link (no form submit — Gutenberg-safe).
 *
 * @param WP_Post $post Post.
 */
function dcp_render_apply_metabox( $post ) {
	if ( ! dcp_is_proposal( $post->ID ) ) {
		echo '<p>' . esc_html__( 'Not a proposal.', 'draft-changes' ) . '</p>';
		return;
	}
	if ( ! dcp_user_can_apply_proposal( $post->ID ) ) {
		echo '<p>' . esc_html__( 'You do not have permission to apply this proposal.', 'draft-changes' ) . '</p>';
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=dcp_apply&post_id=' . (int) $post->ID ),
		'dcp_apply_' . (int) $post->ID
	);
	echo '<p>' . esc_html__( 'Copy this proposal onto the live published post, save a revision for undo, then trash the proposal.', 'draft-changes' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Apply to live', 'draft-changes' ) . '</a></p>';
}

/**
 * Register metabox (visible under Panels in the block editor).
 */
function dcp_add_metaboxes() {
	foreach ( array( 'post', 'page' ) as $type ) {
		add_meta_box(
			'dcp_apply',
			__( 'Draft Changes', 'draft-changes' ),
			'dcp_render_apply_metabox',
			$type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'dcp_add_metaboxes' );

/**
 * Handle Apply to live via admin-post (works from block editor link).
 */
function dcp_handle_apply_post() {
	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	if ( ! $post_id || ! dcp_is_proposal( $post_id ) ) {
		wp_die( esc_html__( 'Invalid proposal.', 'draft-changes' ) );
	}

	check_admin_referer( 'dcp_apply_' . $post_id );

	if ( ! dcp_user_can_apply_proposal( $post_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to apply this proposal.', 'draft-changes' ) );
	}

	$result = dcp_apply_proposal( $post_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	$redirect = get_edit_post_link( $result['source_post_id'], 'raw' );
	wp_safe_redirect( add_query_arg( 'dcp_applied', '1', $redirect ) );
	exit;
}
add_action( 'admin_post_dcp_apply', 'dcp_handle_apply_post' );

/**
 * Success notice after apply.
 */
function dcp_applied_notice() {
	if ( empty( $_GET['dcp_applied'] ) ) {
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo esc_html__( 'Proposal applied to the live post. A revision was saved for undo.', 'draft-changes' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'dcp_applied_notice' );
