<?
# SMSViaFileGetsContents
# Script d'envoi de SMS par www.123-Sms.net
# Copyright (C) 2007 / 123-Sms.net
# Licence MIT : reutilisation, modification et redistribution libres,
# y compris dans un logiciel commercial. Fourni sans garantie.
// initialisation des variables
$requete = '';
$param['email'] = 'votre_identifiant'; // login d'inscription � www.123-sms.net
$param['pass'] = 'MOT2PASSE'; // mot de passe envoy� par www.123-sms.net ou personnalis�.
$param['message'] = 'Ceci est un message de test *** \'���$sage .'; // message que l'on d�sire envoyer
// *** (3 �toiles pour retour chariot)
$param['numero'] = '33611223344-0660616263'; 
// num�ros de t�l�phones auxquels on envoie le message (les num�ros st s�prar�s par un tiret '-'); 336 OU 06 pour envoi vers la France
$param['from'] = 'votre_identifiant'; // expediteur (login d'inscription � www.123-sms.net)
// construction de la requete
foreach($param as $clef => $valeur) // pour chaque champ
{

$requete .= $clef . '=' . urlencode($valeur); // il faut bien formater les valeurs
$requete .= '&';
}
// url d'acc�s � la passerelle
$url = "https://www.123-sms.net/http.php";
// connexion
$reponse = file_get_contents($url . '?' . $requete);
// affichage de la r�ponse
echo '<h1>file_get_contents</h1><p>', htmlentities($reponse), "</p>";
 ?>