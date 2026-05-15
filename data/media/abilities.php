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

				$filetype = wp_check_filetype( $filename );

				$attachment = array(
					'post_mime_type' => isset( $filetype['type'] ) && $filetype['type'] ? $filetype['type'] : 'application/octet-stream',
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
							'url'      => wp_get_attachment_url( $post->ID ),
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
						'url'      => wp_get_attachment_url( $attachment->ID ),
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
 * Format media attachment response.
 *
 * @param \WP_Post $attachment Attachment post.
 * @return array<string,mixed>
 */
function mcp_wp_format_media_attachment_response( \WP_Post $attachment ): array {
	$attachment_id = (int) $attachment->ID;
	$metadata      = wp_get_attachment_metadata( $attachment_id );

	return array(
		'id'          => $attachment_id,
		'title'       => (string) $attachment->post_title,
		'caption'     => (string) $attachment->post_excerpt,
		'description' => (string) $attachment->post_content,
		'alt_text'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'filename'    => basename( (string) $attachment->guid ),
		'url'         => (string) wp_get_attachment_url( $attachment_id ),
		'type'        => (string) $attachment->post_mime_type,
		'date'        => (string) $attachment->post_date_gmt,
		'width'       => $metadata['width'] ?? null,
		'height'      => $metadata['height'] ?? null,
		'sizes'       => $metadata['sizes'] ?? array(),
	);
}

/**
 * 35. Edit Media
 */
function mcp_wp_register_edit_media() {
	wp_register_ability(
		'mcp-wp/edit-media',
		array(
			'label'               => __( 'Edit Media', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update media metadata such as title, caption, description and alt text', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
					'title'         => array( 'type' => 'string', 'description' => 'Media title' ),
					'caption'       => array( 'type' => 'string', 'description' => 'Media caption' ),
					'description'   => array( 'type' => 'string', 'description' => 'Media description' ),
					'alt_text'      => array( 'type' => 'string', 'description' => 'Image alt text' ),
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
				$attachment_id = absint( $input['attachment_id'] ?? 0 );
				$attachment    = get_post( $attachment_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media not found.',
					);
				}

				$update_data = array(
					'ID' => $attachment_id,
				);

				if ( isset( $input['title'] ) ) {
					$update_data['post_title'] = sanitize_text_field( (string) $input['title'] );
				}
				if ( isset( $input['caption'] ) ) {
					$update_data['post_excerpt'] = sanitize_textarea_field( (string) $input['caption'] );
				}
				if ( isset( $input['description'] ) ) {
					$update_data['post_content'] = sanitize_textarea_field( (string) $input['description'] );
				}

				if ( count( $update_data ) > 1 ) {
					$result = wp_update_post( $update_data );
					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'error'   => $result->get_error_message(),
						);
					}
				}

				if ( isset( $input['alt_text'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt_text'] ) );
				}

				$updated_attachment = get_post( $attachment_id );
				if ( ! $updated_attachment || 'attachment' !== $updated_attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media updated but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_media_attachment_response( $updated_attachment ),
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
 * 36. Replace Media File
 */
function mcp_wp_register_replace_media_file() {
	wp_register_ability(
		'mcp-wp/replace-media-file',
		array(
			'label'               => __( 'Replace Media File', 'mcp-wp-capabilities' ),
			'description'         => __( 'Replace an existing media file binary while keeping attachment ID', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id'  => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
					'filename'       => array( 'type' => 'string', 'description' => 'New filename' ),
					'base64_data'    => array( 'type' => 'string', 'description' => 'Base64 encoded file payload' ),
					'delete_old_file'=> array( 'type' => 'boolean', 'description' => 'Delete old physical file when path changes (default true)' ),
				),
				'required'   => array( 'attachment_id', 'filename', 'base64_data' ),
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
				$attachment_id = absint( $input['attachment_id'] ?? 0 );
				$attachment    = get_post( $attachment_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media not found.',
					);
				}

				$old_file = get_attached_file( $attachment_id );
				if ( ! $old_file || ! file_exists( $old_file ) ) {
					return array(
						'success' => false,
						'error'   => 'Existing media file could not be found.',
					);
				}

				$filename   = sanitize_file_name( (string) $input['filename'] );
				$file_data  = base64_decode( (string) $input['base64_data'], true );
				$delete_old = ! array_key_exists( 'delete_old_file', $input ) || ! empty( $input['delete_old_file'] );

				if ( false === $file_data ) {
					return array(
						'success' => false,
						'error'   => 'Invalid base64 data.',
					);
				}

				$target_dir = dirname( $old_file );
				if ( ! is_dir( $target_dir ) ) {
					wp_mkdir_p( $target_dir );
				}

				$new_file = trailingslashit( $target_dir ) . $filename;
				if ( false === file_put_contents( $new_file, $file_data ) ) {
					return array(
						'success' => false,
						'error'   => 'Failed writing replacement media file.',
					);
				}

				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
				}

				$mime = wp_check_filetype( $filename );
				wp_update_post(
					array(
						'ID'             => $attachment_id,
						'post_mime_type' => (string) ( $mime['type'] ?? $attachment->post_mime_type ),
					)
				);

				update_attached_file( $attachment_id, $new_file );
				$metadata = wp_generate_attachment_metadata( $attachment_id, $new_file );
				if ( is_array( $metadata ) ) {
					wp_update_attachment_metadata( $attachment_id, $metadata );
				}

				if ( $delete_old && $old_file !== $new_file && file_exists( $old_file ) ) {
					@unlink( $old_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}

				$updated_attachment = get_post( $attachment_id );
				if ( ! $updated_attachment || 'attachment' !== $updated_attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media replaced but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => mcp_wp_format_media_attachment_response( $updated_attachment ),
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
 * 37. Delete Media
 */
function mcp_wp_register_delete_media() {
	wp_register_ability(
		'mcp-wp/delete-media',
		array(
			'label'               => __( 'Delete Media', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete or trash media attachment', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
					'force'         => array( 'type' => 'boolean', 'description' => 'Permanently delete (default false; attachment is trashed otherwise)' ),
				),
				'required'   => array( 'attachment_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'upload_files' );
			},
			'execute_callback'    => static function ( array $input ) {
				$attachment_id = absint( $input['attachment_id'] ?? 0 );
				$force         = array_key_exists( 'force', $input ) ? (bool) $input['force'] : false;
				$attachment    = get_post( $attachment_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Media not found.',
					);
				}

				$result = wp_delete_attachment( $attachment_id, $force );
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete media.',
					);
				}

				return array(
					'success' => true,
					'message' => $force ? 'Media deleted permanently.' : 'Media moved to trash.',
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
	mcp_wp_register_edit_media();
	mcp_wp_register_replace_media_file();
	mcp_wp_register_delete_media();
}
