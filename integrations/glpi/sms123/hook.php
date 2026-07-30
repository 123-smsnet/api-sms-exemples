<?php
/**
 * Installation, désinstallation et déclencheurs sur les tickets.
 *
 * Licence MIT — 123-SMS.net
 */

/**
 * Création de la table d'historique, du droit et des réglages par défaut.
 *
 * @return boolean
 */
function plugin_sms123_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_SMS123_VERSION);
    $table = 'glpi_plugin_sms123_envois';

    if (!$DB->tableExists($table)) {
        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $signe = DBConnection::getDefaultPrimaryKeySignOption();

        $requete = "CREATE TABLE `$table` (
            `id` int $signe NOT NULL auto_increment,
            `date_envoi` timestamp NULL DEFAULT NULL,
            `tickets_id` int $signe NOT NULL DEFAULT '0',
            `users_id` int $signe NOT NULL DEFAULT '0',
            `numero` varchar(32) DEFAULT NULL,
            `message` text,
            `code` varchar(8) DEFAULT NULL,
            `reference` varchar(64) DEFAULT NULL,
            `succes` tinyint NOT NULL DEFAULT '0',
            `origine` varchar(16) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `date_envoi` (`date_envoi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC;";

        $DB->query($requete) or die('123-SMS : création de ' . $table . ' impossible — ' . $DB->error());
    }

    // Droit dédié : lecture = envoyer un SMS, écriture = idem (un seul usage).
    ProfileRight::addProfileRights(array('plugin_sms123'));
    $migration->addRight('plugin_sms123', ALLSTANDARDRIGHT, array('config' => UPDATE));

    // Réglages par défaut, sans écraser une configuration existante.
    $existant = Config::getConfigurationValues('plugin:sms123');
    if (empty($existant)) {
        Config::setConfigurationValues('plugin:sms123', array(
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
        ));
    }

    $migration->executeMigration();
    return true;
}

/**
 * Suppression de la table, du droit et des réglages.
 *
 * @return boolean
 */
function plugin_sms123_uninstall()
{
    global $DB;

    $table = 'glpi_plugin_sms123_envois';
    if ($DB->tableExists($table)) {
        $DB->query("DROP TABLE `$table`") or die('123-SMS : suppression de ' . $table . ' impossible');
    }

    $config = new Config();
    $config->deleteConfigurationValues('plugin:sms123', array(
        'identifiant', 'cle_api', 'expediteur', 'actif', 'numeros_astreinte',
        'urgence_mini', 'sur_creation', 'sur_resolution', 'modele_creation',
        'modele_resolution', 'mode_test',
    ));

    ProfileRight::deleteProfileRights(array('plugin_sms123'));

    return true;
}

/**
 * Droits proposés dans les profils.
 *
 * @param array $interface
 * @return array
 */
function plugin_sms123_getAddSearchOptions($itemtype)
{
    return array();
}

/**
 * Un ticket vient d'être créé : on prévient l'astreinte si l'urgence le justifie.
 *
 * @param Ticket $ticket
 * @return void
 */
function plugin_sms123_ticket_add(Ticket $ticket)
{
    $c = PluginSms123Api::config();

    if (empty($c['actif']) || empty($c['sur_creation']) || !PluginSms123Api::estConfigure()) {
        return;
    }
    if ((int) $ticket->fields['urgency'] < (int) $c['urgence_mini']) {
        return;
    }

    $numeros = PluginSms123Api::listeNumeros($c['numeros_astreinte']);
    if (!count($numeros)) {
        return;
    }

    $message = PluginSms123Api::remplir($c['modele_creation'], $ticket);
    foreach ($numeros as $numero) {
        PluginSms123Api::envoyer($numero, $message, $ticket->fields['id'], 'creation');
    }
}

/**
 * Un ticket vient d'être mis à jour : SMS au demandeur si le ticket est résolu.
 *
 * @param Ticket $ticket
 * @return void
 */
function plugin_sms123_ticket_update(Ticket $ticket)
{
    $c = PluginSms123Api::config();

    if (empty($c['actif']) || empty($c['sur_resolution']) || !PluginSms123Api::estConfigure()) {
        return;
    }
    // Uniquement au moment où le statut bascule en résolu ou clos.
    if (!isset($ticket->oldvalues['status'])) {
        return;
    }
    $nouveau = (int) $ticket->fields['status'];
    if (!in_array($nouveau, array(CommonITILObject::SOLVED, CommonITILObject::CLOSED), true)) {
        return;
    }

    $message = PluginSms123Api::remplir($c['modele_resolution'], $ticket);

    foreach ($ticket->getUsers(CommonITILActor::REQUESTER) as $acteur) {
        if (empty($acteur['users_id'])) {
            continue;
        }
        $user = new User();
        if (!$user->getFromDB($acteur['users_id'])) {
            continue;
        }
        $numero = !empty($user->fields['mobile']) ? $user->fields['mobile'] : $user->fields['phone'];
        if (empty($numero)) {
            continue;
        }
        PluginSms123Api::envoyer($numero, $message, $ticket->fields['id'], 'resolution');
    }
}
