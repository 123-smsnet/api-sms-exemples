<?php
/* Page d'envoi de SMS - module 123-SMS pour Dolibarr - licence MIT */
$res = 0;
if (!$res && file_exists('../main.inc.php')) { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }
dol_include_once('/sms123/class/sms123api.class.php');

if (!$user->hasRight('sms123', 'envoyer')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$prenumero = GETPOST('numero', 'alphanohtml');
$premessage = GETPOST('message', 'restricthtml');
if ($action == 'send') {
	$prenumero = '';
	$premessage = '';
}

// Envoi puis redirection : le formulaire repart vide (schema POST/Redirect/GET)
if ($action == 'send') {
	$numero = GETPOST('numero', 'alphanohtml');
	$message = GETPOST('message', 'restricthtml');
	if (empty($numero) || empty($message)) {
		setEventMessages('Indiquez un destinataire et un message.', null, 'errors');
	} else {
		$code = Sms123Api::envoyer($numero, $message, 0, 'manuel');
		$ok = in_array($code, array('80', '81'), true);
		setEventMessages(Sms123Api::libelle($code), null, $ok ? 'mesgs' : 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', 'Envoyer un SMS - 123-SMS');

// --------------------------------------------------- solde du compte
$solde = Sms123Api::solde();
$couleur = ($solde !== null && $solde < 20) ? '#c83232' : '#268614';
print '<div class="center" style="margin:6px 0 14px;">';
print '<span style="display:inline-block; padding:8px 18px; border-radius:6px; border:1px solid #c9e6f2; background:#f2faff;">';
print '<b>Solde 123-SMS.net :</b> ';
if ($solde === null) {
	print '<span class="opacitymedium">indisponible</span>';
} else {
	print '<b style="color:'.$couleur.'">'.price2num($solde, 'MT').' SMS</b>';
	if ($solde < 20) {
		print ' <span style="color:#c83232">(pensez a recharger)</span>';
	}
}
print ' &nbsp;&mdash;&nbsp; <a href="https://www.123-sms.net/" target="_blank" rel="noopener">espace client</a>';
print '</span></div>';

// Roue crantee vers la configuration (administrateurs)
$lienconfig = '';
if (!empty($user->admin)) {
	$lienconfig = '<a class="valignmiddle" href="'.dol_buildpath('/sms123/admin/setup.php', 1)
		.'?backtopage='.urlencode($_SERVER['PHP_SELF']).'" title="Configuration du module 123-SMS">'
		.img_picto('Configuration du module 123-SMS', 'setup', 'class="pictofixedwidth"').'</a>';
}
print load_fiche_titre('Envoyer un SMS via 123-SMS.net', $lienconfig, 'object_phoning');

// --------------------------------------------------- formulaire
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="send">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Nouveau message</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Destinataire(s)</td><td>'
	.'<input type="text" id="sms123numero" name="numero" size="40" value="'.dol_escape_htmltag($prenumero).'" placeholder="0601020304 (plusieurs : separes par -)"'.(empty($prenumero) ? ' autofocus' : '').'></td></tr>';
print '<tr class="oddeven"><td>Message</td><td>'
	.'<textarea id="sms123message" name="message" rows="4" cols="60" maxlength="480">'.dol_escape_htmltag($premessage).'</textarea>'
	.'<br><span id="sms123compteur" class="opacitymedium">160 caract&egrave;res GSM par SMS (message long : facturation par segment).</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="Envoyer le SMS"></div>';
print '</form>';

// --------------------------------------------------- historique
print '<br>';
print load_fiche_titre('Historique des envois', '', 'generic');

$sql = 'SELECT rowid, datec, numero, message, code, methode, origine, fk_user FROM '.MAIN_DB_PREFIX.'sms123_envoi';
$sql .= ' WHERE entity = '.((int) $conf->entity);
$sql .= ' ORDER BY datec DESC';
$sql .= $db->plimit(25, 0);
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Date</td><td>Destinataire</td><td>Message</td><td>R&eacute;sultat</td><td>Origine</td>';
print '</tr>';
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$reussi = in_array($obj->code, array('80', '81'), true);
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
		print '<td>'.dol_escape_htmltag($obj->numero).'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($obj->message, 60)).'</td>';
		print '<td><b style="color:'.($reussi ? '#268614' : '#c83232').'">'.dol_escape_htmltag($obj->code).'</b> '
			.'<span class="opacitymedium">'.dol_escape_htmltag(Sms123Api::libelle($obj->code)).'</span></td>';
		print '<td class="opacitymedium">'.dol_escape_htmltag($obj->origine).'</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="5" class="opacitymedium center">Aucun envoi enregistr&eacute; pour le moment.</td></tr>';
}
print '</table>';

print '<br><div class="opacitymedium">Astuce d&eacute;veloppeur : la classe <b>Sms123Api</b> est r&eacute;utilisable partout&nbsp;: '
	.'<code>dol_include_once(\'/sms123/class/sms123api.class.php\'); Sms123Api::envoyer($numero, $message);</code><br>'
	.'Pour des envois automatiques sans code, activez les <b>d&eacute;clencheurs</b> dans la configuration du module.</div>';


// Compteur de caracteres : nombre de SMS et alerte Unicode
print '<script>
(function() {
	var zone = document.getElementById("sms123message");
	var info = document.getElementById("sms123compteur");
	if (!zone || !info) { return; }
	function compter() {
		var texte = zone.value;
		var n = texte.length;
		var unicode = /[^A-Za-z0-9 @£$¥èéùìòÇØøÅåÆæßÉ!\"#%&\'()*+,\-.\/:;<=>?_¡¿§ÄÖÑÜäöñüà\r\n]/.test(texte);
		var parSms = unicode ? 70 : 160;
		var parSegment = unicode ? 67 : 153;
		var sms = n <= parSms ? 1 : Math.ceil(n / parSegment);
		if (n === 0) { sms = 0; }
		var texteInfo = n + " caractere" + (n > 1 ? "s" : "") + " — " + sms + " SMS";
		if (unicode) { texteInfo += " (caracteres speciaux : 70 caracteres par SMS)"; }
		info.textContent = texteInfo;
		info.style.color = sms > 1 ? "#8a5a0f" : "";
	}
	zone.addEventListener("input", compter);
	compter();
})();
</script>';

llxFooter();
$db->close();
