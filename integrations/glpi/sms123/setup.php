<?php
/**
 * 123-SMS pour GLPI — alertes d'astreinte et notifications par SMS.
 *
 * Licence MIT : réutilisation libre, y compris commerciale.
 * Compatible GLPI 10.0 et 11.0.
 *
 * @link https://www.123-sms.net/developpeurs-api-123-sms-pro-glpi.php
 */

define('PLUGIN_SMS123_VERSION', '1.0.0');
define('PLUGIN_SMS123_MIN_GLPI', '10.0.0');
define('PLUGIN_SMS123_MAX_GLPI', '11.0.99');

/**
 * Initialisation du plugin : déclaration des accroches GLPI.
 * Appelé à chaque page, il doit rester léger.
 */
function plugin_init_sms123()
{
    global $PLUGIN_HOOKS;

    // Obligatoire : le plugin gère les jetons anti-CSRF de GLPI.
    $PLUGIN_HOOKS['csrf_compliant']['sms123'] = true;

    Plugin::registerClass('PluginSms123Envoi');

    // Page de configuration (roue dentée dans la liste des plugins).
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['sms123'] = 'front/config.form.php';
    }

    // Entrée de menu « Envoyer un SMS » dans Outils.
    if (Session::haveRight('plugin_sms123', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['sms123'] = array('tools' => 'PluginSms123Envoi');
    }

    // Déclencheurs sur les tickets : création et mise à jour.
    $PLUGIN_HOOKS['item_add']['sms123'] = array('Ticket' => 'plugin_sms123_ticket_add');
    $PLUGIN_HOOKS['item_update']['sms123'] = array('Ticket' => 'plugin_sms123_ticket_update');
}

/**
 * Carte d'identité du plugin, affichée dans la liste des plugins.
 *
 * @return array
 */
function plugin_version_sms123()
{
    return array(
        'name' => '123-SMS',
        'version' => PLUGIN_SMS123_VERSION,
        'author' => 'DRANER.COM (123-SMS.net)',
        'license' => 'MIT',
        'homepage' => 'https://www.123-sms.net/developpeurs-api-123-sms-pro-glpi.php',
        'requirements' => array(
            'glpi' => array(
                'min' => PLUGIN_SMS123_MIN_GLPI,
                'max' => PLUGIN_SMS123_MAX_GLPI,
            ),
        ),
    );
}

/**
 * Vérifie que l'environnement permet d'activer le plugin.
 *
 * @return boolean
 */
function plugin_sms123_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_SMS123_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_SMS123_MAX_GLPI, 'gt')
    ) {
        echo 'Ce plugin demande GLPI ' . PLUGIN_SMS123_MIN_GLPI . ' à ' . PLUGIN_SMS123_MAX_GLPI . '.';
        return false;
    }
    return true;
}

/**
 * Vérifie la configuration ; false affiche le lien « Configurer ».
 *
 * @param boolean $verbose
 * @return boolean
 */
function plugin_sms123_check_config($verbose = false)
{
    if ($verbose) {
        echo 'Renseignez votre identifiant et votre clé API 123-SMS.';
    }
    return true;
}
