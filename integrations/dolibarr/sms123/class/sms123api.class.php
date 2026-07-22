<?php
/* Classe d'appel de l'API 123-SMS.net - licence MIT
 * Reutilisable dans vos triggers, crons et scripts Dolibarr :
 *   require_once DOL_DOCUMENT_ROOT.'/custom/sms123/class/sms123api.class.php';
 *   $code = Sms123Api::envoyer('0601020304', 'Votre commande est prete.');
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

class Sms123Api
{
	/**
	 * Envoie un SMS. Renvoie le code retour API (80 = envoye) ou 'ERR:...'
	 *
	 * @param string $numero  destinataire (0601020304 ou 33601020304,
	 *                        plusieurs numeros separes par des tirets)
	 * @param string $message texte du SMS (160 caracteres GSM par SMS)
	 * @return string
	 */
	public static function envoyer($numero, $message)
	{
		global $conf;

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

		// Appel HTTPS via le client Dolibarr (proxy de l'instance respecte)
		$res = getURLContent('https://www.123-sms.net/http.php', 'POST', http_build_query($champs));
		if (empty($res['http_code']) || $res['http_code'] != 200) {
			dol_syslog('Sms123Api::envoyer echec HTTP '.(empty($res['http_code']) ? '?' : $res['http_code']), LOG_WARNING);
			return 'ERR: appel API impossible (HTTP '.(empty($res['http_code']) ? '?' : $res['http_code']).')';
		}
		$code = trim($res['content']);
		dol_syslog('Sms123Api::envoyer -> code '.$code, LOG_INFO);
		return $code;
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
}
