<?php
/**
 * Helper class for WordPress abilities
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCP_WP_Ability_Helpers
 *
 * Provides utility functions for WordPress abilities
 */
class MCP_WP_Ability_Helpers {

	/**
	 * Check if user has permission to perform action
	 *
	 * @param string $capability The capability to check.
	 * @return bool|\WP_Error True if user has capability, WP_Error otherwise.
	 */
	public static function check_user_capability( string $capability ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'not_authenticated',
				__( 'User must be authenticated', 'mcp-wp-capabilities' )
			);
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is passed dynamically
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'insufficient_capability',
				sprintf(
					__( 'User lacks required capability: %s', 'mcp-wp-capabilities' ),
					$capability
				)
			);
		}

		return true;
	}

	/**
	 * Get page by ID
	 *
	 * @param int $page_id Page ID.
	 * @return \WP_Post|null Post object or null if not found.
	 */
	public static function get_page_object( int $page_id ) {
		$page = get_post( $page_id );

		if ( ! $page || 'page' !== $page->post_type ) {
			return null;
		}

		return $page;
	}

	/**
	 * Get post by ID
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null Post object or null if not found.
	 */
	public static function get_post_object( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Format post/page for API response
	 *
	 * @param \WP_Post $post The post object.
	 * @param bool     $include_content Include full content.
	 * @return array Formatted post data.
	 */
	public static function format_post_response( \WP_Post $post, bool $include_content = true ): array {
		$response = array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'slug'          => $post->post_name,
			'status'        => $post->post_status,
			'type'          => $post->post_type,
			'author_id'     => (int) $post->post_author,
			'date'          => $post->post_date_gmt,
			'modified'      => $post->post_modified_gmt,
			'excerpt'       => $post->post_excerpt,
			'url'           => get_permalink( $post->ID ),
			'parent_id'     => (int) $post->post_parent,
			'featured_image_id' => (int) get_post_thumbnail_id( $post->ID ),
		);

		if ( $include_content ) {
			$response['content'] = $post->post_content;
		}

		return $response;
	}

	/**
	 * Get all Gutenberg patterns
	 *
	 * @return array Array of patterns.
	 */
	public static function get_all_patterns(): array {
		$patterns = array();

		if ( function_exists( 'WP_Block_Patterns_Registry' ) ) {
			$registry = \WP_Block_Patterns_Registry::get_instance();
			$all      = $registry->get_all_registered();

			foreach ( $all as $pattern ) {
				$patterns[] = array(
					'name'        => $pattern['name'] ?? '',
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'content'     => $pattern['content'] ?? '',
					'category'    => $pattern['category'] ?? '',
					'keywords'    => $pattern['keywords'] ?? array(),
				);
			}
		}

		return $patterns;
	}

	/**
	 * Get pattern by name
	 *
	 * @param string $pattern_name Pattern name/slug.
	 * @return array|null Pattern data or null if not found.
	 */
	public static function get_pattern_by_name( string $pattern_name ) {
		if ( function_exists( 'WP_Block_Patterns_Registry' ) ) {
			$registry = \WP_Block_Patterns_Registry::get_instance();
			$pattern  = $registry->get_registered( $pattern_name );

			if ( $pattern ) {
				return array(
					'name'        => $pattern['name'] ?? '',
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'content'     => $pattern['content'] ?? '',
					'category'    => $pattern['category'] ?? '',
					'keywords'    => $pattern['keywords'] ?? array(),
				);
			}
		}

		return null;
	}

	/**
	 * Validate block JSON structure
	 *
	 * @param string $blocks_json Block JSON content.
	 * @return array Array with 'valid' bool and 'errors' array.
	 */
	public static function validate_block_json( string $blocks_json ): array {
		$errors = array();

		// Try to parse JSON
		$blocks = json_decode( $blocks_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$errors[] = sprintf(
				'Invalid JSON: %s',
				json_last_error_msg()
			);
			return array(
				'valid'  => false,
				'errors' => $errors,
			);
		}

		// Ensure blocks is an array
		if ( ! is_array( $blocks ) ) {
			$errors[] = 'Blocks must be an array';
			return array(
				'valid'  => false,
				'errors' => $errors,
			);
		}

		// Validate each block
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				$errors[] = "Block at index {$index} is not an object";
				continue;
			}

			if ( ! isset( $block['blockName'] ) ) {
				$errors[] = "Block at index {$index} missing 'blockName'";
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Get all available block types
	 *
	 * @param string $namespace Optional namespace filter.
	 * @param bool   $include_deprecated Include deprecated blocks.
	 * @return array Array of block types.
	 */
	public static function get_block_types( string $namespace = '', bool $include_deprecated = false ): array {
		$block_types = \WP_Block_Type_Registry::get_instance()->get_registered();
		$result      = array();

		foreach ( $block_types as $block_type ) {
			// Filter by namespace if provided
			if ( $namespace && 0 !== strpos( $block_type->name, $namespace ) ) {
				continue;
			}

			// Filter deprecated if requested
			if ( ! $include_deprecated && isset( $block_type->deprecated ) && $block_type->deprecated ) {
				continue;
			}

			$result[] = array(
				'name'        => $block_type->name,
				'title'       => $block_type->title ?? '',
				'category'    => $block_type->category ?? '',
				'description' => $block_type->description ?? '',
				'icon'        => $block_type->icon ?? null,
				'attributes'  => $block_type->attributes ?? array(),
			);
		}

		return $result;
	}

	/**
	 * Get user data
	 *
	 * @param \WP_User $user User object.
	 * @return array User data.
	 */
	public static function format_user_response( \WP_User $user ): array {
		$roles = $user->roles ?? array();

		return array(
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'roles'        => $roles,
			'registered'   => $user->user_registered,
		);
	}

	/**
	 * Get site statistics
	 *
	 * @return array Site stats.
	 */
	public static function get_site_stats(): array {
		return array(
			'site_title'       => get_bloginfo( 'name' ),
			'site_url'         => home_url(),
			'admin_email'      => get_option( 'admin_email' ),
			'page_count'       => wp_count_posts( 'page' )->publish ?? 0,
			'post_count'       => wp_count_posts( 'post' )->publish ?? 0,
			'user_count'       => count_users()['total_users'] ?? 0,
			'active_plugins'   => count( get_option( 'active_plugins', array() ) ),
			'active_theme'     => wp_get_theme()->get( 'Name' ),
			'wp_version'       => get_bloginfo( 'version' ),
			'php_version'      => phpversion(),
			'timezone'         => wp_timezone_string(),
			'language'         => get_option( 'WPLANG' ),
		);
	}

	/**
	 * Check if string is valid JSON
	 *
	 * @param string $string String to check.
	 * @return bool True if valid JSON.
	 */
	public static function is_json( string $string ): bool {
		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Create MCP response format
	 *
	 * @param bool        $success Success flag.
	 * @param mixed       $data Response data.
	 * @param string|null $message Optional message.
	 * @param string|null $code Optional error code.
	 * @return array Formatted response.
	 */
	public static function create_response(
		bool $success,
		$data = null,
		?string $message = null,
		?string $code = null
	): array {
		$response = array(
			'success' => $success,
		);

		if ( $success ) {
			if ( $data !== null ) {
				$response['data'] = $data;
			}
			if ( $message ) {
				$response['message'] = $message;
			}
		} else {
			$response['error'] = $message ?? 'Unknown error';
			if ( $code ) {
				$response['code'] = $code;
			}
		}

		return $response;
	}
}
