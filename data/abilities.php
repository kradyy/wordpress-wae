<?php
/**
 * WordPress Abilities for MCP
 *
 * Defines 45 comprehensive WordPress capabilities for MCP integration
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all WordPress abilities
 */
function mcp_wp_capabilities_register_all_abilities() {
	// Group 1: Page Management (1-10)
	mcp_wp_register_create_page();
	mcp_wp_register_edit_page();
	mcp_wp_register_create_post();
	mcp_wp_register_edit_post();
	mcp_wp_register_get_page();
	mcp_wp_register_get_post();
	mcp_wp_register_list_pages();
	mcp_wp_register_list_posts();
	mcp_wp_register_delete_page();
	mcp_wp_register_delete_post();

	// Group 2: Gutenberg Patterns & Blocks (11-17)
	mcp_wp_register_list_patterns();
	mcp_wp_register_get_pattern();
	mcp_wp_register_create_pattern();
	mcp_wp_register_edit_pattern();
	mcp_wp_register_delete_pattern();
	mcp_wp_register_get_block_types();
	mcp_wp_register_validate_blocks();

	// Group 3: Users & Permissions (18-22)
	mcp_wp_register_list_users();
	mcp_wp_register_get_user();
	mcp_wp_register_get_current_user();
	mcp_wp_register_create_user();
	mcp_wp_register_edit_user();

	// Group 4: Plugins & Theme (23-28)
	mcp_wp_register_list_plugins();
	mcp_wp_register_get_plugin();
	mcp_wp_register_activate_plugin();
	mcp_wp_register_deactivate_plugin();
	mcp_wp_register_get_theme();
	mcp_wp_register_get_theme_supports();

	// Group 5: Settings & Configuration (29-31)
	mcp_wp_register_get_settings();
	mcp_wp_register_get_gutenberg_settings();
	mcp_wp_register_get_site_stats();

	// Group 6: Media & Assets (32-34)
	mcp_wp_register_upload_media();
	mcp_wp_register_list_media();
	mcp_wp_register_get_media();

	// Group 7: Taxonomy (35-38)
	mcp_wp_register_list_categories();
	mcp_wp_register_list_tags();
	mcp_wp_register_create_category();
	mcp_wp_register_create_tag();

	// Group 8: Advanced (39-45)
	mcp_wp_register_custom_rest_call();
	mcp_wp_register_query_posts_advanced();
	mcp_wp_register_batch_update();
	mcp_wp_register_export_pattern();
	mcp_wp_register_import_pattern();
	mcp_wp_register_get_pattern_usage();
	mcp_wp_register_clone_item();
}

// ============================================================================
// GROUP 1: PAGE MANAGEMENT (1-10)
// ============================================================================

/**
 * 1. Create Page
 */
function mcp_wp_register_create_page() {
	wp_register_ability(
		'mcp-wp/create-page',
		array(
			'label'               => __( 'Create Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a new WordPress page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'              => array( 'type' => 'string', 'description' => 'Page title' ),
					'content'            => array( 'type' => 'string', 'description' => 'Page content (HTML/Gutenberg)' ),
					'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'scheduled' ), 'description' => 'Page status' ),
					'parent_id'          => array( 'type' => 'integer', 'description' => 'Parent page ID' ),
					'template'           => array( 'type' => 'string', 'description' => 'Page template slug' ),
					'featured_image_id'  => array( 'type' => 'integer', 'description' => 'Featured image attachment ID' ),
					'excerpt'            => array( 'type' => 'string', 'description' => 'Page excerpt' ),
					'meta'               => array( 'type' => 'object', 'description' => 'Custom meta fields' ),
				),
				'required'   => array( 'title', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'page_id'   => array( 'type' => 'integer' ),
					'url'       => array( 'type' => 'string' ),
					'data'      => array( 'type' => 'object' ),
					'error'     => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'publish_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$page_id = wp_insert_post(
					array(
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => wp_kses_post( $input['content'] ),
						'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
						'post_type'    => 'page',
						'post_parent'  => isset( $input['parent_id'] ) ? absint( $input['parent_id'] ) : 0,
						'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
					)
				);

				if ( is_wp_error( $page_id ) ) {
					return array(
						'success' => false,
						'error'   => $page_id->get_error_message(),
					);
				}

				if ( isset( $input['featured_image_id'] ) ) {
					set_post_thumbnail( $page_id, absint( $input['featured_image_id'] ) );
				}

				if ( isset( $input['template'] ) && 'page' === get_post_type( $page_id ) ) {
					update_post_meta( $page_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $key => $value ) {
						update_post_meta( $page_id, sanitize_key( $key ), $value );
					}
				}

				$page = get_post( $page_id );
				return array(
					'success' => true,
					'page_id' => $page_id,
					'url'     => get_permalink( $page_id ),
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $page ),
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
 * 2. Edit Page
 */
function mcp_wp_register_edit_page() {
	wp_register_ability(
		'mcp-wp/edit-page',
		array(
			'label'               => __( 'Edit Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Modify an existing WordPress page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id'            => array( 'type' => 'integer', 'description' => 'Page ID to edit' ),
					'title'              => array( 'type' => 'string' ),
					'content'            => array( 'type' => 'string' ),
					'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'scheduled' ) ),
					'template'           => array( 'type' => 'string' ),
					'featured_image_id'  => array( 'type' => 'integer' ),
					'meta'               => array( 'type' => 'object' ),
				),
				'required'   => array( 'page_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$page_id = absint( $input['page_id'] );
				$page    = MCP_WP_Ability_Helpers::get_page_object( $page_id );

				if ( ! $page ) {
					return array(
						'success' => false,
						'error'   => 'Page not found',
					);
				}

				$update_args = array( 'ID' => $page_id );
				if ( isset( $input['title'] ) ) {
					$update_args['post_title'] = sanitize_text_field( $input['title'] );
				}
				if ( isset( $input['content'] ) ) {
					$update_args['post_content'] = wp_kses_post( $input['content'] );
				}
				if ( isset( $input['status'] ) ) {
					$update_args['post_status'] = sanitize_text_field( $input['status'] );
				}

				$result = wp_update_post( $update_args );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				if ( isset( $input['featured_image_id'] ) ) {
					set_post_thumbnail( $page_id, absint( $input['featured_image_id'] ) );
				}

				if ( isset( $input['template'] ) ) {
					update_post_meta( $page_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $key => $value ) {
						update_post_meta( $page_id, sanitize_key( $key ), $value );
					}
				}

				$updated_page = get_post( $page_id );
				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $updated_page ),
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
 * 3. Create Post
 */
function mcp_wp_register_create_post() {
	wp_register_ability(
		'mcp-wp/create-post',
		array(
			'label'               => __( 'Create Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a new WordPress post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'              => array( 'type' => 'string' ),
					'content'            => array( 'type' => 'string' ),
					'excerpt'            => array( 'type' => 'string' ),
					'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'scheduled' ) ),
					'categories'         => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'tags'               => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'featured_image_id'  => array( 'type' => 'integer' ),
					'author_id'          => array( 'type' => 'integer' ),
					'meta'               => array( 'type' => 'object' ),
				),
				'required'   => array( 'title', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'post_id'  => array( 'type' => 'integer' ),
					'url'      => array( 'type' => 'string' ),
					'data'     => array( 'type' => 'object' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'publish_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_id = wp_insert_post(
					array(
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => wp_kses_post( $input['content'] ),
						'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
						'post_type'    => 'post',
						'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
						'post_author'  => isset( $input['author_id'] ) ? absint( $input['author_id'] ) : get_current_user_id(),
						'post_category' => isset( $input['categories'] ) ? array_map( 'absint', (array) $input['categories'] ) : array(),
					)
				);

				if ( is_wp_error( $post_id ) ) {
					return array(
						'success' => false,
						'error'   => $post_id->get_error_message(),
					);
				}

				if ( isset( $input['tags'] ) ) {
					wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', (array) $input['tags'] ) );
				}

				if ( isset( $input['featured_image_id'] ) ) {
					set_post_thumbnail( $post_id, absint( $input['featured_image_id'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $key => $value ) {
						update_post_meta( $post_id, sanitize_key( $key ), $value );
					}
				}

				$post = get_post( $post_id );
				return array(
					'success' => true,
					'post_id' => $post_id,
					'url'     => get_permalink( $post_id ),
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $post ),
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
 * 4. Edit Post
 */
function mcp_wp_register_edit_post() {
	wp_register_ability(
		'mcp-wp/edit-post',
		array(
			'label'               => __( 'Edit Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Modify an existing WordPress post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'            => array( 'type' => 'integer' ),
					'title'              => array( 'type' => 'string' ),
					'content'            => array( 'type' => 'string' ),
					'status'             => array( 'type' => 'string' ),
					'categories'         => array( 'type' => 'array' ),
					'tags'               => array( 'type' => 'array' ),
					'meta'               => array( 'type' => 'object' ),
				),
				'required'   => array( 'post_id' ),
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
				$post_id = absint( $input['post_id'] );
				$post    = MCP_WP_Ability_Helpers::get_post_object( $post_id );

				if ( ! $post ) {
					return array(
						'success' => false,
						'error'   => 'Post not found',
					);
				}

				$update_args = array( 'ID' => $post_id );
				if ( isset( $input['title'] ) ) {
					$update_args['post_title'] = sanitize_text_field( $input['title'] );
				}
				if ( isset( $input['content'] ) ) {
					$update_args['post_content'] = wp_kses_post( $input['content'] );
				}
				if ( isset( $input['status'] ) ) {
					$update_args['post_status'] = sanitize_text_field( $input['status'] );
				}

				$result = wp_update_post( $update_args );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				if ( isset( $input['categories'] ) ) {
					wp_set_post_categories( $post_id, array_map( 'absint', (array) $input['categories'] ) );
				}

				if ( isset( $input['tags'] ) ) {
					wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', (array) $input['tags'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $key => $value ) {
						update_post_meta( $post_id, sanitize_key( $key ), $value );
					}
				}

				$updated_post = get_post( $post_id );
				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $updated_post ),
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
 * 5. Get Page
 */
function mcp_wp_register_get_page() {
	wp_register_ability(
		'mcp-wp/get-page',
		array(
			'label'               => __( 'Get Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Retrieve a page\'s full details', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id'           => array( 'type' => 'integer' ),
					'include_children'  => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'page_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$page_id = absint( $input['page_id'] );
				$page    = MCP_WP_Ability_Helpers::get_page_object( $page_id );

				if ( ! $page ) {
					return array(
						'success' => false,
						'error'   => 'Page not found',
					);
				}

				$data = MCP_WP_Ability_Helpers::format_post_response( $page );

				if ( isset( $input['include_children'] ) && $input['include_children'] ) {
					$children    = get_pages( array( 'parent' => $page_id ) );
					$data['children'] = array_map(
						static function ( $child ) {
							return MCP_WP_Ability_Helpers::format_post_response( $child, false );
						},
						$children
					);
				}

				return array(
					'success' => true,
					'data'    => $data,
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
 * 6. Get Post
 */
function mcp_wp_register_get_post() {
	wp_register_ability(
		'mcp-wp/get-post',
		array(
			'label'               => __( 'Get Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Retrieve a post\'s full details', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'type' => 'integer' ),
				),
				'required'   => array( 'post_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_id = absint( $input['post_id'] );
				$post    = MCP_WP_Ability_Helpers::get_post_object( $post_id );

				if ( ! $post ) {
					return array(
						'success' => false,
						'error'   => 'Post not found',
					);
				}

				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::format_post_response( $post ),
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
 * 7. List Pages
 */
function mcp_wp_register_list_pages() {
	wp_register_ability(
		'mcp-wp/list-pages',
		array(
			'label'               => __( 'List Pages', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all pages with optional filtering', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'parent_id'  => array( 'type' => 'integer', 'description' => 'Filter by parent page' ),
					'status'     => array( 'type' => 'string', 'description' => 'Filter by status' ),
					'per_page'   => array( 'type' => 'integer', 'description' => 'Number to return (default: 10, max: 100)' ),
					'page'       => array( 'type' => 'integer', 'description' => 'Page number' ),
					'search'     => array( 'type' => 'string', 'description' => 'Search by title' ),
				),
				'required'   => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
				$paged    = absint( $input['page'] ?? 1 );

				$query_args = array(
					'post_type'      => 'page',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( isset( $input['parent_id'] ) ) {
					$query_args['post_parent'] = absint( $input['parent_id'] );
				}

				if ( isset( $input['status'] ) ) {
					$query_args['post_status'] = sanitize_text_field( $input['status'] );
				} else {
					$query_args['post_status'] = 'publish';
				}

				if ( isset( $input['search'] ) ) {
					$query_args['s'] = sanitize_text_field( $input['search'] );
				}

				$query = new \WP_Query( $query_args );
				$pages = array_map(
					static function ( $page ) {
						return MCP_WP_Ability_Helpers::format_post_response( $page, false );
					},
					$query->get_posts()
				);

				return array(
					'success' => true,
					'data'    => $pages,
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
 * 8. List Posts
 */
function mcp_wp_register_list_posts() {
	wp_register_ability(
		'mcp-wp/list-posts',
		array(
			'label'               => __( 'List Posts', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get posts with filtering and search', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status'     => array( 'type' => 'string' ),
					'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'per_page'   => array( 'type' => 'integer' ),
					'page'       => array( 'type' => 'integer' ),
					'search'     => array( 'type' => 'string' ),
					'order_by'   => array( 'type' => 'string', 'enum' => array( 'date', 'title', 'modified' ) ),
					'order'      => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ) ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
				$paged    = absint( $input['page'] ?? 1 );
				$orderby  = sanitize_text_field( $input['order_by'] ?? 'date' );
				$order    = sanitize_text_field( $input['order'] ?? 'DESC' );

				$query_args = array(
					'post_type'      => 'post',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => $orderby,
					'order'          => strtoupper( $order ),
					'post_status'    => sanitize_text_field( $input['status'] ?? 'publish' ),
				);

				if ( isset( $input['categories'] ) ) {
					$query_args['category__in'] = array_map( 'absint', (array) $input['categories'] );
				}

				if ( isset( $input['tags'] ) ) {
					$query_args['tag'] = implode( ',', array_map( 'sanitize_text_field', (array) $input['tags'] ) );
				}

				if ( isset( $input['search'] ) ) {
					$query_args['s'] = sanitize_text_field( $input['search'] );
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
 * 9. Delete Page
 */
function mcp_wp_register_delete_page() {
	wp_register_ability(
		'mcp-wp/delete-page',
		array(
			'label'               => __( 'Delete Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete a page (permanently or to trash)', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id'      => array( 'type' => 'integer' ),
					'force_delete' => array( 'type' => 'boolean', 'description' => 'Permanently delete vs trash' ),
				),
				'required'   => array( 'page_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'delete_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$page_id     = absint( $input['page_id'] );
				$force_delete = isset( $input['force_delete'] ) ? (bool) $input['force_delete'] : false;
				$page        = MCP_WP_Ability_Helpers::get_page_object( $page_id );

				if ( ! $page ) {
					return array(
						'success' => false,
						'error'   => 'Page not found',
					);
				}

				$result = wp_delete_post( $page_id, $force_delete );

				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete page',
					);
				}

				return array(
					'success' => true,
					'message' => $force_delete ? 'Page permanently deleted' : 'Page moved to trash',
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
 * 10. Delete Post
 */
function mcp_wp_register_delete_post() {
	wp_register_ability(
		'mcp-wp/delete-post',
		array(
			'label'               => __( 'Delete Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete a post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'force_delete' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'post_id' ),
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
				$post_id     = absint( $input['post_id'] );
				$force_delete = isset( $input['force_delete'] ) ? (bool) $input['force_delete'] : false;
				$post        = MCP_WP_Ability_Helpers::get_post_object( $post_id );

				if ( ! $post ) {
					return array(
						'success' => false,
						'error'   => 'Post not found',
					);
				}

				$result = wp_delete_post( $post_id, $force_delete );

				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete post',
					);
				}

				return array(
					'success' => true,
					'message' => $force_delete ? 'Post permanently deleted' : 'Post moved to trash',
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
// GROUP 2-8: GUTENBERG PATTERNS, USERS, PLUGINS, SETTINGS, MEDIA, TAXONOMY, ADVANCED (11-45)
// ============================================================================
// Load additional abilities from separate file
require_once dirname( __FILE__ ) . '/abilities-11-45.php';
