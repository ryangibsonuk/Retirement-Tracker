<?php
/**
 * Plugin Name: Retirement Tracker
 * Description: One scenario per user: retirement age, pots, state pension, spending. Deterministic projection with correct bridging (cash → ISA → GIA → pension from 57, state from 67). Monthly nudge to update.
 * Version: 1.0.0
 * Author: RetirementCalculators.uk
 * License: GPL v2 or later
 * Text Domain: retirement-tracker
 */

defined( 'ABSPATH' ) || exit;

const RT_VERSION = '1.0.0';
const RT_PLUGIN_DIR = __DIR__;
const RT_PLUGIN_SLUG = 'retirement-tracker';

require_once RT_PLUGIN_DIR . '/includes/class-rt-projection.php';
require_once RT_PLUGIN_DIR . '/includes/class-rt-db.php';
require_once RT_PLUGIN_DIR . '/includes/class-rt-suggestions.php';
require_once RT_PLUGIN_DIR . '/shortcodes/class-rt-shortcodes.php';

register_activation_hook( __FILE__, array( 'RT_DB', 'install' ) );

add_action( 'init', function () {
	RT_Shortcodes::register();
	if ( get_option( 'rt_db_version', 0 ) < 2 ) {
		RT_DB::install();
		update_option( 'rt_db_version', 2 );
	}
} );

add_action( 'wp_scheduled_rt_nudge', array( 'RT_DB', 'cron_nudge' ) );

add_action( 'wp_ajax_rt_save_widget_order', function () {
	check_ajax_referer( 'rt_save_widget_order', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error();
	}
	$order = isset( $_POST['order'] ) ? json_decode( stripslashes( $_POST['order'] ), true ) : array();
	if ( is_array( $order ) ) {
		update_user_meta( get_current_user_id(), 'rt_dashboard_widget_order', wp_json_encode( $order ) );
	}
	wp_send_json_success();
} );

add_action( 'admin_menu', function () {
	add_options_page(
		'Retirement Tracker',
		'Retirement Tracker',
		'manage_options',
		RT_PLUGIN_SLUG,
		'rt_render_options_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( RT_PLUGIN_SLUG, 'rt_form_page_id', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
	register_setting( RT_PLUGIN_SLUG, 'rt_dashboard_page_id', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
} );

function rt_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$form_page_id     = (int) get_option( 'rt_form_page_id', 0 );
	$dashboard_page_id = (int) get_option( 'rt_dashboard_page_id', 0 );
	?>
	<div class="wrap">
		<h1>Retirement Tracker</h1>

		<form method="post" action="options.php">
			<?php settings_fields( RT_PLUGIN_SLUG ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="rt_dashboard_page_id"><?php esc_html_e( 'Dashboard page', 'retirement-tracker' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'selected'         => $dashboard_page_id,
							'name'             => 'rt_dashboard_page_id',
							'id'               => 'rt_dashboard_page_id',
							'show_option_none' => __( '— Select —', 'retirement-tracker' ),
							'post_status'      => array( 'publish', 'draft', 'private' ),
						) );
						?>
						<p class="description"><?php esc_html_e( 'Page with dashboard shortcode. Form redirects here after save.', 'retirement-tracker' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rt_form_page_id"><?php esc_html_e( 'Update form page', 'retirement-tracker' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'selected'         => $form_page_id,
							'name'             => 'rt_form_page_id',
							'id'               => 'rt_form_page_id',
							'show_option_none' => __( '— Select —', 'retirement-tracker' ),
							'post_status'      => array( 'publish', 'draft', 'private' ),
						) );
						?>
						<p class="description"><?php esc_html_e( 'Page that contains [retirement_tracker_form]. Used for “Add my numbers” and nudge email links.', 'retirement-tracker' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Shortcodes', 'retirement-tracker' ); ?></h2>
		<ul>
			<li><strong><code>[retirement_tracker_app]</code></strong> — <?php esc_html_e( 'Main app: landing (log in / sign up) when logged out; full dashboard (charts, progress, suggestions) when logged in. Add ?rt_dummy=1 for demo.', 'retirement-tracker' ); ?></li>
			<li><code>[retirement_tracker_dashboard]</code> — <?php esc_html_e( 'Simple summary.', 'retirement-tracker' ); ?></li>
			<li><code>[retirement_tracker_dashboard mode="full"]</code> — <?php esc_html_e( 'Drag-and-drop widgets.', 'retirement-tracker' ); ?></li>
			<li><code>[retirement_tracker_form]</code> — <?php esc_html_e( 'Update my numbers form (requires login).', 'retirement-tracker' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'Create a page for the dashboard and one for the form (or combine). Protect with “logged in only” or a membership plugin if needed.', 'retirement-tracker' ); ?></p>
		<p><?php esc_html_e( 'Monthly nudge runs via WP-Cron (scheduled on plugin activation). Unsubscribe link: add ?retirement_tracker_nudge_unsubscribe=1 to any URL when logged in.', 'retirement-tracker' ); ?></p>
	</div>
	<?php
}

// Unsubscribe: ?retirement_tracker_nudge_unsubscribe=1 (must be logged in)
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['retirement_tracker_nudge_unsubscribe'] ) || ! is_user_logged_in() ) {
		return;
	}
	RT_DB::set_nudge_opted_out( get_current_user_id(), true );
	wp_safe_redirect( remove_query_arg( 'retirement_tracker_nudge_unsubscribe' ) );
	exit;
} );
