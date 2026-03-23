<?php
/**
 * WordPress Abilities: Full Site Editing Entities
 *
 * Provides CRUD for wp_navigation, wp_template and wp_template_part.
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed FSE post types.
 *
 * @return array<int,string>
 */
function mcp_wp_fse_entity_types(): array {
	return array( 'wp_navigation', 'wp_template', 'wp_template_part' );
}

/**
 * Resolve FSE entity type.
 *
 * @param array<string,mixed> $input Input payload.
 * @param bool                $required Whether required.
 * @return string|\WP_Error
 */
function mcp_wp_resolve_fse_entity_type( array $input, bool $required = true ) {
	$entity_type = isset( $input['entity_type'] ) ? sanitize_key( (string) $input['entity_type'] ) : '';

	if ( '' === $entity_type ) {
		if ( $required ) {
			return new \WP_Error( 'missing_entity_type', 'entity_type is required.' );
		}
		return '';
	}

	if ( ! in_array( $entity_type, mcp_wp_fse_entity_types(), true ) ) {
		return new \WP_Error(
			'invalid_entity_type',
			sprintf(
				'Invalid entity_type "%s". Allowed: %s',
				$entity_type,
				implode( ', ', mcp_wp_fse_entity_types() )
			)
		);
	}

	return $entity_type;
}

/**
 * Ensure FSE entity has current theme relation where relevant.
 *
 * @param int    $post_id Entity post ID.
 * @param string $entity_type Entity type.
 * @return void
 */
function mcp_wp_maybe_assign_fse_theme_term( int $post_id, string $entity_type ): void {
	if ( ! in_array( $entity_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
		return;
	}
	if ( ! taxonomy_exists( 'wp_theme' ) ) {
		return;
	}

	$theme_slug = get_stylesheet();
	if ( '' === $theme_slug ) {
		return;
	}

	wp_set_object_terms( $post_id, array( $theme_slug ), 'wp_theme', false );
}

/**
 * Format FSE entity response.
 *
 * @param \WP_Post $post Entity post.
 * @param bool     $include_content Whether to include content.
 * @return array<string,mixed>
 */
function mcp_wp_format_fse_entity_response( \WP_Post $post, bool $include_content = true ): array {
	$data = array(
		'id'          => (int) $post->ID,
		'entity_type' => (string) $post->post_type,
		'title'       => (string) $post->post_title,
		'slug'        => (string) $post->post_name,
		'status'      => (string) $post->post_status,
		'description' => (string) $post->post_excerpt,
		'author_id'   => (int) $post->post_author,
		'date'        => (string) $post->post_date_gmt,
		'modified'    => (string) $post->post_modified_gmt,
	);

	if ( $include_content ) {
		$data['content'] = (string) $post->post_content;
	}

	if ( taxonomy_exists( 'wp_theme' ) ) {
		$theme_terms = wp_get_object_terms( $post->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $theme_terms ) ) {
			$data['theme_terms'] = array_values(
				array_filter(
					array_map(
						static function ( $term_slug ) {
							return is_scalar( $term_slug ) ? (string) $term_slug : '';
						},
						$theme_terms
					)
				)
			);
		}
	}

	return $data;
}

/**
 * Register list-block-entities ability.
 */
function mcp_wp_register_list_block_entities() {
	wp_register_ability(
		'mcp-wp/list-block-entities',
		array(
			'label'               => __( 'List Block Entities', 'mcp-wp-capabilities' ),
			'description'         => __( 'List wp_navigation/wp_template/wp_template_part entities', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entity_type'     => array( 'type' => 'string', 'description' => 'Optional entity type' ),
					'status'          => array( 'type' => 'string', 'description' => 'Post status filter' ),
					'search'          => array( 'type' => 'string', 'description' => 'Search query' ),
					'per_page'        => array( 'type' => 'integer', 'description' => 'Number to return (default 20, max 100)' ),
					'page'            => array( 'type' => 'integer', 'description' => 'Page number' ),
					'include_content' => array( 'type' => 'boolean', 'description' => 'Include content in each result' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_theme_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$entity_type = mcp_wp_resolve_fse_entity_type( $input, false );
				if ( is_wp_error( $entity_type ) ) {
					return array(
						'success' => false,
						'error'   => $entity_type->get_error_message(),
					);
				}

				$post_type = '' !== $entity_type ? $entity_type : mcp_wp_fse_entity_types();
				$per_page  = min( max( absint( $input['per_page'] ?? 20 ), 1 ), 100 );
				$page      = max( absint( $input['page'] ?? 1 ), 1 );

				$args = array(
					'post_type'      => $post_type,
					'post_status'    => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( isset( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( (string) $input['search'] );
				}

				$query = new \WP_Query( $args );
				$data  = array_map(
					static function ( $post ) use ( $input ) {
						return mcp_wp_format_fse_entity_response( $post, ! empty( $input['include_content'] ) );
					},
					$query->get_posts()
				);

				return array(
					'success' => true,
					'data'    => $data,
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
 * Register get-block-entity ability.
 */
function mcp_wp_register_get_block_entity() {
	wp_register_ability(
		'mcp-wp/get-block-entity',
		array(
			'label'               => __( 'Get Block Entity', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get one block entity by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entity_id'       => array( 'type' => 'integer', 'description' => 'Entity post ID' ),
					'entity_type'     => array( 'type' => 'string', 'description' => 'Expected entity type' ),
					'include_content' => array( 'type' => 'boolean', 'description' => 'Include entity content (default true)' ),
				),
				'required'   => array( 'entity_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_theme_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$entity_id = absint( $input['entity_id'] ?? 0 );
				$post      = get_post( $entity_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Entity not found.',
					);
				}

				if ( ! in_array( $post->post_type, mcp_wp_fse_entity_types(), true ) ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post ID %d is type "%s", not an FSE entity.', $entity_id, $post->post_type ),
					);
				}

				$expected_type = mcp_wp_resolve_fse_entity_type( $input, false );
				if ( is_wp_error( $expected_type ) ) {
					return array(
						'success' => false,
						'error'   => $expected_type->get_error_message(),
					);
				}

				if ( '' !== $expected_type && $expected_type !== $post->post_type ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Entity type mismatch. Got "%s", expected "%s".', $post->post_type, $expected_type ),
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_fse_entity_response(
						$post,
						! isset( $input['include_content'] ) || ! empty( $input['include_content'] )
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
 * Register create-block-entity ability.
 */
function mcp_wp_register_create_block_entity() {
	wp_register_ability(
		'mcp-wp/create-block-entity',
		array(
			'label'               => __( 'Create Block Entity', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create wp_navigation/wp_template/wp_template_part entity', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entity_type' => array( 'type' => 'string', 'description' => 'Entity type' ),
					'title'       => array( 'type' => 'string', 'description' => 'Entity title' ),
					'content'     => array( 'type' => 'string', 'description' => 'Entity block content' ),
					'status'      => array( 'type' => 'string', 'description' => 'Entity status' ),
					'slug'        => array( 'type' => 'string', 'description' => 'Entity slug' ),
					'description' => array( 'type' => 'string', 'description' => 'Entity description' ),
				),
				'required'   => array( 'entity_type' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'entity_id' => array( 'type' => 'integer' ),
					'data'      => array( 'type' => 'object' ),
					'error'     => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_theme_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$entity_type = mcp_wp_resolve_fse_entity_type( $input, true );
				if ( is_wp_error( $entity_type ) ) {
					return array(
						'success' => false,
						'error'   => $entity_type->get_error_message(),
					);
				}

				$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';
				if ( ! get_post_status_object( $status ) ) {
					$status = 'draft';
				}

				$post_data = array(
					'post_type'    => $entity_type,
					'post_status'  => $status,
					'post_title'   => isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : ucfirst( str_replace( 'wp_', '', $entity_type ) ),
					'post_content' => isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '',
					'post_excerpt' => isset( $input['description'] ) ? sanitize_textarea_field( (string) $input['description'] ) : '',
				);

				if ( isset( $input['slug'] ) ) {
					$post_data['post_name'] = sanitize_title( (string) $input['slug'] );
				}

				$entity_id = wp_insert_post( $post_data );
				if ( is_wp_error( $entity_id ) ) {
					return array(
						'success' => false,
						'error'   => $entity_id->get_error_message(),
					);
				}

				mcp_wp_maybe_assign_fse_theme_term( (int) $entity_id, $entity_type );

				$post = get_post( (int) $entity_id );
				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Entity created but could not be loaded.',
					);
				}

				return array(
					'success'   => true,
					'entity_id' => (int) $entity_id,
					'data'      => mcp_wp_format_fse_entity_response( $post, true ),
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
 * Register edit-block-entity ability.
 */
function mcp_wp_register_edit_block_entity() {
	wp_register_ability(
		'mcp-wp/edit-block-entity',
		array(
			'label'               => __( 'Edit Block Entity', 'mcp-wp-capabilities' ),
			'description'         => __( 'Edit wp_navigation/wp_template/wp_template_part entity', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entity_id'   => array( 'type' => 'integer', 'description' => 'Entity post ID' ),
					'title'       => array( 'type' => 'string', 'description' => 'Entity title' ),
					'content'     => array( 'type' => 'string', 'description' => 'Entity block content' ),
					'status'      => array( 'type' => 'string', 'description' => 'Entity status' ),
					'slug'        => array( 'type' => 'string', 'description' => 'Entity slug' ),
					'description' => array( 'type' => 'string', 'description' => 'Entity description' ),
				),
				'required'   => array( 'entity_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_theme_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$entity_id = absint( $input['entity_id'] ?? 0 );
				$post      = get_post( $entity_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Entity not found.',
					);
				}

				if ( ! in_array( $post->post_type, mcp_wp_fse_entity_types(), true ) ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post ID %d is type "%s", not an FSE entity.', $entity_id, $post->post_type ),
					);
				}

				if ( ! current_user_can( 'edit_post', $entity_id ) ) {
					return array(
						'success' => false,
						'error'   => 'Current user cannot edit this entity.',
					);
				}

				$update_data = array( 'ID' => $entity_id );

				if ( isset( $input['title'] ) ) {
					$update_data['post_title'] = sanitize_text_field( (string) $input['title'] );
				}
				if ( isset( $input['content'] ) ) {
					$update_data['post_content'] = wp_kses_post( (string) $input['content'] );
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
				if ( isset( $input['description'] ) ) {
					$update_data['post_excerpt'] = sanitize_textarea_field( (string) $input['description'] );
				}

				$result = wp_update_post( $update_data );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				mcp_wp_maybe_assign_fse_theme_term( $entity_id, (string) $post->post_type );

				$updated = get_post( $entity_id );
				if ( ! $updated instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Entity updated but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_fse_entity_response( $updated, true ),
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
 * Register delete-block-entity ability.
 */
function mcp_wp_register_delete_block_entity() {
	wp_register_ability(
		'mcp-wp/delete-block-entity',
		array(
			'label'               => __( 'Delete Block Entity', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete or trash an FSE entity', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entity_id' => array( 'type' => 'integer', 'description' => 'Entity post ID' ),
					'force'     => array( 'type' => 'boolean', 'description' => 'Permanently delete (default false)' ),
				),
				'required'   => array( 'entity_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_theme_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$entity_id = absint( $input['entity_id'] ?? 0 );
				$force     = ! empty( $input['force'] );
				$post      = get_post( $entity_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Entity not found.',
					);
				}

				if ( ! in_array( $post->post_type, mcp_wp_fse_entity_types(), true ) ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Post ID %d is type "%s", not an FSE entity.', $entity_id, $post->post_type ),
					);
				}

				if ( ! current_user_can( 'delete_post', $entity_id ) ) {
					return array(
						'success' => false,
						'error'   => 'Current user cannot delete this entity.',
					);
				}

				$result = $force ? wp_delete_post( $entity_id, true ) : wp_trash_post( $entity_id );
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete entity.',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Entity deleted permanently.' : 'Entity moved to trash.',
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
 * Register all FSE abilities.
 */
function mcp_wp_register_fse_abilities() {
	mcp_wp_register_list_block_entities();
	mcp_wp_register_get_block_entity();
	mcp_wp_register_create_block_entity();
	mcp_wp_register_edit_block_entity();
	mcp_wp_register_delete_block_entity();
}

