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
    dol_include_once('/sms123/class/sms123api.class.php');
    $code = Sms123Api::envoyer('0601020304', 'Votre commande est prete.');
- Permission dediee « Envoyer des SMS via 123-SMS » ;
- Proxy de l'instance respecte (client HTTP natif Dolibarr).

Installation
------------

ETAPE 1 (a ne pas sauter) : autoriser le repertoire des modules
externes dans conf.php.

Dolibarr n'affiche AUCUN module du dossier custom/ tant que ce
repertoire n'est pas declare. Ouvrez htdocs/conf/conf.php et
verifiez que ces deux lignes existent et ne sont PAS commentees
(pas de // devant) :

    $dolibarr_main_url_root_alt = '/custom';
    $dolibarr_main_document_root_alt = '/chemin/vers/dolibarr/htdocs/custom';

- Sur beaucoup d'installations ces lignes existent deja, commentees :
  il suffit de retirer les // du debut.
- Remplacez le chemin par le chemin reel de votre installation
  (exemple : /var/www/dolibarr/htdocs/custom).
- Si le dossier htdocs/custom/ n'existe pas, creez-le (droits en
  ecriture pour le serveur web).
- Enregistrez, puis rechargez la page des modules dans Dolibarr.

ETAPE 2 : decompressez l'archive dans htdocs/custom/
Vous devez obtenir htdocs/custom/sms123/ contenant
core/modules/modSms123.class.php (si vous obtenez
htdocs/custom/sms123/sms123/..., remontez d'un niveau).

ETAPE 3 : Accueil > Configuration > Modules/Applications, onglet
« Interfaces avec systemes externes » (ou recherchez « SMS ») :
activez « SMS 123-SMS.net ».

ETAPE 4 : cliquez l'icone de configuration du module et renseignez
votre identifiant et votre cle API (transmis par e-mail a
l'inscription sur 123-sms.net, ou lors de la regeneration de la
cle — espace client > API).

ETAPE 5 : donnez la permission « Envoyer des SMS via 123-SMS » aux
utilisateurs concernes (Utilisateurs & groupes > onglet Permissions).

ETAPE 6 : menu Outils > SMS 123-SMS : envoyez un SMS de test
(la reponse s'affiche : 80 = envoye).

Le module n'apparait pas dans la liste ?
----------------------------------------
1. conf.php : les deux lignes de l'etape 1 sont-elles bien actives
   (sans //) et le chemin est-il correct ? C'est la cause n°1.
2. Arborescence : le fichier
   htdocs/custom/sms123/core/modules/modSms123.class.php
   doit exister exactement a cet emplacement.
3. Droits : le serveur web (www-data, apache...) doit pouvoir LIRE
   le dossier sms123 et son contenu.
4. Cache : Accueil > Configuration > Divers > « Purger le cache »,
   ou supprimez le contenu de documents/admin/temp/, puis rechargez.
5. Hebergement mutualise : verifiez que le transfert FTP est
   complet (6 fichiers) et n'a pas ete interrompu.

La roue crantee (configuration) renvoie une erreur 404 ?
-------------------------------------------------------
L'URL affichee est /sms123/admin/setup.php au lieu de
/custom/sms123/admin/setup.php. Cause habituelle : dans conf.php, la
racine URL des modules externes est vide alors que la racine fichiers
est correcte. Dolibarr trouve donc le module (il s'active) mais ne
sait pas construire son URL.

1. Dans htdocs/conf/conf.php, verifiez que ces DEUX lignes sont
   presentes ET coherentes :
      $dolibarr_main_url_root_alt = '/custom';
      $dolibarr_main_document_root_alt = '/chemin/vers/htdocs/custom';
2. Verifiez qu'aucune de ces variables n'est REDEFINIE plus bas dans
   le fichier (les installations automatisees ajoutent parfois un
   second bloc qui ecrase le premier, avec une valeur vide) :
      grep -n "url_root" htdocs/conf/conf.php
   Ne gardez qu'une seule definition de chaque.
3. Rechargez la page des modules.

En attendant, la page de configuration reste accessible en direct :
   https://votre-dolibarr/custom/sms123/admin/setup.php

Conseil de transfert : dezippez l'archive sur votre poste PUIS envoyez
le dossier sms123 par FTP (ou envoyez le zip et dezippez-le sur le
serveur). Envoyer le zip sans le decompresser, ou un transfert
interrompu, laisse une arborescence vide : le module reste invisible.

Compatibilite : Dolibarr 14 et plus, PHP 7.0 a 8.4.
Multi-entites supporte (configuration par entite).

Envois automatiques (relance facture, commande prete...) : la classe
Sms123Api s'appelle en une ligne depuis vos triggers ; 123-SMS
developpe gratuitement des adaptations specifiques — contactez-nous.

Page et documentation :
https://www.123-sms.net/developpeurs-api-123-sms-pro-dolibarr.php
Support : contact@123-sms.net - 02 51 76 07 34
