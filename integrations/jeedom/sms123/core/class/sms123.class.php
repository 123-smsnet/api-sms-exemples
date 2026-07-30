<?php
/**
 * 123-SMS pour Jeedom — envoi de SMS par l'API HTTPS, sans démon ni matériel.
 *
 * Licence MIT : réutilisation libre, y compris commerciale.
 *
 * @link https://www.123-sms.net/envoyer-sms-jeedom.php
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class sms123 extends eqLogic
{
    const URL_ENVOI = 'https://www.123-sms.net/http.php';
    const URL_SOLDE = 'https://www.123-sms.net/solde_comptes.php';

    /** Les seuls codes retour à trois chiffres. */
    const CODES_LONGS = array('100', '101', '102');

    /**
     * Transport alternatif : function($url, array $champs) : string.
     * Sert aux tests automatisés et permet de passer par un relais maison.
     *
     * @var callable|null
     */
    public static $transport = null;

    /*     * *********************** Méthodes statiques *********************** */

    /**
     * Rafraîchit le solde toutes les 30 minutes.
     *
     * @return void
     */
    public static function cron30()
    {
        foreach (self::byType('sms123', true) as $eqLogic) {
            $cmd = $eqLogic->getCmd(null, 'solde');
            if (!is_object($cmd)) {
                continue;
            }
            $solde = self::solde();
            if ($solde !== false && is_numeric($solde)) {
                $eqLogic->checkAndUpdateCmd('solde', (int) $solde);
            }
        }
    }

    /**
     * Le compte est-il renseigné ?
     *
     * @return boolean
     */
    public static function estConfigure()
    {
        return trim(config::byKey('identifiant', 'sms123')) !== ''
            && trim(config::byKey('cle_api', 'sms123')) !== '';
    }

    /**
     * Normalise un numéro : espaces, points, +33, 0033.
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
     * Découpe une liste de numéros saisie librement.
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
     * @param boolean $test
     * @return boolean
     */
    public static function estSucces($code, $test = false)
    {
        if (in_array((string) $code, array('80', '81'), true)) {
            return true;
        }
        return $test && (string) $code === '92';
    }

    /**
     * Sépare le code retour de la référence d'accusé.
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
        $reference = '';
        $reste = trim($reste);
        if ($reste !== '') {
            if (preg_match('/(?:refaccuse|refenvoi|ref)\s*[=:]\s*([A-Za-z0-9_.\-]+)/i', $reste, $r)) {
                $reference = $r[1];
            } elseif (preg_match('/([A-Za-z0-9_.\-]{2,})/', $reste, $r)) {
                $reference = $r[1];
            }
        }
        return array($code, $reference);
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
            '85' => "Le format de l'envoi en différé n'est pas valide.",
            '86' => 'Le groupe de contacts est vide.',
            '87' => 'La valeur « identifiant » est vide.',
            '88' => 'La valeur « clé API » est vide.',
            '89' => 'La valeur « numéro » est vide.',
            '90' => 'La valeur « message » est vide.',
            '91' => 'Doublon : même message déjà envoyé à ce numéro sous 24 h.',
            '92' => "Test d'envoi concluant : requête valide, aucun SMS envoyé, aucun crédit débité.",
            '93' => "Envoi vers les DOM-TOM : activez l'option 14 dans votre espace client.",
            '94' => 'Votre envoi en différé a été supprimé.',
            '95' => "Votre envoi en différé n'a pas pu être supprimé.",
            '96' => "Votre adresse IP n'est pas autorisée (restriction d'accès sur le compte).",
            '97' => 'Sender-ID invalide ou non déclaré.',
            '98' => "La date de début n'est pas valide.",
            '99' => "La date de fin n'est pas valide.",
            '100' => 'La date de fin est antérieure à la date de début.',
            '101' => 'Numéro bloqué : il figure sur la liste noire (désinscription STOP).',
            '102' => 'Changement de Sender-ID : ajoutez « STOP SMS » à la fin du message.',
        );
        return isset($codes[$code]) ? $codes[$code] : 'Code retour : ' . $code;
    }

    /**
     * Appel HTTP vers l'API.
     *
     * @param string $url
     * @param array $champs
     * @return string
     * @throws Exception si la requête ne sort pas
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
                throw new Exception('123-SMS : appel impossible (' . $erreur . ')');
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
            throw new Exception('123-SMS : appel impossible (allow_url_fopen désactivé ?)');
        }
        return $reponse;
    }

    /**
     * Envoie un SMS.
     *
     * @param string $numero
     * @param string $message
     * @return array code, reference, succes, texte
     * @throws Exception
     */
    public static function envoyer($numero, $message)
    {
        if (!self::estConfigure()) {
            throw new Exception('123-SMS : identifiant ou clé API manquants (configuration du plugin).');
        }

        $numero = self::normaliser($numero);
        $message = trim((string) $message);
        if ($numero === '') {
            throw new Exception('123-SMS : numéro de destinataire vide.');
        }
        if ($message === '') {
            throw new Exception('123-SMS : message vide.');
        }

        $test = (config::byKey('mode_test', 'sms123') == 1);
        $champs = array(
            'email' => trim(config::byKey('identifiant', 'sms123')),
            'pass' => trim(config::byKey('cle_api', 'sms123')),
            'numero' => $numero,
            'message' => $message,
            'refaccuse' => 'o',
        );
        $expediteur = trim(config::byKey('expediteur', 'sms123'));
        if ($expediteur !== '') {
            $champs['sender'] = $expediteur;
        }
        if ($test) {
            $champs['test'] = 'o';
        }

        $reponse = static::appeler(self::URL_ENVOI, $champs);
        list($code, $reference) = self::separerCodeReference($reponse);
        $succes = self::estSucces($code, $test);
        $texte = self::messageCode($code);

        if ($succes) {
            log::add('sms123', 'info', 'SMS vers ' . $numero . ' : code ' . $code
                . ($reference !== '' ? ' (référence ' . $reference . ')' : ''));
        } else {
            log::add('sms123', 'error', 'Échec vers ' . $numero . ' : code ' . $code . ' — ' . $texte);
        }

        return array(
            'code' => $code,
            'reference' => $reference,
            'succes' => $succes,
            'texte' => $texte,
        );
    }

    /**
     * Solde du compte, en nombre de SMS.
     *
     * @return string|false
     */
    public static function solde()
    {
        if (!self::estConfigure()) {
            return false;
        }
        try {
            $reponse = trim(static::appeler(self::URL_SOLDE, array(
                'email' => trim(config::byKey('identifiant', 'sms123')),
                'pass' => trim(config::byKey('cle_api', 'sms123')),
            )));
        } catch (Exception $e) {
            log::add('sms123', 'error', $e->getMessage());
            return false;
        }
        return preg_match('/-?\d+/', $reponse, $m) ? $m[0] : $reponse;
    }

    /*     * *********************** Méthodes d'instance *********************** */

    /**
     * Contrôle la configuration de l'équipement avant enregistrement.
     *
     * @return void
     * @throws Exception
     */
    public function preSave()
    {
        $numeros = self::listeNumeros($this->getConfiguration('numero'));
        if (!count($numeros)) {
            throw new Exception('Renseignez au moins un numéro de destinataire.');
        }
        // Virgules et non tirets : un tiret serait avalé par la normalisation.
        $this->setConfiguration('numero', implode(', ', $numeros));
    }

    /**
     * Crée les commandes de l'équipement.
     *
     * @return void
     */
    public function postSave()
    {
        $envoyer = $this->getCmd(null, 'send');
        if (!is_object($envoyer)) {
            $envoyer = new sms123Cmd();
            $envoyer->setLogicalId('send');
            $envoyer->setEqLogic_id($this->getId());
            $envoyer->setName(__('Envoyer un SMS', __FILE__));
            $envoyer->setIsVisible(1);
        }
        $envoyer->setType('action');
        $envoyer->setSubType('message');
        $envoyer->setDisplay('title_disable', 1);
        $envoyer->save();

        $solde = $this->getCmd(null, 'solde');
        if (!is_object($solde)) {
            $solde = new sms123Cmd();
            $solde->setLogicalId('solde');
            $solde->setEqLogic_id($this->getId());
            $solde->setName(__('Solde', __FILE__));
            $solde->setIsVisible(1);
            $solde->setIsHistorized(1);
        }
        $solde->setType('info');
        $solde->setSubType('numeric');
        $solde->setUnite('SMS');
        $solde->save();

        $rafraichir = $this->getCmd(null, 'refresh');
        if (!is_object($rafraichir)) {
            $rafraichir = new sms123Cmd();
            $rafraichir->setLogicalId('refresh');
            $rafraichir->setEqLogic_id($this->getId());
            $rafraichir->setName(__('Rafraîchir le solde', __FILE__));
            $rafraichir->setIsVisible(0);
        }
        $rafraichir->setType('action');
        $rafraichir->setSubType('other');
        $rafraichir->save();
    }

    /**
     * Met à jour le solde de cet équipement.
     *
     * @return void
     */
    public function rafraichirSolde()
    {
        $solde = self::solde();
        if ($solde !== false && is_numeric($solde)) {
            $this->checkAndUpdateCmd('solde', (int) $solde);
        }
    }
}

class sms123Cmd extends cmd
{
    /**
     * Les commandes du plugin ne se suppriment pas à la main.
     *
     * @return boolean
     */
    public function dontRemoveCmd()
    {
        return in_array($this->getLogicalId(), array('send', 'solde', 'refresh'), true);
    }

    /**
     * Exécution de la commande.
     *
     * @param array $_options
     * @return string|void
     * @throws Exception
     */
    public function execute($_options = array())
    {
        $eqLogic = $this->getEqLogic();

        switch ($this->getLogicalId()) {
            case 'refresh':
                $eqLogic->rafraichirSolde();
                return;

            case 'send':
                $titre = isset($_options['title']) ? trim($_options['title']) : '';
                $corps = isset($_options['message']) ? trim($_options['message']) : '';
                $message = trim($titre . ' ' . $corps);
                if ($message === '') {
                    throw new Exception('123-SMS : message vide.');
                }
                // Un SMS standard fait 160 caractères : au-delà, il est facturé en plusieurs crédits.
                if (function_exists('mb_strlen') && mb_strlen($message, 'UTF-8') > 480) {
                    $message = mb_substr($message, 0, 480, 'UTF-8');
                }

                $resultats = array();
                foreach (sms123::listeNumeros($eqLogic->getConfiguration('numero')) as $numero) {
                    $resultat = sms123::envoyer($numero, $message);
                    if (!$resultat['succes']) {
                        throw new Exception('123-SMS : ' . $numero . ' — code ' . $resultat['code']
                            . ' : ' . $resultat['texte']);
                    }
                    $resultats[] = $numero . ' (' . $resultat['code'] . ')';
                }
                $eqLogic->rafraichirSolde();
                return implode(', ', $resultats);
        }
    }
}
