<?php
/* Configuration du module 123-SMS - licence MIT
 * Reglages presentes en onglets : Compte, Envois automatiques,
 * Rappels et relances, Options avancees, Aide.
 */
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

// Onglet affiche : conserve apres chaque enregistrement (champ cache des formulaires)
$onglets = array('compte', 'declencheurs', 'rappels', 'avance', 'aide');
$onglet = GETPOST('onglet', 'aZ09');
if (!in_array($onglet, $onglets, true)) {
	$onglet = 'compte';
}

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
	setEventMessages($langs->transnoentities('Sms123SettingsSaved'), null, 'mesgs');
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
	setEventMessages($langs->transnoentities('Sms123CronSaved'), null, 'mesgs');
}

if ($action == 'savetrig') {
	foreach (array_keys($evenements) as $ev) {
		dolibarr_set_const($db, 'SMS123_TRIG_'.$ev, GETPOST('trig_'.$ev, 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_TPL_'.$ev, GETPOST('tpl_'.$ev, 'restricthtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_DEST_'.$ev, GETPOST('dest_'.$ev, 'aZ09') == 'admin' ? 'admin' : 'client', 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->transnoentities('Sms123TrigSaved'), null, 'mesgs');
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
	setEventMessages($langs->transnoentities('Sms123AdvancedSaved'), null, 'mesgs');
}

/** Champ cache qui ramene sur le meme onglet apres enregistrement. */
function sms123_champ_onglet($onglet)
{
	return '<input type="hidden" name="onglet" value="'.dol_escape_htmltag($onglet).'">';
}

/**
 * Entete d'onglets. Utilise la presentation standard de Dolibarr ; si la
 * fonction n'existe pas, se rabat sur une simple barre de liens plutot que
 * de faire echouer la page.
 *
 * @param array  $tete  lignes array(url, libelle, code)
 * @param string $actif code de l'onglet courant
 * @return string
 */
function sms123_tete_onglets($tete, $actif)
{
	if (function_exists('dol_get_fiche_head')) {
		return dol_get_fiche_head($tete, $actif, '', -1);
	}

	$html = '<div style="margin:0 0 14px;">';
	foreach ($tete as $t) {
		$html .= '<a href="'.$t[0].'" style="margin-right:14px;'
			.($t[2] == $actif ? ' font-weight:bold;' : '').'">'.$t[1].'</a>';
	}

	return $html.'</div>';
}

/** Fermeture de l'entete d'onglets. */
function sms123_fin_onglets()
{
	return function_exists('dol_get_fiche_end') ? dol_get_fiche_end() : '';
}

llxHeader('', $langs->transnoentities('Sms123SetupTitle'));
print load_fiche_titre($langs->transnoentities('Sms123SetupTitle'), '', 'setup');

// --------------------------------------------------- solde (toujours visible)
$solde = Sms123Api::solde();
if ($solde !== null) {
	print '<div class="center" style="margin-bottom:12px;"><span style="display:inline-block; padding:8px 18px; border-radius:6px; border:1px solid #c9e6f2; background:#f2faff;">';
	print '<b>'.$langs->transnoentities('Sms123DiagBalance').' :</b> <b style="color:'.($solde < 20 ? '#c83232' : '#268614').'">'
		.price2num($solde, 'MT').' '.$langs->transnoentities('Sms123SmsUnit').'</b>';
	print ' &nbsp;&mdash;&nbsp; <a href="https://www.123-sms.net/" target="_blank" rel="noopener">'.$langs->transnoentities('Sms123TopUp').'</a>';
	print '</span></div>';
}

// --------------------------------------------------- onglets
$base = dol_buildpath('/sms123/admin/setup.php', 1);
$tete = array();
$i = 0;
foreach (array(
	'compte' => 'Sms123TabAccount',
	'declencheurs' => 'Sms123TabTriggers',
	'rappels' => 'Sms123TabCron',
	'avance' => 'Sms123TabAdvanced',
	'aide' => 'Sms123TabHelp',
) as $code => $cle) {
	$tete[$i][0] = $base.'?onglet='.$code;
	$tete[$i][1] = $langs->transnoentities($cle);
	$tete[$i][2] = $code;
	$i++;
}
print sms123_tete_onglets($tete, $onglet);

// =================================================== onglet : compte
if ($onglet == 'compte') {
	print '<span class="opacitymedium">'.$langs->transnoentities('Sms123SetupIntro').'</span><br><br>';

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print sms123_champ_onglet('compte');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->transnoentities('Sms123Section1').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield"><label for="sms123_identifiant">'.$langs->transnoentities('Sms123Login').'</label></td><td>'
		.'<input type="text" id="sms123_identifiant" name="sms123_identifiant" size="40" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_IDENTIFIANT')).'"></td></tr>';
	print '<tr class="oddeven"><td><label for="sms123_cleapi">'.$langs->transnoentities('Sms123ApiKey').'</label></td><td>'
		.'<input type="password" id="sms123_cleapi" name="sms123_cleapi" size="40" autocomplete="new-password" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_CLEAPI')).'"></td></tr>';
	print '<tr class="oddeven"><td><label for="sms123_sender">'.$langs->transnoentities('Sms123Sender').'</label></td><td>'
		.'<input type="text" id="sms123_sender" name="sms123_sender" size="15" maxlength="11" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_SENDER')).'">'
		.' <span class="opacitymedium">'.$langs->transnoentities('Sms123SenderHint').'</span></td></tr>';
	print '<tr class="oddeven"><td><label for="sms123_num_admin">'.$langs->transnoentities('Sms123AdminNumber').'</label></td><td>'
		.'<input type="text" id="sms123_num_admin" name="sms123_num_admin" size="20" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_NUM_ADMIN')).'" placeholder="0601020304">'
		.' <span class="opacitymedium">'.$langs->transnoentities('Sms123AdminNumberHint').'</span></td></tr>';
	print '</table><br>';
	print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123Save')).'"></div>';
	print '</form>';

	// test de connexion
	print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="test">';
	print sms123_champ_onglet('compte');
	print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->transnoentities('Sms123TestButton')).'">';
	print '<br><span class="opacitymedium">'.$langs->transnoentities('Sms123TestHint').'</span></div>';
	print '</form>';

	if ($action == 'test') {
		print '<br>';
		print load_fiche_titre($langs->transnoentities('Sms123DiagTitle'), '', 'generic');
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->transnoentities('Sms123DiagCheck').'</td>'
			.'<td width="90">'.$langs->transnoentities('Sms123DiagState').'</td><td>'.$langs->transnoentities('Sms123DiagDetail').'</td></tr>';
		foreach (Sms123Api::diagnostic() as $test) {
			list($libelle, $etat, $detail) = $test;
			if ($etat == 'ok') {
				$couleur = '#268614';
				$mot = $langs->transnoentities('Sms123DiagOk');
			} elseif ($etat == 'ko') {
				$couleur = '#c83232';
				$mot = $langs->transnoentities('Sms123DiagKo');
			} else {
				$couleur = '#666666';
				$mot = $langs->transnoentities('Sms123DiagInfo');
			}
			print '<tr class="oddeven"><td>'.$libelle.'</td>'
				.'<td><b style="color:'.$couleur.'">'.$mot.'</b></td>'
				.'<td>'.$detail.'</td></tr>';
		}
		print '</table>';
	}
}

// =================================================== onglet : declencheurs
if ($onglet == 'declencheurs') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savetrig">';
	print sms123_champ_onglet('declencheurs');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="4">'.$langs->transnoentities('Sms123Section2').'</td></tr>';
	print '<tr class="liste_titre"><td width="60">'.$langs->transnoentities('Sms123TrigActive').'</td>'
		.'<td class="titlefield">'.$langs->transnoentities('Sms123TrigEvent').'</td>'
		.'<td width="150">'.$langs->transnoentities('Sms123TrigDest').'</td><td>'.$langs->transnoentities('Sms123Message').'</td></tr>';
	foreach ($evenements as $ev => $cle) {
		$actif = getDolGlobalString('SMS123_TRIG_'.$ev);
		$modele = getDolGlobalString('SMS123_TPL_'.$ev);
		if ($modele === '') {
			$modele = isset($modeles_defaut[$ev]) ? $modeles_defaut[$ev] : '';
		}
		$dest = getDolGlobalString('SMS123_DEST_'.$ev);
		print '<tr class="oddeven">';
		print '<td class="center"><input type="checkbox" name="trig_'.$ev.'" value="1"'.($actif ? ' checked' : '').'></td>';
		print '<td><b>'.$langs->transnoentities($cle).'</b><br><span class="opacitymedium">'.$ev.'</span></td>';
		print '<td><select name="dest_'.$ev.'">';
		print '<option value="client"'.($dest != 'admin' ? ' selected' : '').'>'.$langs->transnoentities('Sms123TrigDestClient').'</option>';
		print '<option value="admin"'.($dest == 'admin' ? ' selected' : '').'>'.$langs->transnoentities('Sms123TrigDestAdmin').'</option>';
		print '</select></td>';
		print '<td><input type="text" name="tpl_'.$ev.'" class="quatrevingtpercent" value="'.dol_escape_htmltag($modele).'"></td>';
		print '</tr>';
	}
	print '</table>';
	print '<div class="opacitymedium" style="margin:8px 0;">'.$langs->transnoentities('Sms123Variables').'<br>'
		.$langs->transnoentities('Sms123VariablesDest').'</div>';
	print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123TrigSaveButton')).'"></div>';
	print '</form>';
}

// =================================================== onglet : rappels et relances
if ($onglet == 'rappels') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savecron">';
	print sms123_champ_onglet('rappels');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->transnoentities('Sms123Section3').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->transnoentities('Sms123RdvActive').'</td><td>'
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
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RdvTypes').'</td><td>';
	if (count($types)) {
		print '<select name="rdv_types[]" multiple size="6" style="min-width:280px;">';
		foreach ($types as $id => $libelle) {
			print '<option value="'.((int) $id).'"'.(in_array((string) $id, $choisis, true) ? ' selected' : '').'>'
				.dol_escape_htmltag($libelle).'</option>';
		}
		print '</select><br><span class="opacitymedium">'.$langs->transnoentities('Sms123RdvTypesHint').'</span>';
	} else {
		print '<span class="opacitymedium">'.$langs->transnoentities('Sms123RdvNoType').'</span>';
	}
	print '</td></tr>';

	$heures = (int) getDolGlobalString('SMS123_RDV_HEURES');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RdvDelay').'</td><td>'
		.'<input type="number" name="rdv_heures" min="1" max="168" value="'.($heures > 0 ? $heures : 24).'" style="width:80px;"> '
		.$langs->transnoentities('Sms123Hours').' <span class="opacitymedium">'.$langs->transnoentities('Sms123RdvDelayHint').'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RdvSource').'</td><td class="opacitymedium">'
		.$langs->transnoentities('Sms123RdvFields').'</td></tr>';
	$tplrdv = getDolGlobalString('SMS123_RDV_TPL');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123Message').'</td><td>'
		.'<input type="text" name="rdv_tpl" class="quatrevingtpercent" value="'
		.dol_escape_htmltag($tplrdv !== '' ? $tplrdv : 'Rappel : rendez-vous le {date} a {heure}. {masociete}').'">'
		.'<br><span class="opacitymedium">'.$langs->transnoentities('Sms123RdvVariables').'</span></td></tr>';

	print '<tr class="liste_titre"><td colspan="2">'.$langs->transnoentities('Sms123Section4').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RelanceActive').'</td><td>'
		.'<input type="checkbox" name="relance_actif" value="1"'.(getDolGlobalString('SMS123_RELANCE_ACTIF') ? ' checked' : '').'></td></tr>';
	$jours = (int) getDolGlobalString('SMS123_RELANCE_JOURS');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RelanceFrom').'</td><td>'
		.'<input type="number" name="relance_jours" min="0" max="365" value="'.$jours.'" style="width:80px;"> '
		.$langs->transnoentities('Sms123RelanceFromHint').'</td></tr>';
	$repeter = (int) getDolGlobalString('SMS123_RELANCE_REPETER');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123RelanceRepeat').'</td><td>'
		.'<input type="number" name="relance_repeter" min="1" max="365" value="'.($repeter > 0 ? $repeter : 15).'" style="width:80px;"> '
		.$langs->transnoentities('Sms123Days').'</td></tr>';
	$tplrel = getDolGlobalString('SMS123_RELANCE_TPL');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123Message').'</td><td>'
		.'<input type="text" name="relance_tpl" class="quatrevingtpercent" value="'
		.dol_escape_htmltag($tplrel !== '' ? $tplrel : '{masociete} : votre facture {ref} de {total} EUR est arrivee a echeance. Merci de regulariser.').'">'
		.'<br><span class="opacitymedium">'.$langs->transnoentities('Sms123RelanceVariables').'</span></td></tr>';
	print '</table>';
	print '<div class="opacitymedium" style="margin:8px 0;">'.$langs->transnoentities('Sms123CronWarning').'</div>';
	print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123CronSaveButton')).'"></div>';
	print '</form>';

	// --------------------------------------------- test de la selection
	print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="testrdv">';
	print sms123_champ_onglet('rappels');
	print '<div class="center"><input type="submit" class="button button-save" value="'
		.dol_escape_htmltag($langs->transnoentities('Sms123RdvTestButton')).'">';
	print '<br><span class="opacitymedium">'.$langs->transnoentities('Sms123RdvTestHint').'</span></div>';
	print '</form>';

	if ($action == 'testrdv') {
		print '<br>';
		print load_fiche_titre($langs->transnoentities('Sms123RdvTestTitle'), '', 'generic');

		if (!getDolGlobalString('SMS123_RDV_ACTIF')) {
			print '<div class="warning">'.$langs->transnoentities('Sms123RdvTestOff').'</div>';
		}

		dol_include_once('/sms123/class/sms123cron.class.php');
		$selection = Sms123Cron::candidatsRappels($db);

		if ($selection['erreur'] !== '') {
			print '<div class="error">'.dol_escape_htmltag($selection['erreur']).'</div>';
		} elseif (!count($selection['types'])) {
			print '<div class="warning">'.$langs->transnoentities('Sms123CronRdvNoType').'</div>';
		} elseif (!count($selection['lignes'])) {
			print '<div class="opacitymedium">'
				.$langs->transnoentities('Sms123RdvTestNone', $selection['heures']).'</div>';
		} else {
			$etats = array(
				'a-envoyer' => array('Sms123RdvStateToSend', '#268614'),
				'deja' => array('Sms123RdvStateDone', '#666666'),
				'sans-numero' => array('Sms123RdvStateNoNumber', '#c83232'),
			);
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>'.$langs->transnoentities('Sms123Date').'</td>'
				.'<td>'.$langs->transnoentities('Sms123RdvEvent').'</td>'
				.'<td>'.$langs->transnoentities('Sms123RdvThirdParty').'</td>'
				.'<td>'.$langs->transnoentities('Sms123MassNumber').'</td>'
				.'<td>'.$langs->transnoentities('Sms123RdvSource').'</td>'
				.'<td>'.$langs->transnoentities('Sms123RdvState').'</td></tr>';
			foreach ($selection['lignes'] as $ligne) {
				list($cleetat, $couleur) = $etats[$ligne['etat']];
				print '<tr class="oddeven">';
				print '<td class="nowraponall">'.dol_print_date($ligne['datep'], 'dayhour').'</td>';
				print '<td>'.dol_escape_htmltag($ligne['label']).'</td>';
				print '<td>'.dol_escape_htmltag($ligne['societe']).'</td>';
				print '<td>'.dol_escape_htmltag($ligne['numero']).'</td>';
				print '<td class="opacitymedium">'.dol_escape_htmltag($ligne['source']).'</td>';
				print '<td style="color:'.$couleur.'">'.$langs->transnoentities($cleetat).'</td>';
				print '</tr>';
			}
			print '</table>';
		}
	}
}

// =================================================== onglet : options avancees
if ($onglet == 'avance') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saveavance">';
	print sms123_champ_onglet('avance');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->transnoentities('Sms123Section5').'</td></tr>';

	// accuses de reception
	print '<tr class="oddeven"><td class="titlefield">'.$langs->transnoentities('Sms123ArActive').'</td><td>'
		.'<input type="checkbox" name="ar_actif" value="1"'.(getDolGlobalString('SMS123_AR_ACTIF') ? ' checked' : '').'>'
		.' <span class="opacitymedium">'.$langs->transnoentities('Sms123ArHint').'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123ArUrl').'</td><td>'
		.'<input type="text" class="quatrevingtpercent" readonly value="'.dol_escape_htmltag(Sms123Api::urlAccuse()).'"></td></tr>';
	print '<tr class="oddeven"><td><label for="sms123_ar_cle">'.$langs->transnoentities('Sms123ArKey').'</label></td><td>'
		.'<input type="text" id="sms123_ar_cle" name="ar_cle" size="30" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_AR_CLE')).'">'
		.' <span class="opacitymedium">'.$langs->transnoentities('Sms123ArKeyHint').'</span></td></tr>';

	// trace agenda
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123AgendaActive').'</td><td>'
		.'<input type="checkbox" name="agenda" value="1"'.(getDolGlobalString('SMS123_AGENDA') ? ' checked' : '').'>'
		.' <span class="opacitymedium">'.$langs->transnoentities('Sms123AgendaHint').'</span></td></tr>';

	// alerte de solde bas
	$seuil = (int) getDolGlobalString('SMS123_ALERTE_SEUIL');
	$repalerte = (int) getDolGlobalString('SMS123_ALERTE_REPETER');
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123AlerteActive').'</td><td>'
		.'<input type="checkbox" name="alerte_actif" value="1"'.(getDolGlobalString('SMS123_ALERTE_ACTIF') ? ' checked' : '').'></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123AlerteSeuil').'</td><td>'
		.'<input type="number" name="alerte_seuil" min="1" max="100000" value="'.($seuil > 0 ? $seuil : 50).'" style="width:90px;"> '
		.$langs->transnoentities('Sms123SmsUnit').'</td></tr>';
	$mailalerte = getDolGlobalString('SMS123_ALERTE_MAIL');
	print '<tr class="oddeven"><td><label for="sms123_alerte_mail">'.$langs->transnoentities('Sms123AlerteMail').'</label></td><td>'
		.'<input type="email" id="sms123_alerte_mail" name="alerte_mail" size="40" value="'.dol_escape_htmltag($mailalerte).'" placeholder="'
		.dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_MAIL')).'"></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123AlerteSms').'</td><td>'
		.'<input type="checkbox" name="alerte_sms" value="1"'.(getDolGlobalString('SMS123_ALERTE_SMS') ? ' checked' : '').'></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123AlerteRepeat').'</td><td>'
		.'<input type="number" name="alerte_repeter" min="1" max="365" value="'.($repalerte > 0 ? $repalerte : 3).'" style="width:80px;"> '
		.$langs->transnoentities('Sms123Days').'</td></tr>';
	print '</table><br>';
	print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123AdvancedSaveButton')).'"></div>';
	print '</form>';

	// --------------------------------------------- verification de l'URL de retour
	print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="testar">';
	print sms123_champ_onglet('avance');
	print '<div class="center"><input type="submit" class="button button-save" value="'
		.dol_escape_htmltag($langs->transnoentities('Sms123ArTestButton')).'">';
	print '<br><span class="opacitymedium">'.$langs->transnoentities('Sms123ArTestHint').'</span></div>';
	print '</form>';

	if ($action == 'testar') {
		$test = Sms123Api::testerUrlAccuse();
		print '<br>';
		if ($test['ok']) {
			print '<div class="ok" style="color:#268614;"><b>'.$langs->transnoentities('Sms123ArTestOk').'</b></div>';
		} elseif (in_array($test['http_code'], array(401, 403), true)) {
			print '<div class="error">'.$langs->transnoentities('Sms123ArTestAuth', $test['http_code']).'</div>';
		} elseif ($test['http_code'] == 0) {
			print '<div class="error">'.$langs->transnoentities('Sms123ArTestUnreachable',
				dol_escape_htmltag($test['erreur'] ? $test['erreur'] : 'HTTP 0')).'</div>';
		} else {
			print '<div class="error">'.$langs->transnoentities('Sms123ArTestKo',
				$test['http_code'], dol_escape_htmltag($test['corps'])).'</div>';
		}
		print '<div class="opacitymedium">'.dol_escape_htmltag($test['url']).'</div>';
	}
}

// =================================================== onglet : aide
if ($onglet == 'aide') {
	print '<div style="margin:6px 0 16px;">'.$langs->transnoentities('Sms123FiveWays').'</div>';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->transnoentities('Sms123HelpDoc').'</td><td>'
		.'<a href="https://www.123-sms.net/developpeurs-api-123-sms-pro-dolibarr.php" target="_blank" rel="noopener">'
		.'123-sms.net &rsaquo; Dolibarr</a></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123HelpSource').'</td><td>'
		.'<a href="https://github.com/123-smsnet/api-sms-exemples" target="_blank" rel="noopener">'
		.'github.com/123-smsnet/api-sms-exemples</a></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->transnoentities('Sms123HelpSupport').'</td><td>'
		.'<a href="mailto:contact@123-sms.net">contact@123-sms.net</a> &nbsp;&mdash;&nbsp; 02 51 76 07 34</td></tr>';
	print '</table>';
}

print sms123_fin_onglets();

llxFooter();
$db->close();
