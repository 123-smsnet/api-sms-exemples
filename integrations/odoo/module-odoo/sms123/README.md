# 123-SMS — Envoi de SMS pour Odoo

Module Odoo (17.0/18.0) d'envoi de SMS via [123-SMS.net](https://www.123-sms.net),
service français depuis 2002. Crédits prépayés sans abonnement ni expiration.

- Assistant d'envoi depuis les contacts (Action > Envoyer un SMS, numéro prérempli) ;
- API réutilisable dans vos actions automatisées et crons :
  `self.env['sms123.api'].envoyer('0601020304', 'Bonjour !')` (80 = envoyé) ;
- Réglages : Configuration > Paramètres généraux > 123-SMS (identifiant + clé API,
  transmis à l'inscription gratuite — 5 SMS offerts).

Odoo Online (SaaS) n'accepte pas les modules tiers : utilisez le script XML-RPC
fourni sur https://www.123-sms.net/developpeurs-api-123-sms-pro-odoo.php

Licence MIT — code source : github.com/123-smsnet/api-sms-exemples
Support : contact@123-sms.net
