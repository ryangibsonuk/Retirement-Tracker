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

	public static function dashboard( $atts ) {
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
					<p><a href="<?php echo esc_url( get_permalink( (int) get_option( 'rt_form_page_id', 0 ) ) ?: '#' ); ?>" class="rt-btn rt-btn-primary"><?php esc_html_e( 'Add my numbers', 'retirement-tracker' ); ?></a></p>
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
				<p><a href="<?php echo esc_url( get_permalink( (int) get_option( 'rt_form_page_id', 0 ) ) ?: '#' ); ?>" class="rt-btn rt-btn-secondary"><?php esc_html_e( 'Update my numbers', 'retirement-tracker' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
		if ( isset( $_POST['rt_save_scenario'] ) && check_admin_referer( 'rt_save_scenario', 'rt_nonce' ) ) {
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
			$message = __( 'Saved. Your numbers have been updated.', 'retirement-tracker' );
			$inputs  = $raw;
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
