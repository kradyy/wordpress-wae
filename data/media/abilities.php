<?php
/**
 * WordPress Abilities 32-34: Media & Assets Management
 *
 * Defines abilities for managing WordPress media and assets
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Register all media abilities
 */
function mcp_wp_register_media_abilities() {
	mcp_wp_register_upload_media();
	mcp_wp_register_list_media();
	mcp_wp_register_get_media();
}
