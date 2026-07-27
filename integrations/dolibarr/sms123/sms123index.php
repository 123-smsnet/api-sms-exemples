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
$numero = GETPOST('numero', 'alphanohtml');
$message = GETPOST('message', 'restricthtml');
$resultat = '';

if ($action == 'send' && !empty($numero) && !empty($message)) {
	$code = Sms123Api::envoyer($numero, $message);
	$resultat = Sms123Api::libelle($code);
	$ok = in_array($code, array('80', '81', '92'));
	setEventMessages($resultat, null, $ok ? 'mesgs' : 'errors');
}

llxHeader('', 'Envoyer un SMS - 123-SMS');
print load_fiche_titre('Envoyer un SMS via 123-SMS.net', '', 'object_phoning');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="send">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Nouveau message</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Destinataire(s)</td><td>'
	.'<input type="text" name="numero" size="40" value="'.dol_escape_htmltag($numero).'" placeholder="0601020304 (plusieurs : separes par -)"></td></tr>';
print '<tr class="oddeven"><td>Message</td><td>'
	.'<textarea name="message" rows="4" cols="60" maxlength="480">'.dol_escape_htmltag($message).'</textarea>'
	.'<br><span class="opacitymedium">160 caract&egrave;res GSM par SMS (message long : facturation par segment).</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="Envoyer le SMS"></div>';
print '</form>';

print '<br><div class="opacitymedium">Astuce d&eacute;veloppeur : la classe <b>Sms123Api</b> '
	.'(class/sms123api.class.php) est r&eacute;utilisable dans vos triggers et crons : '
	.'<code>Sms123Api::envoyer($numero, $message)</code>.</div>';

llxFooter();
$db->close();
