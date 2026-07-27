<?php
/* Retour des accuses de reception 123-SMS.net - licence MIT
 *
 * Page appelee par la passerelle 123-SMS apres remise (ou echec) d'un SMS,
 * conformement a la documentation « Retour des AR par http » :
 *     .../sms123ar.php?erreur=...&refenvoi=...&gsm=...
 *
 * CONTRAT : cette page repond TOUJOURS « OK » en HTTP 200, y compris a un
 * appel sans parametre. C'est ce que verifie 123-SMS au moment de declarer
 * l'URL de retour ; une autre reponse fait refuser l'URL.
 *
 * Elle ne fait que mettre a jour une ligne existante de l'historique :
 * aucune donnee n'est creee, aucun envoi n'est declenche. Tout appel qui ne
 * correspond a aucun envoi connu est ignore et simplement trace au syslog.
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

/**
 * Repond « OK » et s'arrete. Le corps de la reponse doit contenir ce seul
 * mot : les tampons de sortie eventuels sont donc vides au prealable.
 *
 * @param string $trace message a consigner dans le syslog (facultatif)
 * @return void
 */
function sms123_repondre_ok($trace = '')
{
	if ($trace !== '') {
		dol_syslog('sms123ar : '.$trace, LOG_INFO);
	}
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}
	if (!headers_sent()) {
		top_httphead('text/plain');
	}
	print 'OK';
	exit;
}

// Certaines passerelles construisent l'appel avec « ? » meme lorsque l'URL
// declaree contient deja des parametres : la valeur recue est alors du type
// « cle=abc?erreur=0 ». On redecoupe pour retrouver les parametres colles.
foreach ($_GET as $nom => $valeur) {
	if (is_string($valeur) && strpos($valeur, '?') !== false) {
		list($propre, $reste) = explode('?', $valeur, 2);
		$_GET[$nom] = $propre;
		$colles = array();
		parse_str($reste, $colles);
		foreach ($colles as $k => $v) {
			if (!isset($_GET[$k])) {
				$_GET[$k] = $v;
			}
		}
	}
}

$refenvoi = GETPOST('refenvoi', 'alphanohtml');
$gsm = GETPOST('gsm', 'alphanohtml');
$erreur = GETPOST('erreur', 'alphanohtml');

// Cle de securite optionnelle : un appel sans la cle attendue est ignore,
// mais recoit quand meme « OK » (l'URL doit rester declarable).
$cleattendue = getDolGlobalString('SMS123_AR_CLE');
if (!empty($cleattendue) && GETPOST('cle', 'alphanohtml') !== $cleattendue) {
	sms123_repondre_ok('appel refuse : cle de securite absente ou incorrecte');
}

// Appel de verification de l'URL (aucun parametre) : rien a faire
if ($refenvoi === '' && $gsm === '') {
	sms123_repondre_ok('appel sans parametre (verification de l URL)');
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
	sms123_repondre_ok('aucun envoi correspondant (refenvoi='.$refenvoi.', gsm='.$gsm.')');
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
	sms123_repondre_ok('mise a jour impossible pour l envoi '.$rowid);
}

$db->close();
sms123_repondre_ok('envoi '.$rowid.' -> '.$statut);
