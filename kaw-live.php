/**
 * Greyground Auslastungs-Wächter – 12.11.2026
 *
 * Prüft täglich alle Kurse der nächsten 16 Wochen:
 * - gebucht vs. Mindestzahl (je Kursart einstellbar, einzelne Termine überschreibbar)
 * - Prognose aus eigenen Erfahrungswerten (wie voll waren frühere Kurse X Wochen vorher)
 * - Entscheidungstermin = 8 Wochen vor Kursbeginn (Stornofrist Schießstände)
 * Ampel: grün = läuft, gelb = Werbung nötig, rot = Entscheidung nötig.
 * Mails: montags Wochenübersicht + sofort, wenn ein Kurs auf Gelb oder Rot springt.
 * Übersicht und Absage-Hilfe: Werkzeuge › Kursauslastung.
 */

// Entscheidungstermin 56 Tage (8 Wochen), Horizont 112 Tage (16 Wochen)

function gg_kaw_types() {
	return array(
		'intensiv' => array( 'Pistolen-Intensivwochenende', '/intensiv/i' ),
		'skills'   => array( 'Skills and Drills', '/skills/i' ),
		'ar15'     => array( 'AR15 / Selbstladebüchse', '/ar\s?15|selbstlade/i' ),
		'p3'       => array( 'Pistole 3', '/pistole\s*3/i' ),
		'p12'      => array( 'Pistole 1 & 2', '/pistole\s*1/i' ),
	);
}
function gg_kaw_type( $name ) {
	foreach ( gg_kaw_types() as $k => $t ) { if ( preg_match( $t[1], $name ) ) { return $k; } }
	return 'sonst';
}
function gg_kaw_type_label( $k ) { $t = gg_kaw_types(); return isset( $t[ $k ] ) ? $t[ $k ][0] : 'Sonstige'; }

function gg_kaw_settings() {
	$s = get_option( 'gg_kaw_settings', array() );
	return wp_parse_args( $s, array(
		'min'        => array(),          // typ => Zahl
		'min_event'  => array(),          // event_id => Zahl
		'recipients' => 'reload@greyground.de, marcjungbauer@yahoo.de',
	) );
}

/* ---------- Daten ---------- */

function gg_kaw_events() {
	global $wpdb; $p = $wpdb->prefix;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.id, e.name, e.maxCapacity, e.locationId, MIN(pe.periodStart) AS s
		 FROM {$p}amelia_events e JOIN {$p}amelia_events_periods pe ON pe.eventId = e.id
		 WHERE e.status = 'approved' GROUP BY e.id
		 HAVING MIN(pe.periodStart) > UTC_TIMESTAMP() AND MIN(pe.periodStart) < DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d DAY)
		 ORDER BY s", 112 ) );
	$out = array();
	foreach ( $rows as $r ) { $out[] = gg_kaw_eval( $r ); }
	return $out;
}

function gg_kaw_taken( $event_id ) {
	global $wpdb; $p = $wpdb->prefix;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(x.persons),0) FROM ( SELECT DISTINCT cb.id, cb.persons FROM {$p}amelia_customer_bookings cb
		 JOIN {$p}amelia_customer_bookings_to_events_periods bp ON bp.customerBookingId = cb.id
		 JOIN {$p}amelia_events_periods pe ON pe.id = bp.eventPeriodId
		 WHERE pe.eventId = %d AND cb.status IN ('approved','pending') ) x", $event_id ) );
}

/** Anteil der Endbuchungen, der X Wochen vor Kursbeginn üblicherweise schon da ist (Median aus den letzten 18 Monaten). */
function gg_kaw_pace() {
	$c = get_transient( 'gg_kaw_pace' );
	if ( is_array( $c ) ) { return $c; }
	global $wpdb; $p = $wpdb->prefix;
	$ev = $wpdb->get_results( "SELECT e.id, MIN(pe.periodStart) s FROM {$p}amelia_events e JOIN {$p}amelia_events_periods pe ON pe.eventId = e.id
		GROUP BY e.id HAVING MIN(pe.periodStart) < UTC_TIMESTAMP() AND MIN(pe.periodStart) > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 18 MONTH)" );
	$ratios = array();
	foreach ( $ev as $e ) {
		$created = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT cb.created FROM {$p}amelia_customer_bookings cb
			 JOIN {$p}amelia_customer_bookings_to_events_periods bp ON bp.customerBookingId = cb.id
			 JOIN {$p}amelia_events_periods pe ON pe.id = bp.eventPeriodId
			 WHERE pe.eventId = %d AND cb.status = 'approved' AND cb.created IS NOT NULL", $e->id ) );
		$n = count( $created );
		if ( $n < 3 ) { continue; }
		$start = strtotime( $e->s . ' UTC' );
		for ( $w = 0; $w <= 20; $w++ ) {
			$cut = $start - $w * WEEK_IN_SECONDS; $k = 0;
			foreach ( $created as $cr ) { if ( strtotime( $cr . ' UTC' ) <= $cut ) { $k++; } }
			$ratios[ $w ][] = $k / $n;
		}
	}
	$pace = array();
	foreach ( $ratios as $w => $list ) { sort( $list ); $m = count( $list ); $pace[ $w ] = $m ? $list[ (int) floor( ( $m - 1 ) / 2 ) ] : null; }
	$pace['_n'] = count( $ev );
	set_transient( 'gg_kaw_pace', $pace, DAY_IN_SECONDS );
	return $pace;
}

function gg_kaw_eval( $r ) {
	global $wpdb; $p = $wpdb->prefix;
	$s   = gg_kaw_settings();
	$e   = new stdClass();
	$e->id    = (int) $r->id;
	$e->title = html_entity_decode( $r->name, ENT_QUOTES, 'UTF-8' );
	$e->ts    = strtotime( $r->s . ' UTC' );
	$e->cap   = (int) $r->maxCapacity;
	$e->taken = gg_kaw_taken( $e->id );
	$e->type  = gg_kaw_type( $e->title );
	$def      = max( 1, (int) ceil( $e->cap / 2 ) );
	$e->min   = isset( $s['min_event'][ $e->id ] ) && $s['min_event'][ $e->id ] !== '' ? (int) $s['min_event'][ $e->id ] : ( isset( $s['min'][ $e->type ] ) && $s['min'][ $e->type ] !== '' ? (int) $s['min'][ $e->type ] : $def );
	$e->decide = $e->ts - 56 * DAY_IN_SECONDS;
	$loc = $r->locationId ? $wpdb->get_row( $wpdb->prepare( "SELECT name, address, phone FROM {$p}amelia_locations WHERE id = %d", $r->locationId ) ) : null;
	$e->loc       = $loc ? html_entity_decode( $loc->name, ENT_QUOTES, 'UTF-8' ) : '';
	$e->loc_addr  = $loc ? $loc->address : '';
	$e->loc_phone = $loc ? $loc->phone : '';
	$e->trainer = implode( ', ', $wpdb->get_col( $wpdb->prepare( "SELECT u.firstName FROM {$p}amelia_events_to_providers ep JOIN {$p}amelia_users u ON u.id = ep.userId WHERE ep.eventId = %d", $e->id ) ) );
	// Prognose
	$pace = gg_kaw_pace();
	$w    = (int) floor( max( 0, $e->ts - time() ) / WEEK_IN_SECONDS );
	$rat  = isset( $pace[ min( 20, $w ) ] ) ? $pace[ min( 20, $w ) ] : null;
	$e->forecast = ( $rat && $rat >= 0.1 ) ? (int) round( min( $e->cap, $e->taken / $rat ) ) : null;
	// Ampel
	$now = time(); $dd = ( $e->decide - $now ) / DAY_IN_SECONDS;
	if ( $e->taken >= $e->min ) {
		$e->status = 'gruen'; $e->why = 'Mindestzahl erreicht';
	} elseif ( $dd <= 0 ) {
		$e->status = 'rot'; $e->why = 'Entscheidungstermin vorbei, sofort entscheiden';
	} elseif ( $dd <= 7 ) {
		$e->status = 'rot'; $e->why = 'Entscheidung nötig bis ' . wp_date( 'j.n.', $e->decide );
	} elseif ( $dd <= 28 ) {
		$e->status = 'gelb'; $e->why = 'Werbung jetzt, Entscheidung am ' . wp_date( 'j.n.', $e->decide );
	} elseif ( null !== $e->forecast && $e->forecast < $e->min ) {
		$e->status = 'gelb'; $e->why = 'Bucht langsamer als üblich (Prognose ' . $e->forecast . ')';
	} else {
		$e->status = 'gruen'; $e->why = 'im Plan';
	}
	return $e;
}

/* ---------- Mails ---------- */

function gg_kaw_recipients() {
	$s = gg_kaw_settings();
	return array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $s['recipients'] ) ) ) ) );
}

function gg_kaw_send( $to, $subject, $heading, $body ) {
	$html = $body;
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$html = WC()->mailer()->wrap_message( $heading, $body );
		if ( class_exists( 'WC_Email' ) ) { $em = new WC_Email(); $html = $em->style_inline( $html ); }
	}
	$ok  = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	$log = get_option( 'gg_kaw_log', array() );
	array_unshift( $log, array( 'zeit' => current_time( 'mysql' ), 'an' => is_array( $to ) ? implode( ', ', $to ) : $to, 'betreff' => $subject, 'ok' => $ok ? 1 : 0 ) );
	update_option( 'gg_kaw_log', array_slice( $log, 0, 30 ), false );
	return $ok;
}

function gg_kaw_dot( $st ) {
	$c = array( 'gruen' => '#2e7d32', 'gelb' => '#e0a800', 'rot' => '#c62828' );
	return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . ( $c[ $st ] ?? '#999' ) . ';margin-right:6px"></span>';
}

function gg_kaw_table_html( $events ) {
	$h = '<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;font-size:13px"><tr style="text-align:left;border-bottom:1px solid #ddd"><th>Datum</th><th>Kurs</th><th>Gebucht</th><th>Status</th></tr>';
	foreach ( $events as $e ) {
		$h .= '<tr style="border-bottom:1px solid #eee;vertical-align:top"><td style="white-space:nowrap">' . esc_html( wp_date( 'd.m.y', $e->ts ) ) . '</td><td>' . esc_html( $e->title ) . '</td><td style="white-space:nowrap">' . (int) $e->taken . ' / ' . (int) $e->cap . '<br><small>min. ' . (int) $e->min . '</small></td><td>' . gg_kaw_dot( $e->status ) . esc_html( $e->why ) . '</td></tr>';
	}
	return $h . '</table>';
}

function gg_kaw_weekly( $to = null ) {
	$ev   = gg_kaw_events();
	$crit = array_filter( $ev, function ( $e ) { return 'gruen' !== $e->status; } );
	$body = '<p>Hallo,</p><p>hier die Kursauslastung der nächsten 16 Wochen. Entscheidungstermin ist jeweils 8 Wochen vor Kursbeginn.</p>';
	$body .= '<p><strong>' . count( $crit ) . ' von ' . count( $ev ) . ' Kursen brauchen Aufmerksamkeit.</strong></p>';
	if ( $crit ) { $body .= '<h3 style="margin:18px 0 6px">Handlungsbedarf</h3>' . gg_kaw_table_html( $crit ); }
	$body .= '<h3 style="margin:18px 0 6px">Alle Kurse</h3>' . gg_kaw_table_html( $ev );
	$body .= '<p style="margin-top:18px"><a href="' . esc_url( admin_url( 'tools.php?page=gg-auslastung' ) ) . '">Zur Übersicht im Backend</a></p>';
	return gg_kaw_send( $to ? $to : gg_kaw_recipients(), 'Kursauslastung KW ' . wp_date( 'W' ) . ': ' . count( $crit ) . ' mit Handlungsbedarf', 'Kursauslastung', $body );
}

function gg_kaw_daily() {
	$today = wp_date( 'Y-m-d' );
	if ( get_option( 'gg_kaw_lastrun' ) === $today ) { return; }
	if ( (int) wp_date( 'G' ) < 6 ) { return; }
	update_option( 'gg_kaw_lastrun', $today, false );
	$ev    = gg_kaw_events();
	$state = get_option( 'gg_kaw_state', array() );
	$rank  = array( 'gruen' => 0, 'gelb' => 1, 'rot' => 2 );
	$alert = array();
	foreach ( $ev as $e ) {
		$old = $state[ $e->id ] ?? 'gruen';
		if ( $rank[ $e->status ] > $rank[ $old ] ) { $alert[] = $e; }
		$state[ $e->id ] = $e->status;
	}
	update_option( 'gg_kaw_state', $state, false );
	if ( $alert && '1' !== wp_date( 'N' ) ) {
		$body = '<p>Hallo,</p><p>diese Kurse sind seit gestern kritischer geworden:</p>' . gg_kaw_table_html( $alert );
		$body .= '<p style="margin-top:18px"><a href="' . esc_url( admin_url( 'tools.php?page=gg-auslastung' ) ) . '">Zur Übersicht und Absage-Hilfe</a></p>';
		$red = array_filter( $alert, function ( $e ) { return 'rot' === $e->status; } );
		gg_kaw_send( gg_kaw_recipients(), ( $red ? 'Entscheidung nötig: ' : 'Werbung nötig: ' ) . count( $alert ) . ' Kurs(e)', $red ? 'Entscheidung nötig' : 'Werbung nötig', $body );
	}
	if ( '1' === wp_date( 'N' ) ) { gg_kaw_weekly(); }
}
add_action( 'gg_kaw_cron', 'gg_kaw_daily' );
add_action( 'init', function () { if ( ! wp_next_scheduled( 'gg_kaw_cron' ) ) { wp_schedule_event( time() + 600, 'hourly', 'gg_kaw_cron' ); } } );

/* ---------- Backend ---------- */

add_action( 'admin_menu', function () {
	add_management_page( 'Kursauslastung', 'Kursauslastung', 'manage_options', 'gg-auslastung', 'gg_kaw_admin' );
} );

function gg_kaw_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gg_kaw_settings(); $msg = '';
	if ( isset( $_POST['gg_kaw_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gg_kaw_nonce'] ) ), 'gg_kaw' ) ) {
		if ( isset( $_POST['save'] ) ) {
			foreach ( array_keys( gg_kaw_types() ) as $k ) { $v = isset( $_POST['min'][ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST['min'][ $k ] ) ) ) : ''; $s['min'][ $k ] = '' === $v ? '' : absint( $v ); }
			$s['min_event'] = array();
			if ( ! empty( $_POST['min_event'] ) && is_array( $_POST['min_event'] ) ) {
				foreach ( wp_unslash( $_POST['min_event'] ) as $id => $v ) { $v = trim( sanitize_text_field( $v ) ); if ( '' !== $v ) { $s['min_event'][ absint( $id ) ] = absint( $v ); } }
			}
			$s['recipients'] = sanitize_text_field( wp_unslash( $_POST['recipients'] ?? '' ) );
			update_option( 'gg_kaw_settings', $s, false );
			$msg = 'Gespeichert.';
		}
		if ( isset( $_POST['testmail'] ) ) {
			$me = wp_get_current_user()->user_email;
			$msg = gg_kaw_weekly( $me ) ? 'Wochenübersicht an ' . esc_html( $me ) . ' geschickt.' : 'Versand nicht möglich (auf Staging ist der Mailversand gesperrt).';
		}
	}
	echo '<div class="wrap"><h1>Kursauslastung</h1>';
	if ( $msg ) { echo '<div class="notice notice-success"><p>' . wp_kses_post( $msg ) . '</p></div>'; }

	// Absage-Hilfe
	if ( ! empty( $_GET['absage'] ) ) { gg_kaw_cancel_view( absint( $_GET['absage'] ) ); echo '</div>'; return; }

	$pace = gg_kaw_pace();
	echo '<p>Kurse der nächsten 16 Wochen. Entscheidungstermin: 8 Wochen vor Kursbeginn. Prognose aus ' . (int) ( $pace['_n'] ?? 0 ) . ' Kursen der letzten 18 Monate. Geprüft wird täglich ab 6 Uhr, montags geht die Wochenübersicht raus.</p>';
	echo '<form method="post">'; wp_nonce_field( 'gg_kaw', 'gg_kaw_nonce' );
	echo '<table class="widefat striped"><thead><tr><th>Datum</th><th>Kurs</th><th>Ort / Trainer</th><th>Gebucht</th><th>Mindestzahl</th><th>Prognose</th><th>Entscheiden bis</th><th>Status</th><th></th></tr></thead><tbody>';
	foreach ( gg_kaw_events() as $e ) {
		$ov = isset( $s['min_event'][ $e->id ] ) ? (int) $s['min_event'][ $e->id ] : '';
		echo '<tr><td>' . esc_html( wp_date( 'D d.m.y', $e->ts ) ) . '</td><td>' . esc_html( $e->title ) . '<br><small>' . esc_html( gg_kaw_type_label( $e->type ) ) . '</small></td><td>' . esc_html( $e->loc ) . '<br><small>' . esc_html( $e->trainer ) . '</small></td>';
		echo '<td><strong>' . (int) $e->taken . '</strong> / ' . (int) $e->cap . '</td>';
		echo '<td><input type="number" min="0" style="width:64px" name="min_event[' . (int) $e->id . ']" value="' . esc_attr( $ov ) . '" placeholder="' . (int) $e->min . '"></td>';
		echo '<td>' . ( null === $e->forecast ? '–' : (int) $e->forecast ) . '</td><td>' . esc_html( wp_date( 'd.m.y', $e->decide ) ) . '</td>';
		echo '<td>' . gg_kaw_dot( $e->status ) . esc_html( $e->why ) . '</td>';
		echo '<td>' . ( 'gruen' !== $e->status ? '<a href="' . esc_url( admin_url( 'tools.php?page=gg-auslastung&absage=' . $e->id ) ) . '">Absage vorbereiten</a>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<h2 style="margin-top:28px">Mindestzahl je Kursart</h2><p>Leer = halbe Platzzahl. Einzelne Termine oben in der Tabelle überschreiben diesen Wert.</p><table class="form-table">';
	foreach ( gg_kaw_types() as $k => $t ) {
		echo '<tr><th>' . esc_html( $t[0] ) . '</th><td><input type="number" min="0" style="width:80px" name="min[' . esc_attr( $k ) . ']" value="' . esc_attr( $s['min'][ $k ] ?? '' ) . '"></td></tr>';
	}
	echo '</table><h2>Empfänger</h2><p><input type="text" class="regular-text" style="width:480px" name="recipients" value="' . esc_attr( $s['recipients'] ) . '"><br><small>Mehrere Adressen mit Komma trennen.</small></p>';
	echo '<p><button class="button button-primary" name="save" value="1">Speichern</button> <button class="button" name="testmail" value="1">Wochenübersicht jetzt nur an mich schicken</button></p></form>';
	$log = get_option( 'gg_kaw_log', array() );
	if ( $log ) {
		echo '<h2 style="margin-top:28px">Zuletzt verschickt</h2><table class="widefat striped"><tbody>';
		foreach ( array_slice( $log, 0, 10 ) as $l ) { echo '<tr><td>' . esc_html( $l['zeit'] ) . '</td><td>' . esc_html( $l['an'] ) . '</td><td>' . esc_html( $l['betreff'] ) . '</td><td>' . ( $l['ok'] ? 'ok' : 'nicht versendet' ) . '</td></tr>'; }
		echo '</tbody></table>';
	}
	echo '</div>';
}

function gg_kaw_cancel_view( $id ) {
	global $wpdb; $p = $wpdb->prefix;
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT e.id, e.name, e.maxCapacity, e.locationId, MIN(pe.periodStart) s FROM {$p}amelia_events e JOIN {$p}amelia_events_periods pe ON pe.eventId = e.id WHERE e.id = %d GROUP BY e.id", $id ) );
	if ( ! $r ) { echo '<p>Kurs nicht gefunden.</p>'; return; }
	$e = gg_kaw_eval( $r );
	echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=gg-auslastung' ) ) . '">← Zurück zur Übersicht</a></p>';
	echo '<h2>Absage vorbereiten: ' . esc_html( $e->title ) . '</h2><p>' . esc_html( wp_date( 'l, j. F Y, H:i', $e->ts ) ) . ' Uhr · ' . (int) $e->taken . ' von ' . (int) $e->cap . ' gebucht, Mindestzahl ' . (int) $e->min . '.</p>';
	echo '<p><strong>Hier wird nichts automatisch abgesagt oder verschickt.</strong> Die Seite sammelt nur, was ihr für die Entscheidung und die Umbuchung braucht.</p>';

	$people = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT cb.id, u.firstName, u.lastName, u.email, u.phone, cb.status FROM {$p}amelia_customer_bookings cb
		 JOIN {$p}amelia_users u ON u.id = cb.customerId
		 JOIN {$p}amelia_customer_bookings_to_events_periods bp ON bp.customerBookingId = cb.id
		 JOIN {$p}amelia_events_periods pe ON pe.id = bp.eventPeriodId
		 WHERE pe.eventId = %d AND cb.status IN ('approved','pending') ORDER BY u.lastName", $e->id ) );
	echo '<h3>1. Teilnehmer (' . count( $people ) . ')</h3><table class="widefat striped" style="max-width:820px"><tbody>';
	foreach ( $people as $pp ) { echo '<tr><td>' . esc_html( $pp->firstName . ' ' . $pp->lastName ) . '</td><td>' . esc_html( $pp->email ) . '</td><td>' . esc_html( $pp->phone ) . '</td><td>' . esc_html( $pp->status ) . '</td></tr>'; }
	echo '</tbody></table>';

	$alts = array();
	foreach ( $wpdb->get_results( "SELECT e.id, e.name, e.maxCapacity, e.locationId, MIN(pe.periodStart) s FROM {$p}amelia_events e JOIN {$p}amelia_events_periods pe ON pe.eventId = e.id WHERE e.status = 'approved' AND e.id <> " . (int) $e->id . " GROUP BY e.id HAVING MIN(pe.periodStart) > UTC_TIMESTAMP() ORDER BY s" ) as $a ) {
		$x = gg_kaw_eval( $a );
		if ( $x->type === $e->type && $x->cap - $x->taken > 0 ) { $alts[] = $x; }
	}
	echo '<h3>2. Ersatztermine gleicher Kursart mit freien Plätzen</h3>';
	if ( ! $alts ) { echo '<p>Keine. Eventuell einen Ersatztermin anlegen.</p>'; }
	else {
		echo '<table class="widefat striped" style="max-width:820px"><tbody>';
		foreach ( $alts as $a ) { echo '<tr><td>' . esc_html( wp_date( 'd.m.y', $a->ts ) ) . '</td><td>' . esc_html( $a->title ) . '</td><td>' . (int) ( $a->cap - $a->taken ) . ' frei</td></tr>'; }
		echo '</tbody></table>';
	}

	$list = '';
	foreach ( array_slice( $alts, 0, 4 ) as $a ) { $list .= '- ' . wp_date( 'j. F Y', $a->ts ) . ': ' . $a->title . "\n"; }
	$draft  = "Betreff: Dein Kurs am " . wp_date( 'j. F', $e->ts ) . " – wir müssen umplanen\n\n";
	$draft .= "Hallo [Vorname],\n\n";
	$draft .= "leider müssen wir den Kurs „" . $e->title . "“ am " . wp_date( 'j. F Y', $e->ts ) . " absagen, weil zu wenige Anmeldungen zusammengekommen sind. Das tut uns leid.\n\n";
	$draft .= "Wir buchen dich gern kostenlos auf einen anderen Termin um:\n" . ( $list ? $list : "- [Ersatztermin eintragen]\n" );
	$draft .= "\nSag uns einfach kurz, welcher Termin dir passt. Möchtest du lieber nicht umbuchen, erstatten wir dir den vollen Kursbetrag.\n\n";
	$draft .= "Sportliche Grüße\nDavid & Tom\nGreyground";
	echo '<h3>3. Mailentwurf zum Umbuchen</h3><p>Entwurf zum Kopieren. Vor dem Versand prüfen und anpassen.</p><textarea id="gg-aw-draft" rows="16" style="width:100%;max-width:820px;font-family:inherit">' . esc_textarea( $draft ) . '</textarea>';
	echo '<p><button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'gg-aw-draft\').value);this.textContent=\'Kopiert\'">Text kopieren</button></p>';

	echo '<h3>4. Schießstand stornieren</h3><p><strong>' . esc_html( $e->loc ) . '</strong><br>' . esc_html( $e->loc_addr ) . ( $e->loc_phone ? '<br>Telefon: ' . esc_html( $e->loc_phone ) : '' ) . '</p>';
	echo '<p>Frist laut Planung: bis ' . esc_html( wp_date( 'j. F Y', $e->decide ) ) . ' (8 Wochen vorher).</p>';
	echo '<h3>5. Danach</h3><ul style="list-style:disc;margin-left:20px"><li>Umbuchungen in Amelia vornehmen (ohne Benachrichtigung, wenn ihr selbst schreibt).</li><li>Erstattungen für Teilnehmer, die nicht umbuchen, über Stripe/PayPal bzw. Überweisung.</li><li>Kurs in Amelia auf „abgesagt“ setzen, Trainer informieren.</li></ul>';
}