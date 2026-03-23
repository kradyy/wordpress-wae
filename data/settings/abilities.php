<?php
/**
 * WordPress Abilities 29-31: Settings & Configuration
 *
 * Defines abilities for managing WordPress settings and configuration
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 29. Get Settings
 */
function mcp_wp_register_get_settings() {
	wp_register_ability(
		'mcp-wp/get-settings',
		array(
			'label'               => __( 'Get Settings', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get WordPress settings', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				return array(
					'success' => true,
					'data'    => array(
						'site_title'           => get_option( 'blogname' ),
						'site_tagline'         => get_option( 'blogdescription' ),
						'site_url'             => get_option( 'siteurl' ),
						'home_url'             => get_option( 'home' ),
						'admin_email'          => get_option( 'admin_email' ),
						'timezone'             => get_option( 'timezone_string' ),
						'date_format'          => get_option( 'date_format' ),
						'time_format'          => get_option( 'time_format' ),
						'posts_per_page'       => get_option( 'posts_per_page' ),
						'pages_per_page'       => get_option( 'posts_per_page_page' ) ?: 10,
						'blog_public'          => get_option( 'blog_public' ),
						'users_can_register'   => get_option( 'users_can_register' ),
						'default_user_role'    => get_option( 'default_role' ),
						'wp_version'           => get_bloginfo( 'version' ),
						'language'             => get_option( 'WPLANG' ),
						'permalink_structure'  => get_option( 'permalink_structure' ),
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
 * 30. Get Gutenberg Settings
 */
function mcp_wp_register_get_gutenberg_settings() {
	wp_register_ability(
		'mcp-wp/get-gutenberg-settings',
		array(
			'label'               => __( 'Get Gutenberg Settings', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get block editor settings', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				$settings = array(
					'can_use_block_editor'    => function_exists( 'gutenberg_can_edit_post_type' ) ? gutenberg_can_edit_post_type( 'post' ) : true,
					'enable_on_posts'         => function_exists( 'gutenberg_can_edit_post_type' ),
					'enable_on_pages'         => function_exists( 'gutenberg_can_edit_post_type' ),
					'block_patterns_enabled'  => function_exists( 'register_block_pattern' ),
					'custom_colors'           => current_theme_supports( 'editor-color-palette' ),
					'custom_font_sizes'       => current_theme_supports( 'editor-font-sizes' ),
					'wide_alignment'          => current_theme_supports( 'align-wide' ),
				);

				if ( function_exists( 'wp_get_global_stylesheet' ) ) {
					$settings['global_styles_enabled'] = true;
				}

				return array(
					'success' => true,
					'data'    => $settings,
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
 * 31. Get Site Stats
 */
function mcp_wp_register_get_site_stats() {
	wp_register_ability(
		'mcp-wp/get-site-stats',
		array(
			'label'               => __( 'Get Site Stats', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get site overview stats', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				return array(
					'success' => true,
					'data'    => MCP_WP_Ability_Helpers::get_site_stats(),
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
 * Get allowlisted options for controlled settings writes.
 *
 * @return array<string,string>
 */
function mcp_wp_get_settings_update_allowlist(): array {
	$allowlist = array(
		'blogname'            => 'sanitize_text_field',
		'blogdescription'     => 'sanitize_text_field',
		'admin_email'         => 'sanitize_email',
		'timezone_string'     => 'sanitize_text_field',
		'date_format'         => 'sanitize_text_field',
		'time_format'         => 'sanitize_text_field',
		'start_of_week'       => 'absint',
		'posts_per_page'      => 'absint',
		'posts_per_rss'       => 'absint',
		'blog_public'         => 'absint',
		'users_can_register'  => 'absint',
		'default_role'        => 'sanitize_key',
		'show_on_front'       => 'sanitize_text_field',
		'page_on_front'       => 'absint',
		'page_for_posts'      => 'absint',
	);

	/**
	 * Filter allowlisted options for mcp-wp/update-settings.
	 *
	 * @param array<string,string> $allowlist option_name => sanitizer callback.
	 */
	$filtered = apply_filters( 'mcp_wp_settings_update_allowlist', $allowlist );

	return is_array( $filtered ) ? $filtered : $allowlist;
}

/**
 * Validate and normalize one allowlisted option value.
 *
 * @param string $option_name Option key.
 * @param mixed  $raw_value Raw value.
 * @param string $sanitizer Sanitizer callback.
 * @return array{ok:bool,value?:mixed,error?:string}
 */
function mcp_wp_validate_allowlisted_setting_value( string $option_name, $raw_value, string $sanitizer ): array {
	if ( ! is_callable( $sanitizer ) ) {
		return array(
			'ok'    => false,
			'error' => sprintf( 'Sanitizer "%s" is not callable for option "%s".', $sanitizer, $option_name ),
		);
	}

	$value = call_user_func( $sanitizer, $raw_value );

	if ( 'admin_email' === $option_name && ( '' === $value || ! is_email( $value ) ) ) {
		return array(
			'ok'    => false,
			'error' => 'admin_email must be a valid email address.',
		);
	}

	if ( 'default_role' === $option_name ) {
		$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		if ( ! isset( $roles[ $value ] ) ) {
			return array(
				'ok'    => false,
				'error' => sprintf( 'default_role "%s" is not a valid editable role.', (string) $value ),
			);
		}
	}

	if ( in_array( $option_name, array( 'show_on_front' ), true ) && ! in_array( $value, array( 'posts', 'page' ), true ) ) {
		return array(
			'ok'    => false,
			'error' => 'show_on_front must be "posts" or "page".',
		);
	}

	return array(
		'ok'    => true,
		'value' => $value,
	);
}

/**
 * 32. Update Settings (Allowlisted)
 */
function mcp_wp_register_update_settings_allowlisted() {
	wp_register_ability(
		'mcp-wp/update-settings',
		array(
			'label'               => __( 'Update Settings (Allowlisted)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update allowlisted WordPress options only', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'settings' => array(
						'type'        => 'object',
						'description' => 'Option updates as key/value pairs (allowlisted only)',
					),
				),
				'required'   => array( 'settings' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'updated'  => array( 'type' => 'object' ),
					'rejected' => array( 'type' => 'object' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				$settings = $input['settings'] ?? null;
				if ( ! is_array( $settings ) ) {
					return array(
						'success' => false,
						'error'   => 'settings must be an object.',
					);
				}

				$allowlist = mcp_wp_get_settings_update_allowlist();
				$updated   = array();
				$rejected  = array();

				foreach ( $settings as $raw_option_name => $raw_value ) {
					$option_name = sanitize_key( (string) $raw_option_name );
					if ( '' === $option_name ) {
						continue;
					}

					if ( ! isset( $allowlist[ $option_name ] ) ) {
						$rejected[ $option_name ] = 'Option is not allowlisted.';
						continue;
					}

					$validation = mcp_wp_validate_allowlisted_setting_value( $option_name, $raw_value, (string) $allowlist[ $option_name ] );
					if ( empty( $validation['ok'] ) ) {
						$rejected[ $option_name ] = isset( $validation['error'] ) ? (string) $validation['error'] : 'Validation failed.';
						continue;
					}

					$value  = $validation['value'];
					$result = update_option( $option_name, $value );

					$updated[ $option_name ] = array(
						'value'      => get_option( $option_name ),
						'changed'    => (bool) $result,
					);
				}

				if ( empty( $updated ) ) {
					return array(
						'success'  => false,
						'error'    => 'No settings were updated.',
						'updated'  => array(),
						'rejected' => $rejected,
					);
				}

				return array(
					'success'  => true,
					'updated'  => $updated,
					'rejected' => $rejected,
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
 * Register all settings abilities
 */
function mcp_wp_register_settings_abilities() {
	mcp_wp_register_get_settings();
	mcp_wp_register_get_gutenberg_settings();
	mcp_wp_register_get_site_stats();
	mcp_wp_register_update_settings_allowlisted();
}
