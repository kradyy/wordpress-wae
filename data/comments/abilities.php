<?php
/**
 * WordPress Abilities: Comments CRUD
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format comment for API response.
 *
 * @param \WP_Comment $comment Comment object.
 * @return array<string,mixed>
 */
function mcp_wp_format_comment_response( \WP_Comment $comment ): array {
	return array(
		'id'           => (int) $comment->comment_ID,
		'post_id'      => (int) $comment->comment_post_ID,
		'parent_id'    => (int) $comment->comment_parent,
		'author'       => (string) $comment->comment_author,
		'author_email' => (string) $comment->comment_author_email,
		'author_url'   => (string) $comment->comment_author_url,
		'author_ip'    => (string) $comment->comment_author_IP,
		'user_id'      => (int) $comment->user_id,
		'content'      => (string) $comment->comment_content,
		'status'       => wp_get_comment_status( $comment ),
		'type'         => (string) $comment->comment_type,
		'date'         => (string) $comment->comment_date_gmt,
		'post_title'   => get_the_title( (int) $comment->comment_post_ID ),
		'post_url'     => get_permalink( (int) $comment->comment_post_ID ),
	);
}

/**
 * Resolve comment status for insert/update operations.
 *
 * @param string $status Status input.
 * @return string|int
 */
function mcp_wp_normalize_comment_approved_value( string $status ) {
	$normalized = sanitize_key( $status );
	if ( in_array( $normalized, array( 'approve', 'approved' ), true ) ) {
		return 1;
	}
	if ( in_array( $normalized, array( 'hold', 'pending', 'unapproved' ), true ) ) {
		return 0;
	}
	if ( in_array( $normalized, array( 'spam', 'trash' ), true ) ) {
		return $normalized;
	}

	return 1;
}

/**
 * Register list-comments ability.
 */
function mcp_wp_register_list_comments() {
	wp_register_ability(
		'mcp-wp/list-comments',
		array(
			'label'               => __( 'List Comments', 'mcp-wp-capabilities' ),
			'description'         => __( 'List comments with filters', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer', 'description' => 'Filter by post ID' ),
					'status'    => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash', 'all' ) ),
					'search'    => array( 'type' => 'string', 'description' => 'Search comment content/author' ),
					'orderby'   => array( 'type' => 'string', 'enum' => array( 'comment_date_gmt', 'comment_ID' ) ),
					'order'     => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
					'per_page'  => array( 'type' => 'integer', 'description' => 'Number to return (default 20, max 100)' ),
					'page'      => array( 'type' => 'integer', 'description' => 'Page number (default 1)' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'moderate_comments' );
			},
			'execute_callback'    => static function ( array $input ) {
				$per_page = min( max( absint( $input['per_page'] ?? 20 ), 1 ), 100 );
				$page     = max( absint( $input['page'] ?? 1 ), 1 );
				$offset   = ( $page - 1 ) * $per_page;

				$query_args = array(
					'number'  => $per_page,
					'offset'  => $offset,
					'orderby' => isset( $input['orderby'] ) ? sanitize_key( (string) $input['orderby'] ) : 'comment_date_gmt',
					'order'   => isset( $input['order'] ) ? sanitize_key( (string) $input['order'] ) : 'DESC',
				);

				if ( isset( $input['post_id'] ) ) {
					$query_args['post_id'] = absint( $input['post_id'] );
				}

				if ( isset( $input['status'] ) ) {
					$status = sanitize_key( (string) $input['status'] );
					if ( 'all' !== $status ) {
						$query_args['status'] = $status;
					}
				}

				if ( isset( $input['search'] ) ) {
					$query_args['search'] = sanitize_text_field( (string) $input['search'] );
				}

				$comments = get_comments( $query_args );
				if ( ! is_array( $comments ) ) {
					$comments = array();
				}

				$count_args = $query_args;
				unset( $count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
				$count_args['count'] = true;
				$total               = (int) get_comments( $count_args );

				$data = array_map(
					static function ( $comment ) {
						return mcp_wp_format_comment_response( $comment );
					},
					$comments
				);

				return array(
					'success' => true,
					'data'    => $data,
					'total'   => $total,
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
 * Register get-comment ability.
 */
function mcp_wp_register_get_comment() {
	wp_register_ability(
		'mcp-wp/get-comment',
		array(
			'label'               => __( 'Get Comment', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get a comment by ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID' ),
				),
				'required'   => array( 'comment_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'moderate_comments' );
			},
			'execute_callback'    => static function ( array $input ) {
				$comment_id = absint( $input['comment_id'] );
				$comment    = get_comment( $comment_id );

				if ( ! $comment instanceof \WP_Comment ) {
					return array(
						'success' => false,
						'error'   => 'Comment not found.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_comment_response( $comment ),
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
 * Register create-comment ability.
 */
function mcp_wp_register_create_comment() {
	wp_register_ability(
		'mcp-wp/create-comment',
		array(
			'label'               => __( 'Create Comment', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a new comment', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer', 'description' => 'Post ID for comment' ),
					'content'      => array( 'type' => 'string', 'description' => 'Comment content' ),
					'author'       => array( 'type' => 'string', 'description' => 'Author name' ),
					'author_email' => array( 'type' => 'string', 'description' => 'Author email' ),
					'author_url'   => array( 'type' => 'string', 'description' => 'Author URL' ),
					'parent_id'    => array( 'type' => 'integer', 'description' => 'Parent comment ID' ),
					'user_id'      => array( 'type' => 'integer', 'description' => 'User ID' ),
					'status'       => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash' ) ),
				),
				'required'   => array( 'post_id', 'content' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'comment_id' => array( 'type' => 'integer' ),
					'data'       => array( 'type' => 'object' ),
					'error'      => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'moderate_comments' );
			},
			'execute_callback'    => static function ( array $input ) {
				$post_id = absint( $input['post_id'] );
				$post    = get_post( $post_id );

				if ( ! $post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Target post not found.',
					);
				}

				$comment_data = array(
					'comment_post_ID'      => $post_id,
					'comment_content'      => wp_kses_post( (string) $input['content'] ),
					'comment_author'       => sanitize_text_field( (string) ( $input['author'] ?? '' ) ),
					'comment_author_email' => sanitize_email( (string) ( $input['author_email'] ?? '' ) ),
					'comment_author_url'   => esc_url_raw( (string) ( $input['author_url'] ?? '' ) ),
					'comment_parent'       => absint( $input['parent_id'] ?? 0 ),
					'user_id'              => absint( $input['user_id'] ?? 0 ),
					'comment_approved'     => isset( $input['status'] )
						? mcp_wp_normalize_comment_approved_value( (string) $input['status'] )
						: 1,
				);

				$comment_id = wp_insert_comment( $comment_data );
				if ( ! $comment_id ) {
					return array(
						'success' => false,
						'error'   => 'Failed to create comment.',
					);
				}

				$comment = get_comment( $comment_id );
				if ( ! $comment instanceof \WP_Comment ) {
					return array(
						'success' => false,
						'error'   => 'Comment created but could not be loaded.',
					);
				}

				return array(
					'success'    => true,
					'comment_id' => (int) $comment_id,
					'data'       => mcp_wp_format_comment_response( $comment ),
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
 * Register edit-comment ability.
 */
function mcp_wp_register_edit_comment() {
	wp_register_ability(
		'mcp-wp/edit-comment',
		array(
			'label'               => __( 'Edit Comment', 'mcp-wp-capabilities' ),
			'description'         => __( 'Edit an existing comment', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'comment_id'   => array( 'type' => 'integer', 'description' => 'Comment ID' ),
					'content'      => array( 'type' => 'string', 'description' => 'Comment content' ),
					'author'       => array( 'type' => 'string', 'description' => 'Author name' ),
					'author_email' => array( 'type' => 'string', 'description' => 'Author email' ),
					'author_url'   => array( 'type' => 'string', 'description' => 'Author URL' ),
					'parent_id'    => array( 'type' => 'integer', 'description' => 'Parent comment ID' ),
					'status'       => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash' ) ),
				),
				'required'   => array( 'comment_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'moderate_comments' );
			},
			'execute_callback'    => static function ( array $input ) {
				$comment_id = absint( $input['comment_id'] );
				$comment    = get_comment( $comment_id );

				if ( ! $comment instanceof \WP_Comment ) {
					return array(
						'success' => false,
						'error'   => 'Comment not found.',
					);
				}

				$update_data = array(
					'comment_ID' => $comment_id,
				);

				if ( isset( $input['content'] ) ) {
					$update_data['comment_content'] = wp_kses_post( (string) $input['content'] );
				}
				if ( isset( $input['author'] ) ) {
					$update_data['comment_author'] = sanitize_text_field( (string) $input['author'] );
				}
				if ( isset( $input['author_email'] ) ) {
					$update_data['comment_author_email'] = sanitize_email( (string) $input['author_email'] );
				}
				if ( isset( $input['author_url'] ) ) {
					$update_data['comment_author_url'] = esc_url_raw( (string) $input['author_url'] );
				}
				if ( isset( $input['parent_id'] ) ) {
					$update_data['comment_parent'] = absint( $input['parent_id'] );
				}

				$result = wp_update_comment( $update_data );
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to update comment.',
					);
				}

				if ( isset( $input['status'] ) ) {
					$status = sanitize_key( (string) $input['status'] );
					wp_set_comment_status( $comment_id, $status, true );
				}

				$updated_comment = get_comment( $comment_id );
				if ( ! $updated_comment instanceof \WP_Comment ) {
					return array(
						'success' => false,
						'error'   => 'Comment updated but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_comment_response( $updated_comment ),
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
 * Register delete-comment ability.
 */
function mcp_wp_register_delete_comment() {
	wp_register_ability(
		'mcp-wp/delete-comment',
		array(
			'label'               => __( 'Delete Comment', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete or trash a comment', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID' ),
					'force'      => array( 'type' => 'boolean', 'description' => 'Permanently delete (default false; comment is trashed otherwise)' ),
				),
				'required'   => array( 'comment_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'moderate_comments' );
			},
			'execute_callback'    => static function ( array $input ) {
				$comment_id = absint( $input['comment_id'] );
				$force      = array_key_exists( 'force', $input ) ? (bool) $input['force'] : false;
				$comment    = get_comment( $comment_id );

				if ( ! $comment instanceof \WP_Comment ) {
					return array(
						'success' => false,
						'error'   => 'Comment not found.',
					);
				}

				$result = wp_delete_comment( $comment_id, $force );
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete comment.',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Comment deleted permanently.' : 'Comment moved to trash.',
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
 * Register all comments abilities.
 */
function mcp_wp_register_comments_abilities() {
	mcp_wp_register_list_comments();
	mcp_wp_register_get_comment();
	mcp_wp_register_create_comment();
	mcp_wp_register_edit_comment();
	mcp_wp_register_delete_comment();
}

