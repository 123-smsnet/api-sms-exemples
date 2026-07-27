<?php
/* Retour des accuses de reception 123-SMS.net - licence MIT
 *
 * Page appelee par la passerelle 123-SMS apres remise (ou echec) d'un SMS,
 * conformement a la documentation « Retour des AR par http » :
 *     .../sms123ar.php?erreur=...&refenvoi=...&gsm=...
 *
 * Elle ne fait que mettre a jour une ligne existante de l'historique :
 * aucune donnee n'est creee, aucun envoi n'est declenche. Un appel qui ne
 * correspond a aucun envoi connu est ignore silencieusement.
 *
 * L'URL a declarer aupres de 123-SMS est affichee dans la configuration
 * du module (section « Options avancees »).
 */

// Page publique : pas de session utilisateur, pas de jeton CSRF, pas de menu
if (!defined('NOLOGIN')) { define('NOLOGIN', 1); }
if (!defined('NOCSRFCHECK')) { define('NOCSRFCHECK', 1); }
if (!defined('NOTOKENRENEWAL')) { define('NOTOKENRENEWAL', 1); }
if (!defined('NOREQUIREMENU')) { define('NOREQUIREMENU', 1); }
if (!defined('NOREQUIREHTML')) { define('NOREQUIREHTML', 1); }
if (!defined('NOREQUIREAJAX')) { define('NOREQUIREAJAX', 1); }

$res = 0;
if (!$res && file_exists('../main.inc.php')) { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

top_httphead('text/plain');

// Cle de securite optionnelle : si elle est configuree, elle est exigee
$cleattendue = getDolGlobalString('SMS123_AR_CLE');
if (!empty($cleattendue) && GETPOST('cle', 'alphanohtml') !== $cleattendue) {
	http_response_code(403);
	print 'KO';
	exit;
}

$refenvoi = GETPOST('refenvoi', 'alphanohtml');
$gsm = GETPOST('gsm', 'alphanohtml');
$erreur = GETPOST('erreur', 'alphanohtml');

if ($refenvoi === '' && $gsm === '') {
	print 'KO';
	exit;
}

// erreur vide ou nulle = message remis
$remis = ($erreur === '' || $erreur === '0' || strtolower($erreur) === 'ok');
$statut = $remis ? 'remis' : 'non-remis';

// Recherche de l'envoi correspondant : par reference, sinon le dernier SMS
// envoye a ce numero (fenetre de 7 jours, comme la duree de vie d'un SMS).
$rowid = 0;
if ($refenvoi !== '') {
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."sms123_envoi WHERE reference = '".$db->escape($refenvoi)."'";
	$sql .= ' ORDER BY rowid DESC';
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$rowid = (int) $obj->rowid;
	}
	if ($resql) {
		$db->free($resql);
	}
}
if ($rowid == 0 && $gsm !== '') {
	dol_include_once('/sms123/class/sms123api.class.php');
	$numero = Sms123Api::normaliser($gsm);
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'sms123_envoi';
	$sql .= " WHERE numero LIKE '%".$db->escape($numero)."%'";
	$sql .= " AND datec > '".$db->idate(dol_now() - (7 * 86400))."'";
	$sql .= ' ORDER BY rowid DESC';
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$rowid = (int) $obj->rowid;
	}
	if ($resql) {
		$db->free($resql);
	}
}

if ($rowid == 0) {
	dol_syslog('sms123ar : aucun envoi correspondant (refenvoi='.$refenvoi.', gsm='.$gsm.')', LOG_INFO);
	print 'OK';
	exit;
}

$sql = 'UPDATE '.MAIN_DB_PREFIX."sms123_envoi SET statut = '".$db->escape($statut)."'";
$sql .= ", date_ar = '".$db->idate(dol_now())."'";
$sql .= ", erreur_ar = '".$db->escape(dol_trunc($erreur, 60))."'";
if ($refenvoi !== '') {
	$sql .= ", reference = '".$db->escape($refenvoi)."'";
}
$sql .= ' WHERE rowid = '.((int) $rowid);

if (!$db->query($sql)) {
	dol_syslog('sms123ar : '.$db->lasterror(), LOG_ERR);
	print 'KO';
	exit;
}

dol_syslog('sms123ar : envoi '.$rowid.' -> '.$statut, LOG_INFO);
print 'OK';

$db->close();
