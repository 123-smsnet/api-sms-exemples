<?php
/**
 * Historique des SMS envoyés + entrée de menu « Envoyer un SMS ».
 *
 * Licence MIT — 123-SMS.net
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginSms123Envoi extends CommonDBTM
{
    public static $rightname = 'plugin_sms123';

    /**
     * @param integer $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return '123-SMS';
    }

    /**
     * @return string
     */
    public static function getMenuName()
    {
        return 'Envoyer un SMS';
    }

    /**
     * Entrée du menu Outils.
     *
     * @return array
     */
    public static function getMenuContent()
    {
        return array(
            'title' => self::getMenuName(),
            'page' => '/plugins/sms123/front/envoi.php',
            'icon' => 'ti ti-message-2',
            'links' => array(
                'search' => '/plugins/sms123/front/envoi.php',
            ),
        );
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-message-2';
    }

    /**
     * Les N derniers envois.
     *
     * @param integer $limite
     * @return array
     */
    public static function derniers($limite = 20)
    {
        global $DB;

        $lignes = array();
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            return $lignes;
        }
        $requete = $DB->request(array(
            'FROM' => $table,
            'ORDER' => 'id DESC',
            'LIMIT' => (int) $limite,
        ));
        foreach ($requete as $ligne) {
            $lignes[] = $ligne;
        }
        return $lignes;
    }

    /**
     * Tableau HTML des derniers envois.
     *
     * @param integer $limite
     * @return void
     */
    public static function afficherHistorique($limite = 20)
    {
        $lignes = self::derniers($limite);

        echo "<div class='center'><table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='6'>Derniers SMS envoyés</th></tr>";

        if (!count($lignes)) {
            echo "<tr class='tab_bg_1'><td colspan='6' class='center'>Aucun envoi pour le moment.</td></tr>";
            echo '</table></div>';
            return;
        }

        echo "<tr><th>Date</th><th>Numéro</th><th>Message</th><th>Origine</th><th>Ticket</th><th>Résultat</th></tr>";
        foreach ($lignes as $l) {
            $couleur = $l['succes'] ? '#268614' : '#c83232';
            $etat = $l['succes'] ? 'envoyé' : 'échec';
            echo "<tr class='tab_bg_1'>";
            echo '<td>' . Html::convDateTime($l['date_envoi']) . '</td>';
            echo '<td>' . htmlspecialchars($l['numero']) . '</td>';
            echo '<td>' . htmlspecialchars($l['message']) . '</td>';
            echo '<td>' . htmlspecialchars($l['origine']) . '</td>';
            echo '<td>' . ($l['tickets_id'] ? '#' . (int) $l['tickets_id'] : '-') . '</td>';
            echo "<td style='color:" . $couleur . "'><b>" . $l['code'] . '</b> — ' . $etat
                . ' <span style="color:#566b7d">' . htmlspecialchars(PluginSms123Api::messageCode($l['code'])) . '</span></td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
}
