<?php
/**
 * WordPress Abilities 11-45 for MCP
 *
 * Defines abilities for Gutenberg Patterns, Users, Plugins, Settings, Media, Taxonomy and Advanced operations
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// GROUP 2: GUTENBERG PATTERNS & BLOCKS (11-17)
// ============================================================================

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
				$pattern      = MCP_WP_Ability_Helpers::get_pattern_by_name( $pattern_name );

				if ( ! $pattern ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				return array(
					'success' => true,
					'data'    => $pattern,
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

				if ( function_exists( 'register_block_pattern' ) ) {
					register_block_pattern( $pattern_data['name'], $pattern_data );

					return array(
						'success' => true,
						'data'    => $pattern_data,
					);
				}

				return array(
					'success' => false,
					'error'   => 'Block patterns not supported in this WordPress version',
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
				$existing     = MCP_WP_Ability_Helpers::get_pattern_by_name( $pattern_name );

				if ( ! $existing ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				$updated = array_merge( $existing, array_filter( array(
					'title'       => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : null,
					'content'     => isset( $input['content'] ) ? wp_kses_post( $input['content'] ) : null,
					'category'    => isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : null,
					'description' => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : null,
					'keywords'    => isset( $input['keywords'] ) ? array_map( 'sanitize_text_field', (array) $input['keywords'] ) : null,
				), static function ( $val ) { return $val !== null; } ) );

				return array(
					'success' => true,
					'data'    => $updated,
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
				$pattern      = MCP_WP_Ability_Helpers::get_pattern_by_name( $pattern_name );

				if ( ! $pattern ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				if ( function_exists( 'unregister_block_pattern' ) ) {
					unregister_block_pattern( $pattern_name );
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

// ============================================================================
// GROUP 3: USERS & PERMISSIONS (18-22)
// ============================================================================

/**
 * 18. List Users
 */
function mcp_wp_register_list_users() {
	wp_register_ability(
		'mcp-wp/list-users',
		array(
			'label'               => __( 'List Users', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get WordPress users with filtering', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'role'     => array( 'type' => 'string', 'description' => 'Filter by role' ),
					'search'   => array( 'type' => 'string', 'description' => 'Search by name/email' ),
					'per_page' => array( 'type' => 'integer', 'description' => 'Number to return (default: 10, max: 100)' ),
					'page'     => array( 'type' => 'integer', 'description' => 'Page number' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'list_users' );
			},
			'execute_callback'    => static function ( array $input ) {
				$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
				$paged    = absint( $input['page'] ?? 1 );

				$args = array(
					'number' => $per_page,
					'offset' => ( $paged - 1 ) * $per_page,
				);

				if ( isset( $input['role'] ) ) {
					$args['role'] = sanitize_text_field( $input['role'] );
				}

				if ( isset( $input['search'] ) ) {
					$args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*';
				}

				$user_query = new \WP_User_Query( $args );
				$users      = array_map(
					static function ( $user ) {
						return MCP_WP_Ability_Helpers::format_user_response( $user );
					},
					$user_query->get_results()
				);

				return array(
					'success' => true,
					'data'    => $users,
					'total'   => (int) $user_query->total_users,
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
 * 19. Get User
 */
function mcp_wp_register_get_user() {
	wp_register_ability(
		'mcp-wp/get-user',
		array(
			'label'               => __( 'Get User', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get user by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
				),
				'required'   => array( 'user_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'list_users' );
			},
			'execute_callback'    => static function ( array $input ) {
				$user_id = absint( $input['user_id'] );
				$user    = get_user_by( 'id', $user_id );

				if ( ! $user ) {
					return array(
						'success' => false,
						'error'   => 'User not found',
					);
				}

				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_user_response( $user ),
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
 * 20. Get Current User
 */
function mcp_wp_register_get_current_user() {
	wp_register_ability(
		'mcp-wp/get-current-user',
		array(
			'label'               => __( 'Get Current User', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get authenticated user\'s info', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return is_user_logged_in();
			},
			'execute_callback'    => static function () {
				$user_id = get_current_user_id();

				if ( ! $user_id ) {
					return array(
						'success' => false,
						'error'   => 'No user authenticated',
					);
				}

				$user = get_user_by( 'id', $user_id );

				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_user_response( $user ),
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
 * 21. Create User
 */
function mcp_wp_register_create_user() {
	wp_register_ability(
		'mcp-wp/create-user',
		array(
			'label'               => __( 'Create User', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create new user', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'username'     => array( 'type' => 'string', 'description' => 'User login name' ),
					'email'        => array( 'type' => 'string', 'description' => 'User email address' ),
					'password'     => array( 'type' => 'string', 'description' => 'User password' ),
					'first_name'   => array( 'type' => 'string', 'description' => 'First name' ),
					'last_name'    => array( 'type' => 'string', 'description' => 'Last name' ),
					'display_name' => array( 'type' => 'string', 'description' => 'Display name' ),
					'role'         => array( 'type' => 'string', 'description' => 'User role' ),
				),
				'required'   => array( 'username', 'email', 'password' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'user_id'  => array( 'type' => 'integer' ),
					'data'     => array( 'type' => 'object' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'create_users' );
			},
			'execute_callback'    => static function ( array $input ) {
				$user_data = array(
					'user_login'   => sanitize_user( $input['username'] ),
					'user_email'   => sanitize_email( $input['email'] ),
					'user_pass'    => sanitize_text_field( $input['password'] ),
					'first_name'   => isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '',
					'last_name'    => isset( $input['last_name'] ) ? sanitize_text_field( $input['last_name'] ) : '',
					'display_name' => isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '',
				);

				$user_id = wp_insert_user( $user_data );

				if ( is_wp_error( $user_id ) ) {
					return array(
						'success' => false,
						'error'   => $user_id->get_error_message(),
					);
				}

				if ( isset( $input['role'] ) ) {
					$user = new \WP_User( $user_id );
					$user->set_role( sanitize_text_field( $input['role'] ) );
				}

				$user = get_user_by( 'id', $user_id );

				return array(
					'success' => true,
					'user_id' => $user_id,
					'data'    => MCP_WP_Ability_Helpers::format_user_response( $user ),
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
 * 22. Edit User
 */
function mcp_wp_register_edit_user() {
	wp_register_ability(
		'mcp-wp/edit-user',
		array(
			'label'               => __( 'Edit User', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update user info', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'user_id'      => array( 'type' => 'integer', 'description' => 'User ID to update' ),
					'email'        => array( 'type' => 'string', 'description' => 'User email' ),
					'first_name'   => array( 'type' => 'string', 'description' => 'First name' ),
					'last_name'    => array( 'type' => 'string', 'description' => 'Last name' ),
					'display_name' => array( 'type' => 'string', 'description' => 'Display name' ),
					'password'     => array( 'type' => 'string', 'description' => 'New password (optional)' ),
					'role'         => array( 'type' => 'string', 'description' => 'User role' ),
				),
				'required'   => array( 'user_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_users' );
			},
			'execute_callback'    => static function ( array $input ) {
				$user_id = absint( $input['user_id'] );
				$user    = get_user_by( 'id', $user_id );

				if ( ! $user ) {
					return array(
						'success' => false,
						'error'   => 'User not found',
					);
				}

				$user_data = array( 'ID' => $user_id );

				if ( isset( $input['email'] ) ) {
					$user_data['user_email'] = sanitize_email( $input['email'] );
				}

				if ( isset( $input['first_name'] ) ) {
					$user_data['first_name'] = sanitize_text_field( $input['first_name'] );
				}

				if ( isset( $input['last_name'] ) ) {
					$user_data['last_name'] = sanitize_text_field( $input['last_name'] );
				}

				if ( isset( $input['display_name'] ) ) {
					$user_data['display_name'] = sanitize_text_field( $input['display_name'] );
				}

				if ( isset( $input['password'] ) ) {
					$user_data['user_pass'] = sanitize_text_field( $input['password'] );
				}

				$result = wp_update_user( $user_data );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				if ( isset( $input['role'] ) ) {
					$user = new \WP_User( $user_id );
					$user->set_role( sanitize_text_field( $input['role'] ) );
				}

				$updated_user = get_user_by( 'id', $user_id );

				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_user_response( $updated_user ),
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

// ============================================================================
// GROUP 4: PLUGINS & THEME (23-28)
// ============================================================================

/**
 * 23. List Plugins
 */
function mcp_wp_register_list_plugins() {
	wp_register_ability(
		'mcp-wp/list-plugins',
		array(
			'label'               => __( 'List Plugins', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get installed plugins', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array( 'type' => 'string', 'enum' => array( 'active', 'inactive', 'all' ), 'description' => 'Filter by status' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$all_plugins = get_plugins();
				$active_plugins = get_option( 'active_plugins', array() );
				$status_filter = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'all';

				$plugins = array();
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$is_active = in_array( $plugin_file, $active_plugins, true );

					if ( 'active' === $status_filter && ! $is_active ) {
						continue;
					}
					if ( 'inactive' === $status_filter && $is_active ) {
						continue;
					}

					$plugins[] = array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'] ?? '',
						'version'     => $plugin_data['Version'] ?? '',
						'description' => $plugin_data['Description'] ?? '',
						'author'      => $plugin_data['Author'] ?? '',
						'active'      => $is_active,
						'url'         => $plugin_data['PluginURI'] ?? '',
					);
				}

				return array(
					'success' => true,
					'data'    => $plugins,
					'total'   => count( $plugins ),
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
 * 24. Get Plugin
 */
function mcp_wp_register_get_plugin() {
	wp_register_ability(
		'mcp-wp/get-plugin',
		array(
			'label'               => __( 'Get Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get plugin details', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				$all_plugins = get_plugins();

				if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
					return array(
						'success' => false,
						'error'   => 'Plugin not found',
					);
				}

				$plugin_data = $all_plugins[ $plugin_file ];
				$active_plugins = get_option( 'active_plugins', array() );
				$is_active = in_array( $plugin_file, $active_plugins, true );

				return array(
					'success' => true,
					'data'    => array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'] ?? '',
						'version'     => $plugin_data['Version'] ?? '',
						'description' => $plugin_data['Description'] ?? '',
						'author'      => $plugin_data['Author'] ?? '',
						'active'      => $is_active,
						'url'         => $plugin_data['PluginURI'] ?? '',
						'license'     => $plugin_data['License'] ?? '',
						'requires_wp' => $plugin_data['RequiresWP'] ?? '',
						'requires_php' => $plugin_data['RequiresPHP'] ?? '',
					),
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
 * 25. Activate Plugin
 */
function mcp_wp_register_activate_plugin() {
	wp_register_ability(
		'mcp-wp/activate-plugin',
		array(
			'label'               => __( 'Activate Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Activate plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				$result      = activate_plugin( $plugin_file );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'message' => 'Plugin activated successfully',
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
 * 26. Deactivate Plugin
 */
function mcp_wp_register_deactivate_plugin() {
	wp_register_ability(
		'mcp-wp/deactivate-plugin',
		array(
			'label'               => __( 'Deactivate Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Deactivate plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				deactivate_plugins( array( $plugin_file ) );

				return array(
					'success' => true,
					'message' => 'Plugin deactivated successfully',
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
 * 27. Get Theme
 */
function mcp_wp_register_get_theme() {
	wp_register_ability(
		'mcp-wp/get-theme',
		array(
			'label'               => __( 'Get Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get theme info', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'switch_themes' );
			},
			'execute_callback'    => static function () {
				$theme = wp_get_theme();

				return array(
					'success' => true,
					'data'    => array(
						'name'        => $theme->get( 'Name' ),
						'version'     => $theme->get( 'Version' ),
						'description' => $theme->get( 'Description' ),
						'author'      => $theme->get( 'Author' ),
						'author_uri'  => $theme->get( 'AuthorURI' ),
						'theme_uri'   => $theme->get( 'ThemeURI' ),
						'screenshot'  => $theme->get_screenshot(),
						'stylesheet'  => get_stylesheet(),
						'template'    => get_template(),
					),
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
 * 28. Get Theme Supports
 */
function mcp_wp_register_get_theme_supports() {
	wp_register_ability(
		'mcp-wp/get-theme-supports',
		array(
			'label'               => __( 'Get Theme Supports', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get theme features', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'switch_themes' );
			},
			'execute_callback'    => static function () {
				global $_wp_theme_features;

				$features = array(
					'post_thumbnails'       => current_theme_supports( 'post-thumbnails' ),
					'html5'                 => current_theme_supports( 'html5' ),
					'widgets'               => current_theme_supports( 'widgets' ),
					'menus'                 => current_theme_supports( 'menus' ),
					'automatic_feed_links'  => current_theme_supports( 'automatic-feed-links' ),
					'gutenberg'             => current_theme_supports( 'align-wide' ) || current_theme_supports( 'wp-block-styles' ),
					'custom_colors'         => current_theme_supports( 'editor-color-palette' ),
					'custom_fonts'          => current_theme_supports( 'editor-font-sizes' ),
				);

				return array(
					'success' => true,
					'data'    => $features,
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

// ============================================================================
// GROUP 5: SETTINGS & CONFIGURATION (29-31)
// ============================================================================

/**
 * 29. Get Settings
 */
function mcp_wp_register_get_settings() {
	wp_register_ability(
		'mcp-wp/get-settings',
		array(
			'label'               => __( 'Get Settings', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get WordPress settings', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				return array(
					'success' => true,
					'data'    => array(
						'site_title'           => get_option( 'blogname' ),
						'site_tagline'         => get_option( 'blogdescription' ),
						'site_url'             => get_option( 'siteurl' ),
						'home_url'             => get_option( 'home' ),
						'admin_email'          => get_option( 'admin_email' ),
						'timezone'             => get_option( 'timezone_string' ),
						'date_format'          => get_option( 'date_format' ),
						'time_format'          => get_option( 'time_format' ),
						'posts_per_page'       => get_option( 'posts_per_page' ),
						'pages_per_page'       => get_option( 'posts_per_page_page' ) ?: 10,
						'blog_public'          => get_option( 'blog_public' ),
						'users_can_register'   => get_option( 'users_can_register' ),
						'default_user_role'    => get_option( 'default_role' ),
						'wp_version'           => get_bloginfo( 'version' ),
						'language'             => get_option( 'WPLANG' ),
						'permalink_structure'  => get_option( 'permalink_structure' ),
					),
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
 * 30. Get Gutenberg Settings
 */
function mcp_wp_register_get_gutenberg_settings() {
	wp_register_ability(
		'mcp-wp/get-gutenberg-settings',
		array(
			'label'               => __( 'Get Gutenberg Settings', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get block editor settings', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				$settings = array(
					'can_use_block_editor'    => function_exists( 'gutenberg_can_edit_post_type' ) ? gutenberg_can_edit_post_type( 'post' ) : true,
					'enable_on_posts'         => function_exists( 'gutenberg_can_edit_post_type' ),
					'enable_on_pages'         => function_exists( 'gutenberg_can_edit_post_type' ),
					'block_patterns_enabled'  => function_exists( 'register_block_pattern' ),
					'custom_colors'           => current_theme_supports( 'editor-color-palette' ),
					'custom_font_sizes'       => current_theme_supports( 'editor-font-sizes' ),
					'wide_alignment'          => current_theme_supports( 'align-wide' ),
				);

				if ( function_exists( 'wp_get_global_stylesheet' ) ) {
					$settings['global_styles_enabled'] = true;
				}

				return array(
					'success' => true,
					'data'    => $settings,
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
 * 31. Get Site Stats
 */
function mcp_wp_register_get_site_stats() {
	wp_register_ability(
		'mcp-wp/get-site-stats',
		array(
			'label'               => __( 'Get Site Stats', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get site overview stats', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::get_site_stats(),
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

// ============================================================================
// GROUP 6: MEDIA & ASSETS (32-34)
// ============================================================================

/**
 * 32. Upload Media
 */
function mcp_wp_register_upload_media() {
	wp_register_ability(
		'mcp-wp/upload-media',
		array(
			'label'               => __( 'Upload Media', 'mcp-wp-capabilities' ),
			'description'         => __( 'Upload image/media file', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'filename'      => array( 'type' => 'string', 'description' => 'Filename' ),
					'base64_data'   => array( 'type' => 'string', 'description' => 'Base64 encoded file data' ),
					'title'         => array( 'type' => 'string', 'description' => 'Media title' ),
					'description'   => array( 'type' => 'string', 'description' => 'Media description' ),
				),
				'required'   => array( 'filename', 'base64_data' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'attachment_id' => array( 'type' => 'integer' ),
					'url'           => array( 'type' => 'string' ),
					'data'          => array( 'type' => 'object' ),
					'error'         => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'upload_files' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'wp_handle_sideload' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}

				$filename = sanitize_file_name( $input['filename'] );
				$file_data = base64_decode( $input['base64_data'], true );

				if ( false === $file_data ) {
					return array(
						'success' => false,
						'error'   => 'Invalid base64 data',
					);
				}

				$upload_dir = wp_upload_dir();
				$file_path  = $upload_dir['path'] . '/' . $filename;

				if ( ! is_dir( $upload_dir['path'] ) ) {
					wp_mkdir_p( $upload_dir['path'] );
				}

				if ( false === file_put_contents( $file_path, $file_data ) ) {
					return array(
						'success' => false,
						'error'   => 'Failed to write file',
					);
				}

				$attachment = array(
					'post_mime_type' => mime_type_from_filename( $filename ),
					'post_title'     => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : pathinfo( $filename, PATHINFO_FILENAME ),
					'post_content'   => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '',
					'post_status'    => 'inherit',
				);

				$attachment_id = wp_insert_attachment( $attachment, $file_path );

				if ( is_wp_error( $attachment_id ) ) {
					return array(
						'success' => false,
						'error'   => $attachment_id->get_error_message(),
					);
				}

				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
				}

				$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
				wp_update_attachment_metadata( $attachment_id, $metadata );

				return array(
					'success'       => true,
					'attachment_id' => $attachment_id,
					'url'           => wp_get_attachment_url( $attachment_id ),
					'data'          => array(
						'id'       => $attachment_id,
						'title'    => get_the_title( $attachment_id ),
						'filename' => basename( $file_path ),
						'url'      => wp_get_attachment_url( $attachment_id ),
					),
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
 * 33. List Media
 */
function mcp_wp_register_list_media() {
	wp_register_ability(
		'mcp-wp/list-media',
		array(
			'label'               => __( 'List Media', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get uploaded media', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'media_type' => array( 'type' => 'string', 'enum' => array( 'image', 'video', 'audio', 'all' ), 'description' => 'Filter by media type' ),
					'per_page'   => array( 'type' => 'integer', 'description' => 'Number to return (default: 10, max: 100)' ),
					'page'       => array( 'type' => 'integer', 'description' => 'Page number' ),
					'search'     => array( 'type' => 'string', 'description' => 'Search by filename/title' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'upload_files' );
			},
			'execute_callback'    => static function ( array $input ) {
				$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
				$paged    = absint( $input['page'] ?? 1 );

				$query_args = array(
					'post_type'      => 'attachment',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( isset( $input['media_type'] ) && 'all' !== $input['media_type'] ) {
					$media_type = sanitize_text_field( $input['media_type'] );
					if ( 'image' === $media_type ) {
						$query_args['post_mime_type'] = 'image';
					} elseif ( 'video' === $media_type ) {
						$query_args['post_mime_type'] = 'video';
					} elseif ( 'audio' === $media_type ) {
						$query_args['post_mime_type'] = 'audio';
					}
				}

				if ( isset( $input['search'] ) ) {
					$query_args['s'] = sanitize_text_field( $input['search'] );
				}

				$query = new \WP_Query( $query_args );
				$media = array_map(
					static function ( $post ) {
						return array(
							'id'       => $post->ID,
							'title'    => $post->post_title,
							'filename' => basename( $post->guid ),
							'url'      => $post->guid,
							'type'     => $post->post_mime_type,
							'date'     => $post->post_date_gmt,
						);
					},
					$query->get_posts()
				);

				return array(
					'success' => true,
					'data'    => $media,
					'total'   => (int) $query->found_posts,
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
 * 34. Get Media
 */
function mcp_wp_register_get_media() {
	wp_register_ability(
		'mcp-wp/get-media',
		array(
			'label'               => __( 'Get Media', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get media by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
				),
				'required'   => array( 'attachment_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'upload_files' );
			},
			'execute_callback'    => static function ( array $input ) {
				$attachment_id = absint( $input['attachment_id'] );
				$attachment    = get_post( $attachment_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media not found',
					);
				}

				$metadata = wp_get_attachment_metadata( $attachment_id );

				return array(
					'success' => true,
					'data'    => array(
						'id'       => $attachment->ID,
						'title'    => $attachment->post_title,
						'filename' => basename( $attachment->guid ),
						'url'      => $attachment->guid,
						'type'     => $attachment->post_mime_type,
						'date'     => $attachment->post_date_gmt,
						'width'    => $metadata['width'] ?? null,
						'height'   => $metadata['height'] ?? null,
						'sizes'    => $metadata['sizes'] ?? array(),
					),
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

// ============================================================================
// GROUP 7: TAXONOMY (35-38)
// ============================================================================

/**
 * 35. List Categories
 */
function mcp_wp_register_list_categories() {
	wp_register_ability(
		'mcp-wp/list-categories',
		array(
			'label'               => __( 'List Categories', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get post categories', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'parent'      => array( 'type' => 'integer', 'description' => 'Filter by parent category' ),
					'hide_empty'  => array( 'type' => 'boolean', 'description' => 'Hide categories with no posts' ),
					'search'      => array( 'type' => 'string', 'description' => 'Search category name' ),
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
				$args = array(
					'hide_empty' => isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : true,
				);

				if ( isset( $input['parent'] ) ) {
					$args['parent'] = absint( $input['parent'] );
				}

				if ( isset( $input['search'] ) ) {
					$args['search'] = sanitize_text_field( $input['search'] );
				}

				$categories = get_categories( $args );
				$formatted = array_map(
					static function ( $cat ) {
						return array(
							'id'    => $cat->term_id,
							'name'  => $cat->name,
							'slug'  => $cat->slug,
							'count' => $cat->count,
							'parent' => $cat->parent,
							'description' => $cat->description,
						);
					},
					$categories
				);

				return array(
					'success' => true,
					'data'    => $formatted,
					'total'   => count( $formatted ),
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
 * 36. List Tags
 */
function mcp_wp_register_list_tags() {
	wp_register_ability(
		'mcp-wp/list-tags',
		array(
			'label'               => __( 'List Tags', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get post tags', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'hide_empty' => array( 'type' => 'boolean', 'description' => 'Hide tags with no posts' ),
					'search'     => array( 'type' => 'string', 'description' => 'Search tag name' ),
					'orderby'    => array( 'type' => 'string', 'enum' => array( 'name', 'count' ) ),
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
				$args = array(
					'taxonomy'   => 'post_tag',
					'hide_empty' => isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : true,
					'orderby'    => isset( $input['orderby'] ) ? sanitize_text_field( $input['orderby'] ) : 'name',
				);

				if ( isset( $input['search'] ) ) {
					$args['search'] = sanitize_text_field( $input['search'] );
				}

				$tags = get_terms( $args );
				$formatted = array_map(
					static function ( $tag ) {
						return array(
							'id'    => $tag->term_id,
							'name'  => $tag->name,
							'slug'  => $tag->slug,
							'count' => $tag->count,
							'description' => $tag->description,
						);
					},
					is_wp_error( $tags ) ? array() : $tags
				);

				return array(
					'success' => true,
					'data'    => $formatted,
					'total'   => count( $formatted ),
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
 * 37. Create Category
 */
function mcp_wp_register_create_category() {
	wp_register_ability(
		'mcp-wp/create-category',
		array(
			'label'               => __( 'Create Category', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create category', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array( 'type' => 'string', 'description' => 'Category name' ),
					'slug'        => array( 'type' => 'string', 'description' => 'Category slug' ),
					'description' => array( 'type' => 'string', 'description' => 'Category description' ),
					'parent'      => array( 'type' => 'integer', 'description' => 'Parent category ID' ),
				),
				'required'   => array( 'name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'category_id' => array( 'type' => 'integer' ),
					'data'        => array( 'type' => 'object' ),
					'error'       => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_categories' );
			},
			'execute_callback'    => static function ( array $input ) {
				$cat_args = array(
					'cat_name'    => sanitize_text_field( $input['name'] ),
					'description' => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '',
				);

				if ( isset( $input['slug'] ) ) {
					$cat_args['category_nicename'] = sanitize_text_field( $input['slug'] );
				}

				if ( isset( $input['parent'] ) ) {
					$cat_args['category_parent'] = absint( $input['parent'] );
				}

				$result = wp_insert_category( $cat_args );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				$category = get_category( $result );

				return array(
					'success'     => true,
					'category_id' => $result,
					'data'        => array(
						'id'    => $category->term_id,
						'name'  => $category->name,
						'slug'  => $category->slug,
						'description' => $category->description,
					),
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
 * 38. Create Tag
 */
function mcp_wp_register_create_tag() {
	wp_register_ability(
		'mcp-wp/create-tag',
		array(
			'label'               => __( 'Create Tag', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create tag', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array( 'type' => 'string', 'description' => 'Tag name' ),
					'slug'        => array( 'type' => 'string', 'description' => 'Tag slug' ),
					'description' => array( 'type' => 'string', 'description' => 'Tag description' ),
				),
				'required'   => array( 'name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'tag_id'  => array( 'type' => 'integer' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_categories' );
			},
			'execute_callback'    => static function ( array $input ) {
				$tag_args = array(
					'description' => isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '',
				);

				if ( isset( $input['slug'] ) ) {
					$tag_args['slug'] = sanitize_text_field( $input['slug'] );
				}

				$result = wp_insert_term(
					sanitize_text_field( $input['name'] ),
					'post_tag',
					$tag_args
				);

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				$tag = get_term( $result['term_id'] );

				return array(
					'success' => true,
					'tag_id'  => $result['term_id'],
					'data'    => array(
						'id'    => $tag->term_id,
						'name'  => $tag->name,
						'slug'  => $tag->slug,
						'description' => $tag->description,
					),
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

// ============================================================================
// GROUP 8: ADVANCED (39-45)
// ============================================================================

/**
 * 39. Custom REST Call
 */
function mcp_wp_register_custom_rest_call() {
	wp_register_ability(
		'mcp-wp/custom-rest-call',
		array(
			'label'               => __( 'Custom REST Call', 'mcp-wp-capabilities' ),
			'description'         => __( 'Make custom REST calls', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'route'    => array( 'type' => 'string', 'description' => 'REST API route' ),
					'method'   => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'DELETE' ), 'description' => 'HTTP method' ),
					'params'   => array( 'type' => 'object', 'description' => 'Request parameters' ),
					'body'     => array( 'type' => 'object', 'description' => 'Request body (for POST/PUT)' ),
				),
				'required'   => array( 'route', 'method' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'status'     => array( 'type' => 'integer' ),
					'data'       => array( 'type' => 'object' ),
					'error'      => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$route = sanitize_text_field( $input['route'] );
				$method = sanitize_text_field( $input['method'] );
				$params = isset( $input['params'] ) ? (array) $input['params'] : array();

				$request_args = array(
					'method' => $method,
				);

				if ( in_array( $method, array( 'POST', 'PUT' ), true ) && isset( $input['body'] ) ) {
					$request_args['body'] = wp_json_encode( $input['body'] );
				}

				$response = rest_do_request( new \WP_REST_Request( $method, $route, array( 'body' => $params ) ) );

				if ( is_wp_error( $response ) ) {
					return array(
						'success' => false,
						'error'   => $response->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'status'  => $response->get_status(),
					'data'    => $response->get_data(),
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
 * 40. Query Posts Advanced
 */
function mcp_wp_register_query_posts_advanced() {
	wp_register_ability(
		'mcp-wp/query-posts-advanced',
		array(
			'label'               => __( 'Query Posts Advanced', 'mcp-wp-capabilities' ),
			'description'         => __( 'Advanced post queries', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_type'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post types to query' ),
					'status'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post statuses' ),
					'meta_query'     => array( 'type' => 'array', 'description' => 'Meta query conditions' ),
					'date_after'     => array( 'type' => 'string', 'description' => 'Date after (YYYY-MM-DD)' ),
					'date_before'    => array( 'type' => 'string', 'description' => 'Date before (YYYY-MM-DD)' ),
					'author_id'      => array( 'type' => 'integer', 'description' => 'Filter by author ID' ),
					'per_page'       => array( 'type' => 'integer', 'description' => 'Number per page (max 100)' ),
					'page'           => array( 'type' => 'integer', 'description' => 'Page number' ),
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
				$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
				$paged    = absint( $input['page'] ?? 1 );

				$query_args = array(
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( isset( $input['post_type'] ) ) {
					$query_args['post_type'] = array_map( 'sanitize_text_field', (array) $input['post_type'] );
				}

				if ( isset( $input['status'] ) ) {
					$query_args['post_status'] = array_map( 'sanitize_text_field', (array) $input['status'] );
				}

				if ( isset( $input['author_id'] ) ) {
					$query_args['author'] = absint( $input['author_id'] );
				}

				if ( isset( $input['date_after'] ) ) {
					$query_args['date_query'][] = array(
						'after'     => sanitize_text_field( $input['date_after'] ),
						'inclusive' => true,
					);
				}

				if ( isset( $input['date_before'] ) ) {
					if ( ! isset( $query_args['date_query'] ) ) {
						$query_args['date_query'] = array();
					}
					$query_args['date_query'][] = array(
						'before'    => sanitize_text_field( $input['date_before'] ),
						'inclusive' => true,
					);
				}

				if ( isset( $input['meta_query'] ) && is_array( $input['meta_query'] ) ) {
					$query_args['meta_query'] = $input['meta_query'];
				}

				$query = new \WP_Query( $query_args );
				$posts = array_map(
					static function ( $post ) {
						return MCP_WP_Ability_Helpers::format_post_response( $post, false );
					},
					$query->get_posts()
				);

				return array(
					'success' => true,
					'data'    => $posts,
					'total'   => (int) $query->found_posts,
					'pages'   => (int) $query->max_num_pages,
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
 * 41. Batch Update
 */
function mcp_wp_register_batch_update() {
	wp_register_ability(
		'mcp-wp/batch-update',
		array(
			'label'               => __( 'Batch Update', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update multiple items', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'items'  => array( 'type' => 'array', 'description' => 'Array of items to update' ),
					'type'   => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'term' ), 'description' => 'Item type' ),
				),
				'required'   => array( 'items', 'type' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'updated'   => array( 'type' => 'integer' ),
					'failed'    => array( 'type' => 'integer' ),
					'errors'    => array( 'type' => 'array' ),
					'error'     => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$items = (array) $input['items'];
				$type = sanitize_text_field( $input['type'] );
				$updated = 0;
				$failed = 0;
				$errors = array();

				foreach ( $items as $item ) {
					try {
						if ( 'post' === $type || 'page' === $type ) {
							$result = wp_update_post( (array) $item );
							if ( is_wp_error( $result ) ) {
								$failed++;
								$errors[] = $result->get_error_message();
							} else {
								$updated++;
							}
						} elseif ( 'term' === $type ) {
							$term_id = absint( $item['id'] ?? 0 );
							if ( $term_id ) {
								$result = wp_update_term( $term_id, $item['taxonomy'] ?? 'category', (array) $item );
								if ( is_wp_error( $result ) ) {
									$failed++;
									$errors[] = $result->get_error_message();
								} else {
									$updated++;
								}
							}
						}
					} catch ( \Exception $e ) {
						$failed++;
						$errors[] = $e->getMessage();
					}
				}

				return array(
					'success' => true,
					'updated' => $updated,
					'failed'  => $failed,
					'errors'  => $errors,
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
 * 42. Export Pattern
 */
function mcp_wp_register_export_pattern() {
	wp_register_ability(
		'mcp-wp/export-pattern',
		array(
			'label'               => __( 'Export Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Export pattern as JSON', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern name to export' ),
				),
				'required'   => array( 'pattern_name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'json'        => array( 'type' => 'string' ),
					'error'       => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_name = sanitize_text_field( $input['pattern_name'] );
				$pattern = MCP_WP_Ability_Helpers::get_pattern_by_name( $pattern_name );

				if ( ! $pattern ) {
					return array(
						'success' => false,
						'error'   => 'Pattern not found',
					);
				}

				return array(
					'success' => true,
					'json'    => wp_json_encode( $pattern ),
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
 * 43. Import Pattern
 */
function mcp_wp_register_import_pattern() {
	wp_register_ability(
		'mcp-wp/import-pattern',
		array(
			'label'               => __( 'Import Pattern', 'mcp-wp-capabilities' ),
			'description'         => __( 'Import pattern from JSON', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_json' => array( 'type' => 'string', 'description' => 'Pattern JSON data' ),
				),
				'required'   => array( 'pattern_json' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'data'     => array( 'type' => 'object' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_data = json_decode( $input['pattern_json'], true );

				if ( ! is_array( $pattern_data ) ) {
					return array(
						'success' => false,
						'error'   => 'Invalid JSON data',
					);
				}

				if ( ! isset( $pattern_data['name'] ) || ! isset( $pattern_data['content'] ) ) {
					return array(
						'success' => false,
						'error'   => 'Missing required fields: name, content',
					);
				}

				if ( function_exists( 'register_block_pattern' ) ) {
					register_block_pattern(
						sanitize_text_field( $pattern_data['name'] ),
						array(
							'title'       => sanitize_text_field( $pattern_data['title'] ?? 'Imported Pattern' ),
							'content'     => wp_kses_post( $pattern_data['content'] ),
							'category'    => sanitize_text_field( $pattern_data['category'] ?? 'default' ),
							'description' => sanitize_text_field( $pattern_data['description'] ?? '' ),
							'keywords'    => isset( $pattern_data['keywords'] ) ? array_map( 'sanitize_text_field', (array) $pattern_data['keywords'] ) : array(),
						)
					);

					return array(
						'success' => true,
						'data'    => $pattern_data,
					);
				}

				return array(
					'success' => false,
					'error'   => 'Block patterns not supported',
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
 * 44. Get Pattern Usage
 */
function mcp_wp_register_get_pattern_usage() {
	wp_register_ability(
		'mcp-wp/get-pattern-usage',
		array(
			'label'               => __( 'Get Pattern Usage', 'mcp-wp-capabilities' ),
			'description'         => __( 'Find where pattern is used', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern name to search' ),
				),
				'required'   => array( 'pattern_name' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'array' ),
					'count'   => array( 'type' => 'integer' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$pattern_name = sanitize_text_field( $input['pattern_name'] );

				$args = array(
					's'           => $pattern_name,
					'post_type'   => array( 'post', 'page' ),
					'post_status' => 'any',
					'numberposts' => -1,
				);

				$posts = get_posts( $args );
				$usage = array();

				foreach ( $posts as $post ) {
					if ( strpos( $post->post_content, $pattern_name ) !== false ) {
						$usage[] = array(
							'id'    => $post->ID,
							'title' => $post->post_title,
							'type'  => $post->post_type,
							'url'   => get_permalink( $post->ID ),
						);
					}
				}

				return array(
					'success' => true,
					'data'    => $usage,
					'count'   => count( $usage ),
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
 * 45. Clone Item
 */
function mcp_wp_register_clone_item() {
	wp_register_ability(
		'mcp-wp/clone-item',
		array(
			'label'               => __( 'Clone Item', 'mcp-wp-capabilities' ),
			'description'         => __( 'Duplicate page/post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'item_id'      => array( 'type' => 'integer', 'description' => 'Item ID to clone' ),
					'type'         => array( 'type' => 'string', 'enum' => array( 'post', 'page' ), 'description' => 'Item type' ),
					'new_title'    => array( 'type' => 'string', 'description' => 'Title for cloned item' ),
					'new_status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'scheduled' ), 'description' => 'Status for cloned item' ),
				),
				'required'   => array( 'item_id', 'type' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'new_id'   => array( 'type' => 'integer' ),
					'url'      => array( 'type' => 'string' ),
					'data'     => array( 'type' => 'object' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$item_id = absint( $input['item_id'] );
				$type = sanitize_text_field( $input['type'] );
				$original = get_post( $item_id );

				if ( ! $original || $type !== $original->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Item not found or type mismatch',
					);
				}

				$cloned_post = array(
					'post_title'   => isset( $input['new_title'] ) ? sanitize_text_field( $input['new_title'] ) : $original->post_title . ' - Copy',
					'post_content' => $original->post_content,
					'post_excerpt' => $original->post_excerpt,
					'post_status'  => isset( $input['new_status'] ) ? sanitize_text_field( $input['new_status'] ) : 'draft',
					'post_type'    => $original->post_type,
					'post_author'  => get_current_user_id(),
				);

				$new_id = wp_insert_post( $cloned_post );

				if ( is_wp_error( $new_id ) ) {
					return array(
						'success' => false,
						'error'   => $new_id->get_error_message(),
					);
				}

				// Copy featured image
				$featured_image = get_post_thumbnail_id( $item_id );
				if ( $featured_image ) {
					set_post_thumbnail( $new_id, $featured_image );
				}

				// Copy meta
				$meta = get_post_meta( $item_id );
				foreach ( $meta as $key => $values ) {
					if ( '_' !== substr( $key, 0, 1 ) ) {
						foreach ( $values as $value ) {
							add_post_meta( $new_id, $key, $value );
						}
					}
				}

				$cloned = get_post( $new_id );

				return array(
					'success' => true,
					'new_id'  => $new_id,
					'url'     => get_permalink( $new_id ),
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $cloned ),
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
