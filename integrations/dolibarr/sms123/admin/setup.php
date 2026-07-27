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
	setEventMessages('Reglages enregistres.', null, 'mesgs');
}

if ($action == 'savetrig') {
	foreach (array_keys($evenements) as $ev) {
		dolibarr_set_const($db, 'SMS123_TRIG_'.$ev, GETPOST('trig_'.$ev, 'int') ? 1 : 0, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_TPL_'.$ev, GETPOST('tpl_'.$ev, 'restricthtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SMS123_DEST_'.$ev, GETPOST('dest_'.$ev, 'aZ09') == 'admin' ? 'admin' : 'client', 'chaine', 0, '', $conf->entity);
	}
	setEventMessages('Declencheurs enregistres.', null, 'mesgs');
}

llxHeader('', 'Configuration 123-SMS');
print load_fiche_titre('Module SMS 123-SMS.net', '', 'setup');

// --------------------------------------------------- solde
$solde = Sms123Api::solde();
if ($solde !== null) {
	print '<div class="center" style="margin-bottom:12px;"><span style="display:inline-block; padding:8px 18px; border-radius:6px; border:1px solid #c9e6f2; background:#f2faff;">';
	print '<b>Solde du compte :</b> <b style="color:'.($solde < 20 ? '#c83232' : '#268614').'">'.price2num($solde, 'MT').' SMS</b>';
	print ' &nbsp;&mdash;&nbsp; <a href="https://www.123-sms.net/" target="_blank" rel="noopener">recharger</a>';
	print '</span></div>';
}

print '<span class="opacitymedium">Identifiant et cl&eacute; API : transmis par e-mail &agrave; l\'inscription sur '
	.'<a href="https://www.123-sms.net/" target="_blank" rel="noopener">123-sms.net</a> (espace client &rsaquo; API).</span><br><br>';

// --------------------------------------------------- compte
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">1. Compte 123-SMS.net</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Identifiant</td><td>'
	.'<input type="text" name="sms123_identifiant" size="40" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_IDENTIFIANT')).'"></td></tr>';
print '<tr class="oddeven"><td>Cl&eacute; API</td><td>'
	.'<input type="password" name="sms123_cleapi" size="40" autocomplete="new-password" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_CLEAPI')).'"></td></tr>';
print '<tr class="oddeven"><td>Sender-ID (optionnel)</td><td>'
	.'<input type="text" name="sms123_sender" size="15" maxlength="11" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_SENDER')).'">'
	.' <span class="opacitymedium">nom d\'exp&eacute;diteur personnalis&eacute;, &agrave; d&eacute;clarer aupr&egrave;s de 123-SMS</span></td></tr>';
print '<tr class="oddeven"><td>Num&eacute;ro interne (alertes)</td><td>'
	.'<input type="text" name="sms123_num_admin" size="20" value="'.dol_escape_htmltag(getDolGlobalString('SMS123_NUM_ADMIN')).'" placeholder="0601020304">'
	.' <span class="opacitymedium">votre mobile, pour les d&eacute;clencheurs adress&eacute;s &laquo;&nbsp;en interne&nbsp;&raquo;</span></td></tr>';
print '</table><br>';
print '<div class="center"><input type="submit" class="button" value="Enregistrer"></div>';
print '</form>';

// --------------------------------------------------- test connexion
print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="test">';
print '<div class="center"><input type="submit" class="button button-save" value="Tester la connexion">';
print '<br><span class="opacitymedium">Envoi &agrave; blanc : aucun SMS n\'est envoy&eacute;, aucun cr&eacute;dit n\'est d&eacute;bit&eacute;.</span></div>';
print '</form>';

if ($action == 'test') {
	print '<br>';
	print load_fiche_titre('R&eacute;sultat du diagnostic', '', 'generic');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="titlefield">V&eacute;rification</td><td width="90">&Eacute;tat</td><td>D&eacute;tail</td></tr>';
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
}

// --------------------------------------------------- declencheurs
print '<br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="savetrig">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="4">2. Envois automatiques (aucun code &agrave; &eacute;crire)</td></tr>';
print '<tr class="liste_titre"><td width="60">Actif</td><td class="titlefield">&Eacute;v&eacute;nement Dolibarr</td><td width="150">Destinataire</td><td>Message</td></tr>';
foreach ($evenements as $ev => $libelle) {
	$actif = getDolGlobalString('SMS123_TRIG_'.$ev);
	$modele = getDolGlobalString('SMS123_TPL_'.$ev);
	if ($modele === '') {
		$modele = isset($modeles_defaut[$ev]) ? $modeles_defaut[$ev] : '';
	}
	$dest = getDolGlobalString('SMS123_DEST_'.$ev);
	print '<tr class="oddeven">';
	print '<td class="center"><input type="checkbox" name="trig_'.$ev.'" value="1"'.($actif ? ' checked' : '').'></td>';
	print '<td><b>'.$libelle.'</b><br><span class="opacitymedium">'.$ev.'</span></td>';
	print '<td><select name="dest_'.$ev.'">';
	print '<option value="client"'.($dest != 'admin' ? ' selected' : '').'>Le client</option>';
	print '<option value="admin"'.($dest == 'admin' ? ' selected' : '').'>En interne</option>';
	print '</select></td>';
	print '<td><input type="text" name="tpl_'.$ev.'" class="quatrevingtpercent" value="'.dol_escape_htmltag($modele).'"></td>';
	print '</tr>';
}
print '</table>';
print '<div class="opacitymedium" style="margin:8px 0;">Variables disponibles dans les messages&nbsp;: '
	.'<code>{ref}</code> num&eacute;ro du document, <code>{societe}</code> nom du client, '
	.'<code>{total}</code> montant TTC, <code>{date}</code> date du jour, <code>{masociete}</code> votre raison sociale.<br>'
	.'Destinataire &laquo;&nbsp;Le client&nbsp;&raquo;&nbsp;: mobile du tiers (&agrave; d&eacute;faut, son t&eacute;l&eacute;phone). '
	.'&laquo;&nbsp;En interne&nbsp;&raquo;&nbsp;: le num&eacute;ro interne saisi plus haut.</div>';
print '<div class="center"><input type="submit" class="button" value="Enregistrer les declencheurs"></div>';
print '</form>';

print '<br><br><span class="opacitymedium"><b>Trois fa&ccedil;ons d\'envoyer des SMS depuis Dolibarr</b>&nbsp;: '
	.'1) &agrave; la main, menu <b>Outils &rsaquo; SMS 123-SMS</b> (avec historique et solde)&nbsp;; '
	.'2) <b>automatiquement</b> via les d&eacute;clencheurs ci-dessus&nbsp;; '
	.'3) <b>sur mesure</b> dans vos scripts&nbsp;: <code>Sms123Api::envoyer($numero, $message)</code>.</span>';

llxFooter();
$db->close();
