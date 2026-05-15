<?php
/**
 * WordPress Abilities 11-17: Gutenberg Patterns & Blocks
 *
 * Defines abilities for working with Gutenberg patterns and block management
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 11. List Patterns
 */
function mcp_wp_register_list_patterns() {
	wp_register_ability(
		'mcp-wp/list-patterns',
		array(
			'label'               => __( 'List Patterns', 'mcp-wp-capabilities' ),
			'description'         => __( 'List all saved Gutenberg patterns', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'category'   => array( 'type' => 'string', 'description' => 'Filter by category' ),
					'search'     => array( 'type' => 'string', 'description' => 'Search pattern name/title' ),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'data'     => array( 'type' => 'array' ),
					'total'    => array( 'type' => 'integer' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$patterns = MCP_WP_Ability_Helpers::get_all_patterns();

				if ( isset( $input['category'] ) ) {
					$category = sanitize_text_field( $input['category'] );
					$patterns = array_filter(
						$patterns,
						static function ( $pattern ) use ( $category ) {
							return isset( $pattern['category'] ) && $pattern['category'] === $category;
						}
					);
				}

				if ( isset( $input['search'] ) ) {
					$search = strtolower( sanitize_text_field( $input['search'] ) );
					$patterns = array_filter(
						$patterns,
						static function ( $pattern ) use ( $search ) {
							$name = strtolower( $pattern['name'] ?? '' );
							$title = strtolower( $pattern['title'] ?? '' );
							return strpos( $name, $search ) !== false || strpos( $title, $search ) !== false;
						}
					);
				}

				return array(
					'success' => true,
					'data'    => array_values( $patterns ),
					'total'   => count( $patterns ),
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 12. Get Pattern
 */
function mcp_wp_register_get_pattern() {
	wp_register_ability(
		'mcp-wp/get-pattern',
		array(
			'label'               => __( 'Get Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get specific pattern by name', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern name/slug' ),
				),
				'required'   => array( 'pattern_name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_name = sanitize_text_field( $input['pattern_name'] );
				$pattern      = function_exists( 'mcp_wp_capabilities_find_pattern_post' )
					? mcp_wp_capabilities_find_pattern_post( $pattern_name )
					: null;

				if ( ! $pattern ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				return array(
					'success' => true,
					'data'    => function_exists( 'mcp_wp_capabilities_pattern_post_response' )
						? mcp_wp_capabilities_pattern_post_response( $pattern )
						: $pattern,
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 13. Create Pattern
 */
function mcp_wp_register_create_pattern() {
	wp_register_ability(
		'mcp-wp/create-pattern',
		array(
			'label'               => __( 'Create Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create new Gutenberg pattern', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'       => array( 'type' => 'string', 'description' => 'Pattern title' ),
					'name'        => array( 'type' => 'string', 'description' => 'Pattern name/slug' ),
					'content'     => array( 'type' => 'string', 'description' => 'Pattern block content' ),
					'category'    => array( 'type' => 'string', 'description' => 'Pattern category' ),
					'description' => array( 'type' => 'string', 'description' => 'Pattern description' ),
					'keywords'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Keywords for pattern' ),
				),
				'required'   => array( 'title', 'name', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_data = array(
					'title'       => sanitize_text_field( $input['title'] ),
					'name'        => sanitize_text_field( $input['name'] ),
					'content'     => wp_kses_post( $input['content'] ),
					'category'    => isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : 'default',
					'description' => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '',
					'keywords'    => isset( $input['keywords'] ) ? array_map( 'sanitize_text_field', (array) $input['keywords'] ) : array(),
				);

				if ( function_exists( 'mcp_wp_capabilities_upsert_pattern_post' ) ) {
					$result = mcp_wp_capabilities_upsert_pattern_post( $pattern_data['name'], $pattern_data );
					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'error'   => $result->get_error_message(),
						);
					}
					return array(
						'success' => true,
						'data'    => $result,
					);
				}

				return array(
					'success' => false,
					'error'   => 'Persistent block patterns not supported in this environment',
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 14. Edit Pattern
 */
function mcp_wp_register_edit_pattern() {
	wp_register_ability(
		'mcp-wp/edit-pattern',
		array(
			'label'               => __( 'Edit Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Modify saved pattern', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern name to update' ),
					'title'        => array( 'type' => 'string', 'description' => 'Pattern title' ),
					'content'      => array( 'type' => 'string', 'description' => 'Pattern content' ),
					'category'     => array( 'type' => 'string', 'description' => 'Pattern category' ),
					'description'  => array( 'type' => 'string', 'description' => 'Pattern description' ),
					'keywords'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
				'required'   => array( 'pattern_name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_name = sanitize_text_field( $input['pattern_name'] );
				$existing     = function_exists( 'mcp_wp_capabilities_find_pattern_post' )
					? mcp_wp_capabilities_find_pattern_post( $pattern_name )
					: null;

				if ( ! $existing ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				$updated = array(
					'title'       => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $existing->post_title,
					'content'     => isset( $input['content'] ) ? wp_kses_post( $input['content'] ) : $existing->post_content,
					'category'    => isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : (string) get_post_meta( $existing->ID, '_mcp_wp_pattern_category', true ),
					'description' => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : (string) get_post_meta( $existing->ID, '_mcp_wp_pattern_description', true ),
					'keywords'    => isset( $input['keywords'] ) ? array_map( 'sanitize_text_field', (array) $input['keywords'] ) : (array) get_post_meta( $existing->ID, '_mcp_wp_pattern_keywords', true ),
				);
				$result  = function_exists( 'mcp_wp_capabilities_upsert_pattern_post' )
					? mcp_wp_capabilities_upsert_pattern_post( $pattern_name, $updated )
					: new WP_Error( 'mcp_wp_pattern_unavailable', 'Persistent pattern storage unavailable.' );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'data'    => $result,
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 15. Delete Pattern
 */
function mcp_wp_register_delete_pattern() {
	wp_register_ability(
		'mcp-wp/delete-pattern',
		array(
			'label'               => __( 'Delete Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Remove pattern', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern name to delete' ),
				),
				'required'   => array( 'pattern_name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'message'  => array( 'type' => 'string' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'delete_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_name = sanitize_text_field( $input['pattern_name'] );
				$pattern      = function_exists( 'mcp_wp_capabilities_find_pattern_post' )
					? mcp_wp_capabilities_find_pattern_post( $pattern_name )
					: null;

				if ( ! $pattern ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				if ( function_exists( 'unregister_block_pattern' ) && function_exists( 'mcp_wp_capabilities_get_pattern_registry_names' ) ) {
					foreach ( mcp_wp_capabilities_get_pattern_registry_names( $pattern ) as $registered_name ) {
						unregister_block_pattern( $registered_name );
					}
				}

				$deleted = wp_delete_post( $pattern->ID, true );
				if ( $deleted ) {
					return array(
						'success' => true,
						'message' => 'Pattern deleted successfully',
					);
				}

				return array(
					'success' => false,
					'error'   => 'Unable to delete pattern',
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 16. Get Block Types
 */
function mcp_wp_register_get_block_types() {
	wp_register_ability(
		'mcp-wp/get-block-types',
		array(
			'label'               => __( 'Get Block Types', 'mcp-wp-capabilities' ),
			'description'         => __( 'List available block types', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'namespace'           => array( 'type' => 'string', 'description' => 'Filter by namespace (e.g., core)' ),
					'include_deprecated'  => array( 'type' => 'boolean', 'description' => 'Include deprecated blocks' ),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$namespace = isset( $input['namespace'] ) ? sanitize_text_field( $input['namespace'] ) : '';
				$include_deprecated = isset( $input['include_deprecated'] ) ? (bool) $input['include_deprecated'] : false;

				$block_types = MCP_WP_Ability_Helpers::get_block_types( $namespace, $include_deprecated );

				return array(
					'success' => true,
					'data'    => $block_types,
					'total'   => count( $block_types ),
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * 17. Validate Blocks
 */
function mcp_wp_register_validate_blocks() {
	wp_register_ability(
		'mcp-wp/validate-blocks',
		array(
			'label'               => __( 'Validate Blocks', 'mcp-wp-capabilities' ),
			'description'         => __( 'Validate block JSON', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'blocks_json' => array( 'type' => 'string', 'description' => 'Block JSON to validate' ),
				),
				'required'   => array( 'blocks_json' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'valid'   => array( 'type' => 'boolean' ),
					'errors'  => array( 'type' => 'array' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$result = MCP_WP_Ability_Helpers::validate_block_json( $input['blocks_json'] );

				return array(
					'success' => true,
					'valid'   => $result['valid'],
					'errors'  => $result['errors'],
				);
			},
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * Register all pattern abilities
 */
function mcp_wp_register_patterns_abilities() {
	mcp_wp_register_list_patterns();
	mcp_wp_register_get_pattern();
	mcp_wp_register_create_pattern();
	mcp_wp_register_edit_pattern();
	mcp_wp_register_delete_pattern();
	mcp_wp_register_get_block_types();
	mcp_wp_register_validate_blocks();
}
