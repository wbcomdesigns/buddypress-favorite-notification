<?php
/**
 * Template functions for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output notification icon.
 *
 * @param string $type Notification type.
 */
function bpfn_notification_icon( $type = 'favorite' ) {
	$icons = apply_filters(
		'bpfn_notification_icons',
		array(
			'favorite'         => '<i class="dashicons dashicons-heart"></i>',
			'favorite_comment' => '<i class="dashicons dashicons-comment"></i>',
			'like'             => '<i class="dashicons dashicons-thumbs-up"></i>',
		)
	);

	$icon = isset( $icons[ $type ] ) ? $icons[ $type ] : $icons['favorite'];

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML icon markup from filtered array.
	echo apply_filters( 'bpfn_notification_icon_html', $icon, $type );
}

/**
 * Get notification count HTML.
 *
 * @param int    $count   Notification count.
 * @param string $classes Additional CSS classes.
 * @return string HTML output.
 */
function bpfn_get_notification_count_html( $count, $classes = '' ) {
	if ( $count <= 0 ) {
		return '';
	}

	$classes = 'bpfn-count ' . $classes;

	return sprintf(
		'<span class="%s">%s</span>',
		esc_attr( $classes ),
		number_format_i18n( $count )
	);
}

/**
 * Output notification count.
 *
 * @param int    $count   Notification count.
 * @param string $classes Additional CSS classes.
 */
function bpfn_notification_count( $count, $classes = '' ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in bpfn_get_notification_count_html.
	echo bpfn_get_notification_count_html( $count, $classes );
}

/**
 * Get settings form field.
 *
 * @param string $type  Field type.
 * @param string $name  Field name.
 * @param mixed  $value Field value.
 * @param array  $args  Additional arguments.
 * @return string HTML output.
 */
function bpfn_get_settings_field( $type, $name, $value, $args = array() ) {
	$defaults = array(
		'id'          => $name,
		'class'       => '',
		'label'       => '',
		'description' => '',
		'options'     => array(),
	);

	$args = wp_parse_args( $args, $defaults );

	ob_start();

	switch ( $type ) {
		case 'checkbox':
			?>
			<label for="<?php echo esc_attr( $args['id'] ); ?>">
				<input type="checkbox"
						id="<?php echo esc_attr( $args['id'] ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="1"
						<?php checked( $value, 1 ); ?>
						class="<?php echo esc_attr( $args['class'] ); ?>" />
				<?php echo esc_html( $args['label'] ); ?>
			</label>
			<?php
			break;

		case 'radio':
			foreach ( $args['options'] as $option_value => $option_label ) {
				?>
				<label>
					<input type="radio"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $option_value ); ?>"
							<?php checked( $value, $option_value ); ?>
							class="<?php echo esc_attr( $args['class'] ); ?>" />
					<?php echo esc_html( $option_label ); ?>
				</label><br>
				<?php
			}
			break;

		case 'select':
			?>
			<select id="<?php echo esc_attr( $args['id'] ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					class="<?php echo esc_attr( $args['class'] ); ?>">
				<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
						<?php echo esc_html( $option_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
			break;
	}

	if ( ! empty( $args['description'] ) ) {
		echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
	}

	return ob_get_clean();
}

/**
 * Output settings field.
 *
 * @param string $type  Field type.
 * @param string $name  Field name.
 * @param mixed  $value Field value.
 * @param array  $args  Additional arguments.
 */
function bpfn_settings_field( $type, $name, $value, $args = array() ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in bpfn_get_settings_field.
	echo bpfn_get_settings_field( $type, $name, $value, $args );
}

/**
 * Get template part.
 *
 * @param string $template Template name.
 * @param array  $args     Arguments to pass to template.
 */
function bpfn_get_template_part( $template, $args = array() ) {
	// Extract args for template.
	if ( ! empty( $args ) ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction.
		extract( $args );
	}

	// Template locations.
	$locations = array(
		get_stylesheet_directory() . '/buddypress/bp-favorite-notification/' . $template . '.php',
		get_template_directory() . '/buddypress/bp-favorite-notification/' . $template . '.php',
		BPFN_TEMPLATES_PATH . $template . '.php',
	);

	// Find and include template.
	foreach ( $locations as $location ) {
		if ( file_exists( $location ) ) {
			include $location;
			return;
		}
	}

	// Template not found.
	do_action( 'bpfn_template_not_found', $template, $args );
}

/**
 * Check if current page should show notifications.
 *
 * @return bool Whether notifications should be shown.
 */
function bpfn_should_show_notifications() {
	// Must be logged in.
	if ( ! is_user_logged_in() ) {
		return false;
	}

	// Must have notifications component active.
	if ( ! bp_is_active( 'notifications' ) ) {
		return false;
	}

	// Allow filtering.
	return apply_filters( 'bpfn_should_show_notifications', true );
}
