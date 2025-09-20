<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman create subscribe to newsletter widget.
 * Description: Adds subscribe form to page.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Newsman create subscribe to newsletter widget.
 *
 * @codingStandardsIgnoreStart PEAR.NamingConventions.ValidClassName.Invalid
 */
class Newsman_subscribe_widget extends WP_Widget {
	/**
	 * Class constructor.
	 *
	 * @codingStandardsIgnoreEnd
	 */
	public function __construct() {
		parent::__construct(
			// Base ID of your widget.
			'Newsman_Subscribe_Widget',
			// Widget name will appear in UI.
			__( 'Newsman Form', 'newsman' ),
			// Widget description.
			array( 'description' => __( 'Display newsman subscription form on site', 'newsman' ) )
		);
	}

	/**
	 * Creating widget front-end.
	 * This is where the action happens.
	 *
	 * @param array $args Widget args.
	 * @param array $instance Widget details array.
	 * @return void
	 * @codingStandardsIgnoreEnd
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		// Before and after widget arguments are defined by themes.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['before_title'] . $title . $args['after_title'];
		}

		// This is where you run the code and display the output.
		$wp_newsman = new WP_Newsman();
		$wp_newsman->newsman_display_form();
	}

	/**
	 *
	 * Display widget form.
	 *
	 * @param array $instance Widget details array.
	 * @return void
	 */
	public function form( $instance ) {
		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'New title', 'newsman' );
		}
		// Widget admin form.
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/>
		</p>
		<?php
	}

	/**
	 * Updating widget replacing old instances with new.
	 *
	 * @param array $new_instance New widget details array.
	 * @param array $old_instance Old widget details array.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}

/**
 * Register and load the widget.
 *
 * @codingStandardsIgnoreStart Universal.Files.SeparateFunctionsFromOO.Mixed
 */
function wpb_load_widget() {
	register_widget( 'Newsman_subscribe_widget' );
}

/**
 * @codingStandardsIgnoreEnd
 */
add_action( 'widgets_init', 'wpb_load_widget' );
