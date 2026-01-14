<?php
/**
 * WordPress Abilities 35-38: Taxonomy Management
 *
 * Defines abilities for managing WordPress taxonomies (categories and tags)
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Register all taxonomy abilities
 */
function mcp_wp_register_taxonomy_abilities() {
	mcp_wp_register_list_categories();
	mcp_wp_register_list_tags();
	mcp_wp_register_create_category();
	mcp_wp_register_create_tag();
}
