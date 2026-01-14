<?php
/**
 * WordPress Abilities 18-22: User Management
 *
 * Defines abilities for managing WordPress users and permissions
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Register all user abilities
 */
function mcp_wp_register_users_abilities() {
	mcp_wp_register_list_users();
	mcp_wp_register_get_user();
	mcp_wp_register_get_current_user();
	mcp_wp_register_create_user();
	mcp_wp_register_edit_user();
}
