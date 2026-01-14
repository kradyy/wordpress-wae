<?php
/**
 * WordPress Abilities 23-28: Plugins & Theme Management
 *
 * Defines abilities for managing WordPress plugins and themes
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 23. List Plugins
 */
function mcp_wp_register_list_plugins() {
	wp_register_ability(
		'mcp-wp/list-plugins',
		array(
			'label'               => __( 'List Plugins', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get installed plugins', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array( 'type' => 'string', 'enum' => array( 'active', 'inactive', 'all' ), 'description' => 'Filter by status' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$all_plugins = get_plugins();
				$active_plugins = get_option( 'active_plugins', array() );
				$status_filter = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'all';

				$plugins = array();
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$is_active = in_array( $plugin_file, $active_plugins, true );

					if ( 'active' === $status_filter && ! $is_active ) {
						continue;
					}
					if ( 'inactive' === $status_filter && $is_active ) {
						continue;
					}

					$plugins[] = array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'] ?? '',
						'version'     => $plugin_data['Version'] ?? '',
						'description' => $plugin_data['Description'] ?? '',
						'author'      => $plugin_data['Author'] ?? '',
						'active'      => $is_active,
						'url'         => $plugin_data['PluginURI'] ?? '',
					);
				}

				return array(
					'success' => true,
					'data'    => $plugins,
					'total'   => count( $plugins ),
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
 * 24. Get Plugin
 */
function mcp_wp_register_get_plugin() {
	wp_register_ability(
		'mcp-wp/get-plugin',
		array(
			'label'               => __( 'Get Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get plugin details', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				$all_plugins = get_plugins();

				if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
					return array(
						'success' => false,
						'error'   => 'Plugin not found',
					);
				}

				$plugin_data = $all_plugins[ $plugin_file ];
				$active_plugins = get_option( 'active_plugins', array() );
				$is_active = in_array( $plugin_file, $active_plugins, true );

				return array(
					'success' => true,
					'data'    => array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'] ?? '',
						'version'     => $plugin_data['Version'] ?? '',
						'description' => $plugin_data['Description'] ?? '',
						'author'      => $plugin_data['Author'] ?? '',
						'active'      => $is_active,
						'url'         => $plugin_data['PluginURI'] ?? '',
						'license'     => $plugin_data['License'] ?? '',
						'requires_wp' => $plugin_data['RequiresWP'] ?? '',
						'requires_php' => $plugin_data['RequiresPHP'] ?? '',
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
 * 25. Activate Plugin
 */
function mcp_wp_register_activate_plugin() {
	wp_register_ability(
		'mcp-wp/activate-plugin',
		array(
			'label'               => __( 'Activate Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Activate plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'message'  => array( 'type' => 'string' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				$result      = activate_plugin( $plugin_file );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'message' => 'Plugin activated successfully',
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
 * 26. Deactivate Plugin
 */
function mcp_wp_register_deactivate_plugin() {
	wp_register_ability(
		'mcp-wp/deactivate-plugin',
		array(
			'label'               => __( 'Deactivate Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Deactivate plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path' ),
				),
				'required'   => array( 'plugin_file' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'message'  => array( 'type' => 'string' ),
					'error'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = sanitize_text_field( $input['plugin_file'] );
				deactivate_plugins( array( $plugin_file ) );

				return array(
					'success' => true,
					'message' => 'Plugin deactivated successfully',
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
 * 27. Get Theme
 */
function mcp_wp_register_get_theme() {
	wp_register_ability(
		'mcp-wp/get-theme',
		array(
			'label'               => __( 'Get Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get theme info', 'mcp-wp-capabilities' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'switch_themes' );
			},
			'execute_callback'    => static function () {
				$theme = wp_get_theme();

				return array(
					'success' => true,
					'data'    => array(
						'name'        => $theme->get( 'Name' ),
						'version'     => $theme->get( 'Version' ),
						'description' => $theme->get( 'Description' ),
						'author'      => $theme->get( 'Author' ),
						'author_uri'  => $theme->get( 'AuthorURI' ),
						'theme_uri'   => $theme->get( 'ThemeURI' ),
						'screenshot'  => $theme->get_screenshot(),
						'stylesheet'  => get_stylesheet(),
						'template'    => get_template(),
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
 * 28. Get Theme Supports
 */
function mcp_wp_register_get_theme_supports() {
	wp_register_ability(
		'mcp-wp/get-theme-supports',
		array(
			'label'               => __( 'Get Theme Supports', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get theme features', 'mcp-wp-capabilities' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'switch_themes' );
			},
			'execute_callback'    => static function () {
				global $_wp_theme_features;

				$features = array(
					'post_thumbnails'       => current_theme_supports( 'post-thumbnails' ),
					'html5'                 => current_theme_supports( 'html5' ),
					'widgets'               => current_theme_supports( 'widgets' ),
					'menus'                 => current_theme_supports( 'menus' ),
					'automatic_feed_links'  => current_theme_supports( 'automatic-feed-links' ),
					'gutenberg'             => current_theme_supports( 'align-wide' ) || current_theme_supports( 'wp-block-styles' ),
					'custom_colors'         => current_theme_supports( 'editor-color-palette' ),
					'custom_fonts'          => current_theme_supports( 'editor-font-sizes' ),
				);

				return array(
					'success' => true,
					'data'    => $features,
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
 * Register all plugin abilities
 */
function mcp_wp_register_plugins_abilities() {
	mcp_wp_register_list_plugins();
	mcp_wp_register_get_plugin();
	mcp_wp_register_activate_plugin();
	mcp_wp_register_deactivate_plugin();
	mcp_wp_register_get_theme();
	mcp_wp_register_get_theme_supports();
}
