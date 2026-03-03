<?php
/**
 * App view: full SaaS-style dashboard. Used by [retirement_tracker_app].
 * Vars: $dummy, $inputs, $summary, $years, $history, $suggestions, $updated, $prev_summary
 */

defined( 'ABSPATH' ) || exit;

$total_pots = ( $inputs['cash'] ?? 0 ) + ( $inputs['isa'] ?? 0 ) + ( $inputs['gia'] ?? 0 ) + ( $inputs['pensionPot'] ?? 0 );
$ret_age    = (int) ( $inputs['retirementAge'] ?? 65 );
$cur_age    = (int) ( $inputs['currentAge'] ?? 40 );
$years_left = max( 0, $ret_age - $cur_age );
$run_out    = $summary['runOutAge'] ?? null;
$last_age   = $summary['lastAge'] ?? 95;

// Chart data: pot at retirement over time (from history or dummy)
$chart_labels = array();
$chart_values = array();
if ( $dummy && ! empty( $history ) ) {
	foreach ( $history as $h ) {
		$chart_labels[] = date_i18n( 'M Y', strtotime( $h['created_at'] ) );
		$chart_values[] = $h['summary']['potAtRetirement'] ?? 0;
	}
} elseif ( ! empty( $history ) ) {
	foreach ( $history as $h ) {
		$chart_labels[] = date_i18n( 'M Y', strtotime( $h['created_at'] ) );
		$chart_values[] = $h['summary']['potAtRetirement'] ?? 0;
	}
}
if ( empty( $chart_labels ) ) {
	$chart_labels[] = date_i18n( 'M Y', strtotime( 'now' ) );
	$chart_values[] = $summary['potAtRetirement'] ?? 0;
}

wp_enqueue_style( 'rt-shortcodes', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-shortcodes.css', array(), RT_VERSION );
wp_enqueue_style( 'rt-app', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/rt-app.css', array( 'rt-shortcodes' ), RT_VERSION );
wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
wp_localize_script( 'chartjs', 'rtAppChartData', array(
	'labels' => $chart_labels,
	'values' => array_map( 'floatval', $chart_values ),
	'pots'   => array(
		'cash'    => (float) ( $inputs['cash'] ?? 0 ),
		'isa'     => (float) ( $inputs['isa'] ?? 0 ),
		'gia'     => (float) ( $inputs['gia'] ?? 0 ),
		'pension' => (float) ( $inputs['pensionPot'] ?? 0 ),
	),
) );
?>
<div class="rt-app <?php echo $dummy ? 'rt-app-dummy' : ''; ?>">
	<header class="rt-app-header">
		<div class="rt-app-header-inner">
			<span class="rt-app-logo">Retirement Tracker</span>
			<nav class="rt-app-nav">
				<?php if ( $dummy ) : ?>
					<span class="rt-app-user">Demo user</span>
					<span class="rt-app-badge"><?php esc_html_e( 'Sample data', 'retirement-tracker' ); ?></span>
				<?php else : ?>
					<span class="rt-app-user"><?php echo esc_html( wp_get_current_user()->user_email ?? '' ); ?></span>
					<a href="<?php echo esc_url( RT_Shortcodes::get_form_url() ); ?>" class="rt-btn rt-btn-primary rt-btn-sm"><?php esc_html_e( 'Update my numbers', 'retirement-tracker' ); ?></a>
					<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="rt-btn rt-btn-ghost rt-btn-sm"><?php esc_html_e( 'Log out', 'retirement-tracker' ); ?></a>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<main class="rt-app-main">
		<?php if ( isset( $_GET['rt_saved'] ) && ! $dummy ) : ?>
			<div class="rt-app-alert rt-app-alert-success"><?php esc_html_e( 'Your numbers have been updated.', 'retirement-tracker' ); ?></div>
		<?php endif; ?>

		<section class="rt-app-hero">
			<div class="rt-app-hero-grid">
				<div class="rt-app-hero-card rt-app-hero-primary">
					<p class="rt-app-hero-label"><?php esc_html_e( 'Total retirement pots today', 'retirement-tracker' ); ?></p>
					<p class="rt-app-hero-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $total_pots ) ); ?></p>
				</div>
				<div class="rt-app-hero-card">
					<p class="rt-app-hero-label"><?php esc_html_e( 'Years to retirement', 'retirement-tracker' ); ?></p>
					<p class="rt-app-hero-value"><?php echo esc_html( (string) $years_left ); ?></p>
				</div>
				<div class="rt-app-hero-card">
					<p class="rt-app-hero-label"><?php esc_html_e( 'Projected pot at retirement', 'retirement-tracker' ); ?></p>
					<p class="rt-app-hero-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $summary['potAtRetirement'] ?? 0 ) ); ?></p>
				</div>
				<div class="rt-app-hero-card <?php echo $run_out ? 'rt-app-status-warn' : 'rt-app-status-ok'; ?>">
					<p class="rt-app-hero-label"><?php esc_html_e( 'Sustainability', 'retirement-tracker' ); ?></p>
					<p class="rt-app-hero-value">
						<?php
						if ( $run_out !== null && $run_out !== '' ) {
							echo '<span class="rt-fail">' . esc_html( sprintf( __( 'Shortfall from age %d', 'retirement-tracker' ), $run_out ) ) . '</span>';
						} else {
							echo '<span class="rt-ok">' . esc_html( sprintf( __( 'OK to age %d', 'retirement-tracker' ), $last_age ) ) . '</span>';
						}
						?>
					</p>
				</div>
			</div>
		</section>

		<?php if ( ! empty( $prev_summary ) && isset( $prev_summary['potAtRetirement'] ) ) : ?>
			<?php
			$prev_pot = $prev_summary['potAtRetirement'];
			$curr_pot = $summary['potAtRetirement'] ?? 0;
			$delta    = $curr_pot - $prev_pot;
			$delta_pct = $prev_pot > 0 ? ( $delta / $prev_pot ) * 100 : 0;
			?>
			<section class="rt-app-progress">
				<h2><?php esc_html_e( 'Progress since last update', 'retirement-tracker' ); ?></h2>
				<div class="rt-app-progress-bar-wrap">
					<div class="rt-app-progress-bar" style="width: <?php echo esc_attr( min( 100, max( 0, ( $curr_pot / max( 1, $prev_pot * 1.2 ) ) * 100 ) ) ); ?>%;"></div>
				</div>
				<p class="rt-app-progress-text">
					<?php
					echo esc_html(
						sprintf(
							__( 'Projected pot at retirement: %s → %s (%s%s)',
								'retirement-tracker'
							),
							RT_Shortcodes::format_money_static( $prev_pot ),
							RT_Shortcodes::format_money_static( $curr_pot ),
							$delta >= 0 ? '+' : '',
							RT_Shortcodes::format_money_static( $delta ) . ' (' . number_format( $delta_pct, 1 ) . '%)'
						)
					);
					?>
				</p>
			</section>
		<?php endif; ?>

		<section class="rt-app-charts">
			<div class="rt-app-chart-card">
				<h3><?php esc_html_e( 'Pot growth over time', 'retirement-tracker' ); ?></h3>
				<div class="rt-app-chart-wrap"><canvas id="rt-chart-growth"></canvas></div>
			</div>
			<div class="rt-app-chart-card">
				<h3><?php esc_html_e( 'Asset allocation', 'retirement-tracker' ); ?></h3>
				<div class="rt-app-chart-wrap rt-app-chart-pie"><canvas id="rt-chart-allocation"></canvas></div>
			</div>
		</section>

		<?php if ( ! empty( $suggestions ) ) : ?>
			<section class="rt-app-suggestions">
				<h2><?php esc_html_e( 'Insights & suggestions', 'retirement-tracker' ); ?></h2>
				<div class="rt-app-suggestions-grid">
					<?php foreach ( $suggestions as $s ) : ?>
						<div class="rt-app-suggestion rt-app-suggestion-<?php echo esc_attr( $s['type'] ); ?>">
							<h4><?php echo esc_html( $s['title'] ); ?></h4>
							<p><?php echo esc_html( $s['text'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="rt-app-metrics">
			<h2><?php esc_html_e( 'Key numbers', 'retirement-tracker' ); ?></h2>
			<div class="rt-app-metrics-grid">
				<div class="rt-app-metric"><span class="rt-app-metric-label"><?php esc_html_e( 'Pot at 67', 'retirement-tracker' ); ?></span><span class="rt-app-metric-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $summary['potAt67'] ?? 0 ) ); ?></span></div>
				<div class="rt-app-metric"><span class="rt-app-metric-label"><?php esc_html_e( 'Pot at 90', 'retirement-tracker' ); ?></span><span class="rt-app-metric-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $summary['potAt90'] ?? 0 ) ); ?></span></div>
				<div class="rt-app-metric"><span class="rt-app-metric-label"><?php echo esc_html( sprintf( __( 'State pension (from %d)', 'retirement-tracker' ), $inputs['statePensionAge'] ?? 67 ) ); ?></span><span class="rt-app-metric-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $inputs['statePensionAnnual'] ?? 0 ) ); ?>/yr</span></div>
				<div class="rt-app-metric"><span class="rt-app-metric-label"><?php esc_html_e( 'Annual spending in retirement', 'retirement-tracker' ); ?></span><span class="rt-app-metric-value"><?php echo esc_html( RT_Shortcodes::format_money_static( $inputs['annualSpending'] ?? 0 ) ); ?></span></div>
			</div>
		</section>

		<section class="rt-app-reminder">
			<div class="rt-app-reminder-card">
				<span class="rt-app-reminder-icon">📅</span>
				<div>
					<h4><?php esc_html_e( 'Monthly reminder', 'retirement-tracker' ); ?></h4>
					<p><?php esc_html_e( 'We\'ll email you once a month to prompt you to update your numbers. Keep your retirement plan up to date.', 'retirement-tracker' ); ?></p>
				</div>
			</div>
		</section>

		<?php if ( $updated ) : ?>
			<p class="rt-app-updated"><?php echo esc_html( sprintf( __( 'Last updated %s', 'retirement-tracker' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $updated ) ) ) ); ?></p>
		<?php endif; ?>
	</main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
	var d = typeof rtAppChartData !== 'undefined' ? rtAppChartData : { labels: [], values: [], pots: {} };
	if (document.getElementById('rt-chart-growth') && d.labels && d.labels.length) {
		new Chart(document.getElementById('rt-chart-growth'), {
			type: 'line',
			data: {
				labels: d.labels,
				datasets: [{ label: 'Pot at retirement (£)', data: d.values, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.3 }]
			},
			options: { responsive: true, maintainAspectRatio: true, aspectRatio: 2, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
		});
	}
	if (document.getElementById('rt-chart-allocation') && d.pots) {
		var total = (d.pots.cash||0)+(d.pots.isa||0)+(d.pots.gia||0)+(d.pots.pension||0);
		if (total > 0) {
			new Chart(document.getElementById('rt-chart-allocation'), {
				type: 'doughnut',
				data: {
					labels: ['Cash','ISA','GIA','Pension'],
					datasets: [{ data: [d.pots.cash||0,d.pots.isa||0,d.pots.gia||0,d.pots.pension||0], backgroundColor: ['#94a3b8','#2563eb','#059669','#7c3aed'] }]
				},
				options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
			});
		}
	}
});
</script>
