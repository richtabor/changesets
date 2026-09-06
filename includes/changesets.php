<?php
/**
 * Changeset lifecycle: create, stage, preview overlay, publish.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Meta keys.
define( 'DCP_META_CHANGESET_UUID', '_dcp_changeset_uuid' );
define( 'DCP_META_CHANGESET_STATUS', '_dcp_changeset_status' );
define( 'DCP_META_CHANGESET_ID', '_dcp_changeset_id' );
define( 'DCP_META_IS_STAGED', '_dcp_is_staged' );

/**
 * Register the changeset CPT.
 */
function dcp_register_changeset_cpt() {
	register_post_type(
		'dcp_changeset',
		array(
			'label'               => __( 'Changesets', 'draft-changes' ),
			'labels'              => array(
				'name'          => __( 'Changesets', 'draft-changes' ),
				'singular_name' => __( 'Changeset', 'draft-changes' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
			'rewrite'             => false,
			'query_var'           => false,
		)
	);
}
add_action( 'init', 'dcp_register_changeset_cpt' );

/**
 * Create a new changeset.
 *
 * @param string $title Optional. Changeset title.
 * @return int|WP_Error Changeset ID.
 */
function dcp_create_changeset( $title = '' ) {
	if ( empty( $title ) ) {
		$title = sprintf( __( 'Changeset %s', 'draft-changes' ), gmdate( 'Y-m-d H:i:s' ) );
	}

	$changeset_id = wp_insert_post(
		array(
			'post_type'   => 'dcp_changeset',
			'post_title'  => $title,
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
		),
		true
	);

	if ( is_wp_error( $changeset_id ) ) {
		return $changeset_id;
	}

	$uuid = wp_generate_uuid4();
	update_post_meta( $changeset_id, DCP_META_CHANGESET_UUID, $uuid );
	update_post_meta( $changeset_id, DCP_META_CHANGESET_STATUS, 'open' );

	return $changeset_id;
}

/**
 * Get the open changeset (one per site for v1).
 *
 * @return int Changeset ID or 0.
 */
function dcp_get_open_changeset_id() {
	$query = new WP_Query(
		array(
			'post_type'      => 'dcp_changeset',
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => DCP_META_CHANGESET_STATUS,
					'value' => 'open',
				),
			),
			'fields'         => 'ids',
		)
	);

	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

/**
 * Get or create the open changeset.
 *
 * @param string $title Optional title if creating.
 * @return int Changeset ID.
 */
function dcp_get_or_create_open_changeset( $title = '' ) {
	$existing = dcp_get_open_changeset_id();
	if ( $existing ) {
		return $existing;
	}
	return dcp_create_changeset( $title );
}

/**
 * Get changeset UUID.
 *
 * @param int $changeset_id Changeset ID.
 * @return string UUID or empty.
 */
function dcp_get_changeset_uuid( $changeset_id ) {
	return get_post_meta( (int) $changeset_id, DCP_META_CHANGESET_UUID, true );
}

/**
 * Get changeset by UUID.
 *
 * @param string $uuid UUID.
 * @return int Changeset ID or 0.
 */
function dcp_get_changeset_by_uuid( $uuid ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'dcp_changeset',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => DCP_META_CHANGESET_UUID,
					'value' => sanitize_text_field( $uuid ),
				),
			),
			'fields'         => 'ids',
		)
	);

	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

/**
 * Check if a post is staged content.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function dcp_is_staged( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, DCP_META_IS_STAGED, true );
}

/**
 * Get staged content's source ID.
 *
 * @param int $staged_id Staged post ID.
 * @return int Source ID or 0.
 */
function dcp_get_staged_source_id( $staged_id ) {
	return (int) get_post_meta( (int) $staged_id, DCP_META_SOURCE, true );
}

/**
 * Get staged content's changeset ID.
 *
 * @param int $staged_id Staged post ID.
 * @return int Changeset ID or 0.
 */
function dcp_get_staged_changeset_id( $staged_id ) {
	return (int) get_post_meta( (int) $staged_id, DCP_META_CHANGESET_ID, true );
}

/**
 * Create staged content from a published source.
 *
 * @param int $changeset_id Changeset ID.
 * @param int $source_id    Source post ID.
 * @return int|WP_Error Staged post ID.
 */
function dcp_stage_content( $changeset_id, $source_id ) {
	$changeset_id = (int) $changeset_id;
	$source_id    = (int) $source_id;

	$changeset = get_post( $changeset_id );
	if ( ! $changeset || 'dcp_changeset' !== $changeset->post_type ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	$source = get_post( $source_id );
	if ( ! $source || 'publish' !== $source->post_status ) {
		return new WP_Error( 'dcp_invalid_source', __( 'Source must be a published post or page.', 'draft-changes' ) );
	}

	if ( ! in_array( $source->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error( 'dcp_unsupported_type', __( 'Only posts and pages are supported.', 'draft-changes' ) );
	}

	// Check for existing staged content for this source in this changeset.
	$existing = new WP_Query(
		array(
			'post_type'      => $source->post_type,
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => DCP_META_IS_STAGED,
					'value' => '1',
				),
				array(
					'key'   => DCP_META_CHANGESET_ID,
					'value' => $changeset_id,
				),
				array(
					'key'   => DCP_META_SOURCE,
					'value' => $source_id,
				),
			),
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $existing->posts ) ) {
		return new WP_Error(
			'dcp_already_staged',
			__( 'This content is already staged in this changeset.', 'draft-changes' ),
			array( 'staged_id' => (int) $existing->posts[0] )
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

	update_post_meta( $staged_id, DCP_META_IS_STAGED, 1 );
	update_post_meta( $staged_id, DCP_META_CHANGESET_ID, $changeset_id );
	update_post_meta( $staged_id, DCP_META_SOURCE, $source_id );

	$thumb = get_post_thumbnail_id( $source_id );
	if ( $thumb ) {
		set_post_thumbnail( $staged_id, $thumb );
	}

	return $staged_id;
}

/**
 * Create brand-new staged page (no source).
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $title        Page title.
 * @param string $content      Optional. Page content.
 * @param string $slug         Optional. Page slug.
 * @return int|WP_Error Staged page ID.
 */
function dcp_create_staged_page( $changeset_id, $title, $content = '', $slug = '' ) {
	$changeset_id = (int) $changeset_id;

	$changeset = get_post( $changeset_id );
	if ( ! $changeset || 'dcp_changeset' !== $changeset->post_type ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	if ( empty( $title ) ) {
		return new WP_Error( 'dcp_missing_title', __( 'Title is required.', 'draft-changes' ) );
	}

	if ( empty( $slug ) ) {
		$slug = sanitize_title( $title );
	} else {
		$slug = sanitize_title( $slug );
	}

	$staged_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => get_current_user_id(),
			'post_name'    => $slug,
		),
		true
	);

	if ( is_wp_error( $staged_id ) ) {
		return $staged_id;
	}

	update_post_meta( $staged_id, DCP_META_IS_STAGED, 1 );
	update_post_meta( $staged_id, DCP_META_CHANGESET_ID, $changeset_id );
	update_post_meta( $staged_id, DCP_META_SOURCE, 0 );

	return $staged_id;
}

/**
 * Update staged content.
 *
 * @param int   $staged_id Staged post ID.
 * @param array $fields    Keys: title, content, excerpt.
 * @return true|WP_Error
 */
function dcp_update_staged_content( $staged_id, $fields ) {
	$staged_id = (int) $staged_id;
	if ( ! dcp_is_staged( $staged_id ) ) {
		return new WP_Error( 'dcp_not_staged', __( 'Not staged content.', 'draft-changes' ) );
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
 * List staged content in a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return array Array of staged post objects.
 */
function dcp_list_staged_content( $changeset_id ) {
	$changeset_id = (int) $changeset_id;

	$query = new WP_Query(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'draft',
			'posts_per_page' => 100,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => DCP_META_IS_STAGED,
					'value' => '1',
				),
				array(
					'key'   => DCP_META_CHANGESET_ID,
					'value' => $changeset_id,
				),
			),
		)
	);

	$items = array();
	foreach ( $query->posts as $post ) {
		$source_id = dcp_get_staged_source_id( $post->ID );
		$items[]   = array(
			'staged_id'   => $post->ID,
			'source_id'   => $source_id,
			'type'        => $post->post_type,
			'title'       => $post->post_title,
			'slug'        => $post->post_name,
			'modified'    => $post->post_modified_gmt,
			'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
			'is_new'      => 0 === $source_id,
		);
	}

	return $items;
}

/**
 * Approve a changeset (human gate before publish).
 *
 * @param int $changeset_id Changeset ID.
 * @return true|WP_Error
 */
function dcp_approve_changeset( $changeset_id ) {
	$changeset_id = (int) $changeset_id;
	$changeset    = get_post( $changeset_id );

	if ( ! $changeset || 'dcp_changeset' !== $changeset->post_type ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	update_post_meta( $changeset_id, DCP_META_CHANGESET_STATUS, 'approved' );
	update_post_meta( $changeset_id, DCP_META_APPROVED, 1 );
	update_post_meta( $changeset_id, DCP_META_APPROVED_BY, get_current_user_id() );
	update_post_meta( $changeset_id, DCP_META_APPROVED_AT, gmdate( 'c' ) );

	return true;
}

/**
 * Check if changeset is approved.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_is_changeset_approved( $changeset_id ) {
	$status = get_post_meta( (int) $changeset_id, DCP_META_CHANGESET_STATUS, true );
	return 'approved' === $status || 'published' === $status;
}

/**
 * Publish a changeset (apply all staged content to live).
 *
 * @param int $changeset_id Changeset ID.
 * @return array|WP_Error { applied_count, published_new_count }
 */
function dcp_publish_changeset( $changeset_id ) {
	$changeset_id = (int) $changeset_id;
	$changeset    = get_post( $changeset_id );

	if ( ! $changeset || 'dcp_changeset' !== $changeset->post_type ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	$staged_items = dcp_list_staged_content( $changeset_id );
	$applied      = 0;
	$new_published = 0;

	foreach ( $staged_items as $item ) {
		$staged_id = $item['staged_id'];
		$source_id = $item['source_id'];
		$staged    = get_post( $staged_id );

		if ( ! $staged ) {
			continue;
		}

		if ( $source_id > 0 ) {
			// Merge onto existing published source.
			$source = get_post( $source_id );
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
				)
			);

			$thumb = get_post_thumbnail_id( $staged_id );
			if ( $thumb ) {
				set_post_thumbnail( $source_id, $thumb );
			} else {
				delete_post_thumbnail( $source_id );
			}

			wp_delete_post( $staged_id, true );
			$applied++;
		} else {
			// Publish brand-new staged page.
			wp_update_post(
				array(
					'ID'          => $staged_id,
					'post_status' => 'publish',
				)
			);

			delete_post_meta( $staged_id, DCP_META_IS_STAGED );
			delete_post_meta( $staged_id, DCP_META_CHANGESET_ID );
			delete_post_meta( $staged_id, DCP_META_SOURCE );

			$new_published++;
		}
	}

	update_post_meta( $changeset_id, DCP_META_CHANGESET_STATUS, 'published' );

	return array(
		'applied_count'       => $applied,
		'published_new_count' => $new_published,
	);
}

/**
 * Get the active preview changeset UUID from query param or cookie.
 *
 * @return string UUID or empty.
 */
function dcp_get_active_preview_uuid() {
	$uuid = '';

	if ( isset( $_GET['dcp_changeset'] ) ) {
		$uuid = sanitize_text_field( wp_unslash( $_GET['dcp_changeset'] ) );
	} elseif ( isset( $_COOKIE['dcp_changeset'] ) ) {
		$uuid = sanitize_text_field( wp_unslash( $_COOKIE['dcp_changeset'] ) );
	}

	return $uuid;
}

/**
 * Overlay staged content in queries when previewing a changeset.
 * Hooks into pre_get_posts to modify WP_Query.
 *
 * @param WP_Query $query Query object.
 */
function dcp_overlay_staged_content( $query ) {
	$uuid = dcp_get_active_preview_uuid();
	if ( empty( $uuid ) ) {
		return;
	}

	$changeset_id = dcp_get_changeset_by_uuid( $uuid );
	if ( ! $changeset_id ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	// Get staged content for this changeset.
	$staged_query = new WP_Query(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'draft',
			'posts_per_page' => 100,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => DCP_META_IS_STAGED,
					'value' => '1',
				),
				array(
					'key'   => DCP_META_CHANGESET_ID,
					'value' => $changeset_id,
				),
			),
			'fields'         => 'ids',
		)
	);

	if ( empty( $staged_query->posts ) ) {
		return;
	}

	// Build mapping of source_id => staged_id.
	$staged_map = array();
	$new_staged_ids = array();

	foreach ( $staged_query->posts as $staged_id ) {
		$source_id = dcp_get_staged_source_id( $staged_id );
		if ( $source_id > 0 ) {
			$staged_map[ $source_id ] = $staged_id;
		} else {
			$new_staged_ids[] = $staged_id;
		}
	}

	// Modify query to include new staged pages and replace sources with staged versions.
	if ( ! empty( $new_staged_ids ) || ! empty( $staged_map ) ) {
		add_filter( 'posts_results', function( $posts ) use ( $staged_map, $new_staged_ids, $changeset_id ) {
			return dcp_replace_with_staged_content( $posts, $staged_map, $new_staged_ids, $changeset_id );
		}, 10 );
	}
}
add_action( 'pre_get_posts', 'dcp_overlay_staged_content' );

/**
 * Replace posts with staged versions and inject new staged pages.
 *
 * @param array $posts          Array of post objects.
 * @param array $staged_map     Map of source_id => staged_id.
 * @param array $new_staged_ids Array of new staged page IDs.
 * @param int   $changeset_id   Changeset ID.
 * @return array Modified posts.
 */
function dcp_replace_with_staged_content( $posts, $staged_map, $new_staged_ids, $changeset_id ) {
	$modified_posts = array();

	// Replace existing posts with staged versions.
	foreach ( $posts as $post ) {
		if ( isset( $staged_map[ $post->ID ] ) ) {
			$staged = get_post( $staged_map[ $post->ID ] );
			if ( $staged ) {
				$staged->post_status = 'publish';
				$modified_posts[] = $staged;
			} else {
				$modified_posts[] = $post;
			}
		} else {
			$modified_posts[] = $post;
		}
	}

	// Inject new staged pages (make them appear published).
	foreach ( $new_staged_ids as $staged_id ) {
		$staged = get_post( $staged_id );
		if ( $staged ) {
			$staged->post_status = 'publish';
			$modified_posts[] = $staged;
		}
	}

	return $modified_posts;
}
