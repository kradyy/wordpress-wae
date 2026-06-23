<?php
/**
 * ACF (Advanced Custom Fields) Abilities
 *
 * Full control over ACF field groups, field definitions, field values,
 * repeater/flex rows, and options pages via the ACF PHP API.
 *
 * Covers all built-in + PRO field types:
 *   Basic:      text, textarea, number, range, email, url, password
 *   Content:    image, file, wysiwyg, oembed, gallery (PRO)
 *   Choice:     select, checkbox, radio, button_group, true_false
 *   Relational: link, post_object, page_link, relationship, taxonomy, user
 *   jQuery:     google_map, date_picker, date_time_picker, time_picker, color_picker
 *   Layout:     message, accordion, tab, group, repeater (PRO), flexible_content (PRO), clone (PRO)
 *
 * @package MCPWPCapabilities
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

function mcp_acf_is_active(): bool {
	return function_exists( 'acf' ) || function_exists( 'acf_get_field_groups' );
}

/** @return array<string,mixed> */
function mcp_acf_not_active(): array {
	return array( 'success' => false, 'error' => 'Advanced Custom Fields (ACF) is not active on this site.' );
}

/**
 * Normalize a post_id for ACF functions.
 * Accepts int post IDs, "option"/"options", "term_42", "user_7".
 *
 * @param mixed $raw
 * @return int|string
 */
function mcp_acf_normalize_post_id( $raw ) {
	if ( in_array( $raw, array( 'options', 'option' ), true ) ) {
		return 'option';
	}
	if ( is_string( $raw ) && preg_match( '/^(term|user|comment)_\d+$/', $raw ) ) {
		return $raw;
	}
	return absint( $raw );
}

/**
 * Generate a unique ACF key.
 *
 * @param string $prefix  'field' or 'group'.
 */
function mcp_acf_generate_key( string $prefix = 'field' ): string {
	return $prefix . '_' . substr( md5( uniqid( $prefix, true ) ), 0, 13 );
}

/**
 * Build a human-readable descriptor for a single ACF field, with value hints.
 * Recurses into sub_fields (repeater, group) and layouts (flexible_content).
 *
 * @param array<string,mixed> $field
 * @return array<string,mixed>
 */
function mcp_acf_describe_field( array $field ): array {
	$type = (string) ( $field['type'] ?? 'text' );

	$d = array(
		'key'          => $field['key']   ?? '',
		'name'         => $field['name']  ?? '',
		'label'        => $field['label'] ?? '',
		'type'         => $type,
		'required'     => ! empty( $field['required'] ),
		'instructions' => $field['instructions'] ?? '',
	);

	switch ( $type ) {
		case 'select':
		case 'checkbox':
		case 'radio':
		case 'button_group':
			$d['choices']  = $field['choices'] ?? array();
			$d['multiple'] = ! empty( $field['multiple'] ) || 'checkbox' === $type;
			break;
		case 'post_object':
		case 'relationship':
		case 'page_link':
			$d['post_type']  = $field['post_type'] ?? array();
			$d['multiple']   = 'relationship' === $type || ! empty( $field['multiple'] );
			$d['value_hint'] = 'post_object/page_link: post ID (int); relationship: array of post IDs';
			break;
		case 'taxonomy':
			$d['taxonomy']   = $field['taxonomy']   ?? '';
			$d['field_type'] = $field['field_type'] ?? 'checkbox';
			$d['multiple']   = in_array( $d['field_type'], array( 'checkbox', 'multi_select' ), true );
			$d['value_hint'] = 'Term ID (int) or array of term IDs';
			break;
		case 'user':
			$d['multiple']   = ! empty( $field['multiple'] );
			$d['value_hint'] = 'User ID (int) or array of user IDs';
			break;
		case 'image':
		case 'file':
			$d['return_format'] = $field['return_format'] ?? 'array';
			$d['value_hint']    = 'Attachment ID (int) or attachment URL (string)';
			break;
		case 'gallery':
			$d['value_hint'] = 'Array of attachment IDs (integers)';
			break;
		case 'link':
			$d['value_hint'] = '{"url":"https://...","title":"Label","target":"_blank"}';
			break;
		case 'google_map':
			$d['value_hint'] = '{"address":"123 Main St","lat":40.712,"lng":-74.006}';
			break;
		case 'date_picker':
			$d['value_hint'] = '"Ymd" e.g. "20240615"';
			break;
		case 'date_time_picker':
			$d['value_hint'] = '"Y-m-d H:i:s" e.g. "2024-06-15 14:30:00"';
			break;
		case 'time_picker':
			$d['value_hint'] = '"H:i:s" e.g. "14:30:00"';
			break;
		case 'repeater':
			$d['min']        = $field['min'] ?? 0;
			$d['max']        = $field['max'] ?? 0;
			$d['value_hint'] = 'Array of row objects: [{"sub_field_name": value, ...}]';
			$d['sub_fields'] = array_map( 'mcp_acf_describe_field', $field['sub_fields'] ?? array() );
			break;
		case 'flexible_content':
			$d['value_hint'] = 'Array of layout objects: [{"acf_fc_layout":"name", sub_field: val, ...}]';
			$d['layouts']    = array();
			foreach ( $field['layouts'] ?? array() as $layout ) {
				$d['layouts'][] = array(
					'key'        => $layout['key']   ?? '',
					'name'       => $layout['name']  ?? '',
					'label'      => $layout['label'] ?? '',
					'sub_fields' => array_map( 'mcp_acf_describe_field', $layout['sub_fields'] ?? array() ),
				);
			}
			break;
		case 'group':
			$d['value_hint'] = 'Object: {"sub_field_name": value, ...}';
			$d['sub_fields'] = array_map( 'mcp_acf_describe_field', $field['sub_fields'] ?? array() );
			break;
	}

	return $d;
}

/**
 * Build a complete ACF field array from caller input.
 * Handles common properties and all type-specific settings.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function mcp_acf_build_field_array( array $input ): array {
	$type  = sanitize_key( (string) ( $input['type'] ?? 'text' ) );
	$field = array(
		'key'               => isset( $input['key'] ) ? sanitize_text_field( (string) $input['key'] ) : mcp_acf_generate_key( 'field' ),
		'label'             => sanitize_text_field( (string) ( $input['label'] ?? '' ) ),
		'name'              => sanitize_key( (string) ( $input['name'] ?? '' ) ),
		'type'              => $type,
		'instructions'      => sanitize_textarea_field( (string) ( $input['instructions'] ?? '' ) ),
		'required'          => ! empty( $input['required'] ) ? 1 : 0,
		'conditional_logic' => $input['conditional_logic'] ?? 0,
		'wrapper'           => array(
			'width' => (string) ( $input['wrapper_width'] ?? '' ),
			'class' => sanitize_html_class( (string) ( $input['wrapper_class'] ?? '' ) ),
			'id'    => sanitize_html_class( (string) ( $input['wrapper_id'] ?? '' ) ),
		),
	);

	if ( isset( $input['parent'] ) ) {
		$parent = sanitize_text_field( (string) $input['parent'] );
		// Resolve group/field key string to its WP post ID (acf_update_field needs an integer post_parent).
		if ( str_starts_with( $parent, 'group_' ) ) {
			$group = acf_get_field_group( $parent );
			if ( $group && ! empty( $group['ID'] ) ) {
				$parent = (int) $group['ID'];
			}
		} elseif ( str_starts_with( $parent, 'field_' ) ) {
			$pf = acf_get_field( $parent );
			if ( $pf && ! empty( $pf['ID'] ) ) {
				$parent = (int) $pf['ID'];
			}
		}
		$field['parent'] = $parent;
	}
	if ( isset( $input['menu_order'] ) ) {
		$field['menu_order'] = (int) $input['menu_order'];
	}

	switch ( $type ) {

		case 'text':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			$field['prepend']       = (string) ( $input['prepend'] ?? '' );
			$field['append']        = (string) ( $input['append'] ?? '' );
			$field['maxlength']     = (string) ( $input['maxlength'] ?? '' );
			break;

		case 'textarea':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			$field['maxlength']     = (string) ( $input['maxlength'] ?? '' );
			$field['rows']          = (string) ( $input['rows'] ?? '' );
			$field['new_lines']     = in_array( $input['new_lines'] ?? '', array( 'wpautop', 'br', '' ), true )
				? (string) $input['new_lines'] : 'wpautop';
			break;

		case 'number':
		case 'range':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			$field['prepend']       = (string) ( $input['prepend'] ?? '' );
			$field['append']        = (string) ( $input['append'] ?? '' );
			$field['min']           = (string) ( $input['min'] ?? '' );
			$field['max']           = (string) ( $input['max'] ?? '' );
			$field['step']          = (string) ( $input['step'] ?? '' );
			break;

		case 'email':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			$field['prepend']       = (string) ( $input['prepend'] ?? '' );
			$field['append']        = (string) ( $input['append'] ?? '' );
			break;

		case 'url':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			break;

		case 'password':
			$field['placeholder'] = (string) ( $input['placeholder'] ?? '' );
			$field['prepend']     = (string) ( $input['prepend'] ?? '' );
			$field['append']      = (string) ( $input['append'] ?? '' );
			break;

		case 'image':
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'array', 'url', 'id' ), true )
				? (string) $input['return_format'] : 'array';
			$field['preview_size']  = (string) ( $input['preview_size'] ?? 'medium' );
			$field['library']       = in_array( $input['library'] ?? '', array( 'all', 'uploadedTo' ), true )
				? (string) $input['library'] : 'all';
			$field['min_width']     = (string) ( $input['min_width'] ?? '' );
			$field['min_height']    = (string) ( $input['min_height'] ?? '' );
			$field['min_size']      = (string) ( $input['min_size'] ?? '' );
			$field['max_width']     = (string) ( $input['max_width'] ?? '' );
			$field['max_height']    = (string) ( $input['max_height'] ?? '' );
			$field['max_size']      = (string) ( $input['max_size'] ?? '' );
			$field['mime_types']    = (string) ( $input['mime_types'] ?? '' );
			break;

		case 'file':
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'array', 'url', 'id' ), true )
				? (string) $input['return_format'] : 'array';
			$field['library']       = in_array( $input['library'] ?? '', array( 'all', 'uploadedTo' ), true )
				? (string) $input['library'] : 'all';
			$field['min_size']      = (string) ( $input['min_size'] ?? '' );
			$field['max_size']      = (string) ( $input['max_size'] ?? '' );
			$field['mime_types']    = (string) ( $input['mime_types'] ?? '' );
			break;

		case 'wysiwyg':
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['tabs']          = in_array( $input['tabs'] ?? '', array( 'all', 'visual', 'text' ), true )
				? (string) $input['tabs'] : 'all';
			$field['toolbar']       = in_array( $input['toolbar'] ?? '', array( 'full', 'basic' ), true )
				? (string) $input['toolbar'] : 'full';
			$field['media_upload']  = ! empty( $input['media_upload'] ) ? 1 : 0;
			$field['delay']         = ! empty( $input['delay'] ) ? 1 : 0;
			break;

		case 'oembed':
			$field['width']  = (string) ( $input['width'] ?? '' );
			$field['height'] = (string) ( $input['height'] ?? '' );
			break;

		case 'gallery':
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'array', 'url', 'id' ), true )
				? (string) $input['return_format'] : 'array';
			$field['library']       = in_array( $input['library'] ?? '', array( 'all', 'uploadedTo' ), true )
				? (string) $input['library'] : 'all';
			$field['min']           = (string) ( $input['min'] ?? '' );
			$field['max']           = (string) ( $input['max'] ?? '' );
			$field['min_width']     = (string) ( $input['min_width'] ?? '' );
			$field['min_height']    = (string) ( $input['min_height'] ?? '' );
			$field['min_size']      = (string) ( $input['min_size'] ?? '' );
			$field['max_width']     = (string) ( $input['max_width'] ?? '' );
			$field['max_height']    = (string) ( $input['max_height'] ?? '' );
			$field['max_size']      = (string) ( $input['max_size'] ?? '' );
			$field['mime_types']    = (string) ( $input['mime_types'] ?? '' );
			$field['preview_size']  = (string) ( $input['preview_size'] ?? 'medium' );
			break;

		case 'select':
			$field['choices']       = is_array( $input['choices'] ?? null ) ? $input['choices'] : array();
			$field['default_value'] = $input['default_value'] ?? '';
			$field['allow_null']    = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['multiple']      = ! empty( $input['multiple'] ) ? 1 : 0;
			$field['ui']            = ! empty( $input['ui'] ) ? 1 : 0;
			$field['ajax']          = ! empty( $input['ajax'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'value', 'label', 'array' ), true )
				? (string) $input['return_format'] : 'value';
			$field['placeholder']   = (string) ( $input['placeholder'] ?? '' );
			break;

		case 'checkbox':
			$field['choices']       = is_array( $input['choices'] ?? null ) ? $input['choices'] : array();
			$field['default_value'] = $input['default_value'] ?? '';
			$field['layout']        = in_array( $input['layout'] ?? '', array( 'vertical', 'horizontal' ), true )
				? (string) $input['layout'] : 'vertical';
			$field['toggle']        = ! empty( $input['toggle'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'value', 'label', 'array' ), true )
				? (string) $input['return_format'] : 'value';
			$field['allow_custom']  = ! empty( $input['allow_custom'] ) ? 1 : 0;
			$field['save_custom']   = ! empty( $input['save_custom'] ) ? 1 : 0;
			break;

		case 'radio':
			$field['choices']           = is_array( $input['choices'] ?? null ) ? $input['choices'] : array();
			$field['default_value']     = (string) ( $input['default_value'] ?? '' );
			$field['allow_null']        = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['other_choice']      = ! empty( $input['other_choice'] ) ? 1 : 0;
			$field['save_other_choice'] = ! empty( $input['save_other_choice'] ) ? 1 : 0;
			$field['layout']            = in_array( $input['layout'] ?? '', array( 'vertical', 'horizontal' ), true )
				? (string) $input['layout'] : 'vertical';
			$field['return_format']     = in_array( $input['return_format'] ?? '', array( 'value', 'label', 'array' ), true )
				? (string) $input['return_format'] : 'value';
			break;

		case 'button_group':
			$field['choices']       = is_array( $input['choices'] ?? null ) ? $input['choices'] : array();
			$field['default_value'] = (string) ( $input['default_value'] ?? '' );
			$field['allow_null']    = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'value', 'label', 'array' ), true )
				? (string) $input['return_format'] : 'value';
			$field['layout']        = in_array( $input['layout'] ?? '', array( 'horizontal', 'vertical' ), true )
				? (string) $input['layout'] : 'horizontal';
			break;

		case 'true_false':
			$field['message']       = (string) ( $input['message'] ?? '' );
			$field['default_value'] = ! empty( $input['default_value'] ) ? 1 : 0;
			$field['ui']            = ! empty( $input['ui'] ) ? 1 : 0;
			$field['ui_on_text']    = (string) ( $input['ui_on_text'] ?? '' );
			$field['ui_off_text']   = (string) ( $input['ui_off_text'] ?? '' );
			break;

		case 'link':
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'array', 'url' ), true )
				? (string) $input['return_format'] : 'array';
			break;

		case 'post_object':
			$field['post_type']     = is_array( $input['post_type'] ?? null ) ? $input['post_type'] : array();
			$field['taxonomy']      = is_array( $input['taxonomy'] ?? null ) ? $input['taxonomy'] : array();
			$field['allow_null']    = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['multiple']      = ! empty( $input['multiple'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'object', 'id' ), true )
				? (string) $input['return_format'] : 'object';
			$field['ui']            = 1;
			$field['filters']       = is_array( $input['filters'] ?? null )
				? $input['filters'] : array( 'search', 'post_type', 'taxonomy' );
			break;

		case 'page_link':
			$field['post_type']      = is_array( $input['post_type'] ?? null ) ? $input['post_type'] : array();
			$field['taxonomy']       = is_array( $input['taxonomy'] ?? null ) ? $input['taxonomy'] : array();
			$field['allow_null']     = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['multiple']       = ! empty( $input['multiple'] ) ? 1 : 0;
			$field['allow_archives'] = ! empty( $input['allow_archives'] ) ? 1 : 0;
			break;

		case 'relationship':
			$field['post_type']     = is_array( $input['post_type'] ?? null ) ? $input['post_type'] : array();
			$field['taxonomy']      = is_array( $input['taxonomy'] ?? null ) ? $input['taxonomy'] : array();
			$field['filters']       = is_array( $input['filters'] ?? null )
				? $input['filters'] : array( 'search', 'post_type', 'taxonomy' );
			$field['min']           = (string) ( $input['min'] ?? '' );
			$field['max']           = (string) ( $input['max'] ?? '' );
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'object', 'id' ), true )
				? (string) $input['return_format'] : 'object';
			$field['elements']      = is_array( $input['elements'] ?? null ) ? $input['elements'] : array();
			break;

		case 'taxonomy':
			$field['taxonomy']      = (string) ( $input['taxonomy'] ?? 'category' );
			$field['field_type']    = in_array( $input['field_type'] ?? '', array( 'checkbox', 'multi_select', 'radio', 'select' ), true )
				? (string) $input['field_type'] : 'checkbox';
			$field['add_term']      = ! empty( $input['add_term'] ) ? 1 : 0;
			$field['save_terms']    = ! empty( $input['save_terms'] ) ? 1 : 0;
			$field['load_terms']    = ! empty( $input['load_terms'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'id', 'object' ), true )
				? (string) $input['return_format'] : 'id';
			$field['multiple']      = ! empty( $input['multiple'] ) ? 1 : 0;
			$field['allow_null']    = ! empty( $input['allow_null'] ) ? 1 : 0;
			break;

		case 'user':
			$field['role']          = is_array( $input['role'] ?? null ) ? $input['role'] : array();
			$field['allow_null']    = ! empty( $input['allow_null'] ) ? 1 : 0;
			$field['multiple']      = ! empty( $input['multiple'] ) ? 1 : 0;
			$field['return_format'] = in_array( $input['return_format'] ?? '', array( 'array', 'id', 'object' ), true )
				? (string) $input['return_format'] : 'array';
			break;

		case 'google_map':
			$field['center_lat'] = (string) ( $input['center_lat'] ?? '' );
			$field['center_lng'] = (string) ( $input['center_lng'] ?? '' );
			$field['zoom']       = (string) ( $input['zoom'] ?? '' );
			$field['height']     = (string) ( $input['height'] ?? '' );
			break;

		case 'date_picker':
			$field['display_format'] = (string) ( $input['display_format'] ?? 'd/m/Y' );
			$field['return_format']  = (string) ( $input['return_format'] ?? 'd/m/Y' );
			$field['first_day']      = (int) ( $input['first_day'] ?? 1 );
			break;

		case 'date_time_picker':
			$field['display_format'] = (string) ( $input['display_format'] ?? 'd/m/Y g:i a' );
			$field['return_format']  = (string) ( $input['return_format'] ?? 'd/m/Y g:i a' );
			$field['first_day']      = (int) ( $input['first_day'] ?? 1 );
			break;

		case 'time_picker':
			$field['display_format'] = (string) ( $input['display_format'] ?? 'g:i a' );
			$field['return_format']  = (string) ( $input['return_format'] ?? 'g:i a' );
			break;

		case 'color_picker':
			$field['default_value']  = (string) ( $input['default_value'] ?? '' );
			$field['enable_opacity'] = ! empty( $input['enable_opacity'] ) ? 1 : 0;
			$field['return_format']  = in_array( $input['return_format'] ?? '', array( 'string', 'array' ), true )
				? (string) $input['return_format'] : 'string';
			break;

		case 'message':
			$field['message']   = wp_kses_post( (string) ( $input['message'] ?? '' ) );
			$field['esc_html']  = ! empty( $input['esc_html'] ) ? 1 : 0;
			$field['new_lines'] = in_array( $input['new_lines'] ?? '', array( 'wpautop', 'br', '' ), true )
				? (string) $input['new_lines'] : 'wpautop';
			break;

		case 'accordion':
			$field['open']         = ! empty( $input['open'] ) ? 1 : 0;
			$field['multi_expand'] = ! empty( $input['multi_expand'] ) ? 1 : 0;
			$field['endpoint']     = ! empty( $input['endpoint'] ) ? 1 : 0;
			break;

		case 'tab':
			$field['placement'] = in_array( $input['placement'] ?? '', array( 'top', 'left' ), true )
				? (string) $input['placement'] : 'top';
			$field['endpoint']  = ! empty( $input['endpoint'] ) ? 1 : 0;
			break;

		case 'group':
			$field['layout']     = in_array( $input['layout'] ?? '', array( 'block', 'table', 'row' ), true )
				? (string) $input['layout'] : 'block';
			$field['sub_fields'] = is_array( $input['sub_fields'] ?? null ) ? $input['sub_fields'] : array();
			break;

		case 'repeater':
			$field['min']          = (string) ( $input['min'] ?? '' );
			$field['max']          = (string) ( $input['max'] ?? '' );
			$field['layout']       = in_array( $input['layout'] ?? '', array( 'block', 'table', 'row' ), true )
				? (string) $input['layout'] : 'block';
			$field['button_label'] = (string) ( $input['button_label'] ?? 'Add Row' );
			$field['collapsed']    = (string) ( $input['collapsed'] ?? '' );
			$field['sub_fields']   = is_array( $input['sub_fields'] ?? null ) ? $input['sub_fields'] : array();
			break;

		case 'flexible_content':
			$field['button_label'] = (string) ( $input['button_label'] ?? 'Add Layout' );
			$field['min']          = (string) ( $input['min'] ?? '' );
			$field['max']          = (string) ( $input['max'] ?? '' );
			$field['layouts']      = is_array( $input['layouts'] ?? null ) ? $input['layouts'] : array();
			break;

		case 'clone':
			$field['clone']        = is_array( $input['clone'] ?? null ) ? $input['clone'] : array();
			$field['prefix_label'] = ! empty( $input['prefix_label'] ) ? 1 : 0;
			$field['prefix_name']  = ! empty( $input['prefix_name'] ) ? 1 : 0;
			$field['display']      = in_array( $input['display'] ?? '', array( 'seamless', 'group' ), true )
				? (string) $input['display'] : 'seamless';
			$field['layout']       = in_array( $input['layout'] ?? '', array( 'block', 'table', 'row' ), true )
				? (string) $input['layout'] : 'block';
			break;
	}

	return $field;
}

// ---------------------------------------------------------------------------
// Status
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_check(): void {
	wp_register_ability(
		'mcp-wp/acf-check',
		array(
			'label'               => __( 'ACF: Check Status', 'mcp-wp-capabilities' ),
			'description'         => __( 'Check whether ACF is active, its version, PRO status, and any registered options pages', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'active'        => array( 'type' => 'boolean' ),
					'version'       => array( 'type' => 'string' ),
					'pro'           => array( 'type' => 'boolean' ),
					'options_pages' => array( 'type' => 'array' ),
					'error'         => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				if ( ! mcp_acf_is_active() ) {
					return array( 'success' => true, 'active' => false, 'version' => '', 'pro' => false, 'options_pages' => array() );
				}
				$version = defined( 'ACF_VERSION' ) ? ACF_VERSION : ( defined( 'ACF_PRO_VERSION' ) ? ACF_PRO_VERSION : '' );
				$pro     = function_exists( 'acf_pro' ) || defined( 'ACF_PRO' );

				$options_pages = array();
				if ( function_exists( 'acf_get_options_pages' ) ) {
					foreach ( (array) acf_get_options_pages() as $page ) {
						$options_pages[] = array(
							'post_id'    => (string) ( $page['post_id'] ?? '' ),
							'menu_title' => (string) ( $page['menu_title'] ?? '' ),
							'slug'       => (string) ( $page['menu_slug'] ?? '' ),
						);
					}
				}

				return array(
					'success'       => true,
					'active'        => true,
					'version'       => (string) $version,
					'pro'           => $pro,
					'options_pages' => $options_pages,
				);
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Field Groups — CRUD
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_field_groups(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field-groups',
		array(
			'label'               => __( 'ACF: Get Field Groups', 'mcp-wp-capabilities' ),
			'description'         => __( 'List all ACF field groups with location rules and settings', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'active' => array( 'type' => 'boolean', 'description' => 'Filter by active status; omit for all' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$args = array();
				if ( isset( $input['active'] ) ) {
					$args['active'] = (bool) $input['active'];
				}
				$groups = acf_get_field_groups( $args );
				$data   = array();
				foreach ( $groups as $g ) {
					$data[] = array(
						'key'                   => $g['key'] ?? '',
						'title'                 => $g['title'] ?? '',
						'active'                => (bool) ( $g['active'] ?? true ),
						'position'              => $g['position'] ?? 'normal',
						'style'                 => $g['style'] ?? 'default',
						'label_placement'       => $g['label_placement'] ?? 'top',
						'instruction_placement' => $g['instruction_placement'] ?? 'label',
						'hide_on_screen'        => $g['hide_on_screen'] ?? array(),
						'description'           => $g['description'] ?? '',
						'menu_order'            => (int) ( $g['menu_order'] ?? 0 ),
						'location'              => $g['location'] ?? array(),
					);
				}
				return array( 'success' => true, 'data' => $data, 'total' => count( $data ) );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_get_field_group(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field-group',
		array(
			'label'               => __( 'ACF: Get Field Group', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get a single ACF field group by key or title, including all its fields with value hints', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key'            => array( 'type' => 'string', 'description' => 'Field group key (group_...) or exact title string' ),
					'include_fields' => array( 'type' => 'boolean', 'description' => 'Include full field schema (default: true)' ),
				),
				'required'   => array( 'key' ),
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
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key   = sanitize_text_field( (string) $input['key'] );
				$group = acf_get_field_group( $key );

				if ( ! $group ) {
					$lower = strtolower( $key );
					foreach ( acf_get_field_groups() as $g ) {
						if ( strtolower( $g['title'] ) === $lower ) {
							$group = $g;
							break;
						}
					}
				}

				if ( ! $group ) {
					return array( 'success' => false, 'error' => "Field group '{$key}' not found." );
				}

				$include_fields = ! array_key_exists( 'include_fields', $input ) || ! empty( $input['include_fields'] );
				if ( $include_fields ) {
					$fields          = acf_get_fields( $group['key'] );
					$group['fields'] = is_array( $fields )
						? array_values( array_map( 'mcp_acf_describe_field', $fields ) ) : array();
				}

				return array( 'success' => true, 'data' => $group );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_create_field_group(): void {
	wp_register_ability(
		'mcp-wp/acf-create-field-group',
		array(
			'label'               => __( 'ACF: Create Field Group', 'mcp-wp-capabilities' ),
			'description'         => __( 'Create a new ACF field group with location rules', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'title'                 => array( 'type' => 'string' ),
					'key'                   => array( 'type' => 'string', 'description' => 'Unique key — auto-generated if omitted' ),
					'location'              => array(
						'type'        => 'array',
						'description' => 'Location rule groups (OR). Each group is an array of rules (AND). Example: [[{"param":"post_type","operator":"==","value":"post"}]]',
					),
					'position'              => array( 'type' => 'string', 'enum' => array( 'normal', 'acf_after_title', 'side' ) ),
					'style'                 => array( 'type' => 'string', 'enum' => array( 'default', 'seamless' ) ),
					'label_placement'       => array( 'type' => 'string', 'enum' => array( 'top', 'left' ) ),
					'instruction_placement' => array( 'type' => 'string', 'enum' => array( 'label', 'field' ) ),
					'hide_on_screen'        => array( 'type' => 'array', 'description' => 'UI elements to hide (permalink, the_content, excerpt, ...)' ),
					'active'                => array( 'type' => 'boolean' ),
					'description'           => array( 'type' => 'string' ),
					'menu_order'            => array( 'type' => 'integer' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key   = isset( $input['key'] ) ? sanitize_text_field( (string) $input['key'] ) : mcp_acf_generate_key( 'group' );
				$group = array(
					'key'                   => $key,
					'title'                 => sanitize_text_field( (string) $input['title'] ),
					'fields'                => array(),
					'location'              => is_array( $input['location'] ?? null ) ? $input['location']
						: array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
					'menu_order'            => (int) ( $input['menu_order'] ?? 0 ),
					'position'              => in_array( $input['position'] ?? '', array( 'normal', 'acf_after_title', 'side' ), true )
						? (string) $input['position'] : 'normal',
					'style'                 => in_array( $input['style'] ?? '', array( 'default', 'seamless' ), true )
						? (string) $input['style'] : 'default',
					'label_placement'       => in_array( $input['label_placement'] ?? '', array( 'top', 'left' ), true )
						? (string) $input['label_placement'] : 'top',
					'instruction_placement' => in_array( $input['instruction_placement'] ?? '', array( 'label', 'field' ), true )
						? (string) $input['instruction_placement'] : 'label',
					'hide_on_screen'        => is_array( $input['hide_on_screen'] ?? null ) ? $input['hide_on_screen'] : array(),
					'active'                => ! array_key_exists( 'active', $input ) || ! empty( $input['active'] ),
					'description'           => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
				);
				if ( ! acf_update_field_group( $group ) ) {
					return array( 'success' => false, 'error' => 'Failed to create field group.' );
				}
				return array( 'success' => true, 'data' => acf_get_field_group( $key ) ?: $group );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_field_group(): void {
	wp_register_ability(
		'mcp-wp/acf-update-field-group',
		array(
			'label'               => __( 'ACF: Update Field Group', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update settings of an existing ACF field group. Pass only the properties you want to change.', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key'                   => array( 'type' => 'string' ),
					'title'                 => array( 'type' => 'string' ),
					'location'              => array( 'type' => 'array' ),
					'position'              => array( 'type' => 'string', 'enum' => array( 'normal', 'acf_after_title', 'side' ) ),
					'style'                 => array( 'type' => 'string', 'enum' => array( 'default', 'seamless' ) ),
					'label_placement'       => array( 'type' => 'string', 'enum' => array( 'top', 'left' ) ),
					'instruction_placement' => array( 'type' => 'string', 'enum' => array( 'label', 'field' ) ),
					'hide_on_screen'        => array( 'type' => 'array' ),
					'active'                => array( 'type' => 'boolean' ),
					'description'           => array( 'type' => 'string' ),
					'menu_order'            => array( 'type' => 'integer' ),
				),
				'required'   => array( 'key' ),
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
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key   = sanitize_text_field( (string) $input['key'] );
				$group = acf_get_field_group( $key );
				if ( ! $group ) {
					return array( 'success' => false, 'error' => 'Field group not found.' );
				}
				foreach ( array( 'title', 'location', 'position', 'style', 'label_placement', 'instruction_placement', 'hide_on_screen', 'active', 'description', 'menu_order' ) as $prop ) {
					if ( array_key_exists( $prop, $input ) ) {
						$group[ $prop ] = $input[ $prop ];
					}
				}
				if ( ! acf_update_field_group( $group ) ) {
					return array( 'success' => false, 'error' => 'Failed to update field group.' );
				}
				return array( 'success' => true, 'data' => acf_get_field_group( $key ) ?: $group );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_delete_field_group(): void {
	wp_register_ability(
		'mcp-wp/acf-delete-field-group',
		array(
			'label'               => __( 'ACF: Delete Field Group', 'mcp-wp-capabilities' ),
			'description'         => __( 'Permanently delete an ACF field group and all its fields', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key' => array( 'type' => 'string', 'description' => 'Field group key' ),
				),
				'required'   => array( 'key' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key = sanitize_text_field( (string) $input['key'] );
				if ( ! acf_get_field_group( $key ) ) {
					return array( 'success' => false, 'error' => 'Field group not found.' );
				}
				if ( ! acf_delete_field_group( $key ) ) {
					return array( 'success' => false, 'error' => 'Failed to delete field group.' );
				}
				return array( 'success' => true, 'message' => 'Field group deleted.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Fields — CRUD
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_fields(): void {
	wp_register_ability(
		'mcp-wp/acf-get-fields',
		array(
			'label'               => __( 'ACF: Get Fields', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all field definitions in an ACF field group, with value hints for each type', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'group_key' => array( 'type' => 'string', 'description' => 'Field group key' ),
				),
				'required'   => array( 'group_key' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$fields = acf_get_fields( sanitize_text_field( (string) $input['group_key'] ) );
				if ( false === $fields ) {
					return array( 'success' => false, 'error' => 'Field group not found.' );
				}
				$fields = $fields ?: array();
				return array(
					'success' => true,
					'data'    => array_values( array_map( 'mcp_acf_describe_field', $fields ) ),
					'total'   => count( $fields ),
				);
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_get_field(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field',
		array(
			'label'               => __( 'ACF: Get Field', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get a single ACF field definition by key (field_...) or field name', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key' => array( 'type' => 'string', 'description' => 'Field key (field_...) or name' ),
				),
				'required'   => array( 'key' ),
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
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$field = acf_get_field( sanitize_text_field( (string) $input['key'] ) );
				if ( ! $field ) {
					return array( 'success' => false, 'error' => 'Field not found.' );
				}
				return array( 'success' => true, 'data' => mcp_acf_describe_field( $field ) );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_create_field(): void {
	wp_register_ability(
		'mcp-wp/acf-create-field',
		array(
			'label'               => __( 'ACF: Create Field', 'mcp-wp-capabilities' ),
			'description'         => __( 'Add a new field to an ACF field group. Supports all types: text, textarea, number, range, email, url, password, image, file, wysiwyg, oembed, select, checkbox, radio, button_group, true_false, link, post_object, page_link, relationship, taxonomy, user, google_map, date_picker, date_time_picker, time_picker, color_picker, message, accordion, tab, group, repeater (PRO), flexible_content (PRO), clone (PRO), gallery (PRO)', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'parent'               => array( 'type' => 'string', 'description' => 'Parent group key (group_...) or parent field key for sub-fields' ),
					'label'                => array( 'type' => 'string' ),
					'name'                 => array( 'type' => 'string', 'description' => 'Slug (lowercase, underscores)' ),
					'type'                 => array( 'type' => 'string', 'description' => 'Field type — see description for full list' ),
					'key'                  => array( 'type' => 'string', 'description' => 'Auto-generated if omitted' ),
					'instructions'         => array( 'type' => 'string' ),
					'required'             => array( 'type' => 'boolean' ),
					'conditional_logic'    => array( 'description' => '0 = disabled; or array of rule groups' ),
					'wrapper_width'        => array( 'type' => 'string', 'description' => 'e.g. "50%"' ),
					'wrapper_class'        => array( 'type' => 'string' ),
					'wrapper_id'           => array( 'type' => 'string' ),
					'menu_order'           => array( 'type' => 'integer' ),
					'default_value'        => array( 'description' => 'Depends on field type' ),
					'placeholder'          => array( 'type' => 'string' ),
					'prepend'              => array( 'type' => 'string' ),
					'append'               => array( 'type' => 'string' ),
					'maxlength'            => array( 'type' => 'string' ),
					'rows'                 => array( 'type' => 'string' ),
					'new_lines'            => array( 'type' => 'string', 'enum' => array( 'wpautop', 'br', '' ) ),
					'min'                  => array( 'type' => 'string' ),
					'max'                  => array( 'type' => 'string' ),
					'step'                 => array( 'type' => 'string' ),
					'return_format'        => array( 'type' => 'string' ),
					'preview_size'         => array( 'type' => 'string' ),
					'library'              => array( 'type' => 'string', 'enum' => array( 'all', 'uploadedTo' ) ),
					'mime_types'           => array( 'type' => 'string' ),
					'tabs'                 => array( 'type' => 'string', 'enum' => array( 'all', 'visual', 'text' ) ),
					'toolbar'              => array( 'type' => 'string', 'enum' => array( 'full', 'basic' ) ),
					'media_upload'         => array( 'type' => 'boolean' ),
					'choices'              => array( 'type' => 'object', 'description' => '{"value":"Label"} pairs' ),
					'allow_null'           => array( 'type' => 'boolean' ),
					'multiple'             => array( 'type' => 'boolean' ),
					'ui'                   => array( 'type' => 'boolean' ),
					'ajax'                 => array( 'type' => 'boolean' ),
					'layout'               => array( 'type' => 'string' ),
					'toggle'               => array( 'type' => 'boolean' ),
					'allow_custom'         => array( 'type' => 'boolean' ),
					'save_custom'          => array( 'type' => 'boolean' ),
					'other_choice'         => array( 'type' => 'boolean' ),
					'save_other_choice'    => array( 'type' => 'boolean' ),
					'message'              => array( 'type' => 'string' ),
					'ui_on_text'           => array( 'type' => 'string' ),
					'ui_off_text'          => array( 'type' => 'string' ),
					'post_type'            => array( 'type' => 'array' ),
					'taxonomy'             => array( 'type' => 'array' ),
					'field_type'           => array( 'type' => 'string' ),
					'add_term'             => array( 'type' => 'boolean' ),
					'save_terms'           => array( 'type' => 'boolean' ),
					'load_terms'           => array( 'type' => 'boolean' ),
					'filters'              => array( 'type' => 'array' ),
					'elements'             => array( 'type' => 'array' ),
					'role'                 => array( 'type' => 'array' ),
					'center_lat'           => array( 'type' => 'string' ),
					'center_lng'           => array( 'type' => 'string' ),
					'zoom'                 => array( 'type' => 'string' ),
					'display_format'       => array( 'type' => 'string' ),
					'first_day'            => array( 'type' => 'integer' ),
					'enable_opacity'       => array( 'type' => 'boolean' ),
					'esc_html'             => array( 'type' => 'boolean' ),
					'open'                 => array( 'type' => 'boolean' ),
					'multi_expand'         => array( 'type' => 'boolean' ),
					'endpoint'             => array( 'type' => 'boolean' ),
					'placement'            => array( 'type' => 'string', 'enum' => array( 'top', 'left' ) ),
					'sub_fields'           => array( 'type' => 'array', 'description' => 'Sub-field arrays for group/repeater' ),
					'button_label'         => array( 'type' => 'string' ),
					'collapsed'            => array( 'type' => 'string' ),
					'layouts'              => array( 'type' => 'array', 'description' => 'Layout objects for flexible_content' ),
					'clone'                => array( 'type' => 'array', 'description' => 'Field/group keys to clone' ),
					'prefix_label'         => array( 'type' => 'boolean' ),
					'prefix_name'          => array( 'type' => 'boolean' ),
					'display'              => array( 'type' => 'string', 'enum' => array( 'seamless', 'group' ) ),
					'allow_archives'       => array( 'type' => 'boolean' ),
					'min_width'            => array( 'type' => 'string' ),
					'min_height'           => array( 'type' => 'string' ),
					'min_size'             => array( 'type' => 'string' ),
					'max_width'            => array( 'type' => 'string' ),
					'max_height'           => array( 'type' => 'string' ),
					'max_size'             => array( 'type' => 'string' ),
					'delay'                => array( 'type' => 'boolean' ),
					'width'                => array( 'type' => 'string' ),
					'height'               => array( 'type' => 'string' ),
				),
				'required'   => array( 'parent', 'label', 'name', 'type' ),
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
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$field  = mcp_acf_build_field_array( $input );
				$result = acf_update_field( $field );
				if ( ! $result ) {
					return array( 'success' => false, 'error' => 'Failed to create field.' );
				}
				$saved = acf_get_field( $field['key'] );
				return array( 'success' => true, 'data' => $saved ? mcp_acf_describe_field( $saved ) : $field );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_field(): void {
	wp_register_ability(
		'mcp-wp/acf-update-field',
		array(
			'label'               => __( 'ACF: Update Field', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update an existing ACF field definition. Pass the field key and only the properties to change.', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key'   => array( 'type' => 'string', 'description' => 'Existing field key (field_...)' ),
					'label' => array( 'type' => 'string' ),
					'name'  => array( 'type' => 'string' ),
				),
				'required'   => array( 'key' ),
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
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key      = sanitize_text_field( (string) $input['key'] );
				$existing = acf_get_field( $key );
				if ( ! $existing ) {
					return array( 'success' => false, 'error' => 'Field not found.' );
				}
				$merged        = array_merge( $existing, $input );
				$merged['key'] = $existing['key'];
				$field         = mcp_acf_build_field_array( $merged );
				if ( ! acf_update_field( $field ) ) {
					return array( 'success' => false, 'error' => 'Failed to update field.' );
				}
				$saved = acf_get_field( $key );
				return array( 'success' => true, 'data' => $saved ? mcp_acf_describe_field( $saved ) : $field );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_delete_field(): void {
	wp_register_ability(
		'mcp-wp/acf-delete-field',
		array(
			'label'               => __( 'ACF: Delete Field', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete an ACF field definition by key', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'key' => array( 'type' => 'string', 'description' => 'Field key (field_...)' ),
				),
				'required'   => array( 'key' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$key = sanitize_text_field( (string) $input['key'] );
				if ( ! acf_get_field( $key ) ) {
					return array( 'success' => false, 'error' => 'Field not found.' );
				}
				if ( ! acf_delete_field( $key ) ) {
					return array( 'success' => false, 'error' => 'Failed to delete field.' );
				}
				return array( 'success' => true, 'message' => 'Field deleted.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Field Values
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_post_fields(): void {
	wp_register_ability(
		'mcp-wp/acf-get-post-fields',
		array(
			'label'               => __( 'ACF: Get Post Field Values', 'mcp-wp-capabilities' ),
			'description'         => __( 'Read ACF field values for any post, term (term_42), user (user_7), or options page (post_id="option")', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'field_names'  => array( 'type' => 'array', 'description' => 'Specific field names to retrieve; omit for all' ),
					'format_value' => array( 'type' => 'boolean', 'description' => 'Format values (default true); false returns raw stored values' ),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'post_id' => array(),
					'data'    => array( 'type' => 'object' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id      = mcp_acf_normalize_post_id( $input['post_id'] );
				$format_value = ! array_key_exists( 'format_value', $input ) || ! empty( $input['format_value'] );
				$field_names  = isset( $input['field_names'] ) && is_array( $input['field_names'] )
					? array_map( 'sanitize_text_field', $input['field_names'] ) : array();

				if ( ! empty( $field_names ) ) {
					$data = array();
					foreach ( $field_names as $name ) {
						$data[ $name ] = get_field( $name, $post_id, $format_value );
					}
				} else {
					$data = get_fields( $post_id, $format_value );
					if ( false === $data || null === $data ) {
						$data = array();
					}
				}

				return array( 'success' => true, 'post_id' => $post_id, 'data' => $data ?: array() );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_post_field(): void {
	wp_register_ability(
		'mcp-wp/acf-update-post-field',
		array(
			'label'               => __( 'ACF: Update Post Field Value', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update a single ACF field value on a post, term, user, or options page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector' => array( 'type' => 'string', 'description' => 'Field name or key' ),
					'value'    => array( 'description' => 'New value — type must match the ACF field type' ),
				),
				'required'   => array( 'post_id', 'selector', 'value' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id  = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector = sanitize_text_field( (string) $input['selector'] );
				$result   = update_field( $selector, $input['value'], $post_id );
				if ( false === $result ) {
					// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					if ( get_field( $selector, $post_id, false ) == $input['value'] ) {
						return array( 'success' => true, 'message' => 'Field value unchanged (already set).' );
					}
					return array( 'success' => false, 'error' => 'Failed to update field. Check the selector and value.' );
				}
				return array( 'success' => true, 'message' => 'Field updated.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_post_fields(): void {
	wp_register_ability(
		'mcp-wp/acf-update-post-fields',
		array(
			'label'               => __( 'ACF: Update Post Fields (Bulk)', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update multiple ACF field values at once. Returns lists of updated and failed field names.', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'fields'  => array( 'type' => 'object', 'description' => '{"field_name": value, ...}' ),
				),
				'required'   => array( 'post_id', 'fields' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'failed'  => array( 'type' => 'array' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function ( $input = array() ) {
				$payload     = is_array( $input ) ? $input : array();
				$post_id_raw = $payload['post_id'] ?? null;
				if ( in_array( $post_id_raw, array( 'options', 'option' ), true )
					|| ( is_string( $post_id_raw ) && str_starts_with( $post_id_raw, 'user_' ) ) ) {
					return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
				}
				$id = absint( (int) $post_id_raw );
				if ( $id > 0 && current_user_can( 'edit_post', $id ) ) {
					return true;
				}
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id = mcp_acf_normalize_post_id( $input['post_id'] );
				$fields  = is_array( $input['fields'] ) ? $input['fields'] : array();
				$updated = array();
				$failed  = array();

				foreach ( $fields as $selector => $value ) {
					$sel    = sanitize_text_field( (string) $selector );
					$result = update_field( $sel, $value, $post_id );
					if ( false !== $result ) {
						$updated[] = $sel;
					} else {
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( get_field( $sel, $post_id, false ) == $value ) {
							$updated[] = $sel;
						} else {
							$failed[] = array( 'field' => $sel, 'reason' => 'update_field returned false' );
						}
					}
				}

				return array( 'success' => empty( $failed ), 'updated' => $updated, 'failed' => $failed );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_delete_post_field(): void {
	wp_register_ability(
		'mcp-wp/acf-delete-post-field',
		array(
			'label'               => __( 'ACF: Delete Post Field Value', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete the stored value of an ACF field for a specific post, term, user, or options page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector' => array( 'type' => 'string', 'description' => 'Field name or key' ),
				),
				'required'   => array( 'post_id', 'selector' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				if ( ! function_exists( 'delete_field' ) ) {
					return array( 'success' => false, 'error' => 'delete_field() not available in this ACF version.' );
				}
				$post_id  = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector = sanitize_text_field( (string) $input['selector'] );
				delete_field( $selector, $post_id );
				return array( 'success' => true, 'message' => 'Field value deleted.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_get_field_object(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field-object',
		array(
			'label'               => __( 'ACF: Get Field Object', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get a field\'s full definition AND its current value for a specific post/object — useful before editing a value', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'selector'     => array( 'type' => 'string', 'description' => 'Field name or key' ),
					'post_id'      => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'format_value' => array( 'type' => 'boolean', 'description' => 'Format the value (default true)' ),
				),
				'required'   => array( 'selector', 'post_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$selector     = sanitize_text_field( (string) $input['selector'] );
				$post_id      = mcp_acf_normalize_post_id( $input['post_id'] );
				$format_value = ! array_key_exists( 'format_value', $input ) || ! empty( $input['format_value'] );
				$field        = get_field_object( $selector, $post_id, $format_value );
				if ( ! $field ) {
					return array( 'success' => false, 'error' => 'Field not found.' );
				}
				return array( 'success' => true, 'data' => $field );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Repeater / Flexible Content rows
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_repeater_rows(): void {
	wp_register_ability(
		'mcp-wp/acf-get-repeater-rows',
		array(
			'label'               => __( 'ACF: Get Repeater Rows', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all rows of a repeater or flexible_content field', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector'     => array( 'type' => 'string', 'description' => 'Repeater field name' ),
					'format_value' => array( 'type' => 'boolean', 'description' => 'Format sub-field values (default true)' ),
				),
				'required'   => array( 'post_id', 'selector' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id      = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector     = sanitize_text_field( (string) $input['selector'] );
				$format_value = ! array_key_exists( 'format_value', $input ) || ! empty( $input['format_value'] );
				$rows         = get_field( $selector, $post_id, $format_value );
				if ( ! is_array( $rows ) ) {
					return array( 'success' => true, 'data' => array(), 'count' => 0 );
				}
				return array( 'success' => true, 'data' => $rows, 'count' => count( $rows ) );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_add_repeater_row(): void {
	wp_register_ability(
		'mcp-wp/acf-add-repeater-row',
		array(
			'label'               => __( 'ACF: Add Repeater Row', 'mcp-wp-capabilities' ),
			'description'         => __( 'Append a new row to an ACF repeater field (requires ACF PRO)', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector' => array( 'type' => 'string', 'description' => 'Repeater field name' ),
					'row'      => array( 'type' => 'object', 'description' => '{"sub_field_name": value, ...}' ),
				),
				'required'   => array( 'post_id', 'selector', 'row' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'row_index' => array( 'type' => 'integer' ),
					'error'     => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				if ( ! function_exists( 'add_row' ) ) {
					return array( 'success' => false, 'error' => 'add_row() not available — requires ACF PRO.' );
				}
				$post_id  = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector = sanitize_text_field( (string) $input['selector'] );
				$row      = is_array( $input['row'] ) ? $input['row'] : array();
				$result   = add_row( $selector, $row, $post_id );
				if ( false === $result ) {
					return array( 'success' => false, 'error' => 'Failed to add row.' );
				}
				return array( 'success' => true, 'row_index' => (int) $result );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_repeater_row(): void {
	wp_register_ability(
		'mcp-wp/acf-update-repeater-row',
		array(
			'label'               => __( 'ACF: Update Repeater Row', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update a specific row in an ACF repeater field (1-based index, requires ACF PRO)', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector'  => array( 'type' => 'string', 'description' => 'Repeater field name' ),
					'row_index' => array( 'type' => 'integer', 'description' => 'Row number (1-based)' ),
					'row'       => array( 'type' => 'object', 'description' => '{"sub_field_name": value, ...}' ),
				),
				'required'   => array( 'post_id', 'selector', 'row_index', 'row' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				if ( ! function_exists( 'update_row' ) ) {
					return array( 'success' => false, 'error' => 'update_row() not available — requires ACF PRO.' );
				}
				$post_id   = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector  = sanitize_text_field( (string) $input['selector'] );
				$row_index = (int) $input['row_index'];
				$row       = is_array( $input['row'] ) ? $input['row'] : array();
				if ( false === update_row( $selector, $row_index, $row, $post_id ) ) {
					return array( 'success' => false, 'error' => 'Failed to update row.' );
				}
				return array( 'success' => true, 'message' => 'Row updated.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_delete_repeater_row(): void {
	wp_register_ability(
		'mcp-wp/acf-delete-repeater-row',
		array(
			'label'               => __( 'ACF: Delete Repeater Row', 'mcp-wp-capabilities' ),
			'description'         => __( 'Delete a specific row from an ACF repeater field (1-based index, requires ACF PRO)', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'description' => 'Post ID (int), "option", "term_42", "user_7"' ),
					'selector'  => array( 'type' => 'string', 'description' => 'Repeater field name' ),
					'row_index' => array( 'type' => 'integer', 'description' => 'Row number (1-based)' ),
				),
				'required'   => array( 'post_id', 'selector', 'row_index' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				if ( ! function_exists( 'delete_row' ) ) {
					return array( 'success' => false, 'error' => 'delete_row() not available — requires ACF PRO.' );
				}
				$post_id   = mcp_acf_normalize_post_id( $input['post_id'] );
				$selector  = sanitize_text_field( (string) $input['selector'] );
				$row_index = (int) $input['row_index'];
				if ( false === delete_row( $selector, $row_index, $post_id ) ) {
					return array( 'success' => false, 'error' => 'Failed to delete row.' );
				}
				return array( 'success' => true, 'message' => 'Row deleted.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Options pages
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_options(): void {
	wp_register_ability(
		'mcp-wp/acf-get-options',
		array(
			'label'               => __( 'ACF: Get Options Page Fields', 'mcp-wp-capabilities' ),
			'description'         => __( 'Get all ACF field values stored on the global options page or a named options page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'string', 'description' => 'Options page post_id slug (default "option")' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id = isset( $input['post_id'] ) ? sanitize_text_field( (string) $input['post_id'] ) : 'option';
				$data    = get_fields( $post_id );
				return array( 'success' => true, 'data' => $data ?: array() );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_update_option(): void {
	wp_register_ability(
		'mcp-wp/acf-update-option',
		array(
			'label'               => __( 'ACF: Update Option', 'mcp-wp-capabilities' ),
			'description'         => __( 'Update a single ACF field value on an options page', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'selector' => array( 'type' => 'string', 'description' => 'Field name or key' ),
					'value'    => array( 'description' => 'New value' ),
					'post_id'  => array( 'type' => 'string', 'description' => 'Options page post_id slug (default "option")' ),
				),
				'required'   => array( 'selector', 'value' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id  = isset( $input['post_id'] ) ? sanitize_text_field( (string) $input['post_id'] ) : 'option';
				$selector = sanitize_text_field( (string) $input['selector'] );
				if ( false === update_field( $selector, $input['value'], $post_id ) ) {
					return array( 'success' => false, 'error' => 'Failed to update option.' );
				}
				return array( 'success' => true, 'message' => 'Option updated.' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

function mcp_wp_register_acf_get_field_types(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field-types',
		array(
			'label'               => __( 'ACF: Get Field Types', 'mcp-wp-capabilities' ),
			'description'         => __( 'List all registered ACF field types with their categories and PRO status', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'data'    => array( 'type' => 'array' ),
					'error'   => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function () {
				return MCP_WP_Ability_Helpers::check_user_capability( 'manage_options' );
			},
			'execute_callback'    => static function () {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$types = array(
					array( 'type' => 'text',             'label' => 'Text',             'category' => 'basic',      'pro' => false ),
					array( 'type' => 'textarea',         'label' => 'Textarea',         'category' => 'basic',      'pro' => false ),
					array( 'type' => 'number',           'label' => 'Number',           'category' => 'basic',      'pro' => false ),
					array( 'type' => 'range',            'label' => 'Range',            'category' => 'basic',      'pro' => false ),
					array( 'type' => 'email',            'label' => 'Email',            'category' => 'basic',      'pro' => false ),
					array( 'type' => 'url',              'label' => 'URL',              'category' => 'basic',      'pro' => false ),
					array( 'type' => 'password',         'label' => 'Password',         'category' => 'basic',      'pro' => false ),
					array( 'type' => 'image',            'label' => 'Image',            'category' => 'content',    'pro' => false ),
					array( 'type' => 'file',             'label' => 'File',             'category' => 'content',    'pro' => false ),
					array( 'type' => 'wysiwyg',          'label' => 'WYSIWYG',          'category' => 'content',    'pro' => false ),
					array( 'type' => 'oembed',           'label' => 'oEmbed',           'category' => 'content',    'pro' => false ),
					array( 'type' => 'gallery',          'label' => 'Gallery',          'category' => 'content',    'pro' => true  ),
					array( 'type' => 'select',           'label' => 'Select',           'category' => 'choice',     'pro' => false ),
					array( 'type' => 'checkbox',         'label' => 'Checkbox',         'category' => 'choice',     'pro' => false ),
					array( 'type' => 'radio',            'label' => 'Radio',            'category' => 'choice',     'pro' => false ),
					array( 'type' => 'button_group',     'label' => 'Button Group',     'category' => 'choice',     'pro' => false ),
					array( 'type' => 'true_false',       'label' => 'True / False',     'category' => 'choice',     'pro' => false ),
					array( 'type' => 'link',             'label' => 'Link',             'category' => 'relational', 'pro' => false ),
					array( 'type' => 'post_object',      'label' => 'Post Object',      'category' => 'relational', 'pro' => false ),
					array( 'type' => 'page_link',        'label' => 'Page Link',        'category' => 'relational', 'pro' => false ),
					array( 'type' => 'relationship',     'label' => 'Relationship',     'category' => 'relational', 'pro' => false ),
					array( 'type' => 'taxonomy',         'label' => 'Taxonomy',         'category' => 'relational', 'pro' => false ),
					array( 'type' => 'user',             'label' => 'User',             'category' => 'relational', 'pro' => false ),
					array( 'type' => 'google_map',       'label' => 'Google Map',       'category' => 'jquery',     'pro' => false ),
					array( 'type' => 'date_picker',      'label' => 'Date Picker',      'category' => 'jquery',     'pro' => false ),
					array( 'type' => 'date_time_picker', 'label' => 'Date Time Picker', 'category' => 'jquery',     'pro' => false ),
					array( 'type' => 'time_picker',      'label' => 'Time Picker',      'category' => 'jquery',     'pro' => false ),
					array( 'type' => 'color_picker',     'label' => 'Color Picker',     'category' => 'jquery',     'pro' => false ),
					array( 'type' => 'message',          'label' => 'Message',          'category' => 'layout',     'pro' => false ),
					array( 'type' => 'accordion',        'label' => 'Accordion',        'category' => 'layout',     'pro' => false ),
					array( 'type' => 'tab',              'label' => 'Tab',              'category' => 'layout',     'pro' => false ),
					array( 'type' => 'group',            'label' => 'Group',            'category' => 'layout',     'pro' => false ),
					array( 'type' => 'repeater',         'label' => 'Repeater',         'category' => 'layout',     'pro' => true  ),
					array( 'type' => 'flexible_content', 'label' => 'Flexible Content', 'category' => 'layout',     'pro' => true  ),
					array( 'type' => 'clone',            'label' => 'Clone',            'category' => 'layout',     'pro' => true  ),
				);

				if ( function_exists( 'acf_get_field_types' ) ) {
					$built_in = array_column( $types, 'type' );
					foreach ( acf_get_field_types() as $type_key => $type_obj ) {
						if ( ! in_array( $type_key, $built_in, true ) ) {
							$types[] = array(
								'type'     => $type_key,
								'label'    => is_object( $type_obj ) && property_exists( $type_obj, 'label' ) ? $type_obj->label : $type_key,
								'category' => 'custom',
								'pro'      => false,
							);
						}
					}
				}

				return array( 'success' => true, 'data' => $types );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

function mcp_wp_register_acf_get_field_groups_for_post(): void {
	wp_register_ability(
		'mcp-wp/acf-get-field-groups-for-post',
		array(
			'label'               => __( 'ACF: Get Field Groups for Post', 'mcp-wp-capabilities' ),
			'description'         => __( 'List all ACF field groups that apply to a specific post based on location rules, with full field schemas', 'mcp-wp-capabilities' ),
			'category'            => 'mcp-wp',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'        => array( 'type' => 'integer', 'description' => 'Post/page/CPT ID' ),
					'include_fields' => array( 'type' => 'boolean', 'description' => 'Include field schemas (default: true)' ),
				),
				'required'   => array( 'post_id' ),
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
				return MCP_WP_Ability_Helpers::check_user_capability( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ) {
				if ( ! mcp_acf_is_active() ) {
					return mcp_acf_not_active();
				}
				$post_id        = absint( $input['post_id'] );
				$include_fields = ! array_key_exists( 'include_fields', $input ) || ! empty( $input['include_fields'] );
				$post           = get_post( $post_id );
				if ( ! $post ) {
					return array( 'success' => false, 'error' => "Post {$post_id} not found." );
				}
				$groups = acf_get_field_groups( array( 'post_id' => $post_id, 'post_type' => $post->post_type ) );
				$data   = array();
				foreach ( $groups as $group ) {
					$entry = array(
						'key'         => $group['key'],
						'title'       => $group['title'],
						'description' => $group['description'] ?? '',
					);
					if ( $include_fields ) {
						$fields          = acf_get_fields( $group['key'] );
						$entry['fields'] = is_array( $fields )
							? array_values( array_map( 'mcp_acf_describe_field', $fields ) ) : array();
					}
					$data[] = $entry;
				}
				return array( 'success' => true, 'data' => $data, 'total' => count( $data ) );
			},
			'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
		)
	);
}

// ---------------------------------------------------------------------------
// Registration entry point
// ---------------------------------------------------------------------------

/**
 * Register all ACF abilities. Silently skips when ACF is not installed.
 */
function mcp_wp_register_acf_abilities(): void {
	if ( ! mcp_acf_is_active() ) {
		return;
	}

	mcp_wp_register_acf_check();

	mcp_wp_register_acf_get_field_groups();
	mcp_wp_register_acf_get_field_group();
	mcp_wp_register_acf_create_field_group();
	mcp_wp_register_acf_update_field_group();
	mcp_wp_register_acf_delete_field_group();

	mcp_wp_register_acf_get_fields();
	mcp_wp_register_acf_get_field();
	mcp_wp_register_acf_create_field();
	mcp_wp_register_acf_update_field();
	mcp_wp_register_acf_delete_field();

	mcp_wp_register_acf_get_post_fields();
	mcp_wp_register_acf_update_post_field();
	mcp_wp_register_acf_update_post_fields();
	mcp_wp_register_acf_delete_post_field();
	mcp_wp_register_acf_get_field_object();

	mcp_wp_register_acf_get_repeater_rows();
	mcp_wp_register_acf_add_repeater_row();
	mcp_wp_register_acf_update_repeater_row();
	mcp_wp_register_acf_delete_repeater_row();

	mcp_wp_register_acf_get_options();
	mcp_wp_register_acf_update_option();

	mcp_wp_register_acf_get_field_types();
	mcp_wp_register_acf_get_field_groups_for_post();
}
