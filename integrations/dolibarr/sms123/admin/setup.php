<?php
/* Configuration du module 123-SMS - licence MIT */
$res = 0;
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/sms123/class/sms123api.class.php');
dol_include_once('/sms123/core/triggers/interface_99_modSms123_Sms123Triggers.class.php');

if (empty($user->admin)) {
	accessforbidden();
}

$langs->load('sms123@sms123');

$action = GETPOST('action', 'aZ09');
$evenements = InterfaceSms123Triggers::evenements();

$modeles_defaut = array(
	'ORDER_VALIDATE' => '{masociete} : votre commande {ref} est enregistree. Merci !',
	'BILL_VALIDATE' => '{masociete} : votre facture {ref} de {total} EUR est disponible.',
	'BILL_PAYED' => '{masociete} : paiement de la facture {ref} bien recu. Merci !',
	'SHIPPING_VALIDATE' => '{masociete} : votre commande est expediee ({ref}).',
	'PROPAL_VALIDATE' => '{masociete} : votre devis {ref} de {total} EUR vous attend.',
);

if ($action == 'save') {
	foreach (array('SMS123_IDENTIFIANT', 'SMS123_CLEAPI', 'SMS123_SENDER', 'SMS123_NUM_ADMIN') as $cle) {
		dolibarr_set_const($db, $cle, GETPOST(strtolower($cle), 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('Sms123SettingsSaved'), null, 'mesgs');
}

if ($action == 'savecron') {
	dolibarr_set_const($db, 'SMS123_RDV_ACTIF', GETPOST('rdv_actif', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RDV_TYPES', implode(',', array_map('intval', GETPOST('rdv_types', 'array'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RDV_HEURES', (int) GETPOST('rdv_heures', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RDV_TPL', GETPOST('rdv_tpl', 'restricthtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RELANCE_ACTIF', GETPOST('relance_actif', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RELANCE_JOURS', (int) GETPOST('relance_jours', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RELANCE_REPETER', (int) GETPOST('relance_repeter', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_RELANCE_TPL', GETPOST('relance_tpl', 'restricthtml'), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('Sms123CronSaved'), null, 'mesgs');
}

if ($action == 'savetrig') {
	foreach (array_keys($evenements) as $ev) {
		dolibarr_set_const($db, 'SMS123_TRIG_'.$ev, GETPOST('trig_'.$ev, 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_TPL_'.$ev, GETPOST('tpl_'.$ev, 'restricthtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_DEST_'.$ev, GETPOST('dest_'.$ev, 'aZ09') == 'admin' ? 'admin' : 'client', 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('Sms123TrigSaved'), null, 'mesgs');
}

if ($action == 'saveavance') {
	dolibarr_set_const($db, 'SMS123_AR_ACTIF', GETPOST('ar_actif', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_AR_CLE', GETPOST('ar_cle', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_AGENDA', GETPOST('agenda', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_ALERTE_ACTIF', GETPOST('alerte_actif', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_ALERTE_SEUIL', (int) GETPOST('alerte_seuil', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_ALERTE_MAIL', GETPOST('alerte_mail', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_ALERTE_SMS', GETPOST('alerte_sms', 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SMS123_ALERTE_REPETER', (int) GETPOST('alerte_repeter', 'int'), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('Sms123AdvancedSaved'), null, 'mesgs');
}

llxHeader('', $langs->trans('Sms123SetupTitle'));
print load_fiche_titre($langs->trans('Sms123SetupTitle'), '', 'setup');

// --------------------------------------------------- solde
$solde = Sms123Api::solde();
if ($solde !== null) {
	print '<div class="center" style="margin-bottom:12px;"><span style="display:inline-block; padding:8px 18px; border-radius:6px; border:1px solid #c9e6f2; background:#f2faff;">';
	print '<b>'.$langs->trans('Sms123DiagBalance').' :</b> <b style="color:'.($solde < 20 ? '#c83232' : '#268614').'">'
		.price2num($solde, 'MT').' '.$langs->trans('Sms123SmsUnit').'</b>';
	print ' &nbsp;&mdash;&nbsp; <a href="https://www.123-sms.net/" target="_blank" rel="noopener">'.$langs->trans('Sms123TopUp').'</a>';
	print '</span></div>';
}

print '<span class="opacitymedium">'.$langs->trans('Sms123SetupIntro').'</span><br><br>';

// --------------------------------------------------- compte
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Sms123Section1').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield"><label for="sms123_identifiant">'.$langs->trans('Sms123Login').'</label></td><td>'
	.'<input type="text" id="sms123_identifiant" name="sms123_identifiant" size="40" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_IDENTIFIANT')).'"></td></tr>';
print '<tr class="oddeven"><td><label for="sms123_cleapi">'.$langs->trans('Sms123ApiKey').'</label></td><td>'
	.'<input type="password" id="sms123_cleapi" name="sms123_cleapi" size="40" autocomplete="new-password" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_CLEAPI')).'"></td></tr>';
print '<tr class="oddeven"><td><label for="sms123_sender">'.$langs->trans('Sms123Sender').'</label></td><td>'
	.'<input type="text" id="sms123_sender" name="sms123_sender" size="15" maxlength="11" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_SENDER')).'">'
	.' <span class="opacitymedium">'.$langs->trans('Sms123SenderHint').'</span></td></tr>';
print '<tr class="oddeven"><td><label for="sms123_num_admin">'.$langs->trans('Sms123AdminNumber').'</label></td><td>'
	.'<input type="text" id="sms123_num_admin" name="sms123_num_admin" size="20" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_NUM_ADMIN')).'" placeholder="0601020304">'
	.' <span class="opacitymedium">'.$langs->trans('Sms123AdminNumberHint').'</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Sms123Save')).'"></div>';
print '</form>';

// --------------------------------------------------- test connexion
print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="test">';
print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Sms123TestButton')).'">';
print '<br><span class="opacitymedium">'.$langs->trans('Sms123TestHint').'</span></div>';
print '</form>';

if ($action == 'test') {
	print '<br>';
	print load_fiche_titre($langs->trans('Sms123DiagTitle'), '', 'generic');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans('Sms123DiagCheck').'</td>'
		.'<td width="90">'.$langs->trans('Sms123DiagState').'</td><td>'.$langs->trans('Sms123DiagDetail').'</td></tr>';
	foreach (Sms123Api::diagnostic() as $test) {
		list($libelle, $etat, $detail) = $test;
		if ($etat == 'ok') {
			$couleur = '#268614';
			$mot = $langs->trans('Sms123DiagOk');
		} elseif ($etat == 'ko') {
			$couleur = '#c83232';
			$mot = $langs->trans('Sms123DiagKo');
		} else {
			$couleur = '#666666';
			$mot = $langs->trans('Sms123DiagInfo');
		}
		print '<tr class="oddeven"><td>'.$libelle.'</td>'
			.'<td><b style="color:'.$couleur.'">'.$mot.'</b></td>'
			.'<td>'.$detail.'</td></tr>';
	}
	print '</table>';
}

// --------------------------------------------------- declencheurs
print '<br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="savetrig">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="4">'.$langs->trans('Sms123Section2').'</td></tr>';
print '<tr class="liste_titre"><td width="60">'.$langs->trans('Sms123TrigActive').'</td>'
	.'<td class="titlefield">'.$langs->trans('Sms123TrigEvent').'</td>'
	.'<td width="150">'.$langs->trans('Sms123TrigDest').'</td><td>'.$langs->trans('Sms123Message').'</td></tr>';
foreach ($evenements as $ev => $cle) {
	$actif = getDolGlobalString('SMS123_TRIG_'.$ev);
	$modele = getDolGlobalString('SMS123_TPL_'.$ev);
	if ($modele === '') {
		$modele = isset($modeles_defaut[$ev]) ? $modeles_defaut[$ev] : '';
	}
	$dest = getDolGlobalString('SMS123_DEST_'.$ev);
	print '<tr class="oddeven">';
	print '<td class="center"><input type="checkbox" name="trig_'.$ev.'" value="1"'.($actif ? ' checked' : '').'></td>';
	print '<td><b>'.$langs->trans($cle).'</b><br><span class="opacitymedium">'.$ev.'</span></td>';
	print '<td><select name="dest_'.$ev.'">';
	print '<option value="client"'.($dest != 'admin' ? ' selected' : '').'>'.$langs->trans('Sms123TrigDestClient').'</option>';
	print '<option value="admin"'.($dest == 'admin' ? ' selected' : '').'>'.$langs->trans('Sms123TrigDestAdmin').'</option>';
	print '</select></td>';
	print '<td><input type="text" name="tpl_'.$ev.'" class="quatrevingtpercent" value="'.dol_escape_htmltag($modele).'"></td>';
	print '</tr>';
}
print '</table>';
print '<div class="opacitymedium" style="margin:8px 0;">'.$langs->trans('Sms123Variables').'<br>'
	.$langs->trans('Sms123VariablesDest').'</div>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Sms123TrigSaveButton')).'"></div>';
print '</form>';

// --------------------------------------------------- rappels et relances
print '<br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="savecron">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Sms123Section3').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Sms123RdvActive').'</td><td>'
	.'<input type="checkbox" name="rdv_actif" value="1"'.(getDolGlobalString('SMS123_RDV_ACTIF') ? ' checked' : '').'></td></tr>';

// types d'evenements de l'agenda
$types = array();
$sqlt = 'SELECT id, code, libelle FROM '.MAIN_DB_PREFIX.'c_actioncomm WHERE active = 1 ORDER BY position, libelle';
$resqlt = $db->query($sqlt);
if ($resqlt) {
	while ($obj = $db->fetch_object($resqlt)) {
		$types[$obj->id] = empty($obj->libelle) ? $obj->code : $obj->libelle;
	}
	$db->free($resqlt);
}
$choisis = explode(',', getDolGlobalString('SMS123_RDV_TYPES'));
print '<tr class="oddeven"><td>'.$langs->trans('Sms123RdvTypes').'</td><td>';
if (count($types)) {
	print '<select name="rdv_types[]" multiple size="6" style="min-width:280px;">';
	foreach ($types as $id => $libelle) {
		print '<option value="'.((int) $id).'"'.(in_array((string) $id, $choisis, true) ? ' selected' : '').'>'
			.dol_escape_htmltag($libelle).'</option>';
	}
	print '</select><br><span class="opacitymedium">'.$langs->trans('Sms123RdvTypesHint').'</span>';
} else {
	print '<span class="opacitymedium">'.$langs->trans('Sms123RdvNoType').'</span>';
}
print '</td></tr>';

$heures = (int) getDolGlobalString('SMS123_RDV_HEURES');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123RdvDelay').'</td><td>'
	.'<input type="number" name="rdv_heures" min="1" max="168" value="'.($heures > 0 ? $heures : 24).'" style="width:80px;"> '
	.$langs->trans('Sms123Hours').' <span class="opacitymedium">'.$langs->trans('Sms123RdvDelayHint').'</span></td></tr>';
$tplrdv = getDolGlobalString('SMS123_RDV_TPL');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123Message').'</td><td>'
	.'<input type="text" name="rdv_tpl" class="quatrevingtpercent" value="'
	.dol_escape_htmltag($tplrdv !== '' ? $tplrdv : 'Rappel : rendez-vous le {date} a {heure}. {masociete}').'">'
	.'<br><span class="opacitymedium">'.$langs->trans('Sms123RdvVariables').'</span></td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Sms123Section4').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Sms123RelanceActive').'</td><td>'
	.'<input type="checkbox" name="relance_actif" value="1"'.(getDolGlobalString('SMS123_RELANCE_ACTIF') ? ' checked' : '').'></td></tr>';
$jours = (int) getDolGlobalString('SMS123_RELANCE_JOURS');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123RelanceFrom').'</td><td>'
	.'<input type="number" name="relance_jours" min="0" max="365" value="'.$jours.'" style="width:80px;"> '
	.$langs->trans('Sms123RelanceFromHint').'</td></tr>';
$repeter = (int) getDolGlobalString('SMS123_RELANCE_REPETER');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123RelanceRepeat').'</td><td>'
	.'<input type="number" name="relance_repeter" min="1" max="365" value="'.($repeter > 0 ? $repeter : 15).'" style="width:80px;"> '
	.$langs->trans('Sms123Days').'</td></tr>';
$tplrel = getDolGlobalString('SMS123_RELANCE_TPL');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123Message').'</td><td>'
	.'<input type="text" name="relance_tpl" class="quatrevingtpercent" value="'
	.dol_escape_htmltag($tplrel !== '' ? $tplrel : '{masociete} : votre facture {ref} de {total} EUR est arrivee a echeance. Merci de regulariser.').'">'
	.'<br><span class="opacitymedium">'.$langs->trans('Sms123RelanceVariables').'</span></td></tr>';
print '</table>';
print '<div class="opacitymedium" style="margin:8px 0;">'.$langs->trans('Sms123CronWarning').'</div>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Sms123CronSaveButton')).'"></div>';
print '</form>';

// --------------------------------------------------- options avancees
print '<br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="saveavance">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Sms123Section5').'</td></tr>';

// accuses de reception
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Sms123ArActive').'</td><td>'
	.'<input type="checkbox" name="ar_actif" value="1"'.(getDolGlobalString('SMS123_AR_ACTIF') ? ' checked' : '').'>'
	.' <span class="opacitymedium">'.$langs->trans('Sms123ArHint').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Sms123ArUrl').'</td><td>'
	.'<input type="text" class="quatrevingtpercent" readonly value="'.dol_escape_htmltag(Sms123Api::urlAccuse()).'"></td></tr>';
print '<tr class="oddeven"><td><label for="sms123_ar_cle">'.$langs->trans('Sms123ArKey').'</label></td><td>'
	.'<input type="text" id="sms123_ar_cle" name="ar_cle" size="30" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_AR_CLE')).'">'
	.' <span class="opacitymedium">'.$langs->trans('Sms123ArKeyHint').'</span></td></tr>';

// trace agenda
print '<tr class="oddeven"><td>'.$langs->trans('Sms123AgendaActive').'</td><td>'
	.'<input type="checkbox" name="agenda" value="1"'.(getDolGlobalString('SMS123_AGENDA') ? ' checked' : '').'>'
	.' <span class="opacitymedium">'.$langs->trans('Sms123AgendaHint').'</span></td></tr>';

// alerte de solde bas
$seuil = (int) getDolGlobalString('SMS123_ALERTE_SEUIL');
$repalerte = (int) getDolGlobalString('SMS123_ALERTE_REPETER');
print '<tr class="oddeven"><td>'.$langs->trans('Sms123AlerteActive').'</td><td>'
	.'<input type="checkbox" name="alerte_actif" value="1"'.(getDolGlobalString('SMS123_ALERTE_ACTIF') ? ' checked' : '').'></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Sms123AlerteSeuil').'</td><td>'
	.'<input type="number" name="alerte_seuil" min="1" max="100000" value="'.($seuil > 0 ? $seuil : 50).'" style="width:90px;"> '
	.$langs->trans('Sms123SmsUnit').'</td></tr>';
$mailalerte = getDolGlobalString('SMS123_ALERTE_MAIL');
print '<tr class="oddeven"><td><label for="sms123_alerte_mail">'.$langs->trans('Sms123AlerteMail').'</label></td><td>'
	.'<input type="email" id="sms123_alerte_mail" name="alerte_mail" size="40" value="'.dol_escape_htmltag($mailalerte).'" placeholder="'
	.dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_MAIL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Sms123AlerteSms').'</td><td>'
	.'<input type="checkbox" name="alerte_sms" value="1"'.(getDolGlobalString('SMS123_ALERTE_SMS') ? ' checked' : '').'></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Sms123AlerteRepeat').'</td><td>'
	.'<input type="number" name="alerte_repeter" min="1" max="365" value="'.($repalerte > 0 ? $repalerte : 3).'" style="width:80px;"> '
	.$langs->trans('Sms123Days').'</td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Sms123AdvancedSaveButton')).'"></div>';
print '</form>';

print '<br><br><span class="opacitymedium">'.$langs->trans('Sms123FiveWays').'</span>';

llxFooter();
$db->close();
