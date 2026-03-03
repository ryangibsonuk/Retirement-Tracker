<?php
/**
 * DB: scenarios table (one per user), nudge_prefs. Cron nudge logic.
 */

defined( 'ABSPATH' ) || exit;

class RT_DB {

	const TABLE_SCENARIOS   = 'retirement_scenarios';
	const TABLE_SCENARIO_HISTORY = 'retirement_scenario_history';
	const TABLE_NUDGE_PREFS = 'retirement_nudge_prefs';

	public static function install() {
		global $wpdb;
		$scenarios = $wpdb->prefix . self::TABLE_SCENARIOS;
		$history   = $wpdb->prefix . self::TABLE_SCENARIO_HISTORY;
		$nudge     = $wpdb->prefix . self::TABLE_NUDGE_PREFS;
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $scenarios (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			inputs longtext NOT NULL,
			summary longtext,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) $charset;

		CREATE TABLE IF NOT EXISTS $history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			inputs longtext NOT NULL,
			summary longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_created (user_id, created_at)
		) $charset;

		CREATE TABLE IF NOT EXISTS $nudge (
			user_id bigint(20) unsigned NOT NULL,
			nudge_opted_out tinyint(1) NOT NULL DEFAULT 0,
			last_nudge_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (user_id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! wp_next_scheduled( 'wp_scheduled_rt_nudge' ) ) {
			wp_schedule_event( time(), 'monthly', 'wp_scheduled_rt_nudge' );
		}
	}

	/**
	 * Get scenario for current user. Returns array with inputs, summary, updated_at or null.
	 */
	public static function get_scenario( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SCENARIOS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT inputs, summary, updated_at FROM $table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['inputs']   = json_decode( $row['inputs'], true ) ?: array();
		$row['summary']  = json_decode( $row['summary'], true );
		return $row;
	}

	/**
	 * Save scenario for current user. $inputs = array of scenario fields.
	 */
	public static function save_scenario( $user_id, array $inputs ) {
		$result = RT_Projection::run( $inputs );
		$summary = $result['summary'];
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SCENARIOS;
		$wpdb->replace(
			$table,
			array(
				'user_id'    => $user_id,
				'inputs'     => wp_json_encode( $inputs ),
				'summary'    => wp_json_encode( $summary ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
		self::add_scenario_history( $user_id, $inputs, $summary );
		return $summary;
	}

	/** Store snapshot for progress tracking */
	public static function add_scenario_history( $user_id, array $inputs, array $summary ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SCENARIO_HISTORY;
		$wpdb->insert( $table, array(
			'user_id'    => $user_id,
			'inputs'     => wp_json_encode( $inputs ),
			'summary'    => wp_json_encode( $summary ),
			'created_at' => current_time( 'mysql' ),
		), array( '%d', '%s', '%s', '%s' ) );
	}

	/** Get last N history snapshots for charts / monthly progress */
	public static function get_scenario_history( $user_id, $limit = 24 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SCENARIO_HISTORY;
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT inputs, summary, created_at FROM $table WHERE user_id = %d ORDER BY created_at ASC LIMIT %d",
			$user_id,
			$limit
		), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['inputs']  = json_decode( $r['inputs'], true ) ?: array();
			$r['summary'] = json_decode( $r['summary'], true ) ?: array();
		}
		return $rows;
	}

	public static function set_nudge_opted_out( $user_id, $opted_out ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NUDGE_PREFS;
		$wpdb->replace(
			$table,
			array(
				'user_id'         => $user_id,
				'nudge_opted_out' => $opted_out ? 1 : 0,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * WP-Cron: find users not updated in 30 days, not opted out; update last_nudge_at and optionally send email.
	 */
	public static function cron_nudge() {
		global $wpdb;
		$scenarios = $wpdb->prefix . self::TABLE_SCENARIOS;
		$nudge     = $wpdb->prefix . self::TABLE_NUDGE_PREFS;
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM $scenarios WHERE updated_at < %s",
			$cutoff
		) );
		if ( empty( $user_ids ) ) {
			return;
		}

		$opted_out = $wpdb->get_col( "SELECT user_id FROM $nudge WHERE nudge_opted_out = 1 AND user_id IN (" . implode( ',', array_map( 'absint', $user_ids ) ) . ")" );
		$opted_out = array_flip( $opted_out );
		$to_nudge  = array_filter( $user_ids, function ( $id ) use ( $opted_out ) {
			return ! isset( $opted_out[ (int) $id ] );
		} );

		$now = current_time( 'mysql' );
		foreach ( $to_nudge as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( $user && $user->user_email ) {
				$url = get_permalink( (int) get_option( 'rt_form_page_id', 0 ) ) ?: home_url( '/' );
				wp_mail(
					$user->user_email,
					__( 'Time to update your retirement numbers', 'retirement-tracker' ),
					sprintf(
						__( "Hi,\n\nYou haven't updated your retirement numbers in a while. Head over to the tracker and refresh your figures:\n\n%s\n\nUnsubscribe from these reminders: %s", 'retirement-tracker' ),
						$url,
						add_query_arg( 'retirement_tracker_nudge_unsubscribe', '1', home_url( '/' ) )
					)
				);
			}
			$wpdb->replace(
				$nudge,
				array(
					'user_id'       => $uid,
					'nudge_opted_out' => 0,
					'last_nudge_at' => $now,
					'updated_at'    => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}
}
