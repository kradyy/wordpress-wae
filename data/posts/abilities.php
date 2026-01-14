<?php
/**
 * WordPress Abilities 1-10: Page & Post Management
 *
 * Defines abilities for creating, editing, listing, and deleting pages and posts
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Create Page
 */
function mcp_wp_register_create_page() {
	wp_register_ability(
		'mcp-wp/create-page',
		array(
			'label'               => __( 'Create Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create new WordPress page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'          => array( 'type' => 'string', 'description' => 'Page title' ),
					'content'        => array( 'type' => 'string', 'description' => 'Page content (HTML or blocks)' ),
					'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private' ), 'description' => 'Page status' ),
					'slug'           => array( 'type' => 'string', 'description' => 'Page slug' ),
					'parent_id'      => array( 'type' => 'integer', 'description' => 'Parent page ID' ),
					'template'       => array( 'type' => 'string', 'description' => 'Page template' ),
					'featured_image' => array( 'type' => 'integer', 'description' => 'Featured image ID' ),
				),
				'required'   => array( 'title', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'page_id' => array( 'type' => 'integer' ),
					'url'     => array( 'type' => 'string' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_pages' );
			},
			'execute_callback'    => static function ( array $input ) {
				$page_data = array(
					'post_title'   => sanitize_text_field( $input['title'] ),
					'post_content' => wp_kses_post( $input['content'] ),
					'post_status'  => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'draft',
					'post_type'    => 'page',
				);

				if ( isset( $input['slug'] ) ) {
					$page_data['post_name'] = sanitize_title( $input['slug'] );
				}

				if ( isset( $input['parent_id'] ) ) {
					$page_data['post_parent'] = absint( $input['parent_id'] );
				}

				if ( isset( $input['template'] ) ) {
					$page_data['page_template'] = sanitize_text_field( $input['template'] );
				}

				$page_id = wp_insert_post( $page_data );

				if ( is_wp_error( $page_id ) ) {
					return array(
						'success' => false,
						'error'   => $page_id->get_error_message(),
					);
				}

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( $page_id, absint( $input['featured_image'] ) );
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
			'description'         => __( 'Modify existing page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id'        => array( 'type' => 'integer', 'description' => 'Page ID to edit' ),
					'title'          => array( 'type' => 'string', 'description' => 'Page title' ),
					'content'        => array( 'type' => 'string', 'description' => 'Page content' ),
					'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private' ) ),
					'slug'           => array( 'type' => 'string', 'description' => 'Page slug' ),
					'parent_id'      => array( 'type' => 'integer', 'description' => 'Parent page ID' ),
					'featured_image' => array( 'type' => 'integer', 'description' => 'Featured image ID' ),
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

				$update_data = array( 'ID' => $page_id );

				if ( isset( $input['title'] ) ) {
					$update_data['post_title'] = sanitize_text_field( $input['title'] );
				}

				if ( isset( $input['content'] ) ) {
					$update_data['post_content'] = wp_kses_post( $input['content'] );
				}

				if ( isset( $input['status'] ) ) {
					$update_data['post_status'] = sanitize_text_field( $input['status'] );
				}

				if ( isset( $input['slug'] ) ) {
					$update_data['post_name'] = sanitize_title( $input['slug'] );
				}

				if ( isset( $input['parent_id'] ) ) {
					$update_data['post_parent'] = absint( $input['parent_id'] );
				}

				$result = wp_update_post( $update_data );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( $page_id, absint( $input['featured_image'] ) );
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
 * 3. Get Page
 */
function mcp_wp_register_get_page() {
	wp_register_ability(
		'mcp-wp/get-page',
		array(
			'label'               => __( 'Get Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Retrieve page by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id' => array( 'type' => 'integer', 'description' => 'Page ID' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
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

				return array(
					'success' => true,
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
 * 4. List Pages
 */
function mcp_wp_register_list_pages() {
	wp_register_ability(
		'mcp-wp/list-pages',
		array(
			'label'               => __( 'List Pages', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all pages with filtering', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status'    => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private', 'any' ) ),
					'parent_id' => array( 'type' => 'integer', 'description' => 'Filter by parent page' ),
					'search'    => array( 'type' => 'string', 'description' => 'Search term' ),
					'per_page'  => array( 'type' => 'integer', 'description' => 'Number to return (default: 10, max: 100)' ),
					'page'      => array( 'type' => 'integer', 'description' => 'Page number' ),
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

				$args = array(
					'post_type'      => 'page',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'title',
					'order'          => 'ASC',
				);

				if ( isset( $input['status'] ) ) {
					$args['post_status'] = sanitize_text_field( $input['status'] );
				}

				if ( isset( $input['parent_id'] ) ) {
					$args['post_parent'] = absint( $input['parent_id'] );
				}

				if ( isset( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( $input['search'] );
				}

				$query = new \WP_Query( $args );
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
 * 5. Delete Page
 */
function mcp_wp_register_delete_page() {
	wp_register_ability(
		'mcp-wp/delete-page',
		array(
			'label'               => __( 'Delete Page', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_id' => array( 'type' => 'integer', 'description' => 'Page ID to delete' ),
					'force'   => array( 'type' => 'boolean', 'description' => 'Force delete (bypass trash)' ),
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
				$page_id = absint( $input['page_id'] );
				$page    = MCP_WP_Ability_Helpers::get_page_object( $page_id );

				if ( ! $page ) {
					return array(
						'success' => false,
						'error'   => 'Page not found',
					);
				}

				$force  = isset( $input['force'] ) ? (bool) $input['force'] : false;
				$result = wp_delete_post( $page_id, $force );

				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete page',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Page permanently deleted' : 'Page moved to trash',
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
 * 6. Create Post
 */
function mcp_wp_register_create_post() {
	wp_register_ability(
		'mcp-wp/create-post',
		array(
			'label'               => __( 'Create Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create new blog post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'          => array( 'type' => 'string', 'description' => 'Post title' ),
					'content'        => array( 'type' => 'string', 'description' => 'Post content (HTML or blocks)' ),
					'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private' ), 'description' => 'Post status' ),
					'slug'           => array( 'type' => 'string', 'description' => 'Post slug' ),
					'excerpt'        => array( 'type' => 'string', 'description' => 'Post excerpt' ),
					'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Category IDs' ),
					'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names' ),
					'featured_image' => array( 'type' => 'integer', 'description' => 'Featured image ID' ),
				),
				'required'   => array( 'title', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'post_id' => array( 'type' => 'integer' ),
					'url'     => array( 'type' => 'string' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_data = array(
					'post_title'   => sanitize_text_field( $input['title'] ),
					'post_content' => wp_kses_post( $input['content'] ),
					'post_status'  => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'draft',
					'post_type'    => 'post',
				);

				if ( isset( $input['slug'] ) ) {
					$post_data['post_name'] = sanitize_title( $input['slug'] );
				}

				if ( isset( $input['excerpt'] ) ) {
					$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
				}

				$post_id = wp_insert_post( $post_data );

				if ( is_wp_error( $post_id ) ) {
					return array(
						'success' => false,
						'error'   => $post_id->get_error_message(),
					);
				}

				if ( isset( $input['categories'] ) ) {
					wp_set_post_categories( $post_id, array_map( 'absint', (array) $input['categories'] ) );
				}

				if ( isset( $input['tags'] ) ) {
					wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', (array) $input['tags'] ) );
				}

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( $post_id, absint( $input['featured_image'] ) );
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
 * 7. Edit Post
 */
function mcp_wp_register_edit_post() {
	wp_register_ability(
		'mcp-wp/edit-post',
		array(
			'label'               => __( 'Edit Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Modify existing post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'        => array( 'type' => 'integer', 'description' => 'Post ID to edit' ),
					'title'          => array( 'type' => 'string', 'description' => 'Post title' ),
					'content'        => array( 'type' => 'string', 'description' => 'Post content' ),
					'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private' ) ),
					'slug'           => array( 'type' => 'string', 'description' => 'Post slug' ),
					'excerpt'        => array( 'type' => 'string', 'description' => 'Post excerpt' ),
					'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'featured_image' => array( 'type' => 'integer', 'description' => 'Featured image ID' ),
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

				$update_data = array( 'ID' => $post_id );

				if ( isset( $input['title'] ) ) {
					$update_data['post_title'] = sanitize_text_field( $input['title'] );
				}

				if ( isset( $input['content'] ) ) {
					$update_data['post_content'] = wp_kses_post( $input['content'] );
				}

				if ( isset( $input['status'] ) ) {
					$update_data['post_status'] = sanitize_text_field( $input['status'] );
				}

				if ( isset( $input['slug'] ) ) {
					$update_data['post_name'] = sanitize_title( $input['slug'] );
				}

				if ( isset( $input['excerpt'] ) ) {
					$update_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
				}

				$result = wp_update_post( $update_data );

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

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( $post_id, absint( $input['featured_image'] ) );
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
 * 8. Get Post
 */
function mcp_wp_register_get_post() {
	wp_register_ability(
		'mcp-wp/get-post',
		array(
			'label'               => __( 'Get Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'Retrieve post by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
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
 * 9. List Posts
 */
function mcp_wp_register_list_posts() {
	wp_register_ability(
		'mcp-wp/list-posts',
		array(
			'label'               => __( 'List Posts', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all posts with filtering', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private', 'any' ) ),
					'category'   => array( 'type' => 'integer', 'description' => 'Filter by category ID' ),
					'tag'        => array( 'type' => 'string', 'description' => 'Filter by tag slug' ),
					'search'     => array( 'type' => 'string', 'description' => 'Search term' ),
					'author_id'  => array( 'type' => 'integer', 'description' => 'Filter by author' ),
					'per_page'   => array( 'type' => 'integer', 'description' => 'Number to return (default: 10, max: 100)' ),
					'page'       => array( 'type' => 'integer', 'description' => 'Page number' ),
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

				$args = array(
					'post_type'      => 'post',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( isset( $input['status'] ) ) {
					$args['post_status'] = sanitize_text_field( $input['status'] );
				}

				if ( isset( $input['category'] ) ) {
					$args['cat'] = absint( $input['category'] );
				}

				if ( isset( $input['tag'] ) ) {
					$args['tag'] = sanitize_text_field( $input['tag'] );
				}

				if ( isset( $input['author_id'] ) ) {
					$args['author'] = absint( $input['author_id'] );
				}

				if ( isset( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( $input['search'] );
				}

				$query = new \WP_Query( $args );
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
			'description'         => __( 'Delete post', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to delete' ),
					'force'   => array( 'type' => 'boolean', 'description' => 'Force delete (bypass trash)' ),
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
				$post_id = absint( $input['post_id'] );
				$post    = MCP_WP_Ability_Helpers::get_post_object( $post_id );

				if ( ! $post ) {
					return array(
						'success' => false,
						'error'   => 'Post not found',
					);
				}

				$force  = isset( $input['force'] ) ? (bool) $input['force'] : false;
				$result = wp_delete_post( $post_id, $force );

				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete post',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Post permanently deleted' : 'Post moved to trash',
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
 * Register all post/page abilities
 */
function mcp_wp_register_posts_abilities() {
	mcp_wp_register_create_page();
	mcp_wp_register_edit_page();
	mcp_wp_register_get_page();
	mcp_wp_register_list_pages();
	mcp_wp_register_delete_page();
	mcp_wp_register_create_post();
	mcp_wp_register_edit_post();
	mcp_wp_register_get_post();
	mcp_wp_register_list_posts();
	mcp_wp_register_delete_post();
}
