<?php
/**
 * Shortcodes: [retirement_tracker_dashboard], [retirement_tracker_form]
 */

defined( 'ABSPATH' ) || exit;

class RT_Shortcodes {

	public static function register() {
		add_shortcode( 'retirement_tracker_dashboard', array( __CLASS__, 'dashboard' ) );
		add_shortcode( 'retirement_tracker_form', array( __CLASS__, 'form' ) );
	}

	public static function get_form_url() {
		$id = (int) get_option( 'rt_form_page_id', 0 );
		return $id ? get_permalink( $id ) : '#';
	}

	public static function get_dashboard_url() {
		$id = (int) get_option( 'rt_dashboard_page_id', 0 );
		return $id ? get_permalink( $id ) : '#';
	}

	public static function dashboard( $atts ) {
		$atts  = shortcode_atts( array( 'mode' => 'simple', 'dummy' => '0' ), $atts, 'retirement_tracker_dashboard' );
		$dummy = $atts['dummy'] === '1' || $atts['dummy'] === 'yes' || isset( $_GET['rt_dummy'] );
		if ( $atts['mode'] === 'full' ) {
			return self::dashboard_full( $dummy );
		}
		return self::dashboard_simple();
	}

	public static function dashboard_simple() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to see your retirement numbers.', 'retirement-tracker' ) . '</p>';
		}

		$scenario = RT_DB::get_scenario();
		$summary  = $scenario ? ( $scenario['summary'] ?? null ) : null;
		$updated  = $scenario && ! empty( $scenario['updated_at'] ) ? $scenario['updated_at'] : null;

		ob_start();
		?>
		<div class="rt-dashboard">
			<?php if ( $updated ) : ?>
				<p class="rt-muted"><?php echo esc_html( sprintf( __( 'Last updated %s', 'retirement-tracker' ), date_i18n( get_option( 'date_format' ), strtotime( $updated ) ) ) ); ?></p>
			<?php endif; ?>

			<?php if ( ! $summary ) : ?>
				<div class="rt-card rt-card-empty">
					<p class="rt-muted"><?php esc_html_e( "You haven't entered your numbers yet.", 'retirement-tracker' ); ?></p>
					<p><a href="<?php echo esc_url( self::get_form_url() ); ?>" class="rt-btn rt-btn-primary"><?php esc_html_e( 'Add my numbers', 'retirement-tracker' ); ?></a></p>
				</div>
			<?php else : ?>
				<div class="rt-grid">
					<div class="rt-card">
						<p class="rt-label"><?php esc_html_e( 'Pot at retirement', 'retirement-tracker' ); ?></p>
						<p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAtRetirement'] ?? 0 ) ); ?></p>
					</div>
					<div class="rt-card">
						<p class="rt-label"><?php esc_html_e( 'Pot at 67', 'retirement-tracker' ); ?></p>
						<p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAt67'] ?? 0 ) ); ?></p>
					</div>
					<div class="rt-card">
						<p class="rt-label"><?php esc_html_e( 'Pot at 90', 'retirement-tracker' ); ?></p>
						<p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAt90'] ?? 0 ) ); ?></p>
					</div>
					<div class="rt-card">
						<p class="rt-label"><?php esc_html_e( 'Sustainability', 'retirement-tracker' ); ?></p>
						<p class="rt-value">
							<?php
							$run_out = $summary['runOutAge'] ?? null;
							$last    = $summary['lastAge'] ?? null;
							if ( $run_out !== null && $run_out !== '' ) {
								echo '<span class="rt-fail">' . esc_html( sprintf( __( 'Shortfall from %d', 'retirement-tracker' ), $run_out ) ) . '</span>';
							} else {
								echo '<span class="rt-ok">' . esc_html( sprintf( __( 'OK to %d', 'retirement-tracker' ), $last ?: '—' ) ) . '</span>';
							}
							?>
						</p>
					</div>
				</div>
				<p><a href="<?php echo esc_url( self::get_form_url() ); ?>" class="rt-btn rt-btn-secondary"><?php esc_html_e( 'Update my numbers', 'retirement-tracker' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Full dashboard: drag-and-drop widgets, all inputs/outputs, year-by-year table. Optional dummy data.
	 */
	public static function dashboard_full( $dummy = false ) {
		if ( ! is_user_logged_in() && ! $dummy ) {
			return '<p>' . esc_html__( 'Please log in to see your retirement numbers.', 'retirement-tracker' ) . '</p>';
		}

		if ( $dummy ) {
			$dummy_inputs = array(
				'currentAge'        => 45,
				'retirementAge'     => 60,
				'lifeHorizon'       => 95,
				'cash'              => 50000,
				'isa'               => 150000,
				'gia'               => 30000,
				'pensionPot'         => 250000,
				'statePensionAge'    => 67,
				'statePensionAnnual' => 11500,
				'annualSpending'     => 35000,
				'realReturnPct'      => 0.04,
				'realReturnCashPct'  => 0.01,
			);
			$result   = RT_Projection::run( $dummy_inputs );
			$summary  = $result['summary'];
			$inputs   = $dummy_inputs;
			$years    = $result['years'];
			$updated  = null;
		} else {
			$scenario = RT_DB::get_scenario();
			if ( ! $scenario || empty( $scenario['summary'] ) ) {
				ob_start();
				?>
				<div class="rt-dashboard-full rt-empty">
					<p class="rt-muted"><?php esc_html_e( "You haven't entered your numbers yet.", 'retirement-tracker' ); ?></p>
					<p><a href="<?php echo esc_url( self::get_form_url() ); ?>" class="rt-btn rt-btn-primary"><?php esc_html_e( 'Add my numbers', 'retirement-tracker' ); ?></a></p>
				</div>
				<?php
				return ob_get_clean();
			}
			$inputs  = $scenario['inputs'];
			$summary = $scenario['summary'];
			$updated = $scenario['updated_at'] ?? null;
			$result  = RT_Projection::run( $inputs );
			$years   = $result['years'];
		}

		$default_order = array( 'summary', 'pots_today', 'ages', 'pots', 'state', 'spending', 'projection' );
		$saved        = get_user_meta( get_current_user_id(), 'rt_dashboard_widget_order', true );
		$widget_order = $dummy ? $default_order : (array) json_decode( $saved ?: '[]', true );
		if ( empty( $widget_order ) ) {
			$widget_order = $default_order;
		}

		wp_enqueue_style( 'rt-shortcodes', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-shortcodes.css', array(), RT_VERSION );
		wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', array(), '1.15.0', true );
		wp_localize_script( 'sortablejs', 'rtDashboard', array( 'nonce' => wp_create_nonce( 'rt_save_widget_order' ) ) );
		wp_add_inline_script( 'sortablejs', self::dashboard_full_js(), 'after' );

		ob_start();
		?>
		<div class="rt-dashboard-full" data-dummy="<?php echo $dummy ? '1' : '0'; ?>" data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php if ( $updated ) : ?>
				<p class="rt-muted"><?php echo esc_html( sprintf( __( 'Last updated %s', 'retirement-tracker' ), date_i18n( get_option( 'date_format' ), strtotime( $updated ) ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( $dummy ) : ?>
				<p class="rt-dummy-banner"><?php esc_html_e( 'Sample data — use for demo and feedback.', 'retirement-tracker' ); ?></p>
			<?php endif; ?>
			<?php if ( isset( $_GET['rt_saved'] ) ) : ?>
				<p class="rt-message rt-ok"><?php esc_html_e( 'Saved. Your numbers have been updated.', 'retirement-tracker' ); ?></p>
			<?php endif; ?>
			<?php if ( ! $dummy ) : ?>
				<p class="rt-edit-link"><a href="<?php echo esc_url( self::get_form_url() ); ?>" class="rt-btn rt-btn-secondary"><?php esc_html_e( 'Update my numbers', 'retirement-tracker' ); ?></a></p>
			<?php endif; ?>

			<div class="rt-widgets" id="rt-widgets">
				<?php
				$all_ids = array( 'summary', 'pots_today', 'ages', 'pots', 'state', 'spending', 'projection' );
				$order   = array_merge( array_intersect( $widget_order, $all_ids ), array_diff( $all_ids, $widget_order ) );
				foreach ( $order as $id ) {
					self::render_widget( $id, $inputs, $summary, $years );
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_widget( $id, $inputs, $summary, $years ) {
		$label = array(
			'summary'    => __( 'Your numbers', 'retirement-tracker' ),
			'pots_today' => __( 'Total pots today', 'retirement-tracker' ),
			'ages'       => __( 'Ages', 'retirement-tracker' ),
			'pots'       => __( 'Pots (today)', 'retirement-tracker' ),
			'state'      => __( 'State pension', 'retirement-tracker' ),
			'spending'   => __( 'Spending & returns', 'retirement-tracker' ),
			'projection' => __( 'Year-by-year projection', 'retirement-tracker' ),
		);
		$label = $label[ $id ] ?? $id;
		?>
		<div class="rt-widget" data-widget="<?php echo esc_attr( $id ); ?>">
			<div class="rt-widget-handle" title="<?php esc_attr_e( 'Drag to reorder', 'retirement-tracker' ); ?>">⋮⋮</div>
			<div class="rt-widget-body">
				<h3 class="rt-widget-title"><?php echo esc_html( $label ); ?></h3>
				<div class="rt-widget-content">
					<?php
					switch ( $id ) {
						case 'summary':
							$run_out = $summary['runOutAge'] ?? null;
							$last    = $summary['lastAge'] ?? null;
							?>
							<div class="rt-grid rt-grid-2">
								<div class="rt-card"><p class="rt-label"><?php esc_html_e( 'Pot at retirement', 'retirement-tracker' ); ?></p><p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAtRetirement'] ?? 0 ) ); ?></p></div>
								<div class="rt-card"><p class="rt-label"><?php esc_html_e( 'Pot at 67', 'retirement-tracker' ); ?></p><p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAt67'] ?? 0 ) ); ?></p></div>
								<div class="rt-card"><p class="rt-label"><?php esc_html_e( 'Pot at 90', 'retirement-tracker' ); ?></p><p class="rt-value"><?php echo esc_html( self::format_money( $summary['potAt90'] ?? 0 ) ); ?></p></div>
								<div class="rt-card"><p class="rt-label"><?php esc_html_e( 'Sustainability', 'retirement-tracker' ); ?></p><p class="rt-value"><?php echo $run_out !== null && $run_out !== '' ? '<span class="rt-fail">' . esc_html( sprintf( __( 'Shortfall from %d', 'retirement-tracker' ), $run_out ) ) . '</span>' : '<span class="rt-ok">' . esc_html( sprintf( __( 'OK to %d', 'retirement-tracker' ), $last ?: '—' ) ) . '</span>'; ?></p></div>
							</div>
							<?php break;
						case 'pots_today':
							$total = ( $inputs['cash'] ?? 0 ) + ( $inputs['isa'] ?? 0 ) + ( $inputs['gia'] ?? 0 ) + ( $inputs['pensionPot'] ?? 0 );
							?>
							<p class="rt-value rt-value-large"><?php echo esc_html( self::format_money( $total ) ); ?></p>
							<?php break;
						case 'ages':
							?>
							<p><strong><?php esc_html_e( 'Current', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( $inputs['currentAge'] ?? '—' ); ?></p>
							<p><strong><?php esc_html_e( 'Retirement', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( $inputs['retirementAge'] ?? '—' ); ?></p>
							<p><strong><?php esc_html_e( 'Plan to age', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( $inputs['lifeHorizon'] ?? '—' ); ?></p>
							<?php break;
						case 'pots':
							?>
							<p><strong><?php esc_html_e( 'Cash', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['cash'] ?? 0 ) ); ?></p>
							<p><strong><?php esc_html_e( 'ISA', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['isa'] ?? 0 ) ); ?></p>
							<p><strong><?php esc_html_e( 'GIA', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['gia'] ?? 0 ) ); ?></p>
							<p><strong><?php esc_html_e( 'Pension', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['pensionPot'] ?? 0 ) ); ?></p>
							<?php break;
						case 'state':
							?>
							<p><strong><?php esc_html_e( 'Age', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( $inputs['statePensionAge'] ?? '—' ); ?></p>
							<p><strong><?php esc_html_e( 'Annual', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['statePensionAnnual'] ?? 0 ) ); ?></p>
							<?php break;
						case 'spending':
							?>
							<p><strong><?php esc_html_e( 'Annual spending', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( self::format_money( $inputs['annualSpending'] ?? 0 ) ); ?></p>
							<p><strong><?php esc_html_e( 'Investment return (real)', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( number_format( ( $inputs['realReturnPct'] ?? 0 ) * 100, 1 ) ); ?>%</p>
							<p><strong><?php esc_html_e( 'Cash return (real)', 'retirement-tracker' ); ?>:</strong> <?php echo esc_html( number_format( ( $inputs['realReturnCashPct'] ?? 0 ) * 100, 1 ) ); ?>%</p>
							<?php break;
						case 'projection':
							?>
							<div class="rt-projection-table-wrap">
								<table class="rt-projection-table">
									<thead><tr><th><?php esc_html_e( 'Age', 'retirement-tracker' ); ?></th><th><?php esc_html_e( 'Year', 'retirement-tracker' ); ?></th><th><?php esc_html_e( 'Total pot', 'retirement-tracker' ); ?></th><th><?php esc_html_e( 'Status', 'retirement-tracker' ); ?></th></tr></thead>
									<tbody>
									<?php foreach ( array_slice( $years, 0, 25 ) as $y ) : ?>
										<tr class="<?php echo ! empty( $y['fail'] ) ? 'rt-fail-row' : ''; ?>">
											<td><?php echo esc_html( $y['age'] ); ?></td>
											<td><?php echo esc_html( $y['year'] ); ?></td>
											<td><?php echo esc_html( self::format_money( $y['total'] ) ); ?></td>
											<td><?php echo ! empty( $y['fail'] ) ? esc_html__( 'Shortfall', 'retirement-tracker' ) : '—'; ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
								<?php if ( count( $years ) > 25 ) : ?>
									<p class="rt-muted"><?php echo esc_html( sprintf( __( '… and %d more years', 'retirement-tracker' ), count( $years ) - 25 ) ); ?></p>
								<?php endif; ?>
							</div>
							<?php break;
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function dashboard_full_js() {
		return "
		document.addEventListener('DOMContentLoaded', function() {
			var el = document.getElementById('rt-widgets');
			if (!el || el.closest('.rt-dashboard-full').dataset.dummy === '1') return;
			new Sortable(el, {
				handle: '.rt-widget-handle',
				animation: 150,
				onEnd: function() {
					var ids = [];
					el.querySelectorAll('.rt-widget').forEach(function(w) { ids.push(w.dataset.widget); });
					var xhr = new XMLHttpRequest();
					var dash = el.closest('.rt-dashboard-full');
					var url = dash && dash.dataset.ajaxUrl ? dash.dataset.ajaxUrl : '';
					if (!url) return;
					var form = new FormData();
					form.append('action', 'rt_save_widget_order');
					form.append('nonce', (typeof rtDashboard !== 'undefined' && rtDashboard.nonce ? rtDashboard.nonce : ''));
					form.append('order', JSON.stringify(ids));
					var xhr = new XMLHttpRequest();
					xhr.open('POST', url);
					xhr.send(form);
				}
			});
		});
		";
	}

	public static function form( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to update your retirement numbers.', 'retirement-tracker' ) . '</p>';
		}

		$scenario = RT_DB::get_scenario();
		$inputs   = $scenario ? ( $scenario['inputs'] ?? array() ) : array();
		$defaults = array(
			'currentAge'        => 40,
			'retirementAge'     => 65,
			'lifeHorizon'       => 95,
			'cash'              => 0,
			'isa'               => 0,
			'gia'               => 0,
			'pensionPot'         => 0,
			'statePensionAge'    => 67,
			'statePensionAnnual' => 0,
			'annualSpending'     => 30000,
			'realReturnPct'      => 0.04,
			'realReturnCashPct'  => 0.01,
		);
		$inputs = array_merge( $defaults, $inputs );

		$message = '';
		if ( isset( $_POST['rt_save_scenario'] ) && wp_verify_nonce( $_POST['rt_nonce'] ?? '', 'rt_save_scenario' ) ) {
			$raw = array(
				'currentAge'        => (int) ( $_POST['rt_current_age'] ?? 40 ),
				'retirementAge'     => (int) ( $_POST['rt_retirement_age'] ?? 65 ),
				'lifeHorizon'       => (int) ( $_POST['rt_life_horizon'] ?? 95 ),
				'cash'              => self::parse_money( $_POST['rt_cash'] ?? '' ),
				'isa'               => self::parse_money( $_POST['rt_isa'] ?? '' ),
				'gia'               => self::parse_money( $_POST['rt_gia'] ?? '' ),
				'pensionPot'         => self::parse_money( $_POST['rt_pension'] ?? '' ),
				'statePensionAge'    => (int) ( $_POST['rt_state_age'] ?? 67 ),
				'statePensionAnnual' => self::parse_money( $_POST['rt_state_annual'] ?? '' ),
				'annualSpending'     => self::parse_money( $_POST['rt_spending'] ?? '' ),
				'realReturnPct'      => self::parse_pct( $_POST['rt_return'] ?? '' ),
				'realReturnCashPct'  => self::parse_pct( $_POST['rt_return_cash'] ?? '' ),
			);
			RT_DB::save_scenario( get_current_user_id(), $raw );
			$inputs = $raw;
			$dashboard_url = self::get_dashboard_url();
			if ( $dashboard_url !== '#' ) {
				wp_safe_redirect( add_query_arg( 'rt_saved', '1', $dashboard_url ) );
				exit;
			}
			$message = __( 'Saved. Your numbers have been updated.', 'retirement-tracker' );
		}

		wp_enqueue_style( 'rt-shortcodes', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-shortcodes.css', array(), RT_VERSION );

		ob_start();
		?>
		<div class="rt-form-wrap">
			<?php if ( $message ) : ?>
				<p class="rt-message rt-ok"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>

			<form method="post" class="rt-form">
				<?php wp_nonce_field( 'rt_save_scenario', 'rt_nonce' ); ?>
				<input type="hidden" name="rt_save_scenario" value="1" />

				<section class="rt-section">
					<h3 class="rt-section-title"><?php esc_html_e( 'Ages', 'retirement-tracker' ); ?></h3>
					<div class="rt-grid rt-grid-3">
						<div class="rt-field">
							<label for="rt_current_age"><?php esc_html_e( 'Current age', 'retirement-tracker' ); ?></label>
							<input type="number" id="rt_current_age" name="rt_current_age" min="18" max="100" value="<?php echo esc_attr( $inputs['currentAge'] ); ?>" />
						</div>
						<div class="rt-field">
							<label for="rt_retirement_age"><?php esc_html_e( 'Retirement age', 'retirement-tracker' ); ?></label>
							<input type="number" id="rt_retirement_age" name="rt_retirement_age" min="40" max="90" value="<?php echo esc_attr( $inputs['retirementAge'] ); ?>" />
						</div>
						<div class="rt-field">
							<label for="rt_life_horizon"><?php esc_html_e( 'Plan to age', 'retirement-tracker' ); ?></label>
							<input type="number" id="rt_life_horizon" name="rt_life_horizon" min="70" max="100" value="<?php echo esc_attr( $inputs['lifeHorizon'] ); ?>" />
						</div>
					</div>
				</section>

				<section class="rt-section">
					<h3 class="rt-section-title"><?php esc_html_e( 'Pots (today)', 'retirement-tracker' ); ?></h3>
					<div class="rt-grid rt-grid-2">
						<?php
						$pots = array(
							array( 'rt_cash', 'cash', __( 'Cash / emergency fund', 'retirement-tracker' ) ),
							array( 'rt_isa', 'isa', __( 'ISA', 'retirement-tracker' ) ),
							array( 'rt_gia', 'gia', __( 'GIA / other investments', 'retirement-tracker' ) ),
							array( 'rt_pension', 'pensionPot', __( 'DC pension pot(s)', 'retirement-tracker' ) ),
						);
						foreach ( $pots as $p ) :
							list( $name, $key, $label ) = $p;
							$val = $inputs[ $key ] ?? 0;
							?>
							<div class="rt-field">
								<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
								<input type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" inputmode="decimal" value="<?php echo esc_attr( $val == 0 ? '' : (string) (int) $val ); ?>" placeholder="0" />
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="rt-section">
					<h3 class="rt-section-title"><?php esc_html_e( 'State pension', 'retirement-tracker' ); ?></h3>
					<div class="rt-grid rt-grid-2">
						<div class="rt-field">
							<label for="rt_state_age"><?php esc_html_e( 'State pension age', 'retirement-tracker' ); ?></label>
							<input type="number" id="rt_state_age" name="rt_state_age" min="66" max="68" value="<?php echo esc_attr( $inputs['statePensionAge'] ); ?>" />
						</div>
						<div class="rt-field">
							<label for="rt_state_annual"><?php esc_html_e( 'Annual amount (£)', 'retirement-tracker' ); ?></label>
							<input type="text" id="rt_state_annual" name="rt_state_annual" inputmode="decimal" value="<?php echo esc_attr( $inputs['statePensionAnnual'] == 0 ? '' : (string) (int) $inputs['statePensionAnnual'] ); ?>" placeholder="0" />
						</div>
					</div>
				</section>

				<section class="rt-section">
					<h3 class="rt-section-title"><?php esc_html_e( 'Spending & returns', 'retirement-tracker' ); ?></h3>
					<div class="rt-grid rt-grid-2">
						<div class="rt-field">
							<label for="rt_spending"><?php esc_html_e( 'Annual spending in retirement (£)', 'retirement-tracker' ); ?></label>
							<input type="text" id="rt_spending" name="rt_spending" inputmode="decimal" value="<?php echo esc_attr( $inputs['annualSpending'] == 0 ? '' : (string) (int) $inputs['annualSpending'] ); ?>" placeholder="30000" />
						</div>
						<div class="rt-field">
							<label for="rt_return"><?php esc_html_e( 'Investment return (real) %', 'retirement-tracker' ); ?></label>
							<input type="text" id="rt_return" name="rt_return" inputmode="decimal" value="<?php echo esc_attr( $inputs['realReturnPct'] === 0 ? '' : round( $inputs['realReturnPct'] * 100, 1 ) ); ?>" placeholder="4" />
						</div>
						<div class="rt-field">
							<label for="rt_return_cash"><?php esc_html_e( 'Cash return (real) %', 'retirement-tracker' ); ?></label>
							<input type="text" id="rt_return_cash" name="rt_return_cash" inputmode="decimal" value="<?php echo esc_attr( $inputs['realReturnCashPct'] === 0 ? '' : round( $inputs['realReturnCashPct'] * 100, 1 ) ); ?>" placeholder="1" />
						</div>
					</div>
				</section>

				<button type="submit" class="rt-btn rt-btn-primary rt-btn-block"><?php esc_html_e( 'Save and see my numbers', 'retirement-tracker' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function format_money( $n ) {
		return '£' . number_format( (float) $n, 0 );
	}

	private static function parse_money( $s ) {
		$n = (float) preg_replace( '/[^0-9.-]/', '', (string) $s );
		return is_finite( $n ) ? $n : 0;
	}

	private static function parse_pct( $s ) {
		$n = (float) preg_replace( '/[^0-9.-]/', '', (string) $s );
		return is_finite( $n ) ? $n / 100 : 0;
	}
}
