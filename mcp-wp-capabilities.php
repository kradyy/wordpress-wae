<?php
/**
 * Plugin Name: MCP WordPress Capabilities
 * Plugin URI: https://github.com/your-org/mcp-wp-capabilities
 * Description: Comprehensive WordPress capabilities for MCP (Model Context Protocol) integration
 * Version: 1.0.0
 * Author: Chris
 * Author URI: https://mild.se
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

// Also register callbacks on rest_api_init to ensure they're registered in time
add_action(
	'rest_api_init',
	static function () {
		add_action( 'wp_abilities_api_categories_init', 'mcp_wp_capabilities_register_category' );
		add_action( 'wp_abilities_api_init', 'mcp_wp_capabilities_register_abilities' );
	},
	5  // Before MCP Adapter (which runs at 15)
);

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

	// Register a simple test ability first
	wp_register_ability(
		'mcp-wp/test',
		array(
			'label'               => 'Test Ability',
			'description'         => 'A simple test ability',
			'category'            => 'mcp-wp',
			'input_schema'        => array( 'type' => 'object' ),
			'output_schema'       => array( 'type' => 'object' ),
			'permission_callback' => static function () {
				return true;
			},
			'execute_callback'    => static function () {
				return array( 'success' => true, 'message' => 'Test ability works!' );
			},
		)
	);

	require_once MCP_WP_CAPABILITIES_DIR . 'data/abilities.php';
	require_once MCP_WP_CAPABILITIES_DIR . 'data/class-ability-helpers.php';

	// Register all abilities from the data file
	mcp_wp_capabilities_register_all_abilities();
}

/**
 * Register the MCP server
 */
add_action(
	'mcp_adapter_init',
	function ( $adapter ) {
		$adapter->create_server(
			'mcp-wp-capabilities-server',                    // Unique server identifier
			'mcp',                                           // REST API namespace
			'mcp-adapter-default-server',                    // REST API route
			'MCP WordPress Capabilities',                    // Server name
			'WordPress capabilities for MCP integration',    // Server description
			'1.0.0',                                         // Server version
			array(
				\WP\MCP\Transport\HttpTransport::class,      // Transport methods
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,     // Error handler
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class, // Observability handler
			array(                                           // Abilities to expose as tools
				'mcp-wp/test',
				'mcp-wp/create-page',
				'mcp-wp/edit-page',
				'mcp-wp/get-page',
				'mcp-wp/list-pages',
				'mcp-wp/delete-page',
				'mcp-wp/create-post',
				'mcp-wp/edit-post',
				'mcp-wp/get-post',
				'mcp-wp/list-posts',
				'mcp-wp/delete-post',
			),
			array(),                                         // Resources (optional)
			array()                                          // Prompts (optional)
		);
	}
);

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
