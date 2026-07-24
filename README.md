# 123-SMS.net — Exemples officiels de l'API SMS

Exemples d'envoi de SMS professionnels via l'API HTTPS de
[123-SMS.net](https://www.123-sms.net/) — service français édité par
DRANER.COM depuis 2002 (facturation : CLIC-EVENT.com SARL). Crédits prépayés **sans abonnement ni date
d'expiration**, accusés de réception et réponses par HTTP.

## L'API en une ligne

```
POST https://www.123-sms.net/http.php
     email=...&pass=...&numero=33601020304&message=Bonjour
```

| Paramètre | Rôle |
|---|---|
| `email` | votre identifiant 123-SMS (transmis par e-mail à l'inscription) |
| `pass` | clé API (espace client → rubrique API) |
| `numero` | destinataire(s) — `33601020304`, plusieurs séparés par `-` |
| `message` | texte du SMS (160 caractères GSM par SMS) |
| `sender` *(optionnel)* | Sender-ID déclaré |
| `refaccuse` *(optionnel)* | référence pour l'accusé de réception par HTTP |

Réponse : un code texte — `80` envoyé · `81` envoi différé enregistré ·
`82` identifiants invalides · `83` crédit insuffisant · `84` numéro
invalide ([liste complète](https://www.123-sms.net/developpeurs-api.php)).

## Exemples par langage

| Langage | Dossier | Dépendances |
|---|---|---|
| Python 3 | [`exemples/python`](exemples/python) | aucune (urllib) |
| Node.js 18+ | [`exemples/nodejs`](exemples/nodejs) | aucune (fetch natif) |
| TypeScript | [`exemples/typescript`](exemples/typescript) | aucune (Node 18+/Deno/Bun) |
| PHP | [`exemples/php`](exemples/php) | aucune (file_get_contents) |
| PHP cURL | [`exemples/php-curl`](exemples/php-curl) | ext-curl |
| Java 11+ | [`exemples/java`](exemples/java) | aucune (java.net.http) |
| Kotlin | [`exemples/kotlin`](exemples/kotlin) | aucune (JDK 11+) |
| C# / .NET | [`exemples/csharp`](exemples/csharp) | aucune (HttpClient) |
| Rust | [`exemples/rust`](exemples/rust) | ureq |
| PowerShell | [`exemples/powershell`](exemples/powershell) | aucune |
| Bash | [`exemples/bash`](exemples/bash) | curl |
| VBA / Excel | [`exemples/vba-excel`](exemples/vba-excel) | Excel 2013+ (Windows) |
| Nagios / Icinga / Centreon | [`exemples/nagios`](exemples/nagios) | curl |

Chaque exemple : renseignez `EMAIL` / `CLE_API` en tête de fichier, puis
exécutez. La [spécification OpenAPI](openapi/123-sms_openapi.yaml) et la
[collection Postman](openapi/123-sms_postman_collection.json) sont dans
[`openapi/`](openapi).

## Modules et intégrations

- **Dolibarr ERP/CRM** : [`integrations/dolibarr`](integrations/dolibarr) — module dédié
  (page d'envoi + classe `Sms123Api` pour vos triggers et crons)
- **Odoo** : [`integrations/odoo`](integrations/odoo) — module installable (17.0/18.0 :
  assistant d'envoi + API pour vos actions automatisées) + script XML-RPC pour Odoo Online (SaaS)
- **WooCommerce** : [`integrations/woocommerce`](integrations/woocommerce) — plugin de
  notifications SMS de commande ([guide](https://www.123-sms.net/developpeurs-api-123-sms-pro-woocommerce.php))
- **PrestaShop** : [`integrations/prestashop`](integrations/prestashop) — module sms123
  (PrestaShop 1.7 à 8, [guide](https://www.123-sms.net/developpeurs-api-123-sms-pro-prestashop.php))
- Sans code : [Make](https://www.123-sms.net/envoyer-sms-make.php) ·
  [Zapier](https://www.123-sms.net/envoyer-sms-zapier.php) ·
  [n8n](https://www.123-sms.net/envoyer-sms-n8n.php) ·
  [Home Assistant](https://www.123-sms.net/envoyer-sms-home-assistant.php) ·
  [e-mail vers SMS](https://www.123-sms.net/envoyer-sms-par-email.php)

## Bonnes pratiques

- Appelez toujours l'API en **HTTPS** ;
- Ne committez jamais votre clé API : variables d'environnement ou
  gestionnaire de secrets (la clé se régénère en un clic dans l'espace
  client) ;
- N'embarquez pas la clé dans une application distribuée au public
  (mobile, front-end) : faites transiter l'envoi par votre serveur.

## Compte et support

- Inscription et essai gratuit : <https://www.123-sms.net/>
- Documentation développeurs : <https://www.123-sms.net/developpeurs-api.php>
- Support : <contact@123-sms.net> — 02 51 76 07 34

Licence : [MIT](LICENSE) — réutilisation libre, y compris dans un logiciel commercial.
