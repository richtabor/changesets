<?php
/**
 * Plugin Name:       Draft Changes
 * Description:       Let agents propose edits to published posts and pages without changing the live version. Humans review in the editor and apply.
 * Version:           0.1.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Rich Tabor
 * License:           GPL-2.0-or-later
 * Text Domain:       draft-changes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCP_VERSION', '0.1.1' );
define( 'DCP_FILE', __FILE__ );
define( 'DCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCP_URL', plugin_dir_url( __FILE__ ) );

define( 'DCP_META_SOURCE', '_dcp_source_id' );
define( 'DCP_META_IS_PROPOSAL', '_dcp_is_proposal' );
define( 'DCP_META_OPEN_PROPOSAL', '_dcp_open_proposal_id' );

require_once DCP_PATH . 'includes/caps.php';
require_once DCP_PATH . 'includes/proposals.php';
require_once DCP_PATH . 'includes/admin.php';
require_once DCP_PATH . 'abilities/register.php';

/**
 * Bootstrap.
 */
function dcp_init() {
	dcp_register_caps();
}
add_action( 'init', 'dcp_init' );

/**
 * Activation: add custom caps to Administrator and Editor.
 */
function dcp_activate() {
	dcp_register_caps();
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}
		$role->add_cap( 'create_content_proposals' );
		$role->add_cap( 'edit_content_proposals' );
		$role->add_cap( 'apply_content_proposals' );
	}
}
register_activation_hook( __FILE__, 'dcp_activate' );
