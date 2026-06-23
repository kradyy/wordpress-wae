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
 * Ensure WordPress admin dependencies for plugin lifecycle are loaded.
 */
function mcp_wp_require_plugin_lifecycle_dependencies() {
	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'activate_plugin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'plugins_api' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}
	if ( ! class_exists( 'Plugin_Upgrader' ) || ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}

/**
 * Ensure WordPress admin dependencies for theme lifecycle are loaded.
 */
function mcp_wp_require_theme_lifecycle_dependencies() {
	if ( ! function_exists( 'delete_theme' ) ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';
	}
	if ( ! function_exists( 'themes_api' ) ) {
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
	}
	if ( ! class_exists( 'Theme_Upgrader' ) || ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}

/**
 * Try to resolve a plugin file path from a plugin slug.
 *
 * @param string $plugin_slug Plugin slug.
 * @return string
 */
function mcp_wp_find_plugin_file_by_slug( string $plugin_slug ): string {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_slug = sanitize_title( $plugin_slug );
	$plugins     = get_plugins();

	foreach ( $plugins as $plugin_file => $plugin_data ) {
		$plugin_dir = dirname( $plugin_file );
		if ( $plugin_dir === $plugin_slug || str_starts_with( $plugin_file, $plugin_slug . '/' ) ) {
			return (string) $plugin_file;
		}

		$text_domain = isset( $plugin_data['TextDomain'] ) ? sanitize_title( (string) $plugin_data['TextDomain'] ) : '';
		if ( '' !== $text_domain && $text_domain === $plugin_slug ) {
			return (string) $plugin_file;
		}
	}

	return '';
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'activate_plugins' );
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'activate_plugins' );
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'activate_plugins' );
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'activate_plugins' );
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
 * 29. Install Plugin
 */
function mcp_wp_register_install_plugin() {
	wp_register_ability(
		'mcp-wp/install-plugin',
		array(
			'label'               => __( 'Install Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Install plugin from WordPress.org slug or download URL', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_slug'  => array( 'type' => 'string', 'description' => 'WordPress.org plugin slug' ),
					'download_url' => array( 'type' => 'string', 'description' => 'Direct plugin ZIP URL' ),
					'activate'     => array( 'type' => 'boolean', 'description' => 'Activate plugin after installation' ),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'plugin_file' => array( 'type' => 'string' ),
					'message'     => array( 'type' => 'string' ),
					'error'       => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'install_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_plugin_lifecycle_dependencies();

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for plugin installation.',
					);
				}

				$plugin_slug  = isset( $input['plugin_slug'] ) ? sanitize_title( (string) $input['plugin_slug'] ) : '';
				$download_url = isset( $input['download_url'] ) ? esc_url_raw( (string) $input['download_url'] ) : '';

				if ( '' === $plugin_slug && '' === $download_url ) {
					return array(
						'success' => false,
						'error'   => 'Either plugin_slug or download_url is required.',
					);
				}

				if ( '' === $download_url ) {
					$api = plugins_api(
						'plugin_information',
						array(
							'slug'   => $plugin_slug,
							'fields' => array(
								'sections' => false,
							),
						)
					);
					if ( is_wp_error( $api ) ) {
						return array(
							'success' => false,
							'error'   => $api->get_error_message(),
						);
					}
					$download_url = isset( $api->download_link ) ? esc_url_raw( (string) $api->download_link ) : '';
				}

				if ( '' === $download_url ) {
					return array(
						'success' => false,
						'error'   => 'Could not resolve plugin download URL.',
					);
				}

				$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
				$result   = $upgrader->install( $download_url );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Plugin installation failed.',
					);
				}

				$plugin_file = (string) $upgrader->plugin_info();
				if ( '' === $plugin_file && '' !== $plugin_slug ) {
					$plugin_file = mcp_wp_find_plugin_file_by_slug( $plugin_slug );
				}

				if ( ! empty( $input['activate'] ) && '' !== $plugin_file ) {
					$activation = activate_plugin( $plugin_file );
					if ( is_wp_error( $activation ) ) {
						return array(
							'success' => false,
							'error'   => 'Plugin installed but activation failed: ' . $activation->get_error_message(),
						);
					}
				}

				return array(
					'success'     => true,
					'plugin_file' => $plugin_file,
					'message'     => ! empty( $input['activate'] ) ? 'Plugin installed and activated.' : 'Plugin installed successfully.',
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
 * 30. Update Plugin
 */
function mcp_wp_register_update_plugin() {
	wp_register_ability(
		'mcp-wp/update-plugin',
		array(
			'label'               => __( 'Update Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update one installed plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file' => array( 'type' => 'string', 'description' => 'Plugin file path (e.g. akismet/akismet.php)' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'update_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_plugin_lifecycle_dependencies();

				$plugin_file = sanitize_text_field( (string) $input['plugin_file'] );
				$plugins     = get_plugins();
				if ( ! isset( $plugins[ $plugin_file ] ) ) {
					return array(
						'success' => false,
						'error'   => 'Plugin not found.',
					);
				}

				wp_update_plugins();
				$updates     = get_site_transient( 'update_plugins' );
				$has_update  = is_object( $updates ) && isset( $updates->response[ $plugin_file ] );

				if ( ! $has_update ) {
					return array(
						'success' => true,
						'message' => 'Plugin is already up to date.',
					);
				}

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for plugin update.',
					);
				}

				$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
				$result   = $upgrader->upgrade( $plugin_file );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( false === $result ) {
					return array(
						'success' => false,
						'error'   => 'Plugin update failed.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Plugin updated successfully.',
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
 * 31. Delete Plugin
 */
function mcp_wp_register_delete_plugin() {
	wp_register_ability(
		'mcp-wp/delete-plugin',
		array(
			'label'               => __( 'Delete Plugin', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete one installed plugin', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_file'      => array( 'type' => 'string', 'description' => 'Plugin file path' ),
					'deactivate_first' => array( 'type' => 'boolean', 'description' => 'Deactivate plugin before delete (default true)' ),
				),
				'required'   => array( 'plugin_file' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'delete_plugins' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_plugin_lifecycle_dependencies();

				$plugin_file      = sanitize_text_field( (string) $input['plugin_file'] );
				$deactivate_first = ! array_key_exists( 'deactivate_first', $input ) || ! empty( $input['deactivate_first'] );
				$plugins          = get_plugins();

				if ( ! isset( $plugins[ $plugin_file ] ) ) {
					return array(
						'success' => false,
						'error'   => 'Plugin not found.',
					);
				}

				if ( is_plugin_active( $plugin_file ) ) {
					if ( ! $deactivate_first ) {
						return array(
							'success' => false,
							'error'   => 'Plugin is active. Set deactivate_first=true or deactivate manually first.',
						);
					}
					deactivate_plugins( array( $plugin_file ) );
				}

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for plugin delete.',
					);
				}

				$result = delete_plugins( array( $plugin_file ) );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete plugin.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Plugin deleted successfully.',
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
 * 32. List Themes
 */
function mcp_wp_register_list_themes() {
	wp_register_ability(
		'mcp-wp/list-themes',
		array(
			'label'               => __( 'List Themes', 'mcp-wp-capabilities' ),
			'description'         => __( 'List installed themes', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'search'      => array(
						'type'        => 'string',
						'description' => 'Filter themes by name (case-insensitive partial match).',
					),
					'active_only' => array(
						'type'        => 'boolean',
						'description' => 'If true, only return the currently active theme.',
						'default'     => false,
					),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'switch_themes' );
			},
			'execute_callback'    => static function ( array $input ) {
				$search      = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
				$active_only = ! empty( $input['active_only'] );
				$current = wp_get_theme();
				$themes  = wp_get_themes();
				$data    = array();
				$needle  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

				foreach ( $themes as $stylesheet => $theme ) {
					$row = array(
						'stylesheet'  => (string) $stylesheet,
						'name'        => (string) $theme->get( 'Name' ),
						'version'     => (string) $theme->get( 'Version' ),
						'description' => (string) $theme->get( 'Description' ),
						'author'      => (string) $theme->get( 'Author' ),
						'active'      => $current->get_stylesheet() === $stylesheet,
						'template'    => (string) $theme->get_template(),
					);

					if ( $active_only && ! $row['active'] ) {
						continue;
					}

					if ( '' !== $needle ) {
						$haystack = $row['name'] . ' ' . $row['stylesheet'] . ' ' . $row['description'] . ' ' . $row['author'];
						$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
						if ( false === strpos( $haystack, $needle ) ) {
							continue;
						}
					}

					$data[] = $row;
				}

				return array(
					'success' => true,
					'data'    => $data,
					'total'   => count( $data ),
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
 * 33. Switch Theme
 */
function mcp_wp_register_switch_theme() {
	wp_register_ability(
		'mcp-wp/switch-theme',
		array(
			'label'               => __( 'Switch Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Switch active theme', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'stylesheet' => array( 'type' => 'string', 'description' => 'Theme stylesheet slug' ),
				),
				'required'   => array( 'stylesheet' ),
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
			'execute_callback'    => static function ( array $input ) {
				$stylesheet = sanitize_text_field( (string) $input['stylesheet'] );
				$theme      = wp_get_theme( $stylesheet );

				if ( ! $theme->exists() ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Theme "%s" not found.', $stylesheet ),
					);
				}

				switch_theme( $stylesheet );
				$active = wp_get_theme();

				return array(
					'success' => true,
					'data'    => array(
						'stylesheet' => (string) $active->get_stylesheet(),
						'name'       => (string) $active->get( 'Name' ),
						'version'    => (string) $active->get( 'Version' ),
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
 * 34. Install Theme
 */
function mcp_wp_register_install_theme() {
	wp_register_ability(
		'mcp-wp/install-theme',
		array(
			'label'               => __( 'Install Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Install a theme from WordPress.org slug or ZIP URL', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'theme_slug'   => array( 'type' => 'string', 'description' => 'WordPress.org theme slug' ),
					'download_url' => array( 'type' => 'string', 'description' => 'Direct theme ZIP URL' ),
					'activate'     => array( 'type' => 'boolean', 'description' => 'Activate after install' ),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ),
					'message'    => array( 'type' => 'string' ),
					'error'      => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'install_themes' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_theme_lifecycle_dependencies();

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for theme installation.',
					);
				}

				$theme_slug   = isset( $input['theme_slug'] ) ? sanitize_title( (string) $input['theme_slug'] ) : '';
				$download_url = isset( $input['download_url'] ) ? esc_url_raw( (string) $input['download_url'] ) : '';

				if ( '' === $theme_slug && '' === $download_url ) {
					return array(
						'success' => false,
						'error'   => 'Either theme_slug or download_url is required.',
					);
				}

				if ( '' === $download_url ) {
					$api = themes_api(
						'theme_information',
						array(
							'slug' => $theme_slug,
						)
					);
					if ( is_wp_error( $api ) ) {
						return array(
							'success' => false,
							'error'   => $api->get_error_message(),
						);
					}
					$download_url = isset( $api->download_link ) ? esc_url_raw( (string) $api->download_link ) : '';
				}

				if ( '' === $download_url ) {
					return array(
						'success' => false,
						'error'   => 'Could not resolve theme download URL.',
					);
				}

				$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
				$result   = $upgrader->install( $download_url );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Theme installation failed.',
					);
				}

				$stylesheet = '';
				if ( '' !== $theme_slug && wp_get_theme( $theme_slug )->exists() ) {
					$stylesheet = $theme_slug;
				}

				if ( ! empty( $input['activate'] ) && '' !== $stylesheet ) {
					switch_theme( $stylesheet );
				}

				return array(
					'success'    => true,
					'stylesheet' => $stylesheet,
					'message'    => ! empty( $input['activate'] ) ? 'Theme installed and activated.' : 'Theme installed successfully.',
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
 * 35. Update Theme
 */
function mcp_wp_register_update_theme() {
	wp_register_ability(
		'mcp-wp/update-theme',
		array(
			'label'               => __( 'Update Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update one installed theme', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'stylesheet' => array( 'type' => 'string', 'description' => 'Theme stylesheet slug' ),
				),
				'required'   => array( 'stylesheet' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'update_themes' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_theme_lifecycle_dependencies();

				$stylesheet = sanitize_text_field( (string) $input['stylesheet'] );
				$theme      = wp_get_theme( $stylesheet );
				if ( ! $theme->exists() ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Theme "%s" not found.', $stylesheet ),
					);
				}

				wp_update_themes();
				$updates    = get_site_transient( 'update_themes' );
				$has_update = is_object( $updates ) && isset( $updates->response[ $stylesheet ] );

				if ( ! $has_update ) {
					return array(
						'success' => true,
						'message' => 'Theme is already up to date.',
					);
				}

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for theme update.',
					);
				}

				$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
				$result   = $upgrader->upgrade( $stylesheet );

				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( false === $result ) {
					return array(
						'success' => false,
						'error'   => 'Theme update failed.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Theme updated successfully.',
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
 * 36. Delete Theme
 */
function mcp_wp_register_delete_theme() {
	wp_register_ability(
		'mcp-wp/delete-theme',
		array(
			'label'               => __( 'Delete Theme', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete one installed (inactive) theme', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'stylesheet' => array( 'type' => 'string', 'description' => 'Theme stylesheet slug' ),
				),
				'required'   => array( 'stylesheet' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'delete_themes' );
			},
			'execute_callback'    => static function ( array $input ) {
				mcp_wp_require_theme_lifecycle_dependencies();

				$stylesheet = sanitize_text_field( (string) $input['stylesheet'] );
				$theme      = wp_get_theme( $stylesheet );
				if ( ! $theme->exists() ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Theme "%s" not found.', $stylesheet ),
					);
				}

				$current_theme = wp_get_theme();
				if ( $current_theme->get_stylesheet() === $stylesheet || $current_theme->get_template() === $stylesheet ) {
					return array(
						'success' => false,
						'error'   => 'Cannot delete active theme or its parent template.',
					);
				}

				if ( ! WP_Filesystem() ) {
					return array(
						'success' => false,
						'error'   => 'WordPress filesystem could not be initialized for theme delete.',
					);
				}

				$result = delete_theme( $stylesheet );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}
				if ( ! $result ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete theme.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Theme deleted successfully.',
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
	mcp_wp_register_install_plugin();
	mcp_wp_register_update_plugin();
	mcp_wp_register_delete_plugin();
	mcp_wp_register_list_themes();
	mcp_wp_register_switch_theme();
	mcp_wp_register_install_theme();
	mcp_wp_register_update_theme();
	mcp_wp_register_delete_theme();
	mcp_wp_register_get_theme();
	mcp_wp_register_get_theme_supports();

	// Load plugin-specific ability modules.
	$plugin_modules = array(
		'acf' => __DIR__ . '/acf/abilities.php',
	);

	foreach ( $plugin_modules as $module => $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$fn = 'mcp_wp_register_' . $module . '_abilities';
			if ( function_exists( $fn ) ) {
				call_user_func( $fn );
			}
		}
	}
}
