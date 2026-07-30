<?php
/**
 * Page de configuration du plugin 123-SMS.
 *
 * Licence MIT — 123-SMS.net
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$message = '';
$classe = '';

if (isset($_POST['enregistrer'])) {
    Session::checkCSRF($_POST);

    Config::setConfigurationValues('plugin:sms123', array(
        'identifiant' => trim($_POST['identifiant']),
        'cle_api' => trim($_POST['cle_api']),
        'expediteur' => trim($_POST['expediteur']),
        'actif' => isset($_POST['actif']) ? 1 : 0,
        'numeros_astreinte' => trim($_POST['numeros_astreinte']),
        'urgence_mini' => (int) $_POST['urgence_mini'],
        'sur_creation' => isset($_POST['sur_creation']) ? 1 : 0,
        'sur_resolution' => isset($_POST['sur_resolution']) ? 1 : 0,
        'modele_creation' => trim($_POST['modele_creation']),
        'modele_resolution' => trim($_POST['modele_resolution']),
        'mode_test' => isset($_POST['mode_test']) ? 1 : 0,
    ));
    $message = 'Configuration enregistrée.';
    $classe = 'ok';
}

if (isset($_POST['tester'])) {
    Session::checkCSRF($_POST);

    $solde = PluginSms123Api::solde();
    if ($solde === false) {
        $message = 'Connexion impossible : ' . PluginSms123Api::$derniere_reponse;
        $classe = 'ko';
    } elseif (is_numeric($solde)) {
        $message = 'Connexion réussie. Solde du compte : ' . $solde . ' SMS.';
        $classe = 'ok';
    } else {
        $message = 'Réponse inattendue de l\'API : « ' . $solde . ' ».';
        $classe = 'ko';
    }
}

$config = PluginSms123Api::config();

Html::header('123-SMS', $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='center'>";

if ($message !== '') {
    $fond = ($classe === 'ok') ? '#f2faee' : '#fdf1f1';
    $bord = ($classe === 'ok') ? '#268614' : '#c83232';
    echo "<div style='max-width:900px;margin:12px auto;padding:12px 16px;border:2px solid $bord;"
        . "background:$fond;border-radius:6px;text-align:left'>" . htmlspecialchars($message) . '</div>';
}

echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo Html::hidden('_glpi_csrf_token', array('value' => Session::getNewCSRFToken()));

echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>Compte 123-SMS</th></tr>";

echo "<tr class='tab_bg_1'><td width='30%'>Identifiant du compte</td><td>";
echo "<input type='text' name='identifiant' size='40' value='" . htmlspecialchars($config['identifiant']) . "'>";
echo "<br><span class='b'>Transmis par e-mail à l'inscription (espace client &rsaquo; API).</span></td></tr>";

echo "<tr class='tab_bg_1'><td>Clé API</td><td>";
echo "<input type='password' name='cle_api' size='40' value='" . htmlspecialchars($config['cle_api']) . "' autocomplete='new-password'>";
echo '</td></tr>';

echo "<tr class='tab_bg_1'><td>Expéditeur (Sender-ID)</td><td>";
echo "<input type='text' name='expediteur' size='20' maxlength='11' value='" . htmlspecialchars($config['expediteur']) . "'>";
echo '<br>11 caractères maximum. Laissez vide pour un numéro court standard.</td></tr>';

echo "<tr class='tab_bg_1'><td>Envoi à blanc (mode test)</td><td>";
echo "<input type='checkbox' name='mode_test' value='1'" . (!empty($config['mode_test']) ? ' checked' : '') . '>';
echo ' L\'API répond comme en réel, mais rien n\'est envoyé ni débité (code retour 92).</td></tr>';

echo "<tr><th colspan='2'>Alertes automatiques</th></tr>";

echo "<tr class='tab_bg_1'><td>Activer les alertes</td><td>";
echo "<input type='checkbox' name='actif' value='1'" . (!empty($config['actif']) ? ' checked' : '') . '>';
echo ' Sans cette case, aucun SMS n\'est envoyé automatiquement (l\'envoi manuel reste possible).</td></tr>';

echo "<tr class='tab_bg_1'><td>Numéros d'astreinte</td><td>";
echo "<textarea name='numeros_astreinte' cols='45' rows='3'>" . htmlspecialchars($config['numeros_astreinte']) . '</textarea>';
echo '<br>Un ou plusieurs numéros séparés par des virgules, des points-virgules ou des retours à la ligne.</td></tr>';

echo "<tr class='tab_bg_1'><td>SMS à la création d'un ticket</td><td>";
echo "<input type='checkbox' name='sur_creation' value='1'" . (!empty($config['sur_creation']) ? ' checked' : '') . '>';
echo ' Prévenir l\'astreinte dès qu\'un ticket est créé.</td></tr>';

echo "<tr class='tab_bg_1'><td>À partir de l'urgence</td><td>";
echo "<select name='urgence_mini'>";
$urgences = array(1 => 'Très basse (1)', 2 => 'Basse (2)', 3 => 'Moyenne (3)', 4 => 'Haute (4)', 5 => 'Très haute (5)');
foreach ($urgences as $niveau => $libelle) {
    $sel = ((int) $config['urgence_mini'] === $niveau) ? ' selected' : '';
    echo "<option value='$niveau'$sel>" . $libelle . '</option>';
}
echo '</select> et au-dessus.</td></tr>';

echo "<tr class='tab_bg_1'><td>Modèle du SMS d'alerte</td><td>";
echo "<input type='text' name='modele_creation' size='60' value='" . htmlspecialchars($config['modele_creation']) . "'>";
echo '<br>Variables : <code>{id}</code> <code>{titre}</code> <code>{urgence}</code> '
    . '<code>{demandeur}</code> <code>{entite}</code> <code>{categorie}</code></td></tr>';

echo "<tr class='tab_bg_1'><td>SMS au demandeur à la résolution</td><td>";
echo "<input type='checkbox' name='sur_resolution' value='1'" . (!empty($config['sur_resolution']) ? ' checked' : '') . '>';
echo ' Envoyé au numéro de mobile de la fiche utilisateur, si elle en porte un.</td></tr>';

echo "<tr class='tab_bg_1'><td>Modèle du SMS de résolution</td><td>";
echo "<input type='text' name='modele_resolution' size='60' value='" . htmlspecialchars($config['modele_resolution']) . "'>";
echo '</td></tr>';

echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo "<input type='submit' name='enregistrer' class='submit' value='Enregistrer'> &nbsp; ";
echo "<input type='submit' name='tester' class='submit' value='Tester la connexion'>";
echo '</td></tr>';

echo '</table>';
echo '</form>';

echo "<table class='tab_cadre_fixe' style='margin-top:18px'>";
echo "<tr><th colspan='2'>Codes de retour de l'API</th></tr>";
foreach (array('80', '81', '92', '82', '83', '84', '97', '101') as $code) {
    $vert = in_array($code, array('80', '81', '92'), true);
    echo "<tr class='tab_bg_1'><td width='10%' style='color:" . ($vert ? '#268614' : '#c83232') . "'><b>$code</b></td>";
    echo '<td>' . htmlspecialchars(PluginSms123Api::messageCode($code)) . '</td></tr>';
}
echo "<tr class='tab_bg_1'><td colspan='2'>En vert : les réponses qui valent succès. "
    . 'Le code 92 est la réponse normale d\'un envoi à blanc.</td></tr>';
echo '</table>';

echo '<div style="margin-top:18px">';
PluginSms123Envoi::afficherHistorique(10);
echo '</div>';

echo '</div>';

Html::footer();
