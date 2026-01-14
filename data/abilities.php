<?php
/**
 * WordPress Abilities Loader
 *
 * Central loader for all MCP WordPress capabilities organized by category.
 * Each category folder contains ability definitions that are loaded here.
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all WordPress abilities from all category modules
 */
function mcp_wp_capabilities_register_all_abilities() {
	$plugin_dir = dirname( __DIR__ );
	$data_dir   = $plugin_dir . '/data';

	// Define all ability categories and their files
	$ability_categories = array(
		'patterns'  => 'patterns/abilities.php',
		'media'     => 'media/abilities.php',
		'users'     => 'users/abilities.php',
		'taxonomy'  => 'taxonomy/abilities.php',
		'settings'  => 'settings/abilities.php',
		'plugins'   => 'plugins/abilities.php',
		'advanced'  => 'advanced/abilities.php',
	);

	// Load and register abilities from each category
	foreach ( $ability_categories as $category => $file_path ) {
		$full_path = $data_dir . '/' . $file_path;

		if ( file_exists( $full_path ) ) {
			require_once $full_path;

			// Call the category's registration function
			// Convention: mcp_wp_register_{category}_abilities()
			$register_function = 'mcp_wp_register_' . str_replace( '-', '_', $category ) . '_abilities';

			if ( function_exists( $register_function ) ) {
				call_user_func( $register_function );
			}
		}
	}
}
