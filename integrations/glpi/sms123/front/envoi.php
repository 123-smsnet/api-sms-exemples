<?php
/**
 * Envoi manuel d'un SMS depuis GLPI + historique.
 *
 * Licence MIT — 123-SMS.net
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_sms123', READ);

$message = '';
$classe = '';

if (isset($_POST['envoyer'])) {
    Session::checkCSRF($_POST);

    $numeros = PluginSms123Api::listeNumeros($_POST['numeros']);
    $texte = trim($_POST['message']);
    $tickets_id = (int) $_POST['tickets_id'];

    if (!count($numeros)) {
        $message = 'Aucun numéro valide.';
        $classe = 'ko';
    } elseif ($texte === '') {
        $message = 'Le message est vide.';
        $classe = 'ko';
    } elseif (count($numeros) > 200) {
        $message = 'Limite de 200 destinataires par envoi depuis cette page.';
        $classe = 'ko';
    } else {
        $partis = 0;
        $echecs = array();
        foreach ($numeros as $numero) {
            $resultat = PluginSms123Api::envoyer($numero, $texte, $tickets_id, 'manuel');
            if ($resultat['succes']) {
                $partis++;
            } else {
                $echecs[] = $numero . ' (' . $resultat['code'] . ' — ' . $resultat['message'] . ')';
            }
        }
        $message = $partis . ' SMS envoyé(s) sur ' . count($numeros) . '.';
        if (count($echecs)) {
            $message .= ' Échecs : ' . implode(' ; ', $echecs);
        }
        $classe = count($echecs) ? 'ko' : 'ok';
    }
}

Html::header('123-SMS', $_SERVER['PHP_SELF'], 'tools', 'PluginSms123Envoi');

echo "<div class='center'>";

if (!PluginSms123Api::estConfigure()) {
    echo "<div style='max-width:900px;margin:12px auto;padding:12px 16px;border:2px solid #e8a33d;"
        . "background:#fdf6ea;border-radius:6px;text-align:left'>"
        . 'Le plugin n\'est pas encore configuré : renseignez l\'identifiant et la clé API dans '
        . '<b>Configuration &rsaquo; Plugins &rsaquo; 123-SMS</b>.</div>';
}

if ($message !== '') {
    $fond = ($classe === 'ok') ? '#f2faee' : '#fdf1f1';
    $bord = ($classe === 'ok') ? '#268614' : '#c83232';
    echo "<div style='max-width:900px;margin:12px auto;padding:12px 16px;border:2px solid $bord;"
        . "background:$fond;border-radius:6px;text-align:left'>" . htmlspecialchars($message) . '</div>';
}

echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo Html::hidden('_glpi_csrf_token', array('value' => Session::getNewCSRFToken()));

echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>Envoyer un SMS</th></tr>";

echo "<tr class='tab_bg_1'><td width='30%'>Destinataires</td><td>";
echo "<textarea name='numeros' cols='45' rows='3'></textarea>";
echo '<br>Un ou plusieurs numéros, séparés par des virgules, des points-virgules ou des retours à la ligne. '
    . '200 maximum par envoi.</td></tr>';

echo "<tr class='tab_bg_1'><td>Message</td><td>";
echo "<textarea name='message' cols='45' rows='4' maxlength='160'></textarea>";
echo '<br>160 caractères = 1 crédit. Les emoji font tomber la limite à 70.</td></tr>';

echo "<tr class='tab_bg_1'><td>Ticket lié (facultatif)</td><td>";
echo "<input type='number' name='tickets_id' min='0' value='0' style='width:120px'>";
echo '<br>Numéro de ticket, pour retrouver l\'envoi dans l\'historique.</td></tr>';

echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo "<input type='submit' name='envoyer' class='submit' value='Envoyer'>";
echo '</td></tr>';
echo '</table>';
echo '</form>';

echo '<div style="margin-top:18px">';
PluginSms123Envoi::afficherHistorique(30);
echo '</div>';

echo '</div>';

Html::footer();
