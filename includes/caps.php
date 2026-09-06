<?php
/**
 * Capabilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure custom caps exist on Administrator and Editor (safe to call often).
 */
function dcp_register_caps() {
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}
		$role->add_cap( 'manage_changesets' );
		$role->add_cap( 'approve_changesets' );
		$role->add_cap( 'publish_changesets' );
	}
}
