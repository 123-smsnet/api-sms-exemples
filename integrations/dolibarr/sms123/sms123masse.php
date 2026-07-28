<?php
/* Envoi de SMS en masse - module 123-SMS pour Dolibarr - licence MIT
 *
 * Deux facons d'arriver ici :
 *  - depuis la liste des tiers ou des contacts, action de masse
 *    « Envoyer un SMS » (la selection est passee par la session) ;
 *  - directement par le menu Outils > SMS 123-SMS > Envoi en masse,
 *    en saisissant des numeros libres.
 */
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

/** Nombre maximum de destinataires par envoi (garde-fou). */
define('SMS123_MASSE_MAX', 500);
/** Nombre de numeros regroupes dans un meme appel API. */
define('SMS123_MASSE_PAQUET', 20);

$action = GETPOST('action', 'aZ09');

// --------------------------------------------------- selection en session
$selection = isset($_SESSION['sms123_masse']) ? $_SESSION['sms123_masse'] : array();
$type = empty($selection['type']) ? 'societe' : $selection['type'];
$ids = empty($selection['ids']) ? array() : $selection['ids'];

/**
 * Charge les destinataires selectionnes : identifiant, nom, numero, tiers.
 *
 * @param DoliDB $db   base
 * @param string $type societe ou contact
 * @param array  $ids  identifiants selectionnes
 * @return array       liste de tableaux id/nom/numero/socid
 */
function sms123_destinataires($db, $type, $ids)
{
	$liste = array();
	if (!count($ids)) {
		return $liste;
	}
	$in = implode(',', array_map('intval', $ids));

	if ($type == 'contact') {
		$sql = 'SELECT rowid, lastname, firstname, phone_mobile, phone_perso, phone_pro, fk_soc';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'socpeople WHERE rowid IN ('.$in.') ORDER BY lastname, firstname';
		$resql = $db->query($sql);
		if (!$resql) {
			return $liste;
		}
		while ($obj = $db->fetch_object($resql)) {
			$numero = '';
			foreach (array('phone_mobile', 'phone_perso', 'phone_pro') as $champ) {
				if (!empty($obj->$champ)) {
					$numero = $obj->$champ;
					break;
				}
			}
			$liste[] = array(
				'id' => (int) $obj->rowid,
				'nom' => trim($obj->firstname.' '.$obj->lastname),
				'numero' => $numero,
				'socid' => (int) $obj->fk_soc,
			);
		}
		$db->free($resql);

		return $liste;
	}

	// Tiers : phone_mobile n'existe pas sur toutes les versions de Dolibarr
	$sql = 'SELECT rowid, nom, phone, phone_mobile FROM '.MAIN_DB_PREFIX.'societe WHERE rowid IN ('.$in.') ORDER BY nom';
	$resql = $db->query($sql);
	if (!$resql) {
		$sql = 'SELECT rowid, nom, phone FROM '.MAIN_DB_PREFIX.'societe WHERE rowid IN ('.$in.') ORDER BY nom';
		$resql = $db->query($sql);
	}
	if (!$resql) {
		return $liste;
	}
	while ($obj = $db->fetch_object($resql)) {
		$numero = '';
		if (!empty($obj->phone_mobile)) {
			$numero = $obj->phone_mobile;
		} elseif (!empty($obj->phone)) {
			$numero = $obj->phone;
		}
		$liste[] = array(
			'id' => (int) $obj->rowid,
			'nom' => $obj->nom,
			'numero' => $numero,
			'socid' => (int) $obj->rowid,
		);
	}
	$db->free($resql);

	return $liste;
}

/** Decoupe une saisie libre de numeros (retours a la ligne, virgules, points-virgules). */
function sms123_numeros_libres($texte)
{
	$liste = array();
	foreach (preg_split('/[\r\n,;]+/', (string) $texte) as $n) {
		$n = trim($n);
		if ($n !== '') {
			$liste[] = array('id' => 0, 'nom' => '', 'numero' => $n, 'socid' => 0);
		}
	}

	return $liste;
}

$destinataires = sms123_destinataires($db, $type, $ids);

// --------------------------------------------------- envoi
$resultats = array();
$envoyes = 0;
$echecs = 0;

if ($action == 'sendmass') {
	$message = GETPOST('message', 'restricthtml');
	$test = GETPOST('test', 'int') ? 1 : 0;
	$coches = GETPOST('dest', 'array');
	$coches = is_array($coches) ? array_map('intval', $coches) : array();

	// On ne retient que les destinataires reellement coches et joignables
	$cibles = array();
	foreach ($destinataires as $d) {
		if (in_array($d['id'], $coches, true) && $d['numero'] !== '') {
			$cibles[] = $d;
		}
	}
	foreach (sms123_numeros_libres(GETPOST('libres', 'alphanohtml')) as $d) {
		$cibles[] = $d;
	}

	if (empty($message)) {
		setEventMessages($langs->transnoentities('Sms123MassNoMessage'), null, 'errors');
	} elseif (!count($cibles)) {
		setEventMessages($langs->transnoentities('Sms123MassNoRecipient'), null, 'errors');
	} elseif (count($cibles) > SMS123_MASSE_MAX) {
		setEventMessages($langs->transnoentities('Sms123MassLimit', SMS123_MASSE_MAX), null, 'errors');
	} else {
		@set_time_limit(0);
		$personnalise = (strpos($message, '{') !== false) || getDolGlobalString('SMS123_AGENDA');
		$masociete = is_object($mysoc) ? $mysoc->name : '';

		if ($personnalise) {
			// Un appel par destinataire : variables remplacees, trace agenda possible
			foreach ($cibles as $d) {
				$texte = strtr($message, array(
					'{societe}' => $d['nom'],
					'{contact}' => $d['nom'],
					'{masociete}' => $masociete,
				));
				$code = Sms123Api::envoyer($d['numero'], $texte, $test, 'masse', $d['socid']);
				$ok = Sms123Api::estSucces($code, $test);
				$ok ? $envoyes++ : $echecs++;
				$resultats[] = array($d['nom'], $d['numero'], $code, $ok);
			}
		} else {
			// Message identique : regroupement par paquets, un appel pour 20 numeros
			$paquets = array_chunk($cibles, SMS123_MASSE_PAQUET);
			foreach ($paquets as $paquet) {
				$numeros = array();
				foreach ($paquet as $d) {
					$numeros[] = $d['numero'];
				}
				$code = Sms123Api::envoyer(implode('-', $numeros), $message, $test, 'masse');
				$ok = Sms123Api::estSucces($code, $test);
				foreach ($paquet as $d) {
					$ok ? $envoyes++ : $echecs++;
					$resultats[] = array($d['nom'], $d['numero'], $code, $ok);
				}
			}
		}

		setEventMessages($langs->transnoentities('Sms123MassDone', $envoyes, $echecs), null,
			$echecs ? 'warnings' : 'mesgs');
	}
}

// --------------------------------------------------- affichage
llxHeader('', $langs->transnoentities('Sms123MassTitle'));

$joignables = 0;
foreach ($destinataires as $d) {
	if ($d['numero'] !== '') {
		$joignables++;
	}
}

$lienconfig = '';
if (!empty($user->admin)) {
	$lienconfig = '<a class="valignmiddle" href="'.dol_buildpath('/sms123/admin/setup.php', 1)
		.'?backtopage='.urlencode($_SERVER['PHP_SELF']).'" title="'.dol_escape_htmltag($langs->transnoentities('Sms123ConfigLink')).'">'
		.img_picto($langs->transnoentities('Sms123ConfigLink'), 'setup', 'class="pictofixedwidth"').'</a>';
}
print load_fiche_titre($langs->transnoentities('Sms123MassTitle'), $lienconfig, 'object_phoning');

print '<div class="opacitymedium" style="margin-bottom:10px;">'.$langs->transnoentities('Sms123MassIntro').'</div>';

$solde = Sms123Api::solde();
if ($solde !== null && $joignables > 0 && $solde < $joignables) {
	print '<div class="warning">'.$langs->transnoentities('Sms123MassLowBalance', price2num($solde, 'MT'), $joignables).'</div>';
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sendmass">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->transnoentities('Sms123MassSelection');
if (count($destinataires)) {
	print ' &mdash; <span class="opacitymedium">'
		.$langs->transnoentities('Sms123MassCount', $joignables, count($destinataires)).'</span>';
}
print '</td></tr>';

if (!count($destinataires)) {
	print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->transnoentities('Sms123MassNoSelection').'</td></tr>';
} else {
	print '<tr class="liste_titre"><td width="40"><input type="checkbox" id="sms123tout" checked></td>';
	print '<td>'.$langs->transnoentities('Sms123MassName').'</td><td>'.$langs->transnoentities('Sms123MassNumber').'</td></tr>';
	foreach ($destinataires as $d) {
		$sansnumero = ($d['numero'] === '');
		print '<tr class="oddeven">';
		print '<td class="center">';
		if (!$sansnumero) {
			print '<input type="checkbox" class="sms123dest" name="dest[]" value="'.((int) $d['id']).'" checked>';
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag($d['nom']).'</td>';
		print '<td'.($sansnumero ? ' class="opacitymedium"' : '').'>'
			.($sansnumero ? $langs->transnoentities('Sms123MassNoNumber') : dol_escape_htmltag($d['numero'])).'</td>';
		print '</tr>';
	}
}

print '<tr class="oddeven"><td></td><td><label for="sms123libres">'.$langs->transnoentities('Sms123MassFreeNumbers').'</label></td><td>'
	.'<textarea id="sms123libres" name="libres" rows="3" cols="40"></textarea>'
	.'<br><span class="opacitymedium">'.$langs->transnoentities('Sms123MassFreeHint').'</span></td></tr>';

print '<tr class="liste_titre"><td colspan="3">'.$langs->transnoentities('Sms123Message').'</td></tr>';
print '<tr class="oddeven"><td></td><td colspan="2">'
	.'<textarea id="sms123message" name="message" rows="4" cols="70" maxlength="480"></textarea>'
	.'<br><span id="sms123compteur" class="opacitymedium">'.$langs->transnoentities('Sms123CounterHint').'</span>'
	.'<br><span class="opacitymedium">'.$langs->transnoentities('Sms123MassVariables').'</span></td></tr>';
print '<tr class="oddeven"><td></td><td colspan="2">'
	.'<label><input type="checkbox" name="test" value="1"> '.$langs->transnoentities('Sms123MassTest').'</label></td></tr>';
print '</table><br>';

print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->transnoentities('Sms123MassSend')).'">';
print ' &nbsp; <a class="butAction" href="'.dol_buildpath('/sms123/sms123index.php', 1).'">'.$langs->transnoentities('Sms123MassBack').'</a></div>';
print '</form>';

// --------------------------------------------------- resultat
if (count($resultats)) {
	print '<br>';
	print load_fiche_titre($langs->transnoentities('Sms123MassResult'), '', 'generic');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->transnoentities('Sms123MassName').'</td>'
		.'<td>'.$langs->transnoentities('Sms123MassNumber').'</td><td>'.$langs->transnoentities('Sms123Result').'</td></tr>';
	foreach ($resultats as $r) {
		list($nom, $numero, $code, $ok) = $r;
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($nom).'</td>';
		print '<td>'.dol_escape_htmltag($numero).'</td>';
		print '<td><b style="color:'.($ok ? '#268614' : '#c83232').'">'.dol_escape_htmltag($code).'</b> '
			.'<span class="opacitymedium">'.Sms123Api::libelle($code).'</span></td></tr>';
	}
	print '</table>';
}

// Tout cocher / tout decocher + compteur de caracteres
print '<script>
(function() {
	var tout = document.getElementById("sms123tout");
	if (tout) {
		tout.addEventListener("change", function() {
			var cases = document.getElementsByClassName("sms123dest");
			for (var i = 0; i < cases.length; i++) { cases[i].checked = tout.checked; }
		});
	}
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
