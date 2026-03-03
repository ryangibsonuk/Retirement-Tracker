<?php
/**
 * Form view: drag-and-drop blocks for building retirement pot.
 * Vars: $inputs, $message
 */
defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'rt-shortcodes', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-shortcodes.css', array(), RT_VERSION );
wp_enqueue_style( 'rt-form', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-form.css', array( 'rt-shortcodes' ), RT_VERSION );
wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', array(), '1.15.0', true );
wp_add_inline_script( 'sortablejs', "
document.addEventListener('DOMContentLoaded', function() {
	var el = document.getElementById('rt-form-blocks');
	if (el) new Sortable(el, { handle: '.rt-form-block-handle', animation: 150 });
});
", 'after' );
?>
<div class="rt-form-app">
	<header class="rt-form-header">
		<div class="rt-form-header-inner">
			<a href="<?php echo esc_url( RT_Shortcodes::get_dashboard_url() ); ?>" class="rt-form-back">← <?php esc_html_e( 'Back to dashboard', 'retirement-tracker' ); ?></a>
			<h1><?php esc_html_e( 'Build your retirement pot', 'retirement-tracker' ); ?></h1>
			<p class="rt-form-lead"><?php esc_html_e( 'Drag blocks to reorder. Add your pots and income streams — we use cash/ISA/GIA first, then pensions from 57, state pension when it starts.', 'retirement-tracker' ); ?></p>
		</div>
	</header>

	<main class="rt-form-main">
		<?php if ( $message ) : ?>
			<div class="rt-form-message rt-ok"><?php echo esc_html( $message ); ?></div>
		<?php endif; ?>

		<form method="post" class="rt-form-builder">
			<?php wp_nonce_field( 'rt_save_scenario', 'rt_nonce' ); ?>
			<input type="hidden" name="rt_save_scenario" value="1" />

			<!-- Ages -->
			<section class="rt-form-section">
				<h2 class="rt-form-section-title"><?php esc_html_e( 'Your ages', 'retirement-tracker' ); ?></h2>
				<p class="rt-form-hint"><?php esc_html_e( 'We project from today to your plan-to age. Retirement age controls when withdrawals begin.', 'retirement-tracker' ); ?></p>
				<div class="rt-form-row rt-form-row-3">
					<div class="rt-form-field">
						<label for="rt_current_age"><?php esc_html_e( 'Current age', 'retirement-tracker' ); ?></label>
						<input type="number" id="rt_current_age" name="rt_current_age" min="18" max="100" value="<?php echo esc_attr( $inputs['currentAge'] ?? 44 ); ?>" />
					</div>
					<div class="rt-form-field">
						<label for="rt_retirement_age"><?php esc_html_e( 'Retirement age', 'retirement-tracker' ); ?></label>
						<input type="number" id="rt_retirement_age" name="rt_retirement_age" min="40" max="90" value="<?php echo esc_attr( $inputs['retirementAge'] ?? 58 ); ?>" />
					</div>
					<div class="rt-form-field">
						<label for="rt_life_horizon"><?php esc_html_e( 'Plan to age', 'retirement-tracker' ); ?></label>
						<input type="number" id="rt_life_horizon" name="rt_life_horizon" min="70" max="100" value="<?php echo esc_attr( $inputs['lifeHorizon'] ?? 95 ); ?>" />
					</div>
				</div>
			</section>

			<!-- Pots & Income blocks -->
			<section class="rt-form-section">
				<h2 class="rt-form-section-title"><?php esc_html_e( 'Pots & income', 'retirement-tracker' ); ?></h2>
				<p class="rt-form-hint"><?php esc_html_e( 'Add your savings and income sources. Accessible pots (cash, ISA, GIA) fund the gap until pension age 57.', 'retirement-tracker' ); ?></p>

				<div class="rt-form-blocks" id="rt-form-blocks">
					<?php
					$dc = (float) ( $inputs['dcPension'] ?? 0 );
					$sipp = (float) ( $inputs['sipp'] ?? 0 );
					if ( $dc == 0 && $sipp == 0 && ! empty( $inputs['pensionPot'] ) ) {
						$dc = (float) $inputs['pensionPot'] * 0.6;
						$sipp = (float) $inputs['pensionPot'] * 0.4;
					}
					$blocks = array(
						array( 'id' => 'cash', 'icon' => '💷', 'label' => __( 'Cash / emergency fund', 'retirement-tracker' ), 'hint' => __( 'Accessible now. Low growth, high flexibility.', 'retirement-tracker' ), 'field' => 'rt_cash', 'key' => 'cash', 'type' => 'pot' ),
						array( 'id' => 'isa', 'icon' => '📈', 'label' => __( 'ISA', 'retirement-tracker' ), 'hint' => __( 'Tax-free. Accessible anytime.', 'retirement-tracker' ), 'field' => 'rt_isa', 'key' => 'isa', 'type' => 'pot' ),
						array( 'id' => 'gia', 'icon' => '📊', 'label' => __( 'GIA / general investments', 'retirement-tracker' ), 'hint' => __( 'Taxable but flexible. Use after ISA.', 'retirement-tracker' ), 'field' => 'rt_gia', 'key' => 'gia', 'type' => 'pot' ),
						array( 'id' => 'dc', 'icon' => '🏢', 'label' => __( 'DC pension (workplace)', 'retirement-tracker' ), 'hint' => __( 'Accessible from 57. Tax relief on contributions.', 'retirement-tracker' ), 'field' => 'rt_dc', 'key' => 'dcPension', 'type' => 'pot' ),
						array( 'id' => 'sipp', 'icon' => '📋', 'label' => __( 'SIPP / personal pension', 'retirement-tracker' ), 'hint' => __( 'Same as DC — access from 57.', 'retirement-tracker' ), 'field' => 'rt_sipp', 'key' => 'sipp', 'type' => 'pot' ),
						array( 'id' => 'db', 'icon' => '🏛️', 'label' => __( 'DB pension', 'retirement-tracker' ), 'hint' => __( 'Defined benefit — annual income, not a pot.', 'retirement-tracker' ), 'fields' => array( array( 'rt_db_annual', 'dbPensionAnnual', __( 'Annual amount (£)', 'retirement-tracker' ) ), array( 'rt_db_age', 'dbPensionAge', __( 'Start age', 'retirement-tracker' ) ) ), 'type' => 'income' ),
						array( 'id' => 'state', 'icon' => '🏷️', 'label' => __( 'State pension', 'retirement-tracker' ), 'hint' => __( 'From gov.uk — typically from 67.', 'retirement-tracker' ), 'fields' => array( array( 'rt_state_annual', 'statePensionAnnual', __( 'Annual amount (£)', 'retirement-tracker' ) ), array( 'rt_state_age', 'statePensionAge', __( 'Start age', 'retirement-tracker' ) ) ), 'type' => 'income' ),
						array( 'id' => 'btl', 'icon' => '🏠', 'label' => __( 'Buy-to-let income', 'retirement-tracker' ), 'hint' => __( 'Net rental income from retirement.', 'retirement-tracker' ), 'field' => 'rt_btl', 'key' => 'btlAnnual', 'type' => 'income', 'suffix' => '/yr' ),
					);
					foreach ( $blocks as $b ) :
						?>
						<div class="rt-form-block" data-block="<?php echo esc_attr( $b['id'] ); ?>">
							<div class="rt-form-block-handle">⋮⋮</div>
							<div class="rt-form-block-body">
								<div class="rt-form-block-header">
									<span class="rt-form-block-icon"><?php echo esc_html( $b['icon'] ); ?></span>
									<div>
										<h3><?php echo esc_html( $b['label'] ); ?></h3>
										<p class="rt-form-block-hint"><?php echo esc_html( $b['hint'] ); ?></p>
									</div>
								</div>
								<div class="rt-form-block-fields">
									<?php
									if ( ! empty( $b['fields'] ) ) {
										foreach ( $b['fields'] as $f ) {
											list( $name, $key, $lbl ) = $f;
											$val = $inputs[ $key ] ?? ( $key === 'statePensionAge' ? 67 : ( $key === 'dbPensionAge' ? 65 : 0 ) );
											if ( $key === 'statePensionAge' || $key === 'dbPensionAge' ) {
												echo '<div class="rt-form-field"><label for="' . esc_attr( $name ) . '">' . esc_html( $lbl ) . '</label><input type="number" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" min="55" max="75" value="' . esc_attr( $val ) . '" /></div>';
											} else {
												echo '<div class="rt-form-field"><label for="' . esc_attr( $name ) . '">' . esc_html( $lbl ) . '</label><input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" inputmode="decimal" value="' . esc_attr( $val == 0 ? '' : (int) $val ) . '" placeholder="0" /></div>';
											}
										}
									} else {
										$val = $inputs[ $b['key'] ] ?? 0;
										if ( $b['id'] === 'dc' ) $val = $dc;
										if ( $b['id'] === 'sipp' ) $val = $sipp;
										$suf = isset( $b['suffix'] ) ? ' <span class="rt-form-suffix">' . esc_html( $b['suffix'] ) . '</span>' : '';
										echo '<div class="rt-form-field"><label for="' . esc_attr( $b['field'] ) . '">' . esc_html__( 'Amount (£)', 'retirement-tracker' ) . $suf . '</label><input type="text" id="' . esc_attr( $b['field'] ) . '" name="' . esc_attr( $b['field'] ) . '" inputmode="decimal" value="' . esc_attr( $val == 0 ? '' : (int) $val ) . '" placeholder="0" /></div>';
									}
									?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- Spending & returns -->
			<section class="rt-form-section">
				<h2 class="rt-form-section-title"><?php esc_html_e( 'Spending & assumptions', 'retirement-tracker' ); ?></h2>
				<p class="rt-form-hint"><?php esc_html_e( 'Annual spending in retirement and real return assumptions (after inflation).', 'retirement-tracker' ); ?></p>
				<div class="rt-form-row rt-form-row-3">
					<div class="rt-form-field">
						<label for="rt_spending"><?php esc_html_e( 'Annual spending in retirement (£)', 'retirement-tracker' ); ?></label>
						<input type="text" id="rt_spending" name="rt_spending" inputmode="decimal" value="<?php echo esc_attr( ( $inputs['annualSpending'] ?? 0 ) == 0 ? '' : (int) $inputs['annualSpending'] ); ?>" placeholder="30000" />
					</div>
					<div class="rt-form-field">
						<label for="rt_return"><?php esc_html_e( 'Investment return (real) %', 'retirement-tracker' ); ?></label>
						<input type="text" id="rt_return" name="rt_return" inputmode="decimal" value="<?php echo esc_attr( ( $inputs['realReturnPct'] ?? 0.04 ) == 0 ? '' : round( ( $inputs['realReturnPct'] ?? 0 ) * 100, 1 ) ); ?>" placeholder="4" />
					</div>
					<div class="rt-form-field">
						<label for="rt_return_cash"><?php esc_html_e( 'Cash return (real) %', 'retirement-tracker' ); ?></label>
						<input type="text" id="rt_return_cash" name="rt_return_cash" inputmode="decimal" value="<?php echo esc_attr( ( $inputs['realReturnCashPct'] ?? 0.01 ) == 0 ? '' : round( ( $inputs['realReturnCashPct'] ?? 0 ) * 100, 1 ) ); ?>" placeholder="1" />
					</div>
				</div>
			</section>

			<button type="submit" class="rt-form-submit"><?php esc_html_e( 'Save and see my numbers', 'retirement-tracker' ); ?></button>
		</form>
	</main>
</div>
