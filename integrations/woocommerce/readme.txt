=== 123-SMS pour WooCommerce ===
Contributors: 123smsnet
Tags: sms, woocommerce, notification, commande, france
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Notifications SMS de commande WooCommerce via 123-SMS.net, service français d'envoi de SMS professionnels depuis 2002.

== Description ==

Recevez un SMS à chaque commande et tenez vos clients informés par SMS, sans abonnement : des crédits prépayés qui n'expirent jamais.

* **SMS au marchand** à chaque commande confirmée ;
* **SMS au client** à la confirmation et/ou à l'expédition (optionnels) ;
* Modèles personnalisables avec variables : `{numero}` `{prenom}` `{nom}` `{total}` `{boutique}` ;
* Code retour API tracé dans les notes de chaque commande (traçabilité complète) ;
* Sender-ID personnalisable (nom de votre boutique en expéditeur) ;
* Compatible HPOS (stockage haute performance des commandes) ;
* Aucune dépendance : une seule requête HTTPS via l'API WordPress.

= Service externe =

Ce plugin s'appuie sur le service d'envoi de SMS **123-SMS.net** — service français édité par DRANER.COM et facturé par CLIC-EVENT.com SARL, hébergé en France et en Allemagne (OVH / Hetzner). Un compte 123-SMS.net est nécessaire (inscription gratuite, 5 SMS offerts).

À chaque envoi de SMS, le plugin transmet en HTTPS à `https://www.123-sms.net/http.php` : l'identifiant du compte, la clé API, le numéro du destinataire, le message et le sender-ID éventuel. Aucune autre donnée n'est transmise, et rien n'est envoyé tant que le plugin n'est pas configuré.

* Site et tarifs : [123-sms.net](https://www.123-sms.net/)
* Règles d'usage et réglementation SMS : [reglementation-loi-sms-mailing.php](https://www.123-sms.net/reglementation-loi-sms-mailing.php)

= RGPD =

Les SMS envoyés par ce plugin sont transactionnels : liés à l'exécution de la commande du client (base légale : exécution du contrat). N'utilisez pas le téléphone de commande pour de la prospection sans consentement explicite.

== Installation ==

1. Installez et activez le plugin (WooCommerce doit être actif) ;
2. Menu **WooCommerce > SMS 123-SMS.net** ;
3. Renseignez l'identifiant du compte et la clé API (transmis à l'inscription sur 123-SMS.net, rubrique API de l'espace client) ;
4. Cochez les notifications souhaitées et enregistrez ;
5. Passez une commande test : le code retour apparaît dans les notes de la commande (80 = envoyé).

== Frequently Asked Questions ==

= Faut-il un abonnement ? =

Non. 123-SMS.net fonctionne par crédits prépayés, sans abonnement ni frais fixes, et les crédits n'expirent jamais. L'inscription est gratuite avec 5 SMS offerts pour tester.

= Quels numéros sont pris en charge ? =

Les mobiles français (06/07, normalisés automatiquement en 336/337) et les numéros internationaux au format 00 ou +.

= Le client reçoit-il un SMS publicitaire ? =

Non. Le plugin n'envoie que des SMS transactionnels liés à la commande (confirmation, expédition), avec le texte que vous définissez.

= Où voir si un SMS est parti ? =

Dans les notes de la commande WooCommerce : chaque envoi y laisse le code retour de l'API (80 = envoyé ; les autres codes sont documentés sur 123-sms.net).

== Screenshots ==

1. Écran de réglages : identifiant, clé API, sender-ID et modèles de SMS.
2. Code retour API dans les notes de commande.

== Changelog ==

= 1.1.0 =
* Internationalisation complète (text domain 123-sms-for-woocommerce).
* Désinstallation propre (suppression des réglages).
* Conformité annuaire WordPress.org (en-têtes, divulgation du service externe).

= 1.0.0 =
* Version initiale : SMS marchand + client, modèles, traçabilité, HPOS.

== Upgrade Notice ==

= 1.1.0 =
Mise en conformité WordPress.org ; aucun changement de comportement.
