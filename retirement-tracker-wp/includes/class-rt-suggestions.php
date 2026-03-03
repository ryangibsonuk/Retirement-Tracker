<?php
/**
 * Suggestions / insights based on scenario data.
 */

defined( 'ABSPATH' ) || exit;

class RT_Suggestions {

	/**
	 * @param array $inputs
	 * @param array $summary
	 * @param array|null $prev_summary Previous snapshot for growth comparison
	 * @return array List of { type: 'success'|'info'|'warning', title, text }
	 */
	public static function get( array $inputs, array $summary, $prev_summary = null ) {
		$suggestions = array();

		$run_out = $summary['runOutAge'] ?? null;
		$last    = $summary['lastAge'] ?? 95;
		$pot_ret = $summary['potAtRetirement'] ?? 0;
		$ret_age = $inputs['retirementAge'] ?? 65;
		$cur_age = $inputs['currentAge'] ?? 40;
		$spend   = $inputs['annualSpending'] ?? 30000;
		$total_pots = ( $inputs['cash'] ?? 0 ) + ( $inputs['isa'] ?? 0 ) + ( $inputs['gia'] ?? 0 ) + ( $inputs['pensionPot'] ?? 0 );

		if ( $run_out !== null && $run_out !== '' ) {
			$suggestions[] = array(
				'type'  => 'warning',
				'title' => __( 'Shortfall ahead', 'retirement-tracker' ),
				'text'  => sprintf( __( 'Your funds run out at age %d. Consider increasing savings, reducing spending in retirement, or working longer.', 'retirement-tracker' ), $run_out ),
			);
		} else {
			$suggestions[] = array(
				'type'  => 'success',
				'title' => __( 'On track', 'retirement-tracker' ),
				'text'  => sprintf( __( 'Your funds last to age %d. You\'re building a sustainable retirement pot.', 'retirement-tracker' ), $last ),
			);
		}

		if ( $ret_age <= 57 && $total_pots > 0 ) {
			$pension_pct = ( ( $inputs['pensionPot'] ?? 0 ) / max( 1, $total_pots ) ) * 100;
			if ( $pension_pct > 60 ) {
				$suggestions[] = array(
					'type'  => 'info',
					'title' => __( 'Early retirement', 'retirement-tracker' ),
					'text'  => __( 'You have significant pension savings. Remember you can\'t access pensions until 57 — ensure enough in ISA/cash/GIA to bridge until then.', 'retirement-tracker' ),
				);
			}
		}

		$years_to_ret = max( 0, $ret_age - $cur_age );
		if ( $years_to_ret > 10 && $pot_ret > 0 ) {
			$suggestions[] = array(
				'type'  => 'info',
				'title' => __( 'Time on your side', 'retirement-tracker' ),
				'text'  => sprintf( __( 'You have %d years until retirement. Compound growth can make a big difference — even small increases in contributions add up.', 'retirement-tracker' ), $years_to_ret ),
			);
		}

		if ( $prev_summary && isset( $prev_summary['potAtRetirement'] ) && $prev_summary['potAtRetirement'] > 0 ) {
			$growth = ( ( $pot_ret - $prev_summary['potAtRetirement'] ) / $prev_summary['potAtRetirement'] ) * 100;
			if ( $growth > 0 ) {
				$suggestions[] = array(
					'type'  => 'success',
					'title' => __( 'Progress', 'retirement-tracker' ),
					'text'  => sprintf( __( 'Your projected pot at retirement grew by %s since your last update.', 'retirement-tracker' ), number_format( $growth, 1 ) . '%' ),
				);
			}
		}

		return $suggestions;
	}
}
