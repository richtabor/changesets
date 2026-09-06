<?php
/**
 * Lean UI: Changeset bar on preview + Changesets list Preview action.
 *
 * No admin-bar items. Approve / Publish stay abilities (MCP).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the Changeset bar while previewing (logged-in or not).
 */
function cs_render_changeset_bar() {
	$uuid = cs_get_active_preview_uuid();
	if ( ! $uuid ) {
		return;
	}

	$changeset = cs_get_changeset( $uuid );
	if ( ! $changeset ) {
		return;
	}

	$exit_url = add_query_arg(
		array(
			'cs_exit_preview' => '1',
			'changeset'        => false,
		)
	);

	$title  = get_the_title( $changeset );
	$status = cs_get_changeset_status( $changeset->ID );
	?>
	<style id="dcp-changeset-bar-styles">
		/* Changeset preview bar — matches WP admin bar behavior (fixed desktop, scrolls on mobile). */
		html.dcp-previewing {
			--dcp-changeset-bar-height: 32px;
			margin-top: var(--dcp-changeset-bar-height) !important;
		}
		html.dcp-previewing.admin-bar {
			margin-top: calc(32px + var(--dcp-changeset-bar-height)) !important;
		}
		.dcp-changeset-bar {
			position: fixed;
			top: 0;
			left: 0;
			z-index: 99998;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			box-sizing: border-box;
			width: 100%;
			height: var(--dcp-changeset-bar-height);
			padding: 0 14px;
			background: #1e1e1e;
			color: #f0f0f0;
			font: 13px/32px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		}
		body.admin-bar .dcp-changeset-bar {
			top: 32px;
		}
		.dcp-changeset-bar__label {
			display: flex;
			align-items: baseline;
			gap: 8px;
			min-width: 0;
			overflow: hidden;
		}
		.dcp-changeset-bar__kicker {
			opacity: 0.7;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			font-size: 11px;
			font-weight: 600;
			flex: 0 0 auto;
		}
		.dcp-changeset-bar__title {
			font-weight: 600;
			color: #fff;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.dcp-changeset-bar__status {
			opacity: 0.75;
			font-size: 12px;
			flex: 0 0 auto;
		}
		.dcp-changeset-bar__exit {
			flex: 0 0 auto;
			display: inline-block;
			background: #fcf9e8;
			color: #1e1e1e;
			font-weight: 600;
			line-height: 1;
			text-decoration: none;
			padding: 6px 10px;
			border-radius: 2px;
			-webkit-tap-highlight-color: transparent;
		}
		.dcp-changeset-bar__exit:hover,
		.dcp-changeset-bar__exit:focus {
			background: #fff3bf;
			color: #000;
		}
		/* Sticky site headers sit below the Changeset bar (same idea as admin-bar). */
		html.dcp-previewing .is-position-sticky {
			top: var(--dcp-changeset-bar-height) !important;
		}
		html.dcp-previewing.admin-bar .is-position-sticky {
			top: calc(32px + var(--dcp-changeset-bar-height)) !important;
		}
		/* Mobile nav overlay must paint above the Changeset bar. */
		html.dcp-previewing .wp-block-navigation__responsive-container.is-menu-open {
			z-index: 100001 !important;
		}
		html.dcp-previewing.has-modal-open .is-menu-open:where(:not(.disable-default-overlay)) .wp-block-navigation__responsive-dialog {
			margin-top: var(--dcp-changeset-bar-height);
		}
		html.dcp-previewing.admin-bar.has-modal-open .is-menu-open:where(:not(.disable-default-overlay)) .wp-block-navigation__responsive-dialog {
			margin-top: calc(46px + var(--dcp-changeset-bar-height));
		}
		@media screen and (max-width: 782px) {
			html.dcp-previewing {
				--dcp-changeset-bar-height: 46px;
			}
			.dcp-changeset-bar {
				position: absolute;
				font-size: 14px;
				line-height: 46px;
				padding: 0 12px;
			}
			body.admin-bar .dcp-changeset-bar {
				top: 46px;
			}
			html.dcp-previewing.admin-bar .is-position-sticky {
				top: calc(46px + var(--dcp-changeset-bar-height)) !important;
			}
			html.dcp-previewing.admin-bar.has-modal-open .is-menu-open:where(:not(.disable-default-overlay)) .wp-block-navigation__responsive-dialog {
				margin-top: calc(46px + var(--dcp-changeset-bar-height));
			}
			.dcp-changeset-bar__exit {
				padding: 8px 12px;
			}
		}
		@media screen and (min-width: 783px) {
			html.dcp-previewing.admin-bar.has-modal-open .is-menu-open:where(:not(.disable-default-overlay)) .wp-block-navigation__responsive-dialog {
				margin-top: calc(32px + var(--dcp-changeset-bar-height));
			}
		}
	</style>
	<div class="dcp-changeset-bar" role="banner" aria-label="<?php echo esc_attr__( 'Changeset preview', 'changesets' ); ?>">
		<div class="dcp-changeset-bar__label">
			<span class="dcp-changeset-bar__kicker"><?php echo esc_html__( 'Changeset', 'changesets' ); ?></span>
			<span class="dcp-changeset-bar__title"><?php echo esc_html( $title ); ?></span>
			<?php if ( $status && 'open' !== $status ) : ?>
				<span class="dcp-changeset-bar__status"><?php echo esc_html( $status ); ?></span>
			<?php endif; ?>
		</div>
		<a class="dcp-changeset-bar__exit" href="<?php echo esc_url( $exit_url ); ?>">
			<?php echo esc_html__( 'Exit Changeset', 'changesets' ); ?>
		</a>
	</div>
	<script>
		// Ensure one admin-bar offset: remove html margin if admin-bar present.
		if ( document.body.classList.contains( 'admin-bar' ) ) {
			document.documentElement.style.setProperty( '--dcp-changeset-bar-offset', '0px' );
		}
	</script>
	<?php
}
add_action( 'wp_body_open', 'cs_render_changeset_bar', 1 );
add_action( 'wp_footer', 'cs_render_changeset_bar_footer_fallback', 999 );

/**
 * Fallback if the theme never calls wp_body_open.
 */
function cs_render_changeset_bar_footer_fallback() {
	if ( did_action( 'wp_body_open' ) ) {
		return;
	}
	cs_render_changeset_bar();
}

/**
 * Changesets list: Preview opens the front-end overlay; no Quick Edit; no Edit.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function cs_changeset_row_actions( $actions, $post ) {
	if ( ! $post || 'cs_changeset' !== $post->post_type ) {
		return $actions;
	}

	$trash  = isset( $actions['trash'] ) ? array( 'trash' => $actions['trash'] ) : array();
	$status = cs_get_changeset_status( $post->ID );
	$url    = cs_get_preview_url( $post->ID );

	$out = array();
	if ( $url && in_array( $status, array( 'open', 'approved' ), true ) ) {
		$out['cs_preview'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Preview Changeset', 'changesets' )
		);
	}

	return $out + $trash;
}
add_filter( 'post_row_actions', 'cs_changeset_row_actions', 10, 2 );

/**
 * Point list title / edit link at Preview for open changesets.
 *
 * @param string      $url     Edit URL.
 * @param int|WP_Post $post_id Post.
 * @param string      $context Context.
 * @return string
 */
function cs_changeset_edit_link_to_preview( $url, $post_id, $context = 'display' ) {
	$post = get_post( $post_id );
	if ( ! $post || 'cs_changeset' !== $post->post_type ) {
		return $url;
	}
	$status  = cs_get_changeset_status( $post->ID );
	$preview = cs_get_preview_url( $post->ID );
	if ( $preview && in_array( $status, array( 'open', 'approved' ), true ) ) {
		return $preview;
	}
	return $url;
}
add_filter( 'get_edit_post_link', 'cs_changeset_edit_link_to_preview', 10, 3 );

/**
 * Hide Quick Edit on the Changesets list.
 */
function cs_disable_changeset_quick_edit() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-cs_changeset' !== $screen->id ) {
		return;
	}
	wp_add_inline_style(
		'common',
		'.post-type-cs_changeset .row-actions .inline, .post-type-cs_changeset button.editinline { display: none !important; }'
	);
}
add_action( 'admin_enqueue_scripts', 'cs_disable_changeset_quick_edit' );

/**
 * Mark preview sessions on <html> for admin-bar-like offset.
 *
 * @param array $classes Classes.
 * @return array
 */
function cs_previewing_admin_body_class( $classes ) {
	if ( cs_get_active_preview_uuid() && cs_get_changeset( cs_get_active_preview_uuid() ) ) {
		$classes[] = 'dcp-previewing';
	}
	return $classes;
}
add_filter( 'body_class', 'cs_previewing_admin_body_class' );

/**
 * @param string $output Language attributes.
 * @return string
 */
function cs_previewing_html_class( $output ) {
	if ( cs_get_active_preview_uuid() && cs_get_changeset( cs_get_active_preview_uuid() ) ) {
		if ( false !== strpos( $output, 'class="' ) ) {
			$output = str_replace( 'class="', 'class="dcp-previewing ', $output );
		} else {
			$output .= ' class="dcp-previewing"';
		}
	}
	return $output;
}
add_filter( 'language_attributes', 'cs_previewing_html_class' );
