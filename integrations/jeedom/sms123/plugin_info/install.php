<?php
/**
 * Installation / mise à jour / suppression du plugin 123-SMS.
 *
 * Licence MIT — 123-SMS.net
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

/**
 * Exécutée automatiquement après l'installation du plugin.
 *
 * @return void
 */
function sms123_install()
{
    // Le solde est rafraîchi par la fonction cron30 de la classe : rien à planifier ici.
    if (config::byKey('mode_test', 'sms123') === '') {
        config::save('mode_test', 0, 'sms123');
    }
}

/**
 * Exécutée automatiquement après la mise à jour du plugin.
 *
 * @return void
 */
function sms123_update()
{
    sms123_install();
}

/**
 * Exécutée automatiquement après la suppression du plugin.
 *
 * @return void
 */
function sms123_remove()
{
    config::remove('identifiant', 'sms123');
    config::remove('cle_api', 'sms123');
    config::remove('expediteur', 'sms123');
    config::remove('mode_test', 'sms123');
}
