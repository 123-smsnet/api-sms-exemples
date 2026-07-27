<?php
/* Classe d'appel de l'API 123-SMS.net - licence MIT
 * Reutilisable dans vos triggers, crons et scripts Dolibarr :
 *   dol_include_once('/sms123/class/sms123api.class.php');
 *   $code = Sms123Api::envoyer('0601020304', 'Votre commande est prete.');
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

class Sms123Api
{
	const URL = 'https://www.123-sms.net/http.php';
	const URL_SOLDE = 'https://www.123-sms.net/solde_comptes.php';

	/** Charge les traductions du module (appelable plusieurs fois sans surcout). */
	public static function langs()
	{
		global $langs;

		if (is_object($langs)) {
			$langs->load('sms123@sms123');
		}

		return $langs;
	}

	/** Traduction courte, tolerante a un contexte sans $langs (scripts CLI). */
	public static function t($cle, $a = '', $b = '')
	{
		$langs = self::langs();
		if (is_object($langs)) {
			return $langs->transnoentities($cle, $a, $b);
		}

		return $cle;
	}

	/**
	 * Envoie un SMS. Renvoie le code retour API (80 = envoye) ou 'ERR:...'
	 *
	 * @param string $numero  destinataire (0601020304 ou 33601020304,
	 *                        plusieurs numeros separes par des tirets)
	 * @param string $message texte du SMS (160 caracteres GSM par SMS)
	 * @param int    $test    1 = envoi a blanc (rien n'est envoye ni debite)
	 * @param string $origine trace dans l'historique (manuel, trigger, api...)
	 * @param int    $socid   tiers concerne, pour la trace dans l'agenda
	 * @return string
	 */
	public static function envoyer($numero, $message, $test = 0, $origine = 'api', $socid = 0)
	{
		global $db, $user;

		$identifiant = getDolGlobalString('SMS123_IDENTIFIANT');
		$cleapi = getDolGlobalString('SMS123_CLEAPI');
		if (empty($identifiant) || empty($cleapi)) {
			return 'ERR: '.self::t('Sms123NotConfigured');
		}

		$champs = array(
			'email' => $identifiant, // nom du parametre historique de l'API
			'pass' => $cleapi,
			'numero' => self::normaliser($numero),
			'message' => $message,
		);
		$sender = getDolGlobalString('SMS123_SENDER');
		if (!empty($sender)) {
			$champs['sender'] = $sender;
		}
		// Accuses de reception : l'API rappelle ensuite sms123ar.php avec le statut
		if (getDolGlobalString('SMS123_AR_ACTIF') && empty($test)) {
			$champs['refaccuse'] = 'o';
		}
		if (!empty($test)) {
			$champs['test'] = 'o';
		}

		$reponse = self::appel($champs);
		if ($reponse['http_code'] != 200) {
			dol_syslog('Sms123Api::envoyer echec HTTP '.$reponse['http_code'].' '.$reponse['erreur'], LOG_WARNING);
			return 'ERR: appel API impossible (HTTP '.$reponse['http_code'].($reponse['erreur'] ? ' - '.$reponse['erreur'] : '').')';
		}

		// Avec refaccuse, l'API peut renvoyer le code suivi d'une reference d'envoi
		list($code, $reference) = self::separerCodeReference($reponse['contenu']);
		dol_syslog('Sms123Api::envoyer -> code '.$code.' ('.$reponse['methode'].')', LOG_INFO);

		if (empty($test) && is_object($db)) {
			self::historiser($db, $champs['numero'], $message, $code,
				$reponse['methode'], $origine, is_object($user) ? $user->id : 0,
				$reference, $socid);

			if ($socid > 0 && in_array($code, array('80', '81'), true)) {
				self::tracerAgenda($db, $socid, $champs['numero'], $message);
			}
		}

		return $code;
	}

	/**
	 * Separe le code retour (2 chiffres) de la reference d'envoi eventuelle.
	 *
	 * @param string $contenu corps de la reponse HTTP
	 * @return array          array(code, reference)
	 */
	public static function separerCodeReference($contenu)
	{
		$brut = trim((string) $contenu);
		if (preg_match('/^([0-9]{2})[^0-9A-Za-z]*(.*)$/s', $brut, $m)) {
			return array($m[1], trim($m[2]));
		}

		return array($brut, '');
	}

	/**
	 * Appel HTTPS de l'API : POST, avec replis automatiques (GET puis cURL).
	 *
	 * @param array $champs parametres de l'API
	 * @return array        http_code, contenu, methode, erreur, duree (ms)
	 */
	public static function appel($champs)
	{
		$corps = http_build_query($champs, '', '&');
		$debut = microtime(true);

		// $localurl = 2 : autorise les URL externes ET locales. Necessaire
		// lorsque le serveur heberge aussi 123-sms.net (le domaine se resout
		// alors en IP locale et le garde-fou anti-SSRF refuserait l'appel).
		// 'POSTALREADYFORMATED' : la chaine est deja encodee, Dolibarr ne doit
		// pas la re-encoder (sinon requete invalide -> HTTP 400).
		$res = getURLContent(self::URL, 'POSTALREADYFORMATED', $corps, 1,
			array('Content-Type: application/x-www-form-urlencoded'),
			array('https'), 2);
		$code = empty($res['http_code']) ? 0 : (int) $res['http_code'];
		$methode = 'POST';
		$erreur = empty($res['curl_error_msg']) ? '' : $res['curl_error_msg'];

		// Repli 1 : GET (certains pare-feux refusent le POST)
		if ($code != 200) {
			$res2 = getURLContent(self::URL.'?'.$corps, 'GET', '', 1, array(),
				array('https'), 2);
			$code2 = empty($res2['http_code']) ? 0 : (int) $res2['http_code'];
			if ($code2 == 200) {
				$res = $res2;
				$code = $code2;
				$methode = 'GET (repli)';
				$erreur = '';
			}
		}

		// Repli 2 : cURL direct (contourne les restrictions du client Dolibarr)
		if ($code != 200 && function_exists('curl_init')) {
			$direct = self::appelCurl($corps);
			if ($direct['http_code'] == 200) {
				return array(
					'http_code' => 200,
					'contenu' => $direct['contenu'],
					'methode' => 'POST (cURL direct)',
					'erreur' => '',
					'duree' => (int) round((microtime(true) - $debut) * 1000),
				);
			}
			if ($erreur === '' && $direct['erreur'] !== '') {
				$erreur = $direct['erreur'];
			}
		}

		return array(
			'http_code' => $code,
			'contenu' => isset($res['content']) ? trim($res['content']) : '',
			'methode' => $methode,
			'erreur' => $erreur,
			'duree' => (int) round((microtime(true) - $debut) * 1000),
		);
	}

	/** Appel cURL direct, en dernier recours. Le proxy Dolibarr est applique. */
	protected static function appelCurl($corps)
	{
		$ch = curl_init(self::URL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		if (getDolGlobalString('MAIN_PROXY_USE')) {
			curl_setopt($ch, CURLOPT_PROXY, getDolGlobalString('MAIN_PROXY_HOST'));
			if (getDolGlobalString('MAIN_PROXY_PORT')) {
				curl_setopt($ch, CURLOPT_PROXYPORT, getDolGlobalString('MAIN_PROXY_PORT'));
			}
			if (getDolGlobalString('MAIN_PROXY_USER')) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, getDolGlobalString('MAIN_PROXY_USER').':'.getDolGlobalString('MAIN_PROXY_PASS'));
			}
		}
		$contenu = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$erreur = curl_error($ch);
		curl_close($ch);

		return array('http_code' => $code, 'contenu' => trim((string) $contenu), 'erreur' => $erreur);
	}

	/**
	 * Solde de credits SMS du compte.
	 *
	 * @return float|null nombre de SMS restants, null si indisponible
	 */
	public static function solde()
	{
		$identifiant = getDolGlobalString('SMS123_IDENTIFIANT');
		$cleapi = getDolGlobalString('SMS123_CLEAPI');
		if (empty($identifiant) || empty($cleapi)) {
			return null;
		}
		$corps = http_build_query(array('email' => $identifiant, 'pass' => $cleapi), '', '&');
		$res = getURLContent(self::URL_SOLDE.'?'.$corps, 'GET', '', 1, array(), array('https'), 2);
		if (empty($res['http_code']) || $res['http_code'] != 200) {
			return null;
		}
		$val = trim(strip_tags((string) $res['content']));
		if ($val === '' || !preg_match('/^-?[0-9]+([.,][0-9]+)?$/', $val)) {
			return null;
		}

		return (float) str_replace(',', '.', $val);
	}

	/**
	 * Solde mis en cache : evite un appel HTTP a chaque affichage du widget
	 * d'accueil. Le cache est stocke dans les constantes du module.
	 *
	 * @param int $age duree de validite du cache, en secondes
	 * @return float|null
	 */
	public static function soldeCache($age = 900)
	{
		global $db, $conf;

		$valeur = getDolGlobalString('SMS123_SOLDE_CACHE');
		$date = (int) getDolGlobalString('SMS123_SOLDE_CACHE_TS');
		if ($valeur !== '' && $date > 0 && (dol_now() - $date) < $age) {
			return (float) $valeur;
		}

		$solde = self::solde();
		if ($solde !== null && is_object($db)) {
			$entity = is_object($conf) ? $conf->entity : 1;
			dolibarr_set_const($db, 'SMS123_SOLDE_CACHE', $solde, 'chaine', 0, '', $entity);
			dolibarr_set_const($db, 'SMS123_SOLDE_CACHE_TS', dol_now(), 'chaine', 0, '', $entity);
		}

		return $solde;
	}

	/** Enregistre un envoi dans l'historique (table llx_sms123_envoi). */
	public static function historiser($db, $numero, $message, $code, $methode, $origine = 'manuel', $fk_user = 0, $reference = '', $socid = 0)
	{
		global $conf;

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'sms123_envoi(entity, datec, fk_user, fk_soc, numero, message, code, methode, origine, reference)'
			.' VALUES ('.((int) $conf->entity).", '".$db->idate(dol_now())."', "
			.($fk_user > 0 ? (int) $fk_user : 'NULL').', '
			.($socid > 0 ? (int) $socid : 'NULL').", '".$db->escape($numero)."', '"
			.$db->escape(dol_trunc($message, 500))."', '".$db->escape($code)."', '"
			.$db->escape($methode)."', '".$db->escape($origine)."', '"
			.$db->escape($reference)."')";

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog('Sms123Api::historiser '.$db->lasterror(), LOG_WARNING);
			return -1;
		}

		return 1;
	}

	/**
	 * Trace le SMS dans l'agenda du tiers (option SMS123_AGENDA).
	 * Echoue en silence : un souci d'agenda ne doit jamais bloquer un envoi.
	 *
	 * @param DoliDB $db      base
	 * @param int    $socid   tiers concerne
	 * @param string $numero  destinataire
	 * @param string $message texte envoye
	 * @return int 1 si l'evenement a ete cree, 0 sinon
	 */
	public static function tracerAgenda($db, $socid, $numero, $message)
	{
		global $user;

		if (!getDolGlobalString('SMS123_AGENDA') || $socid <= 0) {
			return 0;
		}
		// ActionComm::create() attend un objet User : sans utilisateur courant
		// (script CLI par exemple), on n'ecrit simplement pas dans l'agenda.
		if (!is_object($user)) {
			return 0;
		}
		if (!file_exists(DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$evenement = new ActionComm($db);
		$evenement->type_code = 'AC_OTH_AUTO';
		$evenement->code = 'AC_OTH_AUTO';
		$evenement->label = dol_trunc(self::t('Sms123AgendaLabel', $numero), 128);
		$evenement->note_private = $message;
		$evenement->datep = dol_now();
		$evenement->datef = dol_now();
		$evenement->percentage = -1;
		$evenement->socid = (int) $socid;
		$evenement->userownerid = is_object($user) ? $user->id : 0;
		$evenement->userdoneid = is_object($user) ? $user->id : 0;

		$res = $evenement->create(is_object($user) ? $user : null);
		if ($res <= 0) {
			dol_syslog('Sms123Api::tracerAgenda '.$evenement->error, LOG_WARNING);
			return 0;
		}

		return 1;
	}

	/** Normalise un numero francais : 06/07 -> 336/337, sans espaces. */
	public static function normaliser($numero)
	{
		$parts = array();
		foreach (explode('-', (string) $numero) as $n) {
			$n = preg_replace('/[^0-9+]/', '', $n);
			if (strpos($n, '+') === 0) {
				$n = substr($n, 1);
			}
			if (strpos($n, '00') === 0) {
				$n = substr($n, 2);
			}
			if (strlen($n) == 10 && $n[0] == '0') {
				$n = '33'.substr($n, 1);
			}
			if ($n !== '') {
				$parts[] = $n;
			}
		}
		return implode('-', $parts);
	}

	/** Libelle d'un code retour API. */
	public static function libelle($code)
	{
		$connus = array('80', '81', '82', '83', '84', '91', '97');
		if (in_array((string) $code, $connus, true)) {
			return self::t('Sms123Code'.$code);
		}

		return self::t('Sms123CodeOther', $code);
	}

	/** Libelle d'un statut de remise (accuse de reception). */
	public static function libelleStatut($statut)
	{
		if ($statut === 'remis') {
			return self::t('Sms123Delivered');
		}
		if ($statut === 'non-remis') {
			return self::t('Sms123NotDelivered');
		}
		if ($statut === 'attente') {
			return self::t('Sms123Pending');
		}

		return self::t('Sms123NoReceipt');
	}

	/**
	 * URL publique de retour des accuses de reception, a declarer aupres
	 * de 123-SMS (documentation « Retour des AR par http »).
	 *
	 * @return string
	 */
	public static function urlAccuse()
	{
		global $dolibarr_main_url_root;

		$racine = empty($dolibarr_main_url_root) ? DOL_MAIN_URL_ROOT : $dolibarr_main_url_root;
		$url = rtrim($racine, '/').'/custom/sms123/sms123ar.php';
		$cle = getDolGlobalString('SMS123_AR_CLE');
		if (!empty($cle)) {
			$url .= '?cle='.urlencode($cle);
		}

		return $url;
	}

	/**
	 * Diagnostic complet de la connexion (bouton « Tester la connexion »).
	 * Effectue un envoi A BLANC : rien n'est envoye, rien n'est debite.
	 *
	 * @return array liste de tests : libelle, etat (ok|ko|info), detail
	 */
	public static function diagnostic()
	{
		$tests = array();

		$identifiant = getDolGlobalString('SMS123_IDENTIFIANT');
		$cleapi = getDolGlobalString('SMS123_CLEAPI');
		$tests[] = array(self::t('Sms123DiagLogin'), $identifiant ? 'ok' : 'ko',
			$identifiant ? $identifiant : self::t('Sms123DiagToFill'));
		$tests[] = array(self::t('Sms123DiagKey'), $cleapi ? 'ok' : 'ko',
			$cleapi ? str_pad('', dol_strlen($cleapi), '*') : self::t('Sms123DiagToFill'));

		$sender = getDolGlobalString('SMS123_SENDER');
		$tests[] = array(self::t('Sms123DiagSender'), 'info',
			$sender ? $sender : self::t('Sms123DiagSenderNone'));

		$tests[] = array(self::t('Sms123DiagCurl'), function_exists('curl_init') ? 'ok' : 'ko',
			function_exists('curl_init') ? self::t('Sms123DiagCurlOk') : self::t('Sms123DiagCurlKo'));

		$proxy = getDolGlobalString('MAIN_PROXY_USE');
		$tests[] = array(self::t('Sms123DiagProxy'), 'info',
			$proxy ? self::t('Sms123DiagProxyOn', getDolGlobalString('MAIN_PROXY_HOST')) : self::t('Sms123DiagProxyOff'));

		if (empty($identifiant) || empty($cleapi)) {
			$tests[] = array(self::t('Sms123DiagApiCall'), 'ko', self::t('Sms123DiagNoCred'));
			return $tests;
		}

		// Envoi a blanc vers un numero de test : &test=o -> rien n'est debite
		$reponse = self::appel(array(
			'email' => $identifiant,
			'pass' => $cleapi,
			'numero' => '33600000000',
			'message' => 'Test de connexion Dolibarr',
			'test' => 'o',
		));

		$tests[] = array(self::t('Sms123DiagReach'),
			$reponse['http_code'] == 200 ? 'ok' : 'ko',
			$reponse['http_code'] == 200
				? self::t('Sms123DiagReachOk', $reponse['duree'], $reponse['methode'])
				: 'HTTP '.$reponse['http_code'].($reponse['erreur'] ? ' - '.$reponse['erreur'] : '')
					.' | '.(strpos($reponse['erreur'], 'local IP') !== false
						? self::t('Sms123DiagLocalIp')
						: self::t('Sms123DiagReachKo')));

		if ($reponse['http_code'] == 200) {
			list($code, ) = self::separerCodeReference($reponse['contenu']);
			$identifiants_ok = !in_array($code, array('82', '88', '87'), true);
			$tests[] = array(self::t('Sms123DiagCredOk'), $identifiants_ok ? 'ok' : 'ko',
				self::t('Sms123DiagApiAnswer', $code, self::libelle($code)));

			$solde = self::solde();
			$tests[] = array(self::t('Sms123DiagBalance'), $solde === null ? 'info' : ($solde > 0 ? 'ok' : 'ko'),
				$solde === null ? self::t('Sms123BalanceUnavailable') : self::t('Sms123DiagBalanceLeft', $solde));

			if ($identifiants_ok) {
				$tests[] = array(self::t('Sms123DiagVerdict'), 'ok', self::t('Sms123DiagVerdictOk'));
			} else {
				$tests[] = array(self::t('Sms123DiagVerdict'), 'ko', self::t('Sms123DiagVerdictKo'));
			}
		}

		return $tests;
	}

	/**
	 * Remplace les variables {ref} {societe} {total} {date} d'un modele.
	 *
	 * @param string $modele modele de message
	 * @param object $objet  objet Dolibarr (commande, facture, expedition...)
	 * @return string
	 */
	public static function appliquerModele($modele, $objet)
	{
		global $mysoc;

		$soc = null;
		if (method_exists($objet, 'fetch_thirdparty')) {
			$objet->fetch_thirdparty();
			$soc = empty($objet->thirdparty) ? null : $objet->thirdparty;
		}

		$total = '';
		if (isset($objet->total_ttc)) {
			$total = price2num($objet->total_ttc, 'MT');
		}

		return strtr($modele, array(
			'{ref}' => isset($objet->ref) ? $objet->ref : '',
			'{societe}' => is_object($soc) ? $soc->name : '',
			'{total}' => $total,
			'{date}' => dol_print_date(dol_now(), 'day'),
			'{masociete}' => is_object($mysoc) ? $mysoc->name : '',
		));
	}

	/** Numero de mobile d'un tiers (mobile en priorite, sinon telephone). */
	public static function numeroTiers($soc)
	{
		if (!is_object($soc)) {
			return '';
		}
		if (!empty($soc->phone_mobile)) {
			return $soc->phone_mobile;
		}
		if (!empty($soc->phone)) {
			return $soc->phone;
		}

		return '';
	}
}
