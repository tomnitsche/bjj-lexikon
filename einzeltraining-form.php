add_shortcode('gg_einzeltraining_anfrage', function () {
	$msg = '';
	if (!empty($_POST['gg_et_send']) && isset($_POST['gg_et_nonce']) && wp_verify_nonce($_POST['gg_et_nonce'], 'gg_et')) {
		if (!empty($_POST['gg_et_website'])) { return '<p class="gg-form-ok">Danke, deine Anfrage ist angekommen.</p>'; }
		$f = function ($k) { return isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : ''; };
		$name = $f('gg_et_name'); $mail = sanitize_email(wp_unslash($_POST['gg_et_mail'] ?? '')); $tel = $f('gg_et_tel');
		$pers = $f('gg_et_pers'); $stand = $f('gg_et_stand'); $ziel = $f('gg_et_ziel'); $wann = $f('gg_et_wann');
		$text = isset($_POST['gg_et_text']) ? sanitize_textarea_field(wp_unslash($_POST['gg_et_text'])) : '';
		if (!$name || !is_email($mail) || empty($_POST['gg_et_ds'])) {
			$msg = '<p class="gg-form-err">Bitte Name, eine gültige E-Mail-Adresse und die Datenschutz-Bestätigung angeben.</p>';
		} else {
			$body = "Neue Anfrage Einzeltraining\n\nName: $name\nE-Mail: $mail\nTelefon: $tel\nPersonen: $pers\nStand: $stand\nStand heute / Ziel: $ziel\nZeitraum: $wann\n\nNachricht:\n$text\n";
			$ok = wp_mail('reload@greyground.de', 'Anfrage Einzeltraining: ' . $name, $body, array('Reply-To: ' . $name . ' <' . $mail . '>'));
			if ($ok) { return '<div class="gg-form-ok"><strong>Danke, ' . esc_html($name) . '.</strong><br>Deine Anfrage ist bei uns angekommen. Wir melden uns mit Terminvorschlägen.</div>'; }
			$msg = '<p class="gg-form-err">Das hat leider nicht geklappt. Schreib uns bitte über die Kontaktdaten im <a href="/impressum/">Impressum</a>.</p>';
		}
	}
	ob_start(); ?>
<?php echo $msg; ?>
<form class="gg-form gg-et-form" method="post" action="#anfrage">
	<?php wp_nonce_field('gg_et', 'gg_et_nonce'); ?>
	<label class="gg-f gg-f-half">Name *<input type="text" name="gg_et_name" required autocomplete="name" maxlength="120"></label><label class="gg-f gg-f-half">E-Mail *<input type="email" name="gg_et_mail" required autocomplete="email" maxlength="150"></label>
	<label class="gg-f gg-f-half">Telefon<input type="tel" name="gg_et_tel" autocomplete="tel" maxlength="40"></label>
	<label class="gg-f gg-f-half">Personen<select name="gg_et_pers"><option>1 Person</option><option>2 Personen</option><option>3 Personen</option><option>mehr als 3</option></select></label>
	<label class="gg-f gg-f-half">Wo möchtest du trainieren?<select name="gg_et_stand"><option>Oschenberg bei Bayreuth (Außenstand)</option><option>Helmbrechts (Halle)</option><option>Anderer Stand / bei mir vor Ort</option><option>Weiß ich noch nicht</option></select></label>
	<label class="gg-f gg-f-half">Wunschzeitraum<input type="text" name="gg_et_wann" placeholder="z. B. ein Samstag im März" maxlength="120"></label>
	<label class="gg-f gg-f-full">Dein Stand heute und dein Ziel<select name="gg_et_ziel"><option>Einstieg – ich schieße noch nicht oder kaum</option><option>Sportschütze – ich will präziser und konstanter werden</option><option>Jäger – Kurzwaffe sicher für die Jagd</option><option>Fortgeschritten – gezielt an einzelnen Punkten arbeiten</option><option>Ausrüstung – Beratung zu Waffe, Holster, Optik</option></select></label>
	<label class="gg-f gg-f-full">Nachricht<textarea name="gg_et_text" rows="4" placeholder="Was möchtest du verbessern? Welche Waffe nutzt du?"></textarea></label>
	<div class="gg-hp" aria-hidden="true"><label>Website<input type="text" name="gg_et_website" tabindex="-1" autocomplete="off"></label></div>
	<label class="gg-f-check"><input type="checkbox" name="gg_et_ds" value="1" required> Ich bin einverstanden, dass meine Angaben zur Bearbeitung der Anfrage gespeichert werden. Mehr in der <a href="/datenschutzerklaerung/">Datenschutzerklärung</a>.*</label>
	<button type="submit" name="gg_et_send" value="1" class="gg-f-submit">Anfrage senden</button>
</form>
<?php return ob_get_clean();
});
