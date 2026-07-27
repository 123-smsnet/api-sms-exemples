<?php
/* Configuration du module 123-SMS - licence MIT */
$res = 0;
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/sms123/class/sms123api.class.php');

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'save') {
	dolibarr_set_const($db, 'SMS123_IDENTIFIANT', GETPOST('identifiant', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_CLEAPI', GETPOST('cleapi', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_SENDER', GETPOST('sender', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	setEventMessages('Reglages enregistres.', null, 'mesgs');
}

llxHeader('', 'Configuration 123-SMS');
print load_fiche_titre('Module SMS 123-SMS.net', '', 'setup');

print '<span class="opacitymedium">Identifiant et cl&eacute; API : transmis par e-mail &agrave; l\'inscription sur '
	.'<a href="https://www.123-sms.net/" target="_blank" rel="noopener">123-sms.net</a> '
	.'(espace client &rsaquo; API).</span><br><br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Compte 123-SMS.net</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Identifiant</td><td>'
	.'<input type="text" name="identifiant" size="40" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_IDENTIFIANT')).'"></td></tr>';
print '<tr class="oddeven"><td>Cl&eacute; API</td><td>'
	.'<input type="password" name="cleapi" size="40" autocomplete="new-password" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_CLEAPI')).'"></td></tr>';
print '<tr class="oddeven"><td>Sender-ID (optionnel)</td><td>'
	.'<input type="text" name="sender" size="15" maxlength="11" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_SENDER')).'">'
	.' <span class="opacitymedium">nom d\'exp&eacute;diteur personnalis&eacute;, &agrave; d&eacute;clarer aupr&egrave;s de 123-SMS</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="Enregistrer"></div>';
print '</form>';

print '<br>';

/* ------------------------------------------------ test de connexion */
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="test">';
print '<div class="center"><input type="submit" class="button button-save" value="Tester la connexion">';
print '<br><span class="opacitymedium">Envoi &agrave; blanc : aucun SMS n\'est envoy&eacute;, aucun cr&eacute;dit n\'est d&eacute;bit&eacute;.</span></div>';
print '</form>';

if ($action == 'test') {
	print '<br>';
	print load_fiche_titre('R&eacute;sultat du diagnostic', '', 'generic');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="titlefield">V&eacute;rification</td><td width="60">&Eacute;tat</td><td>D&eacute;tail</td></tr>';
	foreach (Sms123Api::diagnostic() as $test) {
		list($libelle, $etat, $detail) = $test;
		if ($etat == 'ok') {
			$couleur = '#268614';
			$mot = 'OK';
		} elseif ($etat == 'ko') {
			$couleur = '#c83232';
			$mot = 'A CORRIGER';
		} else {
			$couleur = '#666666';
			$mot = 'info';
		}
		print '<tr class="oddeven"><td>'.$libelle.'</td>'
			.'<td><b style="color:'.$couleur.'">'.$mot.'</b></td>'
			.'<td>'.dol_escape_htmltag($detail).'</td></tr>';
	}
	print '</table>';
	print '<br><span class="opacitymedium">En cas de blocage r&eacute;seau, v&eacute;rifiez que le serveur peut joindre '
		.'https://www.123-sms.net en sortie (pare-feu, proxy). Support : contact@123-sms.net</span>';
}

print '<br><br><span class="opacitymedium">Envoi manuel : menu <b>Outils &rsaquo; SMS 123-SMS</b>. '
	.'Automatisations : <code>Sms123Api::envoyer($numero, $message)</code> depuis vos triggers et crons.</span>';

llxFooter();
$db->close();
