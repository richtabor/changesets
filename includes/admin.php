<?php
/**
 * Minimal human UI: banners + admin bar + changeset/staged content support.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin bar preview notice when viewing changeset.
 */
function dcp_admin_bar_preview( $wp_admin_bar ) {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return;
	}

	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset ) {
		return;
	}

	$status = dcp_get_changeset_status( $changeset->ID );
	$exit_url = add_query_arg( 'dcp_exit_preview', '1' );

	$wp_admin_bar->add_node(
		array(
			'id'    => 'dcp-preview',
			'title' => sprintf(
				/* translators: %s: changeset title */
				__( 'Viewing changeset: %s', 'draft-changes' ),
				esc_html( $changeset->post_title )
			),
			'href'  => get_edit_post_link( $changeset->ID ),
			'meta'  => array(
				'class' => 'dcp-preview-notice',
			),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'id'     => 'dcp-exit-preview',
			'parent' => 'dcp-preview',
			'title'  => __( 'Exit Preview', 'draft-changes' ),
			'href'   => $exit_url,
		)
	);

	if ( 'approved' === $status && dcp_user_can_publish_changeset( $changeset->ID ) ) {
		$publish_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dcp_publish_changeset&changeset_id=' . $changeset->ID ),
			'dcp_publish_changeset_' . $changeset->ID
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'dcp-publish-changeset',
				'parent' => 'dcp-preview',
				'title'  => __( 'Publish Changeset', 'draft-changes' ),
				'href'   => $publish_url,
			)
		);
	}
}
add_action( 'admin_bar_menu', 'dcp_admin_bar_preview', 100 );

/**
 * Add CSS for admin bar preview notice.
 */
function dcp_admin_bar_css() {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return;
	}
	?>
	<style>
		#wpadminbar .dcp-preview-notice {
			background: #f0f0f1;
			color: #000;
		}
		#wpadminbar .dcp-preview-notice > .ab-item {
			background: #fcf9e8;
			color: #000;
			font-weight: 600;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'dcp_admin_bar_css' );
add_action( 'admin_head', 'dcp_admin_bar_css' );

/**
 * Admin notice / banner on changeset, staged, and proposal editors.
 */
function dcp_admin_notices() {
	if ( isset( $_GET['dcp_approved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Changeset approved. An agent can Publish Changeset now.', 'draft-changes' ) . '</p></div>';
	}

	if ( isset( $_GET['dcp_published'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Changeset published! All changes are now live.', 'draft-changes' ) . '</p></div>';
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id ) {
		return;
	}

	// Changeset edit screen
	if ( 'dcp_changeset' === get_post_type( $post_id ) ) {
		$status       = dcp_get_changeset_status( $post_id );
		$preview_url  = dcp_get_preview_url( $post_id );
		$staged_count = count( dcp_get_staged_drafts( $post_id ) );

		echo '<div class="notice notice-info" style="padding:12px 16px"><p style="margin:0 0 8px">';
		echo '<strong>' . esc_html__( 'Changeset', 'draft-changes' ) . '</strong>: ';
		echo esc_html(
			sprintf(
				/* translators: %d: number of staged items */
				_n( '%d staged item', '%d staged items', $staged_count, 'draft-changes' ),
				$staged_count
			)
		);
		echo '</p>';

		echo '<p style="margin:0 0 8px"><a class="button button-secondary" href="' . esc_url( $preview_url ) . '" target="_blank">' . esc_html__( 'Preview Changeset', 'draft-changes' ) . '</a></p>';

		if ( 'approved' === $status && dcp_user_can_publish_changeset( $post_id ) ) {
			$publish_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dcp_publish_changeset&changeset_id=' . $post_id ),
				'dcp_publish_changeset_' . $post_id
			);
			echo '<p style="margin:0"><a class="button button-primary" href="' . esc_url( $publish_url ) . '">' . esc_html__( 'Publish Changeset', 'draft-changes' ) . '</a></p>';
		} elseif ( 'open' === $status && dcp_user_can_approve_changeset( $post_id ) ) {
			$approve_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dcp_approve_changeset&changeset_id=' . $post_id ),
				'dcp_approve_changeset_' . $post_id
			);
			echo '<p style="margin:0"><a class="button button-secondary" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve Changeset', 'draft-changes' ) . '</a></p>';
		}

		echo '</div>';
		return;
	}

	// Staged content edit screen
	if ( dcp_is_staged( $post_id ) ) {
		$changeset_id = dcp_get_staged_changeset_id( $post_id );
		$changeset    = dcp_get_changeset( $changeset_id );
		$source_id    = dcp_get_staged_source_id( $post_id );
		$source       = get_post( $source_id );
		$title        = $changeset ? $changeset->post_title : __( '(unknown)', 'draft-changes' );
		$edit         = $changeset ? get_edit_post_link( $changeset->ID ) : '';

		echo '<div class="notice notice-info" style="padding:12px 16px"><p style="margin:0 0 8px">';
		echo esc_html(
			sprintf(
				/* translators: %s: changeset title */
				__( 'Part of changeset: %s', 'draft-changes' ),
				$title
			)
		);
		if ( $edit ) {
			echo ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'View changeset', 'draft-changes' ) . '</a>';
		}
		echo '</p>';

		if ( $source ) {
			$source_edit = get_edit_post_link( $source_id );
			echo '<p style="margin:0">' . esc_html__( 'Source: ', 'draft-changes' ) . esc_html( $source->post_title );
			if ( $source_edit ) {
				echo ' <a href="' . esc_url( $source_edit ) . '">' . esc_html__( 'View live', 'draft-changes' ) . '</a>';
			}
			echo '</p>';
		}

		echo '</div>';
		return;
	}

	// Legacy proposal edit screen
	if ( dcp_is_proposal( $post_id ) ) {
		$source_id = dcp_get_source_id( $post_id );
		$source    = get_post( $source_id );
		$title     = $source ? $source->post_title : __( '(missing)', 'draft-changes' );
		$edit      = $source_id ? get_edit_post_link( $source_id ) : '';
		$approved  = dcp_is_approved( $post_id );

		echo '<div class="notice notice-info" style="padding:12px 16px"><p style="margin:0 0 8px">';
		echo esc_html(
			sprintf(
				/* translators: %s: source post title */
				__( 'This is a proposal. Live content stays published until Publish Live. Source: %s', 'draft-changes' ),
				$title
			)
		);
		if ( $edit ) {
			echo ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Open live post', 'draft-changes' ) . '</a>';
		}
		echo '</p>';

		if ( dcp_user_can_apply_proposal( $post_id ) ) {
			if ( $approved ) {
				echo '<p style="margin:0 0 8px"><strong>' . esc_html__( 'Approved — an agent can Publish Live, or you can Publish Live yourself.', 'draft-changes' ) . '</strong></p>';
			} else {
				$approve_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=dcp_approve&post_id=' . $post_id ),
					'dcp_approve_' . $post_id
				);
				echo '<p style="margin:0 0 8px">' . esc_html__( 'Approve first so an agent can Publish Live. Or Publish Live yourself from the editor button.', 'draft-changes' ) . '</p>';
				echo '<p style="margin:0 0 8px"><a class="button button-secondary" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve', 'draft-changes' ) . '</a></p>';
			}
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dcp_apply&post_id=' . $post_id ),
				'dcp_apply_' . $post_id
			);
			echo '<p style="margin:0"><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Publish Live', 'draft-changes' ) . '</a></p>';
		} else {
			echo '<p style="margin:0">' . esc_html__( 'You do not have permission to approve or publish this proposal.', 'draft-changes' ) . '</p>';
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
 * Handle Approve Changeset via admin-post.
 */
function dcp_handle_approve_changeset() {
	$changeset_id = isset( $_GET['changeset_id'] ) ? (int) $_GET['changeset_id'] : 0;
	check_admin_referer( 'dcp_approve_changeset_' . $changeset_id );
	if ( ! $changeset_id || ! dcp_user_can_approve_changeset( $changeset_id ) ) {
		wp_die( esc_html__( 'Cannot approve this changeset.', 'draft-changes' ) );
	}
	$result = dcp_approve_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}
	wp_safe_redirect( add_query_arg( 'dcp_approved', '1', get_edit_post_link( $changeset_id, 'raw' ) ) );
	exit;
}
add_action( 'admin_post_dcp_approve_changeset', 'dcp_handle_approve_changeset' );

/**
 * Handle Publish Changeset via admin-post.
 */
function dcp_handle_publish_changeset() {
	$changeset_id = isset( $_GET['changeset_id'] ) ? (int) $_GET['changeset_id'] : 0;
	check_admin_referer( 'dcp_publish_changeset_' . $changeset_id );
	if ( ! $changeset_id || ! dcp_user_can_publish_changeset( $changeset_id ) ) {
		wp_die( esc_html__( 'Cannot publish this changeset.', 'draft-changes' ) );
	}
	$result = dcp_publish_changeset( $changeset_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}
	wp_safe_redirect( add_query_arg( 'dcp_published', '1', admin_url( 'edit.php?post_type=dcp_changeset' ) ) );
	exit;
}
add_action( 'admin_post_dcp_publish_changeset', 'dcp_handle_publish_changeset' );

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
	$approved = dcp_is_approved( $post->ID );
	if ( ! $approved ) {
		$approve_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dcp_approve&post_id=' . (int) $post->ID ),
			'dcp_approve_' . (int) $post->ID
		);
		echo '<p>' . esc_html__( 'Approve so an agent can Publish Live. You can also Publish Live yourself anytime.', 'draft-changes' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve', 'draft-changes' ) . '</a></p>';
	} else {
		echo '<p><strong>' . esc_html__( 'Approved — agent may Publish Live.', 'draft-changes' ) . '</strong></p>';
	}
	echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Publish Live', 'draft-changes' ) . '</a></p>';
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

/**
 * Human Approve — unlocks agent Publish Live ability.
 */
function dcp_handle_approve() {
	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	check_admin_referer( 'dcp_approve_' . $post_id );
	if ( ! $post_id || ! dcp_user_can_apply_proposal( $post_id ) ) {
		wp_die( esc_html__( 'Cannot approve this proposal.', 'draft-changes' ) );
	}
	$result = dcp_approve_proposal( $post_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}
	wp_safe_redirect( add_query_arg( 'dcp_approved', '1', get_edit_post_link( $post_id, 'raw' ) ) );
	exit;
}
add_action( 'admin_post_dcp_approve', 'dcp_handle_approve' );

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


/**
 * Block editor: relabel Publish → Publish Live on proposals.
 */
function dcp_enqueue_editor_assets() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id || ! dcp_is_proposal( $post_id ) ) {
		return;
	}

	$source_id   = dcp_get_source_id( $post_id );
	$source_edit = $source_id ? get_edit_post_link( $source_id, 'raw' ) : '';
	$source_edit = $source_edit ? add_query_arg( 'dcp_applied', '1', $source_edit ) : '';

	wp_enqueue_script(
		'dcp-editor',
		DCP_URL . 'assets/editor.js',
		array( 'wp-data', 'wp-dom-ready', 'wp-editor' ),
		DCP_VERSION,
		true
	);
	wp_localize_script(
		'dcp-editor',
		'dcpEditor',
		array(
			'isProposal'    => true,
			'sourceEditUrl' => $source_edit,
			'proposalId'    => $post_id,
			'sourceId'      => $source_id,
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'dcp_enqueue_editor_assets' );
