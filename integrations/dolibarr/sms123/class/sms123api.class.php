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

	/**
	 * Envoie un SMS. Renvoie le code retour API (80 = envoye) ou 'ERR:...'
	 *
	 * @param string $numero  destinataire (0601020304 ou 33601020304,
	 *                        plusieurs numeros separes par des tirets)
	 * @param string $message texte du SMS (160 caracteres GSM par SMS)
	 * @param int    $test    1 = envoi a blanc (rien n'est envoye ni debite)
	 * @param string $origine trace dans l'historique (manuel, trigger, api...)
	 * @return string
	 */
	public static function envoyer($numero, $message, $test = 0, $origine = 'api')
	{
		global $db, $user;

		$identifiant = getDolGlobalString('SMS123_IDENTIFIANT');
		$cleapi = getDolGlobalString('SMS123_CLEAPI');
		if (empty($identifiant) || empty($cleapi)) {
			return 'ERR: module non configure (menu Configuration > Modules > 123-SMS)';
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
		if (!empty($test)) {
			$champs['test'] = 'o';
		}

		$reponse = self::appel($champs);
		if ($reponse['http_code'] != 200) {
			dol_syslog('Sms123Api::envoyer echec HTTP '.$reponse['http_code'].' '.$reponse['erreur'], LOG_WARNING);
			return 'ERR: appel API impossible (HTTP '.$reponse['http_code'].($reponse['erreur'] ? ' - '.$reponse['erreur'] : '').')';
		}
		dol_syslog('Sms123Api::envoyer -> code '.$reponse['contenu'].' ('.$reponse['methode'].')', LOG_INFO);

		if (empty($test) && is_object($db)) {
			self::historiser($db, $champs['numero'], $message, $reponse['contenu'],
				$reponse['methode'], $origine, is_object($user) ? $user->id : 0);
		}

		return $reponse['contenu'];
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

	/** Enregistre un envoi dans l'historique (table llx_sms123_envoi). */
	public static function historiser($db, $numero, $message, $code, $methode, $origine = 'manuel', $fk_user = 0)
	{
		global $conf;

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX."sms123_envoi(entity, datec, fk_user, numero, message, code, methode, origine)"
			." VALUES (".((int) $conf->entity).", '".$db->idate(dol_now())."', "
			.($fk_user > 0 ? (int) $fk_user : 'NULL').", '".$db->escape($numero)."', '"
			.$db->escape(dol_trunc($message, 500))."', '".$db->escape($code)."', '"
			.$db->escape($methode)."', '".$db->escape($origine)."')";

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog('Sms123Api::historiser '.$db->lasterror(), LOG_WARNING);
			return -1;
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
		$libelles = array(
			'80' => 'Le message a ete envoye.',
			'81' => 'Enregistre pour un envoi en differe.',
			'82' => 'Identifiant et/ou cle API invalides.',
			'83' => 'Credit insuffisant : rechargez votre compte.',
			'84' => 'Numero de mobile invalide.',
			'91' => 'Doublon : meme message deja envoye a ce numero sous 24 h.',
			'97' => 'Sender-ID invalide ou non declare.',
		);
		return isset($libelles[$code]) ? $libelles[$code] : 'Code retour : '.$code;
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
		$tests[] = array('Identifiant renseigne', $identifiant ? 'ok' : 'ko',
			$identifiant ? $identifiant : 'a saisir ci-dessus');
		$tests[] = array('Cle API renseignee', $cleapi ? 'ok' : 'ko',
			$cleapi ? str_pad('', dol_strlen($cleapi), '*') : 'a saisir ci-dessus');

		$sender = getDolGlobalString('SMS123_SENDER');
		$tests[] = array('Sender-ID', 'info', $sender ? $sender : '(aucun : numero court par defaut)');

		$tests[] = array('Extension cURL de PHP', function_exists('curl_init') ? 'ok' : 'ko',
			function_exists('curl_init') ? 'disponible' : 'absente : installez php-curl');

		$proxy = getDolGlobalString('MAIN_PROXY_USE');
		$tests[] = array('Proxy Dolibarr', 'info',
			$proxy ? 'actif ('.getDolGlobalString('MAIN_PROXY_HOST').')' : 'aucun (connexion directe)');

		if (empty($identifiant) || empty($cleapi)) {
			$tests[] = array('Appel de l\'API', 'ko', 'test impossible : renseignez d\'abord vos identifiants');
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

		$tests[] = array('Jointure de www.123-sms.net',
			$reponse['http_code'] == 200 ? 'ok' : 'ko',
			$reponse['http_code'] == 200
				? 'HTTP 200 en '.$reponse['duree'].' ms ('.$reponse['methode'].')'
				: 'HTTP '.$reponse['http_code'].($reponse['erreur'] ? ' - '.$reponse['erreur'] : '')
					.(strpos($reponse['erreur'], 'local IP') !== false
						? ' | Votre serveur heberge aussi 123-sms.net : le domaine se resout en IP locale.'
							.' Mettez le module a jour (1.3.0+).'
						: ' : verifiez que le serveur peut sortir en HTTPS (pare-feu, proxy)'));

		if ($reponse['http_code'] == 200) {
			$code = $reponse['contenu'];
			$identifiants_ok = !in_array($code, array('82', '88', '87'), true);
			$tests[] = array('Identifiants acceptes', $identifiants_ok ? 'ok' : 'ko',
				'reponse API : '.$code.' - '.self::libelle($code));

			$solde = self::solde();
			$tests[] = array('Solde du compte', $solde === null ? 'info' : ($solde > 0 ? 'ok' : 'ko'),
				$solde === null ? 'indisponible' : $solde.' SMS restants');

			if ($identifiants_ok) {
				$tests[] = array('Verdict', 'ok', 'Connexion operationnelle : vos SMS peuvent partir.');
			} else {
				$tests[] = array('Verdict', 'ko',
					'Corrigez l\'identifiant et la cle API (espace client 123-sms.net > API).');
			}
		}

		return $tests;
	}

	/**
	 * Remplace les variables {ref} {societe} {total} {date} {contact} d'un modele.
	 *
	 * @param string $modele modele de message
	 * @param object $objet  objet Dolibarr (commande, facture, expedition...)
	 * @return string
	 */
	public static function appliquerModele($modele, $objet)
	{
		global $langs, $mysoc;

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
