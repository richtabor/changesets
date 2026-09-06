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
			'map_meta_cap'        => true,
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
 * Update a changeset title (agent-owned session label).
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $title        New title.
 * @return true|WP_Error
 */
function dcp_update_changeset_title( $changeset_id, $title ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}
	$title = trim( (string) $title );
	if ( '' === $title ) {
		return new WP_Error( 'dcp_missing_title', __( 'Title is required.', 'draft-changes' ) );
	}
	$result = wp_update_post(
		array(
			'ID'         => (int) $changeset_id,
			'post_title' => $title,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return true;
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
 * Get staged options bag for a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return array
 */
function dcp_get_staged_options( $changeset_id ) {
	$raw = get_post_meta( (int) $changeset_id, '_dcp_staged_options', true );
	return is_array( $raw ) ? $raw : array();
}

/**
 * Stage a site option into a changeset (not applied live until Publish).
 *
 * Supported keys initially: show_on_front, page_on_front, page_for_posts, blogname, blogdescription.
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $key          Option name.
 * @param mixed  $value        Option value.
 * @return true|WP_Error
 */
function dcp_stage_option( $changeset_id, $key, $value ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	$allowed = array( 'show_on_front', 'page_on_front', 'page_for_posts', 'blogname', 'blogdescription' );
	if ( ! in_array( $key, $allowed, true ) ) {
		return new WP_Error( 'dcp_unsupported_option', __( 'That setting is not stageable yet.', 'draft-changes' ) );
	}

	if ( in_array( $key, array( 'page_on_front', 'page_for_posts' ), true ) ) {
		$value = (int) $value;
	} else {
		$value = is_string( $value ) ? $value : (string) $value;
	}

	$bag         = dcp_get_staged_options( $changeset_id );
	$bag[ $key ] = $value;
	update_post_meta( (int) $changeset_id, '_dcp_staged_options', $bag );
	return true;
}



/**
 * Get staged global styles (user theme.json array) for a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return array|null
 */
function dcp_get_staged_global_styles( $changeset_id ) {
	$raw = get_post_meta( (int) $changeset_id, '_dcp_staged_global_styles', true );
	if ( is_array( $raw ) ) {
		return $raw;
	}
	if ( is_string( $raw ) && $raw ) {
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}
	return null;
}

/**
 * Save staged global styles JSON onto a changeset.
 *
 * @param int   $changeset_id Changeset ID.
 * @param array $data         User theme.json-shaped data.
 * @return true|WP_Error
 */
function dcp_set_staged_global_styles( $changeset_id, $data ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'dcp_invalid_styles', __( 'Global styles must be an object.', 'draft-changes' ) );
	}
	$data['isGlobalStylesUserThemeJSON'] = true;
	if ( empty( $data['version'] ) ) {
		$data['version'] = 3;
	}
	update_post_meta( (int) $changeset_id, '_dcp_staged_global_styles', $data );
	return true;
}

/**
 * Stage arbitrary global styles into a changeset (merge onto current staged or live user styles).
 *
 * @param int   $changeset_id Changeset ID.
 * @param array $patch        Partial theme.json (settings/styles/etc).
 * @return array|WP_Error
 */
function dcp_stage_global_styles( $changeset_id, $patch ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}
	if ( ! is_array( $patch ) ) {
		return new WP_Error( 'dcp_invalid_styles', __( 'Global styles patch must be an object.', 'draft-changes' ) );
	}

	$base = dcp_get_staged_global_styles( $changeset_id );
	if ( ! $base ) {
		$base = array(
			'version'                       => 3,
			'isGlobalStylesUserThemeJSON'   => true,
		);
		// Seed from live user global styles when present.
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			$user = WP_Theme_JSON_Resolver::get_user_data();
			if ( $user && method_exists( $user, 'get_raw_data' ) ) {
				$raw = $user->get_raw_data();
				if ( is_array( $raw ) && $raw ) {
					$base = $raw;
					$base['isGlobalStylesUserThemeJSON'] = true;
				}
			}
		}
	}

	// Shallow+nested merge for settings/styles keys.
	foreach ( $patch as $key => $value ) {
		if ( in_array( $key, array( 'settings', 'styles' ), true ) && is_array( $value ) ) {
			$existing = isset( $base[ $key ] ) && is_array( $base[ $key ] ) ? $base[ $key ] : array();
			$base[ $key ] = array_replace_recursive( $existing, $value );
		} elseif ( '$schema' !== $key ) {
			$base[ $key ] = $value;
		}
	}

	$result = dcp_set_staged_global_styles( $changeset_id, $base );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'changeset_id' => (int) $changeset_id,
		'staged'       => true,
		'styles'       => $base,
	);
}

/**
 * Resolve a theme style variation by file stem or title.
 *
 * @param string $variation File stem (e.g. 05-twilight) or title (e.g. Twilight).
 * @return array|WP_Error { stem, title, data }
 */
function dcp_resolve_style_variation( $variation ) {
	$variation = trim( (string) $variation );
	if ( '' === $variation ) {
		return new WP_Error( 'dcp_missing_variation', __( 'Style variation is required.', 'draft-changes' ) );
	}

	$dir = trailingslashit( get_stylesheet_directory() ) . 'styles/';
	if ( ! is_dir( $dir ) ) {
		return new WP_Error( 'dcp_no_variations', __( 'This theme has no style variations.', 'draft-changes' ) );
	}

	$files = glob( $dir . '*.json' );
	if ( ! $files ) {
		return new WP_Error( 'dcp_no_variations', __( 'This theme has no style variations.', 'draft-changes' ) );
	}

	foreach ( $files as $file ) {
		$stem = basename( $file, '.json' );
		$raw  = file_get_contents( $file );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		$title = isset( $data['title'] ) ? (string) $data['title'] : $stem;
		if ( strcasecmp( $stem, $variation ) === 0 || strcasecmp( $title, $variation ) === 0 ) {
			unset( $data['$schema'] );
			return array(
				'stem'  => $stem,
				'title' => $title,
				'data'  => $data,
			);
		}
	}

	return new WP_Error( 'dcp_unknown_variation', __( 'Style variation not found.', 'draft-changes' ) );
}

/**
 * Stage a theme style variation into a changeset.
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $variation    File stem or title.
 * @return array|WP_Error
 */
function dcp_stage_style_variation( $changeset_id, $variation ) {
	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	$resolved = dcp_resolve_style_variation( $variation );
	if ( is_wp_error( $resolved ) ) {
		return $resolved;
	}

	$result = dcp_set_staged_global_styles( $changeset_id, $resolved['data'] );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	update_post_meta( (int) $changeset_id, '_dcp_staged_style_variation', $resolved['stem'] );
	update_post_meta( (int) $changeset_id, '_dcp_staged_style_variation_title', $resolved['title'] );

	return array(
		'changeset_id' => (int) $changeset_id,
		'stem'         => $resolved['stem'],
		'title'        => $resolved['title'],
		'staged'       => true,
	);
}

/**
 * Get staged style variation stem for a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return string
 */
function dcp_get_staged_style_variation( $changeset_id ) {
	return (string) get_post_meta( (int) $changeset_id, '_dcp_staged_style_variation', true );
}

/**
 * Create a brand-new page that lives only inside a changeset (no live source).
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $title        Page title.
 * @param string $content      Optional content.
 * @param string $slug         Optional slug.
 * @return int|WP_Error Staged page ID.
 */
function dcp_create_staged_page( $changeset_id, $title, $content = '', $slug = '' ) {
	$changeset_id = (int) $changeset_id;

	$changeset = dcp_get_changeset( $changeset_id );
	if ( ! $changeset ) {
		return new WP_Error( 'dcp_invalid_changeset', __( 'Invalid changeset.', 'draft-changes' ) );
	}

	if ( '' === trim( (string) $title ) ) {
		return new WP_Error( 'dcp_missing_title', __( 'Title is required.', 'draft-changes' ) );
	}

	$slug = $slug ? sanitize_title( $slug ) : sanitize_title( $title );

	$staged_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			'post_name'    => $slug,
		),
		true
	);

	if ( is_wp_error( $staged_id ) ) {
		return $staged_id;
	}

	update_post_meta( $staged_id, '_dcp_is_staged', 1 );
	update_post_meta( $staged_id, '_dcp_changeset_id', $changeset_id );
	update_post_meta( $staged_id, DCP_META_SOURCE, 0 );

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
			'dcp_internal'   => true,
			'suppress_filters' => true,
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

	$staged_ids      = dcp_get_staged_drafts( $changeset_id );
	$applied         = 0;
	$published_new   = 0;
	$source_ids      = array();

	foreach ( $staged_ids as $staged_id ) {
		$staged    = get_post( $staged_id );
		$source_id = dcp_get_staged_source_id( $staged_id );

		if ( ! $staged ) {
			continue;
		}

		if ( $source_id > 0 ) {
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
			$applied++;
		} else {
			// Promote brand-new staged page to live publish.
			wp_update_post(
				array(
					'ID'          => $staged_id,
					'post_status' => 'publish',
				),
				true
			);
			delete_post_meta( $staged_id, '_dcp_is_staged' );
			delete_post_meta( $staged_id, '_dcp_changeset_id' );
			delete_post_meta( $staged_id, DCP_META_SOURCE );
			$source_ids[] = $staged_id;
			$published_new++;
		}
	}

	$options = dcp_get_staged_options( $changeset_id );
	foreach ( $options as $key => $value ) {
		update_option( $key, $value );
	}
	if ( $options ) {
		delete_post_meta( $changeset_id, '_dcp_staged_options' );
	}

	$staged_styles = dcp_get_staged_global_styles( $changeset_id );
	if ( ! $staged_styles ) {
		$variation_stem = dcp_get_staged_style_variation( $changeset_id );
		if ( $variation_stem ) {
			$resolved = dcp_resolve_style_variation( $variation_stem );
			if ( ! is_wp_error( $resolved ) ) {
				$staged_styles = $resolved['data'];
			}
		}
	}
	if ( $staged_styles ) {
		$user_post = null;
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			$user_post = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		}
		if ( is_array( $user_post ) && ! empty( $user_post['ID'] ) ) {
			$payload                               = $staged_styles;
			$payload['isGlobalStylesUserThemeJSON'] = true;
			$payload['version']                     = isset( $payload['version'] ) ? $payload['version'] : 3;
			wp_update_post(
				array(
					'ID'           => (int) $user_post['ID'],
					'post_content' => wp_slash( wp_json_encode( $payload ) ),
				),
				true
			);
		}
		delete_post_meta( $changeset_id, '_dcp_staged_global_styles' );
		delete_post_meta( $changeset_id, '_dcp_staged_style_variation' );
		delete_post_meta( $changeset_id, '_dcp_staged_style_variation_title' );
	}

	update_post_meta( $changeset_id, '_dcp_changeset_status', 'published' );
	update_post_meta( $changeset_id, '_dcp_published_at', gmdate( 'c' ) );
	update_post_meta( $changeset_id, '_dcp_published_by', get_current_user_id() );

	dcp_clear_preview_cookie();

	return array(
		'changeset_id'        => $changeset_id,
		'applied_count'       => $applied,
		'published_new_count' => $published_new,
		'source_ids'          => $source_ids,
		'status'              => 'published',
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
 * Session cookie (expires on browser close) with httponly for security.
 * Set at site root (/) to work across all pages.
 *
 * @param string $uuid Changeset UUID.
 */
function dcp_set_preview_cookie( $uuid ) {
	if ( headers_sent() ) {
		return;
	}
	setcookie( 'dcp_changeset', $uuid, 0, '/', '', is_ssl(), true );
	$_COOKIE['dcp_changeset'] = $uuid;
}

/**
 * Clear preview cookie.
 *
 * Expires the cookie by setting it to empty with a past timestamp.
 */
function dcp_clear_preview_cookie() {
	unset( $_COOKIE['dcp_changeset'] );
	if ( headers_sent() ) {
		return;
	}
	setcookie( 'dcp_changeset', '', time() - YEAR_IN_SECONDS, '/', '', is_ssl(), true );
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
	// Exit first — clear cookie even if UUID only lived in the cookie.
	if ( isset( $_GET['dcp_exit_preview'] ) ) {
		dcp_clear_preview_cookie();
		wp_safe_redirect( remove_query_arg( array( 'dcp_exit_preview', 'dcp_changeset' ) ) );
		exit;
	}

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
}
add_action( 'init', 'dcp_init_preview' );



/**
 * Load staged draft IDs for the active preview changeset once per request.
 *
 * Optimized with a single indexed query and static caching.
 *
 * @return array{changeset_id:int, map:array<int,int>, new_ids:array<int>}|null
 */
function dcp_preview_staged_index() {
	static $index = null;
	static $loaded = false;

	if ( $loaded ) {
		return $index;
	}
	$loaded = true;

	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return null;
	}

	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset ) {
		return null;
	}

	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) AS source_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_status = 'draft'
			AND pm.meta_key IN ('_dcp_is_staged', '_dcp_changeset_id', %s)
			AND EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2
				WHERE pm2.post_id = p.ID
				AND pm2.meta_key = '_dcp_is_staged'
				AND pm2.meta_value = '1'
			)
			AND EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm3
				WHERE pm3.post_id = p.ID
				AND pm3.meta_key = '_dcp_changeset_id'
				AND pm3.meta_value = %d
			)
			GROUP BY p.ID",
			DCP_META_SOURCE,
			DCP_META_SOURCE,
			$changeset->ID
		)
	);

	$map     = array();
	$new_ids = array();
	foreach ( $rows as $row ) {
		$staged_id = (int) $row->ID;
		$source_id = (int) $row->source_id;
		if ( $source_id > 0 ) {
			$map[ $source_id ] = $staged_id;
		} else {
			$new_ids[] = $staged_id;
		}
	}

	$index = array(
		'changeset_id' => (int) $changeset->ID,
		'map'          => $map,
		'new_ids'      => $new_ids,
	);
	return $index;
}

/**
 * Overlay staged content in WP_Query results during preview.
 *
 * Efficiently swaps live posts with their staged versions using pre-loaded index.
 *
 * @param array    $posts Posts.
 * @param WP_Query $query Query.
 * @return array
 */
function dcp_overlay_staged_content( $posts, $query ) {
	$index = dcp_preview_staged_index();
	if ( ! $index || ! $posts ) {
		return $posts;
	}

	$map     = $index['map'];
	$new_ids = $index['new_ids'];

	// Swap live posts with staged versions.
	foreach ( $posts as $i => $post ) {
		if ( isset( $map[ (int) $post->ID ] ) ) {
			$staged = wp_cache_get( $map[ (int) $post->ID ], 'posts' );
			if ( ! $staged ) {
				$staged = get_post( $map[ (int) $post->ID ] );
			}
			if ( $staged ) {
				$overlay              = clone $staged;
				$overlay->ID          = $post->ID;
				$overlay->post_name   = $post->post_name;
				$overlay->post_status = 'publish';
				$posts[ $i ]          = $overlay;
			}
		}
	}

	// Inject brand-new staged pages for relevant queries.
	$post_type   = $query->get( 'post_type' );
	$wants_pages = ( 'page' === $post_type ) || ( is_array( $post_type ) && in_array( 'page', $post_type, true ) );

	if ( $new_ids && $wants_pages ) {
		$existing = array_flip( wp_list_pluck( $posts, 'ID' ) );
		foreach ( $new_ids as $staged_id ) {
			if ( isset( $existing[ $staged_id ] ) ) {
				continue;
			}
			$staged = wp_cache_get( $staged_id, 'posts' );
			if ( ! $staged ) {
				$staged = get_post( $staged_id );
			}
			if ( $staged && 'page' === $staged->post_type ) {
				$overlay              = clone $staged;
				$overlay->post_status = 'publish';
				$posts[]              = $overlay;
			}
		}
	}

	return $posts;
}

/**
 * @param WP_Query $query Query.
 */
function dcp_preview_filter_posts( $query ) {
	static $added = false;
	if ( $added || ! dcp_get_active_preview_uuid() ) {
		return;
	}
	if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}
	$added = true;
	add_filter( 'posts_results', 'dcp_overlay_staged_content', 10, 2 );
}
add_action( 'pre_get_posts', 'dcp_preview_filter_posts' );

/**
 * Overlay staged options during preview.
 *
 * @param mixed  $pre  Short-circuit value.
 * @param string $option Option name.
 * @return mixed
 */
function dcp_preview_filter_option( $pre, $option ) {
	$index = dcp_preview_staged_index();
	if ( ! $index ) {
		return $pre;
	}
	$bag = dcp_get_staged_options( $index['changeset_id'] );
	if ( array_key_exists( $option, $bag ) ) {
		return $bag[ $option ];
	}
	return $pre;
}
add_filter( 'pre_option_show_on_front', 'dcp_preview_filter_option', 10, 2 );
add_filter( 'pre_option_page_on_front', 'dcp_preview_filter_option', 10, 2 );
add_filter( 'pre_option_page_for_posts', 'dcp_preview_filter_option', 10, 2 );
add_filter( 'pre_option_blogname', 'dcp_preview_filter_option', 10, 2 );
add_filter( 'pre_option_blogdescription', 'dcp_preview_filter_option', 10, 2 );

/**
 * Overlay staged style variation onto user theme.json during preview.
 *
 * @param WP_Theme_JSON_Data $theme_json Theme JSON data object.
 * @return WP_Theme_JSON_Data
 */
function dcp_preview_global_styles( $theme_json ) {
	$uuid = dcp_get_active_preview_uuid();
	if ( ! $uuid ) {
		return $theme_json;
	}
	$changeset = dcp_get_changeset( $uuid );
	if ( ! $changeset || ! class_exists( 'WP_Theme_JSON_Data' ) ) {
		return $theme_json;
	}

	$payload = dcp_get_staged_global_styles( $changeset->ID );
	if ( ! $payload ) {
		// Back-compat: variation stem only.
		$stem = dcp_get_staged_style_variation( $changeset->ID );
		if ( ! $stem ) {
			return $theme_json;
		}
		$resolved = dcp_resolve_style_variation( $stem );
		if ( is_wp_error( $resolved ) ) {
			return $theme_json;
		}
		$payload = $resolved['data'];
	}

	$payload['isGlobalStylesUserThemeJSON'] = true;
	if ( empty( $payload['version'] ) ) {
		$payload['version'] = 3;
	}
	return new WP_Theme_JSON_Data( $payload, 'custom' );
}
add_filter( 'wp_theme_json_data_user', 'dcp_preview_global_styles' );





/**
 * Inject new staged pages into get_pages for core/page-list (SQL-backed index, no nested get_pages).
 *
 * @param array $pages Pages.
 * @param array $args  Args.
 * @return array
 */
function dcp_preview_filter_get_pages( $pages, $args ) {
	$index = dcp_preview_staged_index();
	if ( ! $index || empty( $index['new_ids'] ) ) {
		return $pages;
	}

	$existing = array();
	foreach ( $pages as $p ) {
		$existing[ (int) $p->ID ] = true;
	}
	foreach ( $index['new_ids'] as $staged_id ) {
		if ( isset( $existing[ (int) $staged_id ] ) ) {
			continue;
		}
		$staged = get_post( $staged_id );
		if ( ! $staged || 'page' !== $staged->post_type ) {
			continue;
		}
		$overlay              = clone $staged;
		$overlay->post_status = 'publish';
		$pages[]              = $overlay;
	}
	return $pages;
}
add_filter( 'get_pages', 'dcp_preview_filter_get_pages', 10, 2 );

/**
 * Resolve /slug/ to a source-less staged page while previewing.
 *
 * @param array $query_vars Query vars.
 * @return array
 */
function dcp_preview_resolve_new_page( $query_vars ) {
	if ( empty( $query_vars['pagename'] ) ) {
		return $query_vars;
	}
	$index = dcp_preview_staged_index();
	if ( ! $index || empty( $index['new_ids'] ) ) {
		return $query_vars;
	}
	$pagename = $query_vars['pagename'];
	foreach ( $index['new_ids'] as $staged_id ) {
		$staged = get_post( $staged_id );
		if ( $staged && 'page' === $staged->post_type && $staged->post_name === $pagename ) {
			unset( $query_vars['pagename'] );
			$query_vars['page_id']   = $staged_id;
			$query_vars['post_type'] = 'page';
			return $query_vars;
		}
	}
	return $query_vars;
}
add_filter( 'request', 'dcp_preview_resolve_new_page' );

/**
 * Allow main query to load draft staged pages when preview resolves page_id to one.
 *
 * @param WP_Query $query Query.
 */
function dcp_preview_allow_staged_status( $query ) {
	if ( ! $query->is_main_query() || ! dcp_get_active_preview_uuid() ) {
		return;
	}
	$page_id = (int) $query->get( 'page_id' );
	if ( ! $page_id ) {
		return;
	}
	$index = dcp_preview_staged_index();
	if ( $index && in_array( $page_id, $index['new_ids'], true ) ) {
		$query->set( 'post_status', array( 'publish', 'draft' ) );
	}
}
add_action( 'pre_get_posts', 'dcp_preview_allow_staged_status', 20 );

/**
 * Hide staged drafts from the default Pages / Posts admin lists.
 *
 * @param WP_Query $query Query.
 */
function dcp_hide_staged_from_admin_lists( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-page', 'edit-post' ), true ) ) {
		return;
	}
	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = array();
	}
	$meta_query[] = array(
		'relation' => 'OR',
		array(
			'key'     => '_dcp_is_staged',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_dcp_is_staged',
			'value'   => '1',
			'compare' => '!=',
		),
	);
	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'dcp_hide_staged_from_admin_lists' );

/**
 * When a changeset is deleted, hard-delete its staged drafts.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function dcp_delete_changeset_staged( $post_id, $post ) {
	if ( ! $post || 'dcp_changeset' !== $post->post_type ) {
		return;
	}
	foreach ( dcp_get_staged_drafts( $post_id ) as $staged_id ) {
		wp_delete_post( $staged_id, true );
	}
}
add_action( 'before_delete_post', 'dcp_delete_changeset_staged', 10, 2 );

/**
 * Whether the current user can approve a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_user_can_approve_changeset( $changeset_id ) {
	return current_user_can( 'approve_changesets' ) || current_user_can( 'publish_posts' ) || current_user_can( 'publish_pages' );
}

/**
 * Whether the current user can publish a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return bool
 */
function dcp_user_can_publish_changeset( $changeset_id ) {
	return current_user_can( 'publish_changesets' ) || current_user_can( 'publish_posts' ) || current_user_can( 'publish_pages' );
}
