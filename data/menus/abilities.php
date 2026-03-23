<?php
/**
 * WordPress Abilities: Menus & Menu Items
 *
 * Defines abilities for listing, creating and editing navigation menus.
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get registered menu locations.
 *
 * @return array<string,string>
 */
function mcp_wp_get_registered_menu_locations(): array {
	$locations = get_registered_nav_menus();
	return is_array( $locations ) ? $locations : array();
}

/**
 * Find the best "primary" location key.
 *
 * @return string
 */
function mcp_wp_find_primary_location_key(): string {
	$registered = mcp_wp_get_registered_menu_locations();
	if ( empty( $registered ) ) {
		return '';
	}

	$candidates = array(
		'primary',
		'main',
		'header',
		'menu-1',
		'primary-menu',
		'main-menu',
	);

	foreach ( $candidates as $candidate ) {
		if ( isset( $registered[ $candidate ] ) ) {
			return $candidate;
		}
	}

	$keys = array_keys( $registered );
	return isset( $keys[0] ) ? (string) $keys[0] : '';
}

/**
 * Resolve a menu location key from input.
 *
 * @param string $input_location      Input location key.
 * @param bool   $fallback_to_primary Whether to fallback to best primary location.
 * @return string|\WP_Error
 */
function mcp_wp_resolve_menu_location_key( string $input_location = '', bool $fallback_to_primary = false ) {
	$registered = mcp_wp_get_registered_menu_locations();
	if ( empty( $registered ) ) {
		return new \WP_Error( 'menu_locations_unavailable', 'Theme has no registered menu locations.' );
	}

	$input_location = trim( $input_location );
	if ( '' !== $input_location ) {
		$key = sanitize_key( $input_location );
		if ( isset( $registered[ $key ] ) ) {
			return $key;
		}

		return new \WP_Error(
			'invalid_menu_location',
			sprintf( 'Menu location "%s" is not registered in this theme.', $input_location )
		);
	}

	if ( ! $fallback_to_primary ) {
		return '';
	}

	$primary = mcp_wp_find_primary_location_key();
	if ( '' === $primary ) {
		return new \WP_Error( 'menu_locations_unavailable', 'Theme has no registered menu locations.' );
	}

	return $primary;
}

/**
 * Format menu term for response.
 *
 * @param \WP_Term $menu Menu term.
 * @return array<string,mixed>
 */
function mcp_wp_format_menu_response( \WP_Term $menu ): array {
	$registered = mcp_wp_get_registered_menu_locations();
	$locations  = get_nav_menu_locations();
	$assigned   = array();

	if ( is_array( $locations ) ) {
		foreach ( $locations as $location_key => $menu_id ) {
			if ( (int) $menu_id !== (int) $menu->term_id ) {
				continue;
			}

			$assigned[] = array(
				'location' => (string) $location_key,
				'label'    => isset( $registered[ $location_key ] ) ? (string) $registered[ $location_key ] : (string) $location_key,
			);
		}
	}

	return array(
		'id'          => (int) $menu->term_id,
		'name'        => (string) $menu->name,
		'slug'        => (string) $menu->slug,
		'description' => (string) $menu->description,
		'count'       => (int) $menu->count,
		'locations'   => $assigned,
	);
}

/**
 * Format menu item for response.
 *
 * @param \WP_Post $item_post Menu item post object.
 * @return array<string,mixed>
 */
function mcp_wp_format_menu_item_response( \WP_Post $item_post ): array {
	$item = wp_setup_nav_menu_item( $item_post );
	if ( ! $item ) {
		return array(
			'id' => (int) $item_post->ID,
		);
	}

	$classes = array();
	if ( isset( $item->classes ) && is_array( $item->classes ) ) {
		$classes = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return is_scalar( $value ) ? (string) $value : '';
					},
					$item->classes
				)
			)
		);
	}

	return array(
		'id'         => (int) $item->ID,
		'title'      => (string) $item->title,
		'url'        => (string) $item->url,
		'type'       => (string) $item->type,
		'object'     => (string) $item->object,
		'object_id'  => (int) $item->object_id,
		'parent_id'  => (int) $item->menu_item_parent,
		'menu_order' => (int) $item->menu_order,
		'target'     => (string) $item->target,
		'attr_title' => (string) $item->attr_title,
		'description'=> (string) $item->description,
		'classes'    => $classes,
		'xfn'        => (string) $item->xfn,
		'status'     => (string) $item_post->post_status,
	);
}

/**
 * Get menu items as formatted array.
 *
 * @param int $menu_id Menu ID.
 * @return array<int,array<string,mixed>>
 */
function mcp_wp_get_formatted_menu_items( int $menu_id ): array {
	$items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
	if ( ! is_array( $items ) ) {
		return array();
	}

	usort(
		$items,
		static function ( $left, $right ) {
			return (int) $left->menu_order <=> (int) $right->menu_order;
		}
	);

	return array_map(
		static function ( $item ) {
			return mcp_wp_format_menu_item_response( $item );
		},
		$items
	);
}

/**
 * Resolve menu term from mixed input.
 *
 * @param array<string,mixed> $input Input payload.
 * @return \WP_Term|\WP_Error
 */
function mcp_wp_get_menu_from_input( array $input ) {
	$menu_id = absint( $input['menu_id'] ?? 0 );
	if ( $menu_id > 0 ) {
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( $menu instanceof \WP_Term ) {
			return $menu;
		}
	}

	$menu_slug = isset( $input['menu_slug'] ) ? sanitize_text_field( (string) $input['menu_slug'] ) : '';
	if ( '' !== $menu_slug ) {
		$menu = wp_get_nav_menu_object( $menu_slug );
		if ( $menu instanceof \WP_Term ) {
			return $menu;
		}
	}

	$requested_location = isset( $input['location'] ) ? (string) $input['location'] : '';
	if ( '' !== trim( $requested_location ) ) {
		$location_key = mcp_wp_resolve_menu_location_key( $requested_location, false );
		if ( is_wp_error( $location_key ) ) {
			return $location_key;
		}

		$locations = get_nav_menu_locations();
		$menu_at_location = isset( $locations[ $location_key ] ) ? (int) $locations[ $location_key ] : 0;
		if ( $menu_at_location > 0 ) {
			$menu = wp_get_nav_menu_object( $menu_at_location );
			if ( $menu instanceof \WP_Term ) {
				return $menu;
			}
		}

		return new \WP_Error(
			'menu_not_assigned',
			sprintf( 'No menu is assigned to location "%s".', $location_key )
		);
	}

	$primary_location = mcp_wp_find_primary_location_key();
	if ( '' !== $primary_location ) {
		$locations = get_nav_menu_locations();
		$menu_at_primary = isset( $locations[ $primary_location ] ) ? (int) $locations[ $primary_location ] : 0;
		if ( $menu_at_primary > 0 ) {
			$menu = wp_get_nav_menu_object( $menu_at_primary );
			if ( $menu instanceof \WP_Term ) {
				return $menu;
			}
		}
	}

	$all_menus = wp_get_nav_menus();
	if ( is_array( $all_menus ) && isset( $all_menus[0] ) && $all_menus[0] instanceof \WP_Term ) {
		return $all_menus[0];
	}

	return new \WP_Error( 'menu_not_found', 'No navigation menu found.' );
}

/**
 * Resolve menu id for an existing menu item.
 *
 * @param int $menu_item_id Menu item ID.
 * @return int
 */
function mcp_wp_get_menu_id_for_item( int $menu_item_id ): int {
	$terms = wp_get_object_terms( $menu_item_id, 'nav_menu', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms[0] ) ) {
		return 0;
	}

	return (int) $terms[0];
}

/**
 * List menu locations.
 */
function mcp_wp_register_list_menu_locations() {
	wp_register_ability(
		'mcp-wp/list-menu-locations',
		array(
			'label'               => __( 'List Menu Locations', 'mcp-wp-capabilities' ),
			'description'         => __( 'List registered menu locations and assigned menus', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'array' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function () {
				$registered = mcp_wp_get_registered_menu_locations();
				$locations  = get_nav_menu_locations();
				$data       = array();

				foreach ( $registered as $location_key => $label ) {
					$menu_id = isset( $locations[ $location_key ] ) ? (int) $locations[ $location_key ] : 0;
					$menu    = $menu_id > 0 ? wp_get_nav_menu_object( $menu_id ) : null;

					$data[] = array(
						'location'  => (string) $location_key,
						'label'     => (string) $label,
						'menu_id'   => $menu_id,
						'menu_name' => $menu instanceof \WP_Term ? (string) $menu->name : '',
					);
				}

				return array(
					'success' => true,
					'data'    => $data,
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
 * List menus.
 */
function mcp_wp_register_list_menus() {
	wp_register_ability(
		'mcp-wp/list-menus',
		array(
			'label'               => __( 'List Menus', 'mcp-wp-capabilities' ),
			'description'         => __( 'List navigation menus', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'include_items' => array( 'type' => 'boolean', 'description' => 'Include menu items for each menu' ),
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
				$include_items = ! empty( $input['include_items'] );
				$menus         = wp_get_nav_menus();
				$data          = array();

				if ( is_array( $menus ) ) {
					foreach ( $menus as $menu ) {
						if ( ! $menu instanceof \WP_Term ) {
							continue;
						}

						$row = mcp_wp_format_menu_response( $menu );
						if ( $include_items ) {
							$row['items'] = mcp_wp_get_formatted_menu_items( (int) $menu->term_id );
						}
						$data[] = $row;
					}
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
 * Get one menu with optional items.
 */
function mcp_wp_register_get_menu() {
	wp_register_ability(
		'mcp-wp/get-menu',
		array(
			'label'               => __( 'Get Menu', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get a navigation menu by id, slug or location', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_id'       => array( 'type' => 'integer', 'description' => 'Menu term ID' ),
					'menu_slug'     => array( 'type' => 'string', 'description' => 'Menu slug' ),
					'location'      => array( 'type' => 'string', 'description' => 'Theme location key, e.g. primary' ),
					'include_items' => array( 'type' => 'boolean', 'description' => 'Include menu items' ),
				),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'read' );
			},
			'execute_callback'    => static function ( array $input ) {
				$menu = mcp_wp_get_menu_from_input( $input );
				if ( is_wp_error( $menu ) ) {
					return array(
						'success' => false,
						'error'   => $menu->get_error_message(),
					);
				}

				$data = mcp_wp_format_menu_response( $menu );
				if ( ! array_key_exists( 'include_items', $input ) || ! empty( $input['include_items'] ) ) {
					$data['items'] = mcp_wp_get_formatted_menu_items( (int) $menu->term_id );
				}

				return array(
					'success' => true,
					'data'    => $data,
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
 * Edit menu metadata and optional location assignment.
 */
function mcp_wp_register_edit_menu() {
	wp_register_ability(
		'mcp-wp/edit-menu',
		array(
			'label'               => __( 'Edit Menu', 'mcp-wp-capabilities' ),
			'description'         => __( 'Edit a navigation menu', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_id'      => array( 'type' => 'integer', 'description' => 'Menu term ID' ),
					'menu_name'    => array( 'type' => 'string', 'description' => 'New menu name' ),
					'menu_slug'    => array( 'type' => 'string', 'description' => 'New menu slug' ),
					'description'  => array( 'type' => 'string', 'description' => 'Menu description' ),
					'location'     => array( 'type' => 'string', 'description' => 'Optional location key to assign menu to' ),
					'use_primary'  => array( 'type' => 'boolean', 'description' => 'Use best primary location if location is omitted' ),
				),
				'required'   => array( 'menu_id' ),
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
				$menu_id = absint( $input['menu_id'] ?? 0 );
				if ( $menu_id <= 0 ) {
					return array(
						'success' => false,
						'error'   => 'Valid menu_id is required.',
					);
				}

				$menu = wp_get_nav_menu_object( $menu_id );
				if ( ! $menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu not found.',
					);
				}

				$has_changes = false;
				$menu_data   = array(
					'menu-name'   => (string) $menu->name,
					'description' => (string) $menu->description,
				);

				if ( array_key_exists( 'menu_name', $input ) ) {
					$menu_name = sanitize_text_field( (string) $input['menu_name'] );
					if ( '' === $menu_name ) {
						return array(
							'success' => false,
							'error'   => 'menu_name cannot be empty.',
						);
					}
					$menu_data['menu-name'] = $menu_name;
					$has_changes            = true;
				}

				if ( array_key_exists( 'description', $input ) ) {
					$menu_data['description'] = sanitize_textarea_field( (string) $input['description'] );
					$has_changes              = true;
				}

				$location_key = '';
				if ( array_key_exists( 'location', $input ) || ! empty( $input['use_primary'] ) ) {
					$location_key = mcp_wp_resolve_menu_location_key(
						(string) ( $input['location'] ?? '' ),
						! empty( $input['use_primary'] )
					);
					if ( is_wp_error( $location_key ) || '' === $location_key ) {
						return array(
							'success' => false,
							'error'   => is_wp_error( $location_key ) ? $location_key->get_error_message() : 'No location provided.',
						);
					}
					$has_changes = true;
				}

				if ( ! $has_changes && ! array_key_exists( 'menu_slug', $input ) ) {
					return array(
						'success' => false,
						'error'   => 'No editable fields were provided.',
					);
				}

				$updated_menu_id = wp_update_nav_menu_object( $menu_id, $menu_data );
				if ( is_wp_error( $updated_menu_id ) ) {
					return array(
						'success' => false,
						'error'   => $updated_menu_id->get_error_message(),
					);
				}

				if ( array_key_exists( 'menu_slug', $input ) ) {
					$menu_slug = sanitize_title( (string) $input['menu_slug'] );
					if ( '' === $menu_slug ) {
						return array(
							'success' => false,
							'error'   => 'menu_slug cannot be empty.',
						);
					}

					$slug_result = wp_update_term(
						$menu_id,
						'nav_menu',
						array(
							'slug' => $menu_slug,
						)
					);
					if ( is_wp_error( $slug_result ) ) {
						return array(
							'success' => false,
							'error'   => $slug_result->get_error_message(),
						);
					}
				}

				if ( is_string( $location_key ) && '' !== $location_key ) {
					$locations = get_nav_menu_locations();
					if ( ! is_array( $locations ) ) {
						$locations = array();
					}
					$locations[ $location_key ] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
				}

				$updated_menu = wp_get_nav_menu_object( $menu_id );
				if ( ! $updated_menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu updated but could not be loaded.',
					);
				}

				$data = mcp_wp_format_menu_response( $updated_menu );
				if ( is_string( $location_key ) && '' !== $location_key ) {
					$data['assigned_location'] = $location_key;
				}

				return array(
					'success' => true,
					'data'    => $data,
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
 * Create a new menu.
 */
function mcp_wp_register_create_menu() {
	wp_register_ability(
		'mcp-wp/create-menu',
		array(
			'label'               => __( 'Create Menu', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a new navigation menu', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_name'      => array( 'type' => 'string', 'description' => 'Menu name' ),
					'menu_slug'      => array( 'type' => 'string', 'description' => 'Optional menu slug' ),
					'location'       => array( 'type' => 'string', 'description' => 'Optional location key' ),
					'assign_primary' => array( 'type' => 'boolean', 'description' => 'Assign menu to primary location if available' ),
				),
				'required'   => array( 'menu_name' ),
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
				$menu_name = sanitize_text_field( (string) $input['menu_name'] );
				if ( '' === $menu_name ) {
					return array(
						'success' => false,
						'error'   => 'Menu name is required.',
					);
				}

				$menu_id = wp_create_nav_menu( $menu_name );
				if ( is_wp_error( $menu_id ) ) {
					return array(
						'success' => false,
						'error'   => $menu_id->get_error_message(),
					);
				}

				$menu_id = (int) $menu_id;

				if ( ! empty( $input['menu_slug'] ) ) {
					wp_update_term(
						$menu_id,
						'nav_menu',
						array(
							'slug' => sanitize_title( (string) $input['menu_slug'] ),
						)
					);
				}

				$assign_primary = ! empty( $input['assign_primary'] );
				$location_input = isset( $input['location'] ) ? (string) $input['location'] : '';
				$location_key   = mcp_wp_resolve_menu_location_key( $location_input, $assign_primary );

				if ( is_wp_error( $location_key ) && '' !== trim( $location_input ) ) {
					return array(
						'success' => false,
						'error'   => $location_key->get_error_message(),
					);
				}

				if ( is_string( $location_key ) && '' !== $location_key ) {
					$locations = get_nav_menu_locations();
					if ( ! is_array( $locations ) ) {
						$locations = array();
					}
					$locations[ $location_key ] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
				}

				$menu = wp_get_nav_menu_object( $menu_id );
				if ( ! $menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu was created but could not be loaded.',
					);
				}

				$data = mcp_wp_format_menu_response( $menu );
				if ( is_string( $location_key ) && '' !== $location_key ) {
					$data['assigned_location'] = $location_key;
				}

				return array(
					'success' => true,
					'data'    => $data,
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
 * Delete a menu.
 */
function mcp_wp_register_delete_menu() {
	wp_register_ability(
		'mcp-wp/delete-menu',
		array(
			'label'               => __( 'Delete Menu', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete a navigation menu', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_id' => array( 'type' => 'integer', 'description' => 'Menu term ID' ),
				),
				'required'   => array( 'menu_id' ),
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
				$menu_id = absint( $input['menu_id'] ?? 0 );
				if ( $menu_id <= 0 ) {
					return array(
						'success' => false,
						'error'   => 'Valid menu_id is required.',
					);
				}

				$menu = wp_get_nav_menu_object( $menu_id );
				if ( ! $menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu not found.',
					);
				}

				$deleted = wp_delete_nav_menu( $menu_id );
				if ( ! $deleted ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete menu.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Menu deleted successfully.',
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
 * Assign menu to a location.
 */
function mcp_wp_register_assign_menu_location() {
	wp_register_ability(
		'mcp-wp/assign-menu-location',
		array(
			'label'               => __( 'Assign Menu Location', 'mcp-wp-capabilities' ),
			'description'         => __( 'Assign a menu to a theme menu location', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_id'        => array( 'type' => 'integer', 'description' => 'Menu term ID' ),
					'location'       => array( 'type' => 'string', 'description' => 'Location key, e.g. primary' ),
					'use_primary'    => array( 'type' => 'boolean', 'description' => 'Use best primary location if location is omitted' ),
				),
				'required'   => array( 'menu_id' ),
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
				$menu_id = absint( $input['menu_id'] );
				$menu    = wp_get_nav_menu_object( $menu_id );
				if ( ! $menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu not found.',
					);
				}

				$use_primary  = ! empty( $input['use_primary'] );
				$location_key = mcp_wp_resolve_menu_location_key( (string) ( $input['location'] ?? '' ), $use_primary );
				if ( is_wp_error( $location_key ) || '' === $location_key ) {
					return array(
						'success' => false,
						'error'   => is_wp_error( $location_key ) ? $location_key->get_error_message() : 'No location provided.',
					);
				}

				$locations = get_nav_menu_locations();
				if ( ! is_array( $locations ) ) {
					$locations = array();
				}
				$locations[ $location_key ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );

				$registered = mcp_wp_get_registered_menu_locations();
				return array(
					'success' => true,
					'data'    => array(
						'menu'     => mcp_wp_format_menu_response( $menu ),
						'location' => array(
							'key'   => $location_key,
							'label' => isset( $registered[ $location_key ] ) ? (string) $registered[ $location_key ] : $location_key,
						),
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
 * Create menu item.
 */
function mcp_wp_register_create_menu_item() {
	wp_register_ability(
		'mcp-wp/create-menu-item',
		array(
			'label'               => __( 'Create Menu Item', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a menu item in a menu', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_id'         => array( 'type' => 'integer', 'description' => 'Menu term ID' ),
					'menu_slug'       => array( 'type' => 'string', 'description' => 'Menu slug' ),
					'location'        => array( 'type' => 'string', 'description' => 'Menu location key, e.g. primary' ),
					'title'           => array( 'type' => 'string', 'description' => 'Menu link title' ),
					'type'            => array( 'type' => 'string', 'enum' => array( 'custom', 'post_type', 'taxonomy' ), 'description' => 'Menu item type' ),
					'url'             => array( 'type' => 'string', 'description' => 'URL (for custom type)' ),
					'object'          => array( 'type' => 'string', 'description' => 'Object type (e.g. page, post, category)' ),
					'object_id'       => array( 'type' => 'integer', 'description' => 'Object ID for post_type/taxonomy items' ),
					'parent_item_id'  => array( 'type' => 'integer', 'description' => 'Parent menu item ID' ),
					'position'        => array( 'type' => 'integer', 'description' => 'Menu order position' ),
					'target'          => array( 'type' => 'string', 'enum' => array( '', '_blank' ), 'description' => 'Link target' ),
					'status'          => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'description' => 'Menu item status' ),
				),
				'required'   => array( 'title' ),
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
				$menu = mcp_wp_get_menu_from_input( $input );
				if ( is_wp_error( $menu ) ) {
					return array(
						'success' => false,
						'error'   => $menu->get_error_message(),
					);
				}

				$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
				if ( '' === $title ) {
					return array(
						'success' => false,
						'error'   => 'Menu item title is required.',
					);
				}

				$type = sanitize_key( (string) ( $input['type'] ?? 'custom' ) );
				if ( ! in_array( $type, array( 'custom', 'post_type', 'taxonomy' ), true ) ) {
					$type = 'custom';
				}

				$menu_item_args = array(
					'menu-item-title'     => $title,
					'menu-item-status'    => sanitize_key( (string) ( $input['status'] ?? 'publish' ) ),
					'menu-item-parent-id' => absint( $input['parent_item_id'] ?? 0 ),
				);

				if ( isset( $input['position'] ) ) {
					$menu_item_args['menu-item-position'] = absint( $input['position'] );
				}

				if ( isset( $input['target'] ) ) {
					$target = sanitize_text_field( (string) $input['target'] );
					$menu_item_args['menu-item-target'] = '_blank' === $target ? '_blank' : '';
				}

				if ( 'custom' === $type ) {
					$url = esc_url_raw( (string) ( $input['url'] ?? '' ) );
					if ( '' === $url ) {
						return array(
							'success' => false,
							'error'   => 'Custom menu items require "url".',
						);
					}

					$menu_item_args['menu-item-type'] = 'custom';
					$menu_item_args['menu-item-url']  = $url;
				} else {
					$object    = sanitize_key( (string) ( $input['object'] ?? '' ) );
					$object_id = absint( $input['object_id'] ?? 0 );
					if ( '' === $object || $object_id <= 0 ) {
						return array(
							'success' => false,
							'error'   => 'Post type/taxonomy menu items require "object" and "object_id".',
						);
					}

					$menu_item_args['menu-item-type']      = $type;
					$menu_item_args['menu-item-object']    = $object;
					$menu_item_args['menu-item-object-id'] = $object_id;
				}

				$menu_item_id = wp_update_nav_menu_item( (int) $menu->term_id, 0, $menu_item_args );
				if ( is_wp_error( $menu_item_id ) ) {
					return array(
						'success' => false,
						'error'   => $menu_item_id->get_error_message(),
					);
				}

				$item_post = get_post( (int) $menu_item_id );
				if ( ! $item_post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Menu item created but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => array(
						'menu' => mcp_wp_format_menu_response( $menu ),
						'item' => mcp_wp_format_menu_item_response( $item_post ),
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
 * Edit menu item.
 */
function mcp_wp_register_edit_menu_item() {
	wp_register_ability(
		'mcp-wp/edit-menu-item',
		array(
			'label'               => __( 'Edit Menu Item', 'mcp-wp-capabilities' ),
			'description'         => __( 'Edit an existing menu item', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_item_id'    => array( 'type' => 'integer', 'description' => 'Menu item ID' ),
					'menu_id'         => array( 'type' => 'integer', 'description' => 'Menu term ID (optional if item already belongs to a menu)' ),
					'title'           => array( 'type' => 'string', 'description' => 'Menu title' ),
					'type'            => array( 'type' => 'string', 'enum' => array( 'custom', 'post_type', 'taxonomy' ), 'description' => 'Menu item type' ),
					'url'             => array( 'type' => 'string', 'description' => 'URL (for custom type)' ),
					'object'          => array( 'type' => 'string', 'description' => 'Object type (e.g. page, post, category)' ),
					'object_id'       => array( 'type' => 'integer', 'description' => 'Object ID' ),
					'parent_item_id'  => array( 'type' => 'integer', 'description' => 'Parent menu item ID' ),
					'position'        => array( 'type' => 'integer', 'description' => 'Menu order position' ),
					'target'          => array( 'type' => 'string', 'enum' => array( '', '_blank' ), 'description' => 'Link target' ),
					'status'          => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'description' => 'Menu item status' ),
				),
				'required'   => array( 'menu_item_id' ),
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
				$menu_item_id = absint( $input['menu_item_id'] );
				$item_post    = get_post( $menu_item_id );
				if ( ! $item_post instanceof \WP_Post || 'nav_menu_item' !== $item_post->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Menu item not found.',
					);
				}

				$menu_id = absint( $input['menu_id'] ?? 0 );
				if ( $menu_id <= 0 ) {
					$menu_id = mcp_wp_get_menu_id_for_item( $menu_item_id );
				}
				if ( $menu_id <= 0 ) {
					return array(
						'success' => false,
						'error'   => 'Could not resolve menu for this item.',
					);
				}

				$menu = wp_get_nav_menu_object( $menu_id );
				if ( ! $menu instanceof \WP_Term ) {
					return array(
						'success' => false,
						'error'   => 'Menu not found.',
					);
				}

				$existing = wp_setup_nav_menu_item( $item_post );
				if ( ! $existing ) {
					return array(
						'success' => false,
						'error'   => 'Could not load existing menu item.',
					);
				}

				$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : (string) $existing->type;
				if ( ! in_array( $type, array( 'custom', 'post_type', 'taxonomy' ), true ) ) {
					$type = (string) $existing->type;
				}

				$menu_item_args = array(
					'menu-item-title'     => isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : (string) $existing->title,
					'menu-item-status'    => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : (string) $item_post->post_status,
					'menu-item-parent-id' => isset( $input['parent_item_id'] ) ? absint( $input['parent_item_id'] ) : (int) $existing->menu_item_parent,
					'menu-item-target'    => isset( $input['target'] ) && '_blank' === sanitize_text_field( (string) $input['target'] ) ? '_blank' : (string) $existing->target,
				);

				if ( isset( $input['position'] ) ) {
					$menu_item_args['menu-item-position'] = absint( $input['position'] );
				}

				if ( 'custom' === $type ) {
					$menu_item_args['menu-item-type'] = 'custom';
					$menu_item_args['menu-item-url']  = isset( $input['url'] )
						? esc_url_raw( (string) $input['url'] )
						: (string) $existing->url;
				} else {
					$menu_item_args['menu-item-type'] = $type;
					$menu_item_args['menu-item-object'] = isset( $input['object'] )
						? sanitize_key( (string) $input['object'] )
						: (string) $existing->object;
					$menu_item_args['menu-item-object-id'] = isset( $input['object_id'] )
						? absint( $input['object_id'] )
						: (int) $existing->object_id;
				}

				$result = wp_update_nav_menu_item( $menu_id, $menu_item_id, $menu_item_args );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				}

				$updated_post = get_post( $menu_item_id );
				if ( ! $updated_post instanceof \WP_Post ) {
					return array(
						'success' => false,
						'error'   => 'Menu item updated but could not be loaded.',
					);
				}

				return array(
					'success' => true,
					'data'    => array(
						'menu' => mcp_wp_format_menu_response( $menu ),
						'item' => mcp_wp_format_menu_item_response( $updated_post ),
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
 * Delete menu item.
 */
function mcp_wp_register_delete_menu_item() {
	wp_register_ability(
		'mcp-wp/delete-menu-item',
		array(
			'label'               => __( 'Delete Menu Item', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete a menu item', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'menu_item_id' => array( 'type' => 'integer', 'description' => 'Menu item ID' ),
				),
				'required'   => array( 'menu_item_id' ),
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
				$menu_item_id = absint( $input['menu_item_id'] );
				$item_post    = get_post( $menu_item_id );
				if ( ! $item_post instanceof \WP_Post || 'nav_menu_item' !== $item_post->post_type ) {
					return array(
						'success' => false,
						'error'   => 'Menu item not found.',
					);
				}

				$deleted = wp_delete_post( $menu_item_id, true );
				if ( ! $deleted ) {
					return array(
						'success' => false,
						'error'   => 'Failed to delete menu item.',
					);
				}

				return array(
					'success' => true,
					'message' => 'Menu item deleted successfully.',
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
 * Register all menu abilities.
 */
function mcp_wp_register_menus_abilities() {
	mcp_wp_register_list_menu_locations();
	mcp_wp_register_list_menus();
	mcp_wp_register_get_menu();
	mcp_wp_register_edit_menu();
	mcp_wp_register_create_menu();
	mcp_wp_register_delete_menu();
	mcp_wp_register_assign_menu_location();
	mcp_wp_register_create_menu_item();
	mcp_wp_register_edit_menu_item();
	mcp_wp_register_delete_menu_item();
}
