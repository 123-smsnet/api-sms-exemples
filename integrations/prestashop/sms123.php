<?php
/**
 * 123-SMS pour PrestaShop — notifications SMS de commande via 123-SMS.net
 * Service français d'envoi de SMS professionnels depuis 2002.
 *
 * Licence MIT : réutilisation libre (github.com/123-smsnet/api-sms-exemples)
 * Compatible PrestaShop 1.7 à 8.x.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Sms123 extends Module
{
    public function __construct()
    {
        $this->name = 'sms123';
        $this->tab = 'emailing';
        $this->version = '1.0.0';
        $this->author = '123-SMS.net (DRANER.com)';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SMS 123-SMS.net');
        $this->description = $this->l('Notifications SMS de commande : SMS au marchand à chaque commande, SMS au client à la confirmation et à l\'expédition. Crédits prépayés sans abonnement.');
        $this->confirmUninstall = $this->l('Supprimer le module et ses réglages ?');
    }

    public function install()
    {
        Configuration::updateValue('SMS123_ADMIN_ON', 1);
        Configuration::updateValue('SMS123_CONF_ON', 0);
        Configuration::updateValue('SMS123_EXP_ON', 0);
        Configuration::updateValue('SMS123_TPL_ADMIN', 'Nouvelle commande {numero} de {prenom} {nom} : {total} EUR');
        Configuration::updateValue('SMS123_TPL_CONF', '{boutique} : votre commande {numero} est confirmee. Merci !');
        Configuration::updateValue('SMS123_TPL_EXP', '{boutique} : votre commande {numero} a ete expediee.');

        return parent::install()
            && $this->registerHook('actionOrderStatusPostUpdate');
    }

    public function uninstall()
    {
        foreach (['SMS123_IDENTIFIANT', 'SMS123_CLEAPI', 'SMS123_SENDER', 'SMS123_NUM_ADMIN',
            'SMS123_ADMIN_ON', 'SMS123_CONF_ON', 'SMS123_EXP_ON',
            'SMS123_TPL_ADMIN', 'SMS123_TPL_CONF', 'SMS123_TPL_EXP'] as $cle) {
            Configuration::deleteByName($cle);
        }

        return parent::uninstall();
    }

    /* ------------------------------------------------ écran de configuration */

    public function getContent()
    {
        $sortie = '';
        if (Tools::isSubmit('submitSms123')) {
            foreach (['SMS123_IDENTIFIANT', 'SMS123_CLEAPI', 'SMS123_SENDER', 'SMS123_NUM_ADMIN',
                'SMS123_TPL_ADMIN', 'SMS123_TPL_CONF', 'SMS123_TPL_EXP'] as $cle) {
                Configuration::updateValue($cle, trim((string) Tools::getValue($cle)));
            }
            foreach (['SMS123_ADMIN_ON', 'SMS123_CONF_ON', 'SMS123_EXP_ON'] as $cle) {
                Configuration::updateValue($cle, (int) Tools::getValue($cle));
            }
            $sortie .= $this->displayConfirmation($this->l('Réglages enregistrés.'));
        }

        return $sortie . $this->renderForm();
    }

    protected function renderForm()
    {
        $interrupteur = function ($nom) {
            return [
                ['id' => $nom . '_on', 'value' => 1, 'label' => $this->l('Oui')],
                ['id' => $nom . '_off', 'value' => 0, 'label' => $this->l('Non')],
            ];
        };

        $formulaire = [['form' => [
            'legend' => ['title' => $this->l('SMS 123-SMS.net'), 'icon' => 'icon-mobile'],
            'description' => $this->l('Identifiant et clé API : transmis par e-mail à l\'inscription sur 123-sms.net (espace client > API). Variables des modèles : {numero} {prenom} {nom} {total} {boutique}'),
            'input' => [
                ['type' => 'text', 'label' => $this->l('Identifiant 123-SMS'), 'name' => 'SMS123_IDENTIFIANT', 'required' => true],
                ['type' => 'text', 'label' => $this->l('Clé API'), 'name' => 'SMS123_CLEAPI', 'required' => true],
                ['type' => 'text', 'label' => $this->l('Sender-ID (optionnel)'), 'name' => 'SMS123_SENDER',
                    'desc' => $this->l('Nom d\'expéditeur personnalisé (11 caractères max), à déclarer auprès de 123-SMS.')],
                ['type' => 'text', 'label' => $this->l('Numéro du marchand'), 'name' => 'SMS123_NUM_ADMIN'],
                ['type' => 'switch', 'label' => $this->l('SMS au marchand'), 'name' => 'SMS123_ADMIN_ON',
                    'is_bool' => true, 'values' => $interrupteur('admin'),
                    'desc' => $this->l('À chaque paiement accepté.')],
                ['type' => 'text', 'label' => $this->l('Modèle marchand'), 'name' => 'SMS123_TPL_ADMIN'],
                ['type' => 'switch', 'label' => $this->l('SMS client — confirmation'), 'name' => 'SMS123_CONF_ON',
                    'is_bool' => true, 'values' => $interrupteur('conf')],
                ['type' => 'text', 'label' => $this->l('Modèle confirmation'), 'name' => 'SMS123_TPL_CONF'],
                ['type' => 'switch', 'label' => $this->l('SMS client — expédition'), 'name' => 'SMS123_EXP_ON',
                    'is_bool' => true, 'values' => $interrupteur('exp')],
                ['type' => 'text', 'label' => $this->l('Modèle expédition'), 'name' => 'SMS123_TPL_EXP'],
            ],
            'submit' => ['title' => $this->l('Enregistrer')],
        ]]];

        $assistant = new HelperForm();
        $assistant->module = $this;
        $assistant->name_controller = $this->name;
        $assistant->token = Tools::getAdminTokenLite('AdminModules');
        $assistant->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $assistant->submit_action = 'submitSms123';
        $assistant->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $assistant->fields_value = [
            'SMS123_IDENTIFIANT' => Configuration::get('SMS123_IDENTIFIANT'),
            'SMS123_CLEAPI' => Configuration::get('SMS123_CLEAPI'),
            'SMS123_SENDER' => Configuration::get('SMS123_SENDER'),
            'SMS123_NUM_ADMIN' => Configuration::get('SMS123_NUM_ADMIN'),
            'SMS123_ADMIN_ON' => (int) Configuration::get('SMS123_ADMIN_ON'),
            'SMS123_CONF_ON' => (int) Configuration::get('SMS123_CONF_ON'),
            'SMS123_EXP_ON' => (int) Configuration::get('SMS123_EXP_ON'),
            'SMS123_TPL_ADMIN' => Configuration::get('SMS123_TPL_ADMIN'),
            'SMS123_TPL_CONF' => Configuration::get('SMS123_TPL_CONF'),
            'SMS123_TPL_EXP' => Configuration::get('SMS123_TPL_EXP'),
        ];

        return $assistant->generateForm($formulaire);
    }

    /* ------------------------------------------------ envois */

    public function hookActionOrderStatusPostUpdate($params)
    {
        if (empty($params['newOrderStatus']) || empty($params['id_order'])) {
            return;
        }
        $statut = (int) $params['newOrderStatus']->id;
        $commande = new Order((int) $params['id_order']);
        if (!Validate::isLoadedObject($commande)) {
            return;
        }

        if ($statut === (int) Configuration::get('PS_OS_PAYMENT')) {
            if (Configuration::get('SMS123_ADMIN_ON') && Configuration::get('SMS123_NUM_ADMIN')) {
                $this->envoyer(Configuration::get('SMS123_NUM_ADMIN'), $this->modele('SMS123_TPL_ADMIN', $commande), 'marchand');
            }
            if (Configuration::get('SMS123_CONF_ON')) {
                $this->smsClient($commande, 'SMS123_TPL_CONF', 'confirmation');
            }
        } elseif ($statut === (int) Configuration::get('PS_OS_SHIPPING')) {
            if (Configuration::get('SMS123_EXP_ON')) {
                $this->smsClient($commande, 'SMS123_TPL_EXP', 'expedition');
            }
        }
    }

    protected function smsClient($commande, $tpl, $etape)
    {
        $adresse = new Address((int) $commande->id_address_delivery);
        $tel = $adresse->phone_mobile ?: $adresse->phone;
        if ($tel) {
            $this->envoyer($tel, $this->modele($tpl, $commande), 'client (' . $etape . ')');
        }
    }

    protected function modele($cle, $commande)
    {
        $client = new Customer((int) $commande->id_customer);

        return strtr((string) Configuration::get($cle), [
            '{numero}' => $commande->reference,
            '{prenom}' => $client->firstname,
            '{nom}' => $client->lastname,
            '{total}' => number_format((float) $commande->total_paid, 2, ',', ' '),
            '{boutique}' => Configuration::get('PS_SHOP_NAME'),
        ]);
    }

    /** Normalise un numéro français : 06/07 -> 336/337, sans espaces. */
    public static function normaliser($tel)
    {
        $tel = preg_replace('/[^0-9+]/', '', (string) $tel);
        if (strpos($tel, '+') === 0) {
            $tel = substr($tel, 1);
        }
        if (strpos($tel, '00') === 0) {
            $tel = substr($tel, 2);
        }
        if (strlen($tel) === 10 && $tel[0] === '0') {
            $tel = '33' . substr($tel, 1);
        }

        return $tel;
    }

    /** Appel HTTPS de l'API 123-SMS.net ; code retour tracé dans les logs (80 = envoyé). */
    protected function envoyer($numero, $message, $contexte)
    {
        $contexteHttp = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'email' => Configuration::get('SMS123_IDENTIFIANT'),
                'pass' => Configuration::get('SMS123_CLEAPI'),
                'numero' => self::normaliser($numero),
                'message' => $message,
                'sender' => Configuration::get('SMS123_SENDER'),
            ]),
            'timeout' => 15,
        ]]);
        $code = trim((string) Tools::file_get_contents('https://www.123-sms.net/http.php', false, $contexteHttp));
        PrestaShopLogger::addLog(
            '123-SMS ' . $contexte . ' : code retour ' . $code . ($code === '80' ? ' (envoye)' : ''),
            $code === '80' ? 1 : 2
        );

        return $code;
    }
}
