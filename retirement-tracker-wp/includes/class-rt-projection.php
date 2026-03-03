<?php
/**
 * Deterministic projection: same logic as the Next.js engine.
 * Withdrawal order: cash → ISA → GIA; pension from 57; state pension from statePensionAge.
 */

defined( 'ABSPATH' ) || exit;

class RT_Projection {

	const PENSION_ACCESS_AGE = 57;

	/**
	 * @param array $input Scenario inputs (currentAge, retirementAge, lifeHorizon, cash, isa, gia, pensionPot, statePensionAge, statePensionAnnual, annualSpending, realReturnPct, realReturnCashPct)
	 * @return array{ summary: array, years: array }
	 */
	public static function run( array $input ) {
		$cur    = (int) ( $input['currentAge'] ?? 40 );
		$ret    = (int) ( $input['retirementAge'] ?? 65 );
		$end    = (int) max( $ret, $input['lifeHorizon'] ?? 95 );
		$r_inv  = self::clamp( (float) ( $input['realReturnPct'] ?? 0.04 ) );
		$r_cash = self::clamp( (float) ( $input['realReturnCashPct'] ?? 0.01 ) );

		$cash   = self::clamp( (float) ( $input['cash'] ?? 0 ) );
		$isa    = self::clamp( (float) ( $input['isa'] ?? 0 ) );
		$gia    = self::clamp( (float) ( $input['gia'] ?? 0 ) );
		$pension = self::clamp( (float) ( $input['pensionPot'] ?? 0 ) );

		$state_age   = (int) ( $input['statePensionAge'] ?? 67 );
		$state_ann   = self::clamp( (float) ( $input['statePensionAnnual'] ?? 0 ) );
		$spending    = self::clamp( (float) ( $input['annualSpending'] ?? 30000 ) );

		$years     = array();
		$run_out_age = null;
		$current_year = (int) date( 'Y' );

		for ( $age = $cur; $age <= $end; $age++ ) {
			$year = $current_year + ( $age - $cur );

			$cash   *= ( 1 + $r_cash );
			$isa    *= ( 1 + $r_inv );
			$gia    *= ( 1 + $r_inv );
			$pension *= ( 1 + $r_inv );

			$withdrawal = 0;
			$income     = 0;
			$shortfall  = 0;

			if ( $age >= $ret ) {
				$income = $age >= $state_age ? $state_ann : 0;
				$need   = max( 0, $spending - $income );

				if ( $need > 0 ) {
					$remaining = $need;

					$from_cash = min( $cash, $remaining );
					$cash -= $from_cash;
					$remaining -= $from_cash;
					$withdrawal += $from_cash;

					if ( $remaining > 0 ) {
						$from_isa = min( $isa, $remaining );
						$isa -= $from_isa;
						$remaining -= $from_isa;
						$withdrawal += $from_isa;
					}
					if ( $remaining > 0 ) {
						$from_gia = min( $gia, $remaining );
						$gia -= $from_gia;
						$remaining -= $from_gia;
						$withdrawal += $from_gia;
					}
					if ( $remaining > 0 && $age >= self::PENSION_ACCESS_AGE ) {
						$from_pension = min( $pension, $remaining );
						$pension -= $from_pension;
						$remaining -= $from_pension;
						$withdrawal += $from_pension;
					}
					$shortfall = $remaining;
				}
			}

			$total = $cash + $isa + $gia + $pension;
			$fail  = $age >= $ret && $shortfall > 0;
			if ( $fail && $run_out_age === null ) {
				$run_out_age = $age;
			}

			$years[] = array(
				'age'        => $age,
				'year'       => $year,
				'cash'       => (int) round( $cash ),
				'isa'        => (int) round( $isa ),
				'gia'        => (int) round( $gia ),
				'pension'    => (int) round( $pension ),
				'total'      => (int) round( $total ),
				'withdrawal' => (int) round( $withdrawal ),
				'income'     => (int) round( $income ),
				'fail'       => $fail,
			);
		}

		$last  = end( $years );
		$at_ret = null;
		$at_67  = null;
		$at_90  = null;
		foreach ( $years as $y ) {
			if ( (int) $y['age'] === $ret ) $at_ret = $y;
			if ( (int) $y['age'] === 67 ) $at_67 = $y;
			if ( (int) $y['age'] === 90 ) $at_90 = $y;
		}

		$summary = array(
			'potAtRetirement' => $at_ret ? (int) $at_ret['total'] : 0,
			'potAt67'        => $at_67 ? (int) $at_67['total'] : 0,
			'potAt90'        => $at_90 ? (int) $at_90['total'] : 0,
			'runOutAge'      => $run_out_age,
			'lastAge'        => $last ? (int) $last['age'] : $end,
		);

		return array( 'summary' => $summary, 'years' => $years );
	}

	private static function clamp( $n ) {
		return is_finite( $n ) ? (float) $n : 0.0;
	}
}
