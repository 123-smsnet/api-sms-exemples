Module 123-SMS pour Dolibarr ERP/CRM
====================================

Envoi de SMS professionnels depuis Dolibarr via l'API HTTPS de
123-SMS.net (service francais depuis 2002, credits prepayes sans
abonnement ni date d'expiration). Licence MIT.

Contenu
-------
- Page « Outils > SMS 123-SMS » : envoi manuel (un ou plusieurs
  destinataires, numeros francais normalises automatiquement) ;
- Classe Sms123Api reutilisable dans vos triggers, crons et scripts :
    require_once DOL_DOCUMENT_ROOT.'/custom/sms123/class/sms123api.class.php';
    $code = Sms123Api::envoyer('0601020304', 'Votre commande est prete.');
- Permission dediee « Envoyer des SMS via 123-SMS » ;
- Proxy de l'instance respecte (client HTTP natif Dolibarr).

Installation (2 minutes)
------------------------
1. Decompressez l'archive dans htdocs/custom/  (vous devez obtenir
   htdocs/custom/sms123/) ;
2. Accueil > Configuration > Modules/Applications : activez
   « SMS 123-SMS.net » (famille Interfaces) ;
3. Cliquez l'icone de configuration du module : renseignez votre
   identifiant et votre cle API (transmis par e-mail a l'inscription
   sur 123-sms.net, ou lors de la regeneration de la cle — espace
   client > API) ;
4. Donnez la permission « Envoyer des SMS » aux utilisateurs concernes ;
5. Menu Outils > SMS 123-SMS : envoyez un SMS de test
   (la reponse s'affiche : 80 = envoye).

Compatibilite : squelette standard Dolibarr, developpe pour
Dolibarr 14 et plus (PHP 7+). Multi-entites supporte (configuration
par entite).

Envois automatiques (relance facture, commande prete...) : la classe
Sms123Api s'appelle en une ligne depuis vos triggers ; 123-SMS
developpe gratuitement des adaptations specifiques — contactez-nous.

Page et documentation :
https://www.123-sms.net/developpeurs-api-123-sms-pro-dolibarr.php
Support : contact@123-sms.net - 02 51 76 07 34
