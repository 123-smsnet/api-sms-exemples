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

	/**
	 * Envoie un SMS. Renvoie le code retour API (80 = envoye) ou 'ERR:...'
	 *
	 * @param string $numero  destinataire (0601020304 ou 33601020304,
	 *                        plusieurs numeros separes par des tirets)
	 * @param string $message texte du SMS (160 caracteres GSM par SMS)
	 * @param int    $test    1 = envoi a blanc (rien n'est envoye ni debite)
	 * @return string
	 */
	public static function envoyer($numero, $message, $test = 0)
	{
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

		return $reponse['contenu'];
	}

	/**
	 * Appel HTTPS de l'API : POST, avec repli automatique en GET si
	 * l'hebergement ou un pare-feu refuse le POST.
	 *
	 * @param array $champs parametres de l'API
	 * @return array        http_code, contenu, methode, erreur, duree (ms)
	 */
	public static function appel($champs)
	{
		$corps = http_build_query($champs, '', '&');
		$debut = microtime(true);

		// 'POSTALREADYFORMATED' : la chaine est deja encodee, Dolibarr ne doit
		// pas la re-encoder (sinon requete invalide -> HTTP 400).
		$res = getURLContent(self::URL, 'POSTALREADYFORMATED', $corps, 1,
			array('Content-Type: application/x-www-form-urlencoded'));
		$code = empty($res['http_code']) ? 0 : (int) $res['http_code'];
		$methode = 'POST';

		if ($code != 200) {
			$res2 = getURLContent(self::URL.'?'.$corps, 'GET');
			$code2 = empty($res2['http_code']) ? 0 : (int) $res2['http_code'];
			if ($code2 == 200) {
				$res = $res2;
				$code = $code2;
				$methode = 'GET (repli)';
			}
		}

		return array(
			'http_code' => $code,
			'contenu' => isset($res['content']) ? trim($res['content']) : '',
			'methode' => $methode,
			'erreur' => empty($res['curl_error_msg']) ? '' : $res['curl_error_msg'],
			'duree' => (int) round((microtime(true) - $debut) * 1000),
		);
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
					.' : verifiez que le serveur peut sortir en HTTPS');

		if ($reponse['http_code'] == 200) {
			$code = $reponse['contenu'];
			$identifiants_ok = !in_array($code, array('82', '88', '87'), true);
			$tests[] = array('Identifiants accept&eacute;s', $identifiants_ok ? 'ok' : 'ko',
				'reponse API : '.$code.' - '.self::libelle($code));
			if ($identifiants_ok) {
				$tests[] = array('Verdict', 'ok',
					'Connexion operationnelle : vos SMS peuvent partir.');
			} else {
				$tests[] = array('Verdict', 'ko',
					'Corrigez l\'identifiant et la cle API (espace client 123-sms.net > API).');
			}
		}

		return $tests;
	}
}
