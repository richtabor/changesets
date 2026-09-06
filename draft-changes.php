<?php
/**
 * Plugin Name:       Draft Changes
 * Description:       Let agents accumulate edits in a Changeset, preview them on the real site, then Publish Changeset after human approval.
 * Version:           0.2.18
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Rich Tabor
 * License:           GPL-2.0-or-later
 * Text Domain:       draft-changes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCP_VERSION', '0.2.18' );
define( 'DCP_FILE', __FILE__ );
define( 'DCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCP_URL', plugin_dir_url( __FILE__ ) );

define( 'DCP_META_SOURCE', '_dcp_source_id' );

require_once DCP_PATH . 'includes/caps.php';
require_once DCP_PATH . 'includes/changesets.php';
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
}
register_activation_hook( __FILE__, 'dcp_activate' );
