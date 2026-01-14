<?php
/**
 * WordPress Abilities 39-45: Advanced Features
 *
 * Defines advanced capabilities for REST calls, batch operations, and pattern management
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Register all advanced abilities
 */
function mcp_wp_register_advanced_abilities() {
	mcp_wp_register_custom_rest_call();
	mcp_wp_register_query_posts_advanced();
	mcp_wp_register_batch_update();
	mcp_wp_register_export_pattern();
	mcp_wp_register_import_pattern();
	mcp_wp_register_get_pattern_usage();
	mcp_wp_register_clone_item();
}
