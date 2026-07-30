<?php
/**
 * Appel de l'API 123-SMS et traduction des codes retour.
 *
 * Licence MIT — 123-SMS.net
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginSms123Api
{
    const URL_ENVOI = 'https://www.123-sms.net/http.php';
    const URL_SOLDE = 'https://www.123-sms.net/solde_comptes.php';

    /** Les seuls codes retour à trois chiffres : tout le reste en fait deux. */
    const CODES_LONGS = array('100', '101', '102');

    /** Dernière réponse brute, pour l'affichage du diagnostic. */
    public static $derniere_reponse = '';

    /**
     * Transport alternatif : function($url, array $champs) : string|false.
     * Sert aux tests automatisés et permet de passer par un relais maison.
     *
     * @var callable|null
     */
    public static $transport = null;

    /**
     * Configuration du plugin (valeurs par défaut incluses).
     *
     * @return array
     */
    public static function config()
    {
        $defauts = array(
            'identifiant' => '',
            'cle_api' => '',
            'expediteur' => '',
            'actif' => 0,
            'numeros_astreinte' => '',
            'urgence_mini' => 4,
            'sur_creation' => 1,
            'sur_resolution' => 0,
            'modele_creation' => 'Ticket #{id} ({urgence}) : {titre} - {demandeur}',
            'modele_resolution' => 'Votre ticket #{id} est resolu : {titre}',
            'mode_test' => 0,
        );
        $config = Config::getConfigurationValues('plugin:sms123');
        return array_merge($defauts, is_array($config) ? $config : array());
    }

    /**
     * Le plugin est-il prêt à envoyer ?
     *
     * @return boolean
     */
    public static function estConfigure()
    {
        $c = self::config();
        return $c['identifiant'] !== '' && $c['cle_api'] !== '';
    }

    /**
     * Normalise un numéro : espaces, points, +33.
     *
     * @param string $numero
     * @return string
     */
    public static function normaliser($numero)
    {
        $n = preg_replace('/[^0-9+]/', '', (string) $numero);
        if (strpos($n, '+') === 0) {
            $n = substr($n, 1);
        }
        if (strpos($n, '0033') === 0) {
            $n = '33' . substr($n, 4);
        }
        return $n;
    }

    /**
     * Découpe une liste de numéros saisie librement (virgules, points-virgules, retours).
     *
     * @param string $liste
     * @return array
     */
    public static function listeNumeros($liste)
    {
        // On ne coupe PAS sur les espaces : « 06 01 02 03 04 » est un seul numero.
        $bruts = preg_split('/[,;\r\n]+/', (string) $liste, -1, PREG_SPLIT_NO_EMPTY);
        $out = array();
        foreach ($bruts as $b) {
            $n = self::normaliser($b);
            if ($n !== '') {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * 80 et 81 valent succès ; 92 vaut succès en envoi à blanc.
     *
     * @param string $code
     * @param integer $test
     * @return boolean
     */
    public static function estSucces($code, $test = 0)
    {
        if (in_array((string) $code, array('80', '81'), true)) {
            return true;
        }
        return !empty($test) && (string) $code === '92';
    }

    /**
     * Sépare le code retour de la référence d'accusé (refaccuse).
     *
     * @param string $contenu
     * @return array array($code, $reference)
     */
    public static function separerCodeReference($contenu)
    {
        $brut = trim((string) $contenu);
        if (!preg_match('/^([0-9]{2,3})(.*)$/s', $brut, $m)) {
            return array($brut, '');
        }
        $code = $m[1];
        $reste = $m[2];
        if (strlen($code) == 3 && !in_array($code, self::CODES_LONGS, true)) {
            $reste = substr($code, 2) . $reste;
            $code = substr($code, 0, 2);
        }
        return array($code, self::extraireReference($reste));
    }

    /**
     * Extrait la référence d'un reste de réponse.
     *
     * @param string $texte
     * @return string
     */
    public static function extraireReference($texte)
    {
        $texte = trim((string) $texte);
        if ($texte === '') {
            return '';
        }
        if (preg_match('/(?:refaccuse|refenvoi|ref)\s*[=:]\s*([A-Za-z0-9_.\-]+)/i', $texte, $m)) {
            return $m[1];
        }
        if (preg_match('/([A-Za-z0-9_.\-]{2,})/', $texte, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Message en clair pour un code retour.
     *
     * @param string $code
     * @return string
     */
    public static function messageCode($code)
    {
        $codes = array(
            '80' => 'Le message a été envoyé.',
            '81' => 'Enregistré pour un envoi en différé.',
            '82' => 'Identifiant et/ou clé API invalides.',
            '83' => 'Crédit insuffisant : rechargez votre compte.',
            '84' => 'Numéro de mobile invalide.',
            '85' => 'Le format de l\'envoi en différé n\'est pas valide.',
            '86' => 'Le groupe de contacts est vide.',
            '87' => 'La valeur « identifiant » est vide.',
            '88' => 'La valeur « clé API » est vide.',
            '89' => 'La valeur « numéro » est vide.',
            '90' => 'La valeur « message » est vide.',
            '91' => 'Doublon : même message déjà envoyé à ce numéro sous 24 h.',
            '92' => 'Test d\'envoi concluant : la requête est valide (aucun SMS envoyé, aucun crédit débité).',
            '93' => 'Envoi vers les DOM-TOM : activez l\'option 14 dans votre espace client.',
            '94' => 'Votre envoi en différé a été supprimé.',
            '95' => 'Votre envoi en différé n\'a pas pu être supprimé.',
            '96' => 'Votre adresse IP n\'est pas autorisée (restriction d\'accès sur le compte).',
            '97' => 'Sender-ID invalide ou non déclaré.',
            '98' => 'La date de début n\'est pas valide.',
            '99' => 'La date de fin n\'est pas valide.',
            '100' => 'La date de fin est antérieure à la date de début.',
            '101' => 'Numéro bloqué : il figure sur la liste noire (désinscription STOP).',
            '102' => 'Changement de Sender-ID : ajoutez « STOP SMS » à la fin du message.',
            'ERR' => 'Échec technique : le SMS n\'est pas parti (réseau, DNS ou pare-feu).',
        );
        return isset($codes[$code]) ? $codes[$code] : 'Code retour : ' . $code;
    }

    /**
     * Appel HTTP, cURL si disponible, flux PHP sinon.
     *
     * @param string $url
     * @param array $champs
     * @return string|false
     */
    protected static function appeler($url, array $champs)
    {
        if (is_callable(self::$transport)) {
            return call_user_func(self::$transport, $url, $champs);
        }

        $corps = http_build_query($champs);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $reponse = curl_exec($ch);
            $erreur = curl_error($ch);
            curl_close($ch);
            if ($reponse === false) {
                self::$derniere_reponse = 'cURL : ' . $erreur;
                return false;
            }
            return $reponse;
        }

        $contexte = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $corps,
            'timeout' => 20,
        )));
        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse === false) {
            self::$derniere_reponse = 'Appel HTTP impossible (allow_url_fopen ?)';
        }
        return $reponse;
    }

    /**
     * Envoie un SMS et historise le résultat.
     *
     * @param string $numero
     * @param string $message
     * @param integer $tickets_id  0 si envoi manuel
     * @param string $origine      creation | resolution | manuel | test
     * @return array code, reference, succes, message
     */
    public static function envoyer($numero, $message, $tickets_id = 0, $origine = 'manuel')
    {
        $c = self::config();
        $numero = self::normaliser($numero);
        $message = trim((string) $message);

        if (!self::estConfigure()) {
            return self::resultat('ERR', '', 0, 'Identifiant ou clé API manquants.', $numero, $message, $tickets_id, $origine);
        }
        if ($numero === '' || $message === '') {
            return self::resultat('ERR', '', 0, 'Numéro ou message vide.', $numero, $message, $tickets_id, $origine);
        }

        $champs = array(
            'email' => $c['identifiant'],
            'pass' => $c['cle_api'],
            'numero' => $numero,
            'message' => $message,
            'refaccuse' => 'o',
        );
        if (!empty($c['expediteur'])) {
            $champs['sender'] = $c['expediteur'];
        }
        if (!empty($c['mode_test'])) {
            $champs['test'] = 'o';
        }

        $reponse = static::appeler(self::URL_ENVOI, $champs);
        if ($reponse === false) {
            return self::resultat('ERR', '', 0, self::$derniere_reponse, $numero, $message, $tickets_id, $origine);
        }

        self::$derniere_reponse = trim($reponse);
        list($code, $reference) = self::separerCodeReference($reponse);
        $succes = self::estSucces($code, !empty($c['mode_test'])) ? 1 : 0;

        return self::resultat($code, $reference, $succes, self::messageCode($code), $numero, $message, $tickets_id, $origine);
    }

    /**
     * Historise et renvoie le résultat d'un envoi.
     *
     * @return array
     */
    protected static function resultat($code, $reference, $succes, $texte, $numero, $message, $tickets_id, $origine)
    {
        $envoi = new PluginSms123Envoi();
        $envoi->add(array(
            // glpi_currenttime n'existe pas hors session (tâche planifiée, CLI).
            'date_envoi' => isset($_SESSION['glpi_currenttime'])
                ? $_SESSION['glpi_currenttime']
                : date('Y-m-d H:i:s'),
            'tickets_id' => (int) $tickets_id,
            'numero' => $numero,
            'message' => $message,
            'code' => $code,
            'reference' => $reference,
            'succes' => (int) $succes,
            'origine' => $origine,
            'users_id' => isset($_SESSION['glpiID']) ? (int) $_SESSION['glpiID'] : 0,
        ));

        if (!$succes) {
            Toolbox::logInFile('sms123', sprintf(
                "Echec vers %s (%s) : code %s - %s\n",
                $numero,
                $origine,
                $code,
                $texte
            ));
        }

        return array(
            'code' => $code,
            'reference' => $reference,
            'succes' => (bool) $succes,
            'message' => $texte,
        );
    }

    /**
     * Solde du compte, en nombre de SMS.
     *
     * @return string|false
     */
    public static function solde()
    {
        $c = self::config();
        if (!self::estConfigure()) {
            return false;
        }
        $reponse = static::appeler(self::URL_SOLDE, array(
            'email' => $c['identifiant'],
            'pass' => $c['cle_api'],
        ));
        if ($reponse === false) {
            return false;
        }
        $reponse = trim($reponse);
        self::$derniere_reponse = $reponse;
        return preg_match('/-?\d+/', $reponse, $m) ? $m[0] : $reponse;
    }

    /**
     * Remplace les variables d'un modèle par les données du ticket.
     *
     * @param string $modele
     * @param Ticket $ticket
     * @return string
     */
    public static function remplir($modele, Ticket $ticket)
    {
        $urgences = array(1 => 'tres basse', 2 => 'basse', 3 => 'moyenne', 4 => 'haute', 5 => 'tres haute');
        $urgence = (int) $ticket->fields['urgency'];

        $demandeur = '';
        if (method_exists($ticket, 'getUsers')) {
            foreach ($ticket->getUsers(CommonITILActor::REQUESTER) as $u) {
                $user = new User();
                if (!empty($u['users_id']) && $user->getFromDB($u['users_id'])) {
                    $demandeur = $user->getFriendlyName();
                    break;
                }
            }
        }

        $entite = '';
        if (isset($ticket->fields['entities_id'])) {
            $entite = Dropdown::getDropdownName('glpi_entities', $ticket->fields['entities_id']);
        }

        $valeurs = array(
            '{id}' => $ticket->fields['id'],
            '{titre}' => isset($ticket->fields['name']) ? $ticket->fields['name'] : '',
            '{urgence}' => isset($urgences[$urgence]) ? $urgences[$urgence] : $urgence,
            '{demandeur}' => $demandeur,
            '{entite}' => $entite,
            '{categorie}' => isset($ticket->fields['itilcategories_id'])
                ? Dropdown::getDropdownName('glpi_itilcategories', $ticket->fields['itilcategories_id'])
                : '',
        );

        $texte = strtr((string) $modele, $valeurs);
        // Un SMS standard fait 160 caractères : on coupe proprement.
        if (function_exists('mb_substr')) {
            return mb_substr($texte, 0, 160, 'UTF-8');
        }
        return substr($texte, 0, 160);
    }
}
