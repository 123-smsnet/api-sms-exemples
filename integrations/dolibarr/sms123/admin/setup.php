<?php
/* Configuration du module 123-SMS - licence MIT */
$res = 0;
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
if ($action == 'save') {
	dolibarr_set_const($db, 'SMS123_IDENTIFIANT', GETPOST('identifiant', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_CLEAPI', GETPOST('cleapi', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_SENDER', GETPOST('sender', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	setEventMessages('Configuration enregistree', null, 'mesgs');
}

llxHeader('', '123-SMS - Configuration');
print load_fiche_titre('123-SMS.net &mdash; Configuration', '', 'title_setup');

print '<div class="opacitymedium">Identifiant et cl&eacute; API : transmis par e-mail &agrave; '
	.'l\'inscription sur <a href="https://www.123-sms.net" target="_blank" rel="noopener">123-SMS.net</a> '
	.'ou lors de la r&eacute;g&eacute;n&eacute;ration de la cl&eacute; (espace client &rsaquo; API).</div><br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Param&egrave;tre</td><td>Valeur</td></tr>';
print '<tr class="oddeven"><td>Identifiant 123-SMS</td><td>'
	.'<input type="text" name="identifiant" size="40" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_IDENTIFIANT')).'"></td></tr>';
print '<tr class="oddeven"><td>Cl&eacute; API</td><td>'
	.'<input type="password" name="cleapi" size="40" autocomplete="new-password" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_CLEAPI')).'"></td></tr>';
print '<tr class="oddeven"><td>Sender-ID (optionnel, &agrave; d&eacute;clarer aupr&egrave;s de 123-SMS)</td><td>'
	.'<input type="text" name="sender" size="15" maxlength="11" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_SENDER')).'"></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="Enregistrer"></div>';
print '</form>';

llxFooter();
$db->close();
