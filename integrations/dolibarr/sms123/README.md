Module 123-SMS pour Dolibarr ERP/CRM
====================================

Envoi de SMS professionnels depuis Dolibarr via l'API HTTPS de
123-SMS.net (service francais depuis 2002, credits prepayes sans
abonnement ni date d'expiration). Licence MIT.

Contenu
-------
- Page « Outils > SMS 123-SMS » : envoi manuel (un ou plusieurs
  destinataires, numeros francais normalises automatiquement) ;
- Envoi en masse depuis les listes de tiers et de contacts ;
- Classe Sms123Api reutilisable dans vos triggers, crons et scripts :
    dol_include_once('/sms123/class/sms123api.class.php');
    $code = Sms123Api::envoyer('0601020304', 'Votre commande est prete.');
- Permission dediee « Envoyer des SMS via 123-SMS » ;
- Interface en francais et en anglais (fr_FR, en_US) ;
- Proxy de l'instance respecte (client HTTP natif Dolibarr).

Cinq facons d'envoyer des SMS depuis Dolibarr
---------------------------------------------
1. A LA MAIN : menu Outils > SMS 123-SMS. La page affiche le solde du
   compte, envoie a un ou plusieurs destinataires, et conserve
   l'historique des envois (le formulaire se vide apres chaque envoi).
2. AUTOMATIQUEMENT, SANS CODE : dans la configuration du module,
   cochez les evenements qui doivent declencher un SMS (commande
   validee, facture validee, facture payee, expedition validee, devis
   valide), choisissez le destinataire (le client ou votre numero
   interne) et le message. Variables : {ref} {societe} {total} {date}
   {masociete}.
3. DEPUIS LES FICHES : un bouton « Envoyer un SMS » apparait sur les
   fiches tiers, contact, devis, commande, facture et expedition. Le
   numero et un message contextuel sont pre-remplis.
4. EN MASSE : sur la liste des tiers ou des contacts, filtrez, cochez
   les lignes puis choisissez l'action de masse « Envoyer un SMS
   (123-SMS) ». La page d'envoi en masse recapitule les destinataires
   joignables, signale ceux qui n'ont pas de numero, accepte des
   numeros libres en complement et propose un envoi a blanc. Les
   variables {societe} {contact} {masociete} personnalisent chaque
   message ; sans variable, les numeros sont regroupes par paquets de
   20 pour accelerer l'envoi. Limite : 500 destinataires par envoi.
   La page est aussi accessible par Outils > SMS 123-SMS > Envoi en masse.
5. SUR MESURE : une ligne dans vos scripts, triggers ou crons :
      dol_include_once('/sms123/class/sms123api.class.php');
      $code = Sms123Api::envoyer('0601020304', 'Bonjour !');

Rappels de rendez-vous et relances de factures (automatiques)
-------------------------------------------------------------
Le module fournit deux taches planifiees, desactivees par defaut :

- RAPPELS DE RENDEZ-VOUS : SMS envoye avant les evenements de l agenda
  dont le TYPE est coche dans la configuration (par exemple
  « Rendez-vous » uniquement). Le rappel part des que l evenement entre
  dans la fenetre choisie (24 h par defaut), une seule fois par
  evenement : la tache peut donc tourner toutes les heures comme trois
  fois par jour, aucun rendez-vous n est saute.

  ATTENTION AUX TYPES D EVENEMENTS : Dolibarr n affiche le champ
  « Type » sur les fiches d evenement QUE si l option « Utiliser les
  types d evenements » du module Agenda est activee (Accueil >
  Configuration > Modules > Agenda). Tant qu elle ne l est pas, tous
  les evenements sont du type « Autre » et un filtre par type ne
  selectionne rien. La configuration du module 123-SMS le signale et
  propose un lien direct vers cette option ; a defaut, cochez « Tous
  les types d evenements ».

  NUMERO UTILISE, dans cet ordre :
    1. contact lie a l evenement : Mobile, puis Personnel, puis Professionnel
    2. a defaut, tiers lie a l evenement : Mobile, puis Telephone
  Le premier champ renseigne gagne ; si aucun ne l est, l evenement est
  ignore. Le contact est cherche aussi bien sur le champ historique de
  l evenement que dans ses ressources (ou Dolibarr enregistre les
  contacts depuis la version 9).

  CAS PAR CAS : chaque fiche d evenement porte une case « Rappel SMS ».
  Elle est cochee d office quand le type de l evenement est concerne :
  la decocher ecarte ce rendez-vous precis. A l inverse, la cocher sur un
  evenement dont le type n est pas coche dans la configuration envoie
  quand meme le rappel. Tant que personne ne la modifie, la fiche suit
  la configuration : rien n est enregistre en base. Revenir a l etat
  d origine efface le choix particulier.

  Le bouton « Tester la selection » de la configuration affiche, sans
  rien envoyer, la liste exacte des evenements concernes en ce moment,
  le numero trouve pour chacun, le champ d ou il vient et son etat
  (sera envoye / deja rappele / aucun numero). C est le premier reflexe
  si un rappel n arrive pas.

CODES DE RETOUR DE L API
------------------------
Le module connait et traduit les 23 codes de l API 123-SMS (80 a 102) :
ils s affichent en clair dans l historique, dans les journaux et dans
l onglet « Aide » de la configuration.

Les trois qui valent SUCCES : 80 (message envoye), 81 (enregistre pour
un envoi differe) et 92 (test d envoi concluant). Le 92 est la reponse
normale d un envoi A BLANC : bouton « Simuler », « Tester la
connexion » ou case « envoi a blanc » de l envoi en masse. Il confirme
que la requete est valide, sans rien envoyer ni debiter.

Les plus utiles a connaitre : 82 identifiants invalides, 83 credit
insuffisant, 84 numero invalide, 91 doublon sous 24 h, 96 adresse IP
non autorisee, 97 Sender-ID non declare, 101 numero blackliste (STOP).
Le code ERR est propre au module : la requete n a pas atteint la
passerelle (reseau, pare-feu, proxy) ; la raison est affichee a cote.

RIEN NE PART : COMMENT VOIR CE QUI SE PASSE
-------------------------------------------
L onglet « Rappels et relances » de la configuration donne trois
niveaux de diagnostic, du plus general au plus fin :

1. ETAT DES TACHES PLANIFIEES : chaque tache du module y est listee
   avec « activee ou non », sa DERNIERE EXECUTION et son dernier compte
   rendu, lus directement dans la table des taches de Dolibarr. Si la
   tache est activee mais affiche « jamais executee », le probleme
   n est pas le module : le cron de Dolibarr n est pas en service cote
   serveur (ligne crontab appelant scripts/cron/cron_run_jobs.php).

2. TESTER LA SELECTION : quels evenements seraient traites maintenant,
   avec le numero trouve et l etat de chacun. Aucun appel reseau.

3. SIMULER MAINTENANT / EXECUTER MAINTENANT : joue la tache et affiche
   son journal complet, etape par etape (criteres retenus, nombre
   d evenements, numero utilise, message, code de reponse de l API).
   « Simuler » appelle reellement l API en mode essai : rien n est
   envoye ni debite, mais tout le chemin est parcouru.

Le meme journal est ecrit dans le syslog de Dolibarr (prefixe
« Sms123Cron : » et « Sms123Api::envoyer »), consultable dans
Accueil > Outils d administration > Fichiers de log.

Enfin, une tentative qui echoue AVANT d atteindre la passerelle (pas de
reseau, pare-feu, proxy) est desormais tracee dans l historique avec le
code ERR et la raison : plus aucun envoi ne disparait sans laisser de
trace.

  Variables : {date} {heure} {label} {societe} {masociete}.

- RELANCES DE FACTURES IMPAYEES : SMS aux clients dont la facture
  validee et non payee est echue depuis X jours, avec un intervalle
  minimum entre deux relances de la meme facture.
  Variables : {ref} {total} {date} {societe} {masociete}.

- ALERTE DE SOLDE BAS : lorsque le credit passe sous le seuil que vous
  fixez, le module previent par e-mail (et par SMS sur votre numero
  interne si vous le souhaitez), sans repeter l alerte plus d une fois
  tous les X jours.

Mise en service :
1. Configuration du module : activez la fonction, choisissez les types
   d evenements / les delais, adaptez les messages ;
2. Accueil > Configuration > Taches planifiees : activez les lignes
   « 123-SMS » (elles sont creees a l installation du module) ;
3. Le cron de Dolibarr doit lui-meme etre en service cote serveur
   (ligne crontab appelant scripts/cron/cron_run_jobs.php).

Chaque envoi automatique est trace dans l historique avec son origine
(rappel-rdv#123, relance-facture#456, masse, alerte-solde).

Accuses de reception
--------------------
Option « Demander les accuses de reception » (configuration, section
Options avancees) : chaque SMS part alors avec &refaccuse=o et la
passerelle rappelle ensuite votre Dolibarr pour indiquer si le message
a ete remis. Le statut (Remis / Non remis / En attente) apparait dans
une colonne supplementaire de l historique.

Deux conditions :
1. cochez l option dans la configuration du module ;
2. communiquez a 123-SMS l URL de retour affichee juste en dessous
   (documentation « Retour des accuses de reception par http »).

Cette URL repond TOUJOURS « OK » en HTTP 200, y compris a un appel sans
parametre : c est ce que 123-SMS verifie au moment de la declarer. Le
bouton « Verifier l URL de retour » de la configuration fait ce controle
depuis votre serveur et affiche la reponse obtenue : utilisez-le avant
de communiquer l URL.

L URL est publique par nature : elle n accepte que la mise a jour d un
envoi deja enregistre, ne cree jamais de donnee et ne declenche aucun
envoi. Une cle de securite facultative peut etre ajoutee : les appels
qui ne la portent pas sont ignores (ils recoivent quand meme « OK »,
sans quoi l URL ne serait pas declarable).

REPONSE HTTP 401 OU 403 AU TEST ?
Votre serveur web protege le dossier par un mot de passe (.htaccess,
authentification HTTP Basic) : PHP n est jamais execute, et 123-SMS
recevra la meme reponse. Il faut autoriser l acces au SEUL fichier
sms123ar.php.

Apache : dans htdocs/custom/sms123/.htaccess (ou dans le VirtualHost)
    <Files "sms123ar.php">
        Require all granted
        # ligne utile seulement si mod_access_compat est charge :
        Satisfy Any
    </Files>

Nginx : dans le bloc server
    location = /custom/sms123/sms123ar.php {
        auth_basic off;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock;   # adaptez
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

Rejouez ensuite « Verifier l URL de retour » : la reponse doit etre OK.
Ce fichier ne renvoie que le mot OK et ne lit aucune donnee : l ouvrir
n expose rien d autre.

Trace dans l agenda du client
-----------------------------
Option « Tracer les SMS dans l agenda du client » : chaque SMS lie a un
tiers cree un evenement sur sa fiche (libelle « SMS envoye au ... »,
texte complet en note privee). L historique client devient complet, et
le SMS se retrouve dans les rapports d activite habituels de Dolibarr.

Widget d accueil
----------------
Un widget « SMS 123-SMS.net » affiche le solde du compte et les
derniers envois des la connexion. Activez-le dans Accueil >
Configuration > Widgets. Le solde y est mis en cache 15 minutes : la
page d accueil ne declenche pas un appel API a chaque affichage.

Compteur de caracteres
----------------------
Le formulaire d envoi affiche en direct le nombre de caracteres et le
nombre de SMS correspondant, et signale les caracteres speciaux qui
font passer la limite de 160 a 70 caracteres.

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
   complet (12 fichiers) et n'a pas ete interrompu.

Mise a jour depuis une version anterieure
-----------------------------------------
Remplacez les fichiers, puis DESACTIVEZ et REACTIVEZ le module dans
Configuration > Modules. La reactivation ajoute a la table
d historique les colonnes des nouvelles fonctions (tiers, reference,
statut de remise), cree la tache planifiee d alerte de solde, le
widget d accueil et l entree de menu « Envoi en masse ». Vos reglages
(identifiant, cle API, declencheurs, rappels) sont conserves, ainsi
que l historique deja enregistre.

Tester la connexion
-------------------
L'ecran de configuration du module comporte un bouton
« Tester la connexion » : il effectue un envoi A BLANC (rien n'est
envoye, rien n'est debite) et affiche un diagnostic complet :
identifiants renseignes, extension cURL, proxy Dolibarr, jointure de
www.123-sms.net (code HTTP et temps de reponse), validite des
identifiants, et un verdict clair. C'est le premier reflexe en cas de
probleme d'envoi.

Erreur « appel API impossible (HTTP 400) » sur les versions < 1.2.0 :
corrigee. Mettez a jour le module (le corps de la requete etait
re-encode par le client HTTP de Dolibarr). La version 1.2.0 bascule
aussi automatiquement en GET si un pare-feu refuse le POST.

Erreur « IP is a local IP. Must be an external URL » ?
-----------------------------------------------------
Votre serveur heberge aussi 123-sms.net (ou son DNS resout le domaine
en adresse locale) : le garde-fou anti-SSRF de Dolibarr refuse alors
l'appel. Corrige a partir de la version 1.3.0 du module (les URL
locales sont explicitement autorisees pour cet appel, avec un repli
cURL direct). Mettez simplement le module a jour.

Le menu « Outils > SMS 123-SMS » n'apparait pas ?
------------------------------------------------
Les entrees de menu sont enregistrees en base au moment de l'activation
du module. Si vous avez mis a jour le module apres l'avoir active, ou si
le menu a ete cree avec une ancienne version :

1. Configuration > Modules : DESACTIVEZ le module, puis REACTIVEZ-le
   (les reglages identifiant / cle API sont conserves) ;
2. Verifiez que l'utilisateur a la permission « Envoyer des SMS via
   123-SMS » (Utilisateurs & groupes > onglet Permissions) — un
   administrateur l'a d'office ;
3. Le menu se trouve dans le menu du HAUT « Outils », colonne de
   gauche : il n'apparait qu'apres avoir clique sur Outils.

La page reste accessible en direct :
   https://votre-dolibarr/custom/sms123/sms123index.php

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

Compatibilite : Dolibarr 16 et plus, PHP 7.0 a 8.4.
Multi-entites supporte (configuration par entite).

Envois automatiques (relance facture, commande prete...) : la classe
Sms123Api s'appelle en une ligne depuis vos triggers ; 123-SMS
developpe gratuitement des adaptations specifiques — contactez-nous.

Page et documentation :
https://www.123-sms.net/developpeurs-api-123-sms-pro-dolibarr.php
Support : contact@123-sms.net - 02 51 76 07 34
