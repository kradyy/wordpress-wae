<?php
/**
 * WordPress Abilities: Generic Custom Post Type CRUD
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restricted post types for generic CRUD endpoint.
 *
 * @return array<int,string>
 */
function mcp_wp_restricted_generic_post_types(): array {
	return array(
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
	);
}

/**
 * Resolve post type from payload.
 *
 * @param array<string,mixed> $input Input payload.
 * @param bool                $required Whether post_type is required.
 * @return string|\WP_Error
 */
function mcp_wp_get_generic_post_type_from_input( array $input, bool $required = true ) {
	$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : '';

	if ( '' === $post_type ) {
		if ( $required ) {
			return new \WP_Error( 'missing_post_type', 'post_type is required.' );
		}
		return '';
	}

	if ( ! post_type_exists( $post_type ) ) {
		return new \WP_Error( 'invalid_post_type', sprintf( 'Post type "%s" does not exist.', $post_type ) );
	}

	if ( in_array( $post_type, mcp_wp_restricted_generic_post_types(), true ) ) {
		return new \WP_Error( 'restricted_post_type', sprintf( 'Post type "%s" is not allowed for generic CRUD.', $post_type ) );
	}

	return $post_type;
}

/**
 * Check generic post type capability.
 *
 * @param string $post_type Post type key.
 * @param string $capability_key Capability key in post type cap object.
 * @return bool|\WP_Error
 */
function mcp_wp_check_generic_post_type_capability( string $post_type, string $capability_key ) {
	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object || ! isset( $post_type_object->cap ) ) {
		return new \WP_Error( 'post_type_capability_missing', 'Could not resolve post type capabilities.' );
	}

	$capability = isset( $post_type_object->cap->$capability_key ) ? (string) $post_type_object->cap->$capability_key : 'edit_posts';
	return MCP_WP_Ability_Helpers::check_user_capability( $capability );
}

/**
 * Format generic post response.
 *
 * @param \WP_Post $post Post object.
 * @param bool     $include_content Whether to include content.
 * @param bool     $include_meta Whether to include post meta.
 * @return array<string,mixed>
 */
function mcp_wp_format_generic_post_response( \WP_Post $post, bool $include_content = true, bool $include_meta = false ): array {
	$data = array(
		'id'         => (int) $post->ID,
		'post_type'  => (string) $post->post_type,
		'status'     => (string) $post->post_status,
		'title'      => (string) $post->post_title,
		'slug'       => (string) $post->post_name,
		'excerpt'    => (string) $post->post_excerpt,
		'author_id'  => (int) $post->post_author,
		'parent_id'  => (int) $post->post_parent,
		'date'       => (string) $post->post_date_gmt,
		'modified'   => (string) $post->post_modified_gmt,
		'url'        => get_permalink( $post->ID ),
	);

	if ( $include_content ) {
		$data['content'] = (string) $post->post_content;
	}

	$post_type_object = get_post_type_object( $post->post_type );
	if ( $post_type_object && is_array( $post_type_object->taxonomies ) && ! empty( $post_type_object->taxonomies ) ) {
		$taxonomies_data = array();
		foreach ( $post_type_object->taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$taxonomies_data[ $taxonomy ] = array_map(
				static function ( $term ) {
					return array(
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
						'slug' => (string) $term->slug,
					);
				},
				$terms
			);
		}
		$data['taxonomies'] = $taxonomies_data;
	}

	if ( $include_meta ) {
		$meta = get_post_meta( $post->ID );
		if ( is_array( $meta ) ) {
			$data['meta'] = $meta;
		}
	}

	return $data;
}

/**
 * Apply generic post meta updates.
 *
 * @param int                $post_id Post ID.
 * @param array<string,mixed> $meta Meta object.
 * @return true|\WP_Error
 */
function mcp_wp_apply_generic_post_meta_updates( int $post_id, array $meta ) {
	foreach ( $meta as $raw_key => $raw_value ) {
		$key = sanitize_key( (string) $raw_key );
		if ( '' === $key ) {
			continue;
		}

		if ( null === $raw_value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		update_post_meta( $post_id, $key, $raw_value );
	}

	return true;
}

/**
 * Apply taxonomy terms for generic post.
 *
 * @param int                 $post_id Post ID.
 * @param string              $post_type Post type.
 * @param array<string,mixed> $taxonomy_terms Taxonomy terms payload.
 * @return true|\WP_Error
 */
function mcp_wp_apply_generic_post_taxonomy_terms( int $post_id, string $post_type, array $taxonomy_terms ) {
	foreach ( $taxonomy_terms as $raw_taxonomy => $raw_terms ) {
		$taxonomy = sanitize_key( (string) $raw_taxonomy );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'invalid_taxonomy', sprintf( 'Taxonomy "%s" does not exist.', (string) $raw_taxonomy ) );
		}

		if ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return new \WP_Error( 'taxonomy_not_assigned', sprintf( 'Taxonomy "%s" is not assigned to post type "%s".', $taxonomy, $post_type ) );
		}

		if ( ! is_array( $raw_terms ) ) {
			return new \WP_Error( 'invalid_taxonomy_terms', sprintf( 'Taxonomy terms for "%s" must be an array.', $taxonomy ) );
		}

		$terms = array();
		foreach ( $raw_terms as $term_value ) {
			if ( is_scalar( $term_value ) ) {
				$terms[] = (string) $term_value;
			}
		}

		$result = wp_set_object_terms( $post_id, $terms, $taxonomy, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

/**
 * Register create-content ability.
 */
function mcp_wp_register_create_content() {
	wp_register_ability(
		'mcp-wp/create-content',
		array(
			'label'               => __( 'Create Content (Generic CPT)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create content for any allowed post type', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_type'      => array( 'type' => 'string', 'description' => 'Post type key' ),
					'title'          => array( 'type' => 'string', 'description' => 'Title' ),
					'content'        => array( 'type' => 'string', 'description' => 'Content (HTML/blocks)' ),
					'excerpt'        => array( 'type' => 'string', 'description' => 'Excerpt' ),
					'status'         => array( 'type' => 'string', 'description' => 'Post status' ),
					'slug'           => array( 'type' => 'string', 'description' => 'Slug' ),
					'parent_id'      => array( 'type' => 'integer', 'description' => 'Parent post ID' ),
					'author_id'      => array( 'type' => 'integer', 'description' => 'Author ID' ),
					'featured_image' => array( 'type' => 'integer', 'description' => 'Featured image attachment ID' ),
					'meta'           => array( 'type' => 'object', 'description' => 'Meta key/value object' ),
					'taxonomy_terms' => array( 'type' => 'object', 'description' => 'Object: taxonomy => terms[]' ),
				),
				'required'   => array( 'post_type' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'post_id' => array( 'type' => 'integer' ),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function ( $input = array() ) {
				$payload   = is_array( $input ) ? $input : array();
				$post_type = mcp_wp_get_generic_post_type_from_input( $payload, true );
				if ( is_wp_error( $post_type ) ) {
					return $post_type;
				}
				return mcp_wp_check_generic_post_type_capability( $post_type, 'create_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_type = mcp_wp_get_generic_post_type_from_input( $input, true );
				if ( is_wp_error( $post_type ) ) {
					return array(
						'success' => false,
						'error'   => $post_type->get_error_message(),
					);
				}

				$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';
				if ( ! get_post_status_object( $status ) ) {
					$status = 'draft';
				}

				$post_data = array(
					'post_type'   => $post_type,
					'post_status' => $status,
					'post_title'  => isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '',
					'post_content'=> isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '',
					'post_excerpt'=> isset( $input['excerpt'] ) ? sanitize_textarea_field( (string) $input['excerpt'] ) : '',
				);

				if ( isset( $input['slug'] ) ) {
					$post_data['post_name'] = sanitize_title( (string) $input['slug'] );
				}
				if ( isset( $input['parent_id'] ) ) {
					$post_data['post_parent'] = absint( $input['parent_id'] );
				}
				if ( isset( $input['author_id'] ) ) {
					$post_data['post_author'] = absint( $input['author_id'] );
				}

				$post_id = wp_insert_post( $post_data );
				if ( is_wp_error( $post_id ) ) {
					return array(
						'success' => false,
						'error'   => $post_id->get_error_message(),
					);
				}

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( (int) $post_id, absint( $input['featured_image'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					$meta_result = mcp_wp_apply_generic_post_meta_updates( (int) $post_id, $input['meta'] );
					if ( is_wp_error( $meta_result ) ) {
						return array(
							'success' => false,
							'error'   => $meta_result->get_error_message(),
						);
					}
				}

				if ( isset( $input['taxonomy_terms'] ) && is_array( $input['taxonomy_terms'] ) ) {
					$terms_result = mcp_wp_apply_generic_post_taxonomy_terms( (int) $post_id, $post_type, $input['taxonomy_terms'] );
					if ( is_wp_error( $terms_result ) ) {
						return array(
							'success' => false,
							'error'   => $terms_result->get_error_message(),
						);
					}
				}

				$post = get_post( (int) $post_id );
				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Content was created but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'post_id' => (int) $post_id,
					'data'    => mcp_wp_format_generic_post_response( $post, true, ! empty( $input['meta'] ) ),
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
 * Register get-content ability.
 */
function mcp_wp_register_get_content() {
	wp_register_ability(
		'mcp-wp/get-content',
		array(
			'label'               => __( 'Get Content (Generic CPT)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get content item by ID for any post type', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'       => array( 'type' => 'integer', 'description' => 'Post ID' ),
					'post_type'     => array( 'type' => 'string', 'description' => 'Expected post type (optional)' ),
					'include_meta'  => array( 'type' => 'boolean', 'description' => 'Include post meta' ),
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
				$post_id = absint( $input['post_id'] ?? 0 );
				$post    = get_post( $post_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Content not found.',
					);
				}

				$expected_type = mcp_wp_get_generic_post_type_from_input( $input, false );
				if ( is_wp_error( $expected_type ) ) {
					return array(
						'success' => false,
						'error'   => $expected_type->get_error_message(),
					);
				}

				if ( '' !== $expected_type && $expected_type !== $post->post_type ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post ID %d is type "%s", expected "%s".', $post_id, $post->post_type, $expected_type ),
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_generic_post_response(
						$post,
						true,
						! empty( $input['include_meta'] )
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
 * Register list-content ability.
 */
function mcp_wp_register_list_content() {
	wp_register_ability(
		'mcp-wp/list-content',
		array(
			'label'               => __( 'List Content (Generic CPT)', 'mcp-wp-capabilities' ),
			'description'         => __( 'List content for any allowed post type', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_type'      => array( 'type' => 'string', 'description' => 'Post type key' ),
					'status'         => array( 'type' => 'string', 'description' => 'Status filter' ),
					'search'         => array( 'type' => 'string', 'description' => 'Search query' ),
					'author_id'      => array( 'type' => 'integer', 'description' => 'Filter by author ID' ),
					'parent_id'      => array( 'type' => 'integer', 'description' => 'Filter by parent ID' ),
					'orderby'        => array( 'type' => 'string', 'description' => 'Order by field' ),
					'order'          => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
					'per_page'       => array( 'type' => 'integer', 'description' => 'Number to return (default 20, max 100)' ),
					'page'           => array( 'type' => 'integer', 'description' => 'Page number (default 1)' ),
					'include_content'=> array( 'type' => 'boolean', 'description' => 'Include content in each item' ),
				),
				'required'   => array( 'post_type' ),
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
				$post_type = mcp_wp_get_generic_post_type_from_input( $input, true );
				if ( is_wp_error( $post_type ) ) {
					return array(
						'success' => false,
						'error'   => $post_type->get_error_message(),
					);
				}

				$per_page = min( max( absint( $input['per_page'] ?? 20 ), 1 ), 100 );
				$page     = max( absint( $input['page'] ?? 1 ), 1 );

				$args = array(
					'post_type'      => $post_type,
					'post_status'    => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'orderby'        => isset( $input['orderby'] ) ? sanitize_key( (string) $input['orderby'] ) : 'date',
					'order'          => isset( $input['order'] ) ? sanitize_key( (string) $input['order'] ) : 'DESC',
				);

				if ( isset( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( (string) $input['search'] );
				}
				if ( isset( $input['author_id'] ) ) {
					$args['author'] = absint( $input['author_id'] );
				}
				if ( isset( $input['parent_id'] ) ) {
					$args['post_parent'] = absint( $input['parent_id'] );
				}

				$query = new \WP_Query( $args );
				$items = array_map(
					static function ( $post ) use ( $input ) {
						return mcp_wp_format_generic_post_response(
							$post,
							! empty( $input['include_content'] ),
							false
						);
					},
					$query->get_posts()
				);

				return array(
					'success' => true,
					'data'    => $items,
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
 * Register edit-content ability.
 */
function mcp_wp_register_edit_content() {
	wp_register_ability(
		'mcp-wp/edit-content',
		array(
			'label'               => __( 'Edit Content (Generic CPT)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Edit content item for any allowed post type', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'         => array( 'type' => 'integer', 'description' => 'Post ID' ),
					'post_type'       => array( 'type' => 'string', 'description' => 'Expected post type (optional)' ),
					'title'           => array( 'type' => 'string', 'description' => 'Title' ),
					'content'         => array( 'type' => 'string', 'description' => 'Content' ),
					'excerpt'         => array( 'type' => 'string', 'description' => 'Excerpt' ),
					'status'          => array( 'type' => 'string', 'description' => 'Status' ),
					'slug'            => array( 'type' => 'string', 'description' => 'Slug' ),
					'parent_id'       => array( 'type' => 'integer', 'description' => 'Parent ID' ),
					'author_id'       => array( 'type' => 'integer', 'description' => 'Author ID' ),
					'featured_image'  => array( 'type' => 'integer', 'description' => 'Featured image attachment ID' ),
					'meta'            => array( 'type' => 'object', 'description' => 'Meta key/value object' ),
					'taxonomy_terms'  => array( 'type' => 'object', 'description' => 'Object: taxonomy => terms[]' ),
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
				$post_id = absint( $input['post_id'] ?? 0 );
				$post    = get_post( $post_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Content not found.',
					);
				}

				$expected_type = mcp_wp_get_generic_post_type_from_input( $input, false );
				if ( is_wp_error( $expected_type ) ) {
					return array(
						'success' => false,
						'error'   => $expected_type->get_error_message(),
					);
				}
				if ( '' !== $expected_type && $expected_type !== $post->post_type ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post ID %d is type "%s", expected "%s".', $post_id, $post->post_type, $expected_type ),
					);
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return array(
						'success' => false,
						'error'   => 'Current user cannot edit this post.',
					);
				}

				$update_data = array( 'ID' => $post_id );

				if ( isset( $input['title'] ) ) {
					$update_data['post_title'] = sanitize_text_field( (string) $input['title'] );
				}
				if ( isset( $input['content'] ) ) {
					$update_data['post_content'] = wp_kses_post( (string) $input['content'] );
				}
				if ( isset( $input['excerpt'] ) ) {
					$update_data['post_excerpt'] = sanitize_textarea_field( (string) $input['excerpt'] );
				}
				if ( isset( $input['status'] ) ) {
					$status = sanitize_key( (string) $input['status'] );
					if ( get_post_status_object( $status ) ) {
						$update_data['post_status'] = $status;
					}
				}
				if ( isset( $input['slug'] ) ) {
					$update_data['post_name'] = sanitize_title( (string) $input['slug'] );
				}
				if ( isset( $input['parent_id'] ) ) {
					$update_data['post_parent'] = absint( $input['parent_id'] );
				}
				if ( isset( $input['author_id'] ) ) {
					$update_data['post_author'] = absint( $input['author_id'] );
				}

				$result = wp_update_post( $update_data );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				if ( isset( $input['featured_image'] ) ) {
					set_post_thumbnail( $post_id, absint( $input['featured_image'] ) );
				}

				if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
					$meta_result = mcp_wp_apply_generic_post_meta_updates( $post_id, $input['meta'] );
					if ( is_wp_error( $meta_result ) ) {
						return array(
							'success' => false,
							'error'   => $meta_result->get_error_message(),
						);
					}
				}

				if ( isset( $input['taxonomy_terms'] ) && is_array( $input['taxonomy_terms'] ) ) {
					$terms_result = mcp_wp_apply_generic_post_taxonomy_terms( $post_id, (string) $post->post_type, $input['taxonomy_terms'] );
					if ( is_wp_error( $terms_result ) ) {
						return array(
							'success' => false,
							'error'   => $terms_result->get_error_message(),
						);
					}
				}

				$updated_post = get_post( $post_id );
				if ( ! $updated_post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Content updated but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_generic_post_response(
						$updated_post,
						true,
						isset( $input['meta'] ) && is_array( $input['meta'] )
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
 * Register delete-content ability.
 */
function mcp_wp_register_delete_content() {
	wp_register_ability(
		'mcp-wp/delete-content',
		array(
			'label'               => __( 'Delete Content (Generic CPT)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete or trash content item by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
					'force'   => array( 'type' => 'boolean', 'description' => 'Permanently delete (default false)' ),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'delete_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_id = absint( $input['post_id'] ?? 0 );
				$force   = ! empty( $input['force'] );
				$post    = get_post( $post_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Content not found.',
					);
				}

				if ( in_array( $post->post_type, mcp_wp_restricted_generic_post_types(), true ) ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post type "%s" cannot be deleted via generic CRUD.', $post->post_type ),
					);
				}

				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					return array(
						'success' => false,
						'error'   => 'Current user cannot delete this post.',
					);
				}

				$result = $force ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete content.',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Content deleted permanently.' : 'Content moved to trash.',
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
 * Register all generic content abilities.
 */
function mcp_wp_register_cpt_abilities() {
	mcp_wp_register_create_content();
	mcp_wp_register_get_content();
	mcp_wp_register_list_content();
	mcp_wp_register_edit_content();
	mcp_wp_register_delete_content();
}

