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
 * Register all settings abilities
 */
function mcp_wp_register_settings_abilities() {
	mcp_wp_register_get_settings();
	mcp_wp_register_get_gutenberg_settings();
	mcp_wp_register_get_site_stats();
}
