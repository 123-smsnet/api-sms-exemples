<?PHP
# SMSViaCurl
# Script d'envoi de SMS par www.123-Sms.net
# Copyright (C) 2007 / 123-Sms.net Last Update 03/2020
# Licence MIT : reutilisation, modification et redistribution libres,
# y compris dans un logiciel commercial. Fourni sans garantie.
// initialisation des variables
$requete = '';
$param['email'] = 'monemail@mondomaine.com'; // login d'inscription à www.123-sms.net
$param['pass'] = 'MOT2PASSE'; // mot de passe envoyé par www.123-sms.net ou personnalisé.
$param['message'] = 'Ceci est un message de test *** \'éèê$sage .'; // message que l'on désire envoyer
// *** (3 étoiles pour retour chariot)
$param['numero'] = '33611223344-0660616263'; 
// numéros de téléphones auxquels on envoie le message	(les numéros st séprarés par un tiret '-'); 336 OU 06 pour envoi vers la France
$param['from'] = 'monemail@mondomaine.com'; // expediteur (login d'inscription à www.123-sms.net)
// construction de la requete
foreach($param as $clef => $valeur) // pour chaque champ
{

  $requete .= $clef . '=' . urlencode($valeur); // il faut bien formater les valeurs
  $requete .= '&';
}

  // url d'accès à la passerelle
  $url = "https://www.123-sms.net/http.php";
  // initialisation curl
  $ch = curl_init();
  // parametres
  curl_setopt($ch, CURLOPT_URL, $url); // url
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // retourne une variable
                                               // au lieu de l'afficher directement
  curl_setopt($ch, CURLOPT_POST, 1); // active la méthode POST
  curl_setopt($ch, CURLOPT_POSTFIELDS, $requete); // requete
  // execute la connexion CURL
  $reponse = curl_exec($ch);
  // fermeture de la connexion
  curl_close($ch);
  // affichage de la réponse
  echo '<h1>CURL</h1><p>', htmlentities($reponse), "</p>";

  unset($reponse);
 ?>
