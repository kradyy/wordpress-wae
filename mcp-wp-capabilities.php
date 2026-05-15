<?php
/**
 * Plugin Name: MCP WordPress Capabilities
 * Plugin URI: https://github.com/kradyy/wordpress-wae
 * Description: Comprehensive WordPress capabilities for MCP (Model Context Protocol) integration
 * Version: 1.0.0
 * Author: Chris
 * Author URI: https://github.com/kradyy
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

	// Hotfix third-party ability schema bugs without editing vendor code.
	add_action(
		'wp_abilities_api_init',
		'mcp_wp_capabilities_hotfix_execute_ability_schema',
		999
	);

	// Fallback for contexts where the action already fired before hooks were attached.
	if ( did_action( 'wp_abilities_api_init' ) ) {
		mcp_wp_capabilities_hotfix_execute_ability_schema();
	}
}
add_action( 'plugins_loaded', 'mcp_wp_capabilities_init' );

// Also register callbacks on rest_api_init to ensure they're registered in time
add_action(
	'rest_api_init',
	static function () {
		add_action( 'wp_abilities_api_categories_init', 'mcp_wp_capabilities_register_category' );
		add_action( 'wp_abilities_api_init', 'mcp_wp_capabilities_register_abilities' );
		add_action( 'wp_abilities_api_init', 'mcp_wp_capabilities_hotfix_execute_ability_schema', 999 );

		// Fallback if wp_abilities_api_init already ran in this request.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			mcp_wp_capabilities_hotfix_execute_ability_schema();
		}
	},
	5  // Before MCP Adapter (which runs at 15)
);

/**
 * Normalize an MCP pattern name to the persisted wp_block post slug.
 *
 * @param string $name Pattern name/slug supplied by the ability caller.
 * @return string
 */
function mcp_wp_capabilities_normalize_pattern_post_slug( string $name ): string {
	$slug = sanitize_title( str_replace( '/', '-', $name ) );
	return '' !== $slug ? $slug : sanitize_key( $name );
}

/**
 * Build the registry names that should resolve a persisted MCP pattern.
 *
 * @param WP_Post $post Pattern post.
 * @return array<int,string>
 */
function mcp_wp_capabilities_get_pattern_registry_names( WP_Post $post ): array {
	$names       = array();
	$stored_name = (string) get_post_meta( $post->ID, '_mcp_wp_pattern_name', true );
	$post_slug   = (string) $post->post_name;

	if ( '' !== $stored_name ) {
		$names[] = $stored_name;
	}
	if ( '' !== $post_slug ) {
		$names[] = $post_slug;
		$names[] = 'mild-mcp-patterns/' . $post_slug;
	}

	return array_values( array_unique( array_filter( $names ) ) );
}

/**
 * Build a stable API response for a persisted pattern post.
 *
 * @param WP_Post $post Pattern post.
 * @return array<string,mixed>
 */
function mcp_wp_capabilities_pattern_post_response( WP_Post $post ): array {
	$slug         = (string) $post->post_name;
	$pattern_name = 'mild-mcp-patterns/' . $slug;

	return array(
		'id'                  => (int) $post->ID,
		'name'                => $pattern_name,
		'raw_name'            => (string) get_post_meta( $post->ID, '_mcp_wp_pattern_name', true ),
		'slug'                => $slug,
		'title'               => $post->post_title,
		'content'             => $post->post_content,
		'category'            => (string) get_post_meta( $post->ID, '_mcp_wp_pattern_category', true ),
		'pattern_block_markup' => '<!-- wp:pattern {"slug":"' . esc_attr( $pattern_name ) . '"} /-->',
		'synced_block_markup' => '<!-- wp:block {"ref":' . (int) $post->ID . '} /-->',
	);
}

/**
 * Register one persisted pattern with WordPress for this request.
 *
 * @param WP_Post|int $post Pattern post or post ID.
 * @return void
 */
function mcp_wp_capabilities_register_pattern_post( $post ): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$post = $post instanceof WP_Post ? $post : get_post( (int) $post );
	if ( ! $post instanceof WP_Post || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'mcp-wp',
			array( 'label' => __( 'MCP WordPress', 'mcp-wp-capabilities' ) )
		);
	}

	$category = (string) get_post_meta( $post->ID, '_mcp_wp_pattern_category', true );
	$keywords = get_post_meta( $post->ID, '_mcp_wp_pattern_keywords', true );
	$pattern  = array(
		'title'       => $post->post_title,
		'content'     => $post->post_content,
		'categories'  => array( '' !== $category ? $category : 'mcp-wp' ),
		'description' => (string) get_post_meta( $post->ID, '_mcp_wp_pattern_description', true ),
		'keywords'    => is_array( $keywords ) ? array_values( array_filter( array_map( 'sanitize_text_field', $keywords ) ) ) : array(),
	);

	$registry = class_exists( 'WP_Block_Patterns_Registry' )
		? WP_Block_Patterns_Registry::get_instance()
		: null;

	foreach ( mcp_wp_capabilities_get_pattern_registry_names( $post ) as $name ) {
		if ( $registry && method_exists( $registry, 'is_registered' ) && $registry->is_registered( $name ) ) {
			continue;
		}
		register_block_pattern( $name, $pattern );
	}
}

/**
 * Register persisted MCP-created patterns on frontend/admin requests.
 *
 * @return void
 */
function mcp_wp_capabilities_register_persisted_patterns(): void {
	$posts = get_posts(
		array(
			'post_type'      => 'wp_block',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_mcp_wp_pattern_managed',
			'meta_value'     => '1',
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		mcp_wp_capabilities_register_pattern_post( $post );
	}
}
add_action( 'init', 'mcp_wp_capabilities_register_persisted_patterns', 20 );

/**
 * Find a persisted MCP pattern by supplied registry name or slug.
 *
 * @param string $name Pattern name.
 * @return WP_Post|null
 */
function mcp_wp_capabilities_find_pattern_post( string $name ): ?WP_Post {
	$slug = mcp_wp_capabilities_normalize_pattern_post_slug( $name );
	$post = get_page_by_path( $slug, OBJECT, 'wp_block' );
	if ( $post instanceof WP_Post ) {
		return $post;
	}

	$matches = get_posts(
		array(
			'post_type'      => 'wp_block',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_mcp_wp_pattern_name',
			'meta_value'     => sanitize_text_field( $name ),
			'no_found_rows'  => true,
		)
	);

	return ! empty( $matches ) && $matches[0] instanceof WP_Post ? $matches[0] : null;
}

/**
 * Create or update a persisted MCP pattern post.
 *
 * @param string $name Pattern name/slug.
 * @param array<string,mixed> $pattern_data Pattern data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_wp_capabilities_upsert_pattern_post( string $name, array $pattern_data ) {
	$title    = sanitize_text_field( (string) ( $pattern_data['title'] ?? 'Generated Pattern' ) );
	$content  = (string) ( $pattern_data['content'] ?? '' );
	$slug     = mcp_wp_capabilities_normalize_pattern_post_slug( $name );
	$category = sanitize_text_field( (string) ( $pattern_data['category'] ?? 'mcp-wp' ) );

	if ( '' === $slug || '' === trim( $content ) ) {
		return new WP_Error( 'mcp_wp_pattern_missing_fields', 'Pattern name and content are required.' );
	}

	$post_data = array(
		'post_type'    => 'wp_block',
		'post_name'    => $slug,
		'post_title'   => '' !== $title ? $title : $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
	);

	$existing = mcp_wp_capabilities_find_pattern_post( $name );
	if ( $existing instanceof WP_Post ) {
		$post_data['ID'] = $existing->ID;
		$post_id         = wp_update_post( $post_data, true );
	} else {
		$post_id = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
		return is_wp_error( $post_id )
			? $post_id
			: new WP_Error( 'mcp_wp_pattern_insert_failed', 'Failed to create pattern post.' );
	}

	$post_id = (int) $post_id;
	update_post_meta( $post_id, '_mcp_wp_pattern_managed', '1' );
	update_post_meta( $post_id, '_mcp_wp_pattern_name', sanitize_text_field( $name ) );
	update_post_meta( $post_id, '_mcp_wp_pattern_category', $category );
	update_post_meta( $post_id, '_mcp_wp_pattern_description', sanitize_text_field( (string) ( $pattern_data['description'] ?? '' ) ) );
	update_post_meta( $post_id, '_mcp_wp_pattern_keywords', array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $pattern_data['keywords'] ?? array() ) ) ) ) );

	$post = get_post( $post_id );
	if ( $post instanceof WP_Post ) {
		mcp_wp_capabilities_register_pattern_post( $post );
	}

	return $post instanceof WP_Post ? mcp_wp_capabilities_pattern_post_response( $post ) : array();
}

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
 * Hotfix the execute-ability output schema when bundled adapters miss `output_schema.properties.data.type`.
 *
 * Some distributions register `mcp-adapter/execute-ability` with an incomplete output schema.
 * This produces REST validation notices and can confuse downstream MCP tool calls.
 */
function mcp_wp_capabilities_hotfix_execute_ability_schema(): void {
	if ( ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_unregister_ability' ) || ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$ability = wp_get_ability( 'mcp-adapter/execute-ability' );
	if ( ! $ability ) {
		return;
	}

	$output_schema = $ability->get_output_schema();
	$data_schema   = array();

	if (
		is_array( $output_schema )
		&& isset( $output_schema['properties'] )
		&& is_array( $output_schema['properties'] )
		&& isset( $output_schema['properties']['data'] )
		&& is_array( $output_schema['properties']['data'] )
	) {
		$data_schema = $output_schema['properties']['data'];
	}

	// Already fixed upstream or by another plugin.
	if ( isset( $data_schema['type'] ) ) {
		return;
	}

	if ( ! class_exists( '\\WP\\MCP\\Abilities\\ExecuteAbilityAbility' ) ) {
		return;
	}

	wp_unregister_ability( 'mcp-adapter/execute-ability' );

	wp_register_ability(
		'mcp-adapter/execute-ability',
		array(
			'label'               => 'Execute Ability',
			'description'         => 'Execute a WordPress ability with the provided parameters. This is the primary execution layer that can run any registered ability.',
			'category'            => 'mcp-adapter',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'ability_name' => array(
						'type'        => 'string',
						'description' => 'The full name of the ability to execute',
					),
					'parameters'   => array(
						'type'        => 'object',
						'description' => 'Parameters to pass to the ability',
					),
				),
				'required'             => array( 'ability_name', 'parameters' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array(
						'type'        => array( 'object', 'array', 'string', 'number', 'integer', 'boolean', 'null' ),
						'description' => 'The result data from the ability execution',
					),
					'error'   => array(
						'type'        => 'string',
						'description' => 'Error message if execution failed',
					),
				),
				'required'   => array( 'success' ),
			),
			'permission_callback' => array( '\\WP\\MCP\\Abilities\\ExecuteAbilityAbility', 'check_permission' ),
			'execute_callback'    => array( '\\WP\\MCP\\Abilities\\ExecuteAbilityAbility', 'execute' ),
			'meta'                => array(
				'annotations' => array(
					'priority'      => '1.0',
					'readOnlyHint'  => false,
					'openWorldHint' => true,
				),
			),
		)
	);
}

/**
 * Discover all public mcp-wp/* abilities exposed as MCP tools.
 *
 * @return array<int,string>
 */
function mcp_wp_capabilities_discover_public_tool_abilities(): array {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$abilities = wp_get_abilities();
	$tools     = array();

	foreach ( $abilities as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'get_meta' ) ) {
			continue;
		}

		$ability_name = (string) $ability->get_name();
		if ( 0 !== strpos( $ability_name, 'mcp-wp/' ) ) {
			continue;
		}

		$meta = $ability->get_meta();
		if ( ! is_array( $meta ) ) {
			continue;
		}

		$is_public    = ! empty( $meta['mcp']['public'] );
		$ability_type = isset( $meta['mcp']['type'] ) ? (string) $meta['mcp']['type'] : 'tool';

		if ( ! $is_public || 'tool' !== $ability_type ) {
			continue;
		}

		$tools[] = $ability_name;
	}

	$tools = array_values( array_unique( $tools ) );
	sort( $tools, SORT_STRING );
	return $tools;
}

// Register the MCP server
// Using the action hook mcp_adapter_init ensures we don't create the server too soon
add_action(
	'mcp_adapter_init',
	function ( $adapter ) {
		$all_abilities = mcp_wp_capabilities_discover_public_tool_abilities();

		error_log( 'MCP Server: Registering with ' . count( $all_abilities ) . ' abilities' );
		error_log( 'MCP Server: Abilities = ' . wp_json_encode( $all_abilities ) );

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
			$all_abilities,                                  // Abilities to expose as tools
			array(),                                         // Resources (optional)
			array()                                          // Prompts (optional)
		);
	},
	20  // Run after abilities are registered (default priority is 10)
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
