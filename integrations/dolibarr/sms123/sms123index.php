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

$langs->load('sms123@sms123');

$action = GETPOST('action', 'aZ09');
$prenumero = GETPOST('numero', 'alphanohtml');
$premessage = GETPOST('message', 'restricthtml');
$socid = (int) GETPOST('socid', 'int');
if ($action == 'send') {
	$prenumero = '';
	$premessage = '';
}

// Envoi puis redirection : le formulaire repart vide (schema POST/Redirect/GET)
if ($action == 'send') {
	$numero = GETPOST('numero', 'alphanohtml');
	$message = GETPOST('message', 'restricthtml');
	if (empty($numero) || empty($message)) {
		setEventMessages($langs->transnoentities('Sms123NeedRecipientAndMessage'), null, 'errors');
	} else {
		$code = Sms123Api::envoyer($numero, $message, 0, 'manuel', $socid);
		$ok = in_array($code, array('80', '81'), true);
		setEventMessages(Sms123Api::libelle($code), null, $ok ? 'mesgs' : 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->transnoentities('Sms123SendTitle'));

// --------------------------------------------------- solde du compte
$solde = Sms123Api::solde();
$couleur = ($solde !== null && $solde < 20) ? '#c83232' : '#268614';
print '<div class="center" style="margin:6px 0 14px;">';
print '<span style="display:inline-block; padding:8px 18px; border-radius:6px; border:1px solid #c9e6f2; background:#f2faff;">';
print '<b>'.$langs->transnoentities('Sms123Balance').' :</b> ';
if ($solde === null) {
	print '<span class="opacitymedium">'.$langs->transnoentities('Sms123BalanceUnavailable').'</span>';
} else {
	print '<b style="color:'.$couleur.'">'.price2num($solde, 'MT').' '.$langs->transnoentities('Sms123SmsUnit').'</b>';
	if ($solde < 20) {
		print ' <span style="color:#c83232">('.$langs->transnoentities('Sms123TopUpAdvice').')</span>';
	}
}
print ' &nbsp;&mdash;&nbsp; <a href="https://www.123-sms.net/" target="_blank" rel="noopener">'.$langs->transnoentities('Sms123CustomerArea').'</a>';
print '</span></div>';

// Roue crantee vers la configuration (administrateurs)
$lienconfig = '';
if (!empty($user->admin)) {
	$lienconfig = '<a class="valignmiddle" href="'.dol_buildpath('/sms123/admin/setup.php', 1)
		.'?backtopage='.urlencode($_SERVER['PHP_SELF']).'" title="'.dol_escape_htmltag($langs->transnoentities('Sms123ConfigLink')).'">'
		.img_picto($langs->transnoentities('Sms123ConfigLink'), 'setup', 'class="pictofixedwidth"').'</a>';
}
print load_fiche_titre($langs->transnoentities('Sms123SendTitle'), $lienconfig, 'object_phoning');

// --------------------------------------------------- formulaire
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="send">';
print '<input type="hidden" name="socid" value="'.((int) $socid).'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->transnoentities('Sms123NewMessage').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield"><label for="sms123numero">'.$langs->transnoentities('Sms123Recipients').'</label></td><td>'
	.'<input type="text" id="sms123numero" name="numero" size="40" value="'.dol_escape_htmltag($prenumero).'" placeholder="'
	.dol_escape_htmltag($langs->transnoentities('Sms123RecipientsHint')).'"'.(empty($prenumero) ? ' autofocus' : '').'></td></tr>';
print '<tr class="oddeven"><td><label for="sms123message">'.$langs->transnoentities('Sms123Message').'</label></td><td>'
	.'<textarea id="sms123message" name="message" rows="4" cols="60" maxlength="480">'.dol_escape_htmltag($premessage).'</textarea>'
	.'<br><span id="sms123compteur" class="opacitymedium">'.$langs->transnoentities('Sms123CounterHint').'</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123SendButton')).'">';
print ' &nbsp; <a class="butAction" href="'.dol_buildpath('/sms123/sms123masse.php', 1).'">'.$langs->transnoentities('Sms123MassLink').'</a></div>';
print '</form>';

// --------------------------------------------------- historique
print '<br>';
print load_fiche_titre($langs->transnoentities('Sms123History'), '', 'generic');

$ar = getDolGlobalString('SMS123_AR_ACTIF');

$sql = 'SELECT rowid, datec, numero, message, code, methode, origine, fk_user, reference, statut, date_ar, erreur_ar';
$sql .= ' FROM '.MAIN_DB_PREFIX.'sms123_envoi';
$sql .= ' WHERE entity = '.((int) $conf->entity);
$sql .= ' ORDER BY datec DESC';
$sql .= $db->plimit(25, 0);
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->transnoentities('Sms123Date').'</td><td>'.$langs->transnoentities('Sms123Recipient').'</td>';
print '<td>'.$langs->transnoentities('Sms123Message').'</td><td>'.$langs->transnoentities('Sms123Result').'</td>';
if ($ar) {
	print '<td>'.$langs->transnoentities('Sms123Status').'</td>';
}
print '<td>'.$langs->transnoentities('Sms123Origin').'</td>';
print '</tr>';
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$reussi = in_array($obj->code, array('80', '81'), true);
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
		print '<td>'.dol_escape_htmltag($obj->numero).'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($obj->message, 60)).'</td>';
		print '<td><b style="color:'.($reussi ? '#268614' : '#c83232').'">'.dol_escape_htmltag($obj->code).'</b> '
			.'<span class="opacitymedium">'.Sms123Api::libelle($obj->code);
		if ($obj->code === 'ERR' && !empty($obj->reference)) {
			print ' &mdash; '.dol_escape_htmltag($obj->reference);
		}
		print '</span></td>';
		if ($ar) {
			$statut = empty($obj->statut) ? ($reussi ? 'attente' : '') : $obj->statut;
			$couleurar = ($statut == 'remis') ? '#268614' : (($statut == 'non-remis') ? '#c83232' : '');
			print '<td'.($couleurar ? ' style="color:'.$couleurar.'"' : ' class="opacitymedium"').'>'
				.Sms123Api::libelleStatut($statut);
			if ($statut == 'non-remis' && !empty($obj->erreur_ar)) {
				print ' <span class="opacitymedium">('.dol_escape_htmltag($obj->erreur_ar).')</span>';
			}
			print '</td>';
		}
		print '<td class="opacitymedium">'.dol_escape_htmltag($obj->origine).'</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="'.($ar ? 6 : 5).'" class="opacitymedium center">'
		.$langs->transnoentities('Sms123NoHistory').'</td></tr>';
}
print '</table>';

print '<br><div class="opacitymedium">'.$langs->transnoentities('Sms123DevTip')
	.' <code>dol_include_once(\'/sms123/class/sms123api.class.php\'); Sms123Api::envoyer($numero, $message);</code><br>'
	.$langs->transnoentities('Sms123DevTipTriggers').'</div>';

// Compteur de caracteres : nombre de SMS et alerte Unicode.
// Les caracteres GSM non ASCII sont ecrits en echappement \uXXXX pour que
// ce fichier reste en ASCII pur quel que soit l'encodage du serveur.
print '<script>
(function() {
	var zone = document.getElementById("sms123message");
	var info = document.getElementById("sms123compteur");
	if (!zone || !info) { return; }
	var motChars = '.json_encode($langs->transnoentities('Sms123Chars')).';
	var motSms = '.json_encode($langs->transnoentities('Sms123SmsUnit')).';
	var motUnicode = '.json_encode($langs->transnoentities('Sms123UnicodeWarning')).';
	var gsm = /[^A-Za-z0-9 @\u00A3$\u00A5\u00E8\u00E9\u00F9\u00EC\u00F2\u00C7\u00D8\u00F8\u00C5\u00E5\u00C6\u00E6\u00DF\u00C9!\"#%&\'()*+,\-.\/:;<=>?_\u00A1\u00BF\u00A7\u00C4\u00D6\u00D1\u00DC\u00E4\u00F6\u00F1\u00FC\u00E0\r\n]/;
	function compter() {
		var texte = zone.value;
		var n = texte.length;
		var unicode = gsm.test(texte);
		var parSms = unicode ? 70 : 160;
		var parSegment = unicode ? 67 : 153;
		var sms = n <= parSms ? 1 : Math.ceil(n / parSegment);
		if (n === 0) { sms = 0; }
		var texteInfo = n + " " + motChars + " \u2014 " + sms + " " + motSms;
		if (unicode) { texteInfo += " " + motUnicode; }
		info.textContent = texteInfo;
		info.style.color = sms > 1 ? "#8a5a0f" : "";
	}
	zone.addEventListener("input", compter);
	compter();
})();
</script>';

llxFooter();
$db->close();
