<?php
/**
 * Plugin Name: MCP WordPress Capabilities
 * Plugin URI: https://github.com/your-org/mcp-wp-capabilities
 * Description: Comprehensive WordPress capabilities for MCP (Model Context Protocol) integration
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0-or-later.html
 * Text Domain: mcp-wp-capabilities
 * Domain Path: /languages
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'MCP_WP_CAPABILITIES_VERSION', '1.0.0' );
define( 'MCP_WP_CAPABILITIES_FILE', __FILE__ );
define( 'MCP_WP_CAPABILITIES_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCP_WP_CAPABILITIES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin dependencies and register abilities
 */
function mcp_wp_capabilities_init() {
	// Check if Abilities API is available
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action(
			'admin_notices',
			static function () {
				wp_admin_notice(
					__( 'MCP WordPress Capabilities requires WordPress 6.9+ with Abilities API', 'mcp-wp-capabilities' ),
					array(
						'type'    => 'error',
						'dismiss' => false,
					),
				);
			}
		);
		return;
	}

	// Register ability category
	add_action(
		'wp_abilities_api_categories_init',
		'mcp_wp_capabilities_register_category'
	);

	// Load all abilities
	add_action(
		'wp_abilities_api_init',
		'mcp_wp_capabilities_register_abilities'
	);
}
add_action( 'plugins_loaded', 'mcp_wp_capabilities_init' );

/**
 * Register the ability category
 */
function mcp_wp_capabilities_register_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'mcp-wp',
		array(
			'label'       => __( 'MCP WordPress Capabilities', 'mcp-wp-capabilities' ),
			'description' => __( 'WordPress capabilities for MCP integration with Figma and design automation', 'mcp-wp-capabilities' ),
		)
	);
}

/**
 * Register all WordPress abilities
 */
function mcp_wp_capabilities_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	require_once MCP_WP_CAPABILITIES_DIR . 'data/abilities.php';
	require_once MCP_WP_CAPABILITIES_DIR . 'data/class-ability-helpers.php';

	// Register all abilities from the data file
	mcp_wp_capabilities_register_all_abilities();
}

/**
 * Get plugin information
 */
function mcp_wp_capabilities_get_plugin_info() {
	return array(
		'name'    => 'MCP WordPress Capabilities',
		'version' => MCP_WP_CAPABILITIES_VERSION,
		'file'    => MCP_WP_CAPABILITIES_FILE,
		'dir'     => MCP_WP_CAPABILITIES_DIR,
		'url'     => MCP_WP_CAPABILITIES_URL,
	);
}
