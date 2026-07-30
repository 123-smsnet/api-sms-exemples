# 123-SMS pour Jeedom

Envoyer des SMS depuis Jeedom par l'API 123-SMS : alertes de scénario,
notifications, messages à un proche. **Aucun matériel, aucune carte SIM,
aucun démon** — un simple appel HTTPS.

- **Licence** : MIT (réutilisation libre, y compris commerciale)
- **Compatibilité** : Jeedom 4.2 et plus
- **Dépendances** : aucune
- **Compte** : crédits prépayés, sans abonnement, sans date d'expiration
  ([inscription gratuite, 5 SMS offerts](https://www.123-sms.net/))

## Pourquoi ce plugin

Après l'extinction de la 2G, les box et transmetteurs qui envoyaient
leurs SMS par carte SIM ne partent plus. Ce plugin fait sortir l'alerte
par Internet : le destinataire, lui, n'a besoin que du réseau mobile —
et d'aucune application installée.

## Installation

1. **Plugins › Gestion des plugins › Market**, ou installation manuelle
   du dossier `sms123` dans `plugins/`.
2. Activez le plugin.
3. **Configuration** : renseignez l'**identifiant** et la **clé API**
   (transmis par e-mail à l'inscription, espace client › API), et
   éventuellement un **expéditeur** (Sender-ID, 11 caractères).
4. **Ajouter** un destinataire : un nom (Astreinte, Famille, Gardien…)
   et un ou plusieurs numéros de mobile.

## Les commandes créées

| Commande | Type | Usage |
|---|---|---|
| Envoyer un SMS | action / message | scénarios, centre de messages, notifications |
| Solde | info / numérique | nombre de SMS restants, rafraîchi toutes les 30 min |
| Rafraîchir le solde | action | mise à jour immédiate du solde |

La commande **Envoyer un SMS** étant de sous-type `message`, elle apparaît
partout où Jeedom propose d'envoyer un message : bloc « Message » d'un
scénario, alertes, notifications du système.

## Exemple de scénario

```
SI  binaire[Capteur fuite cuisine] == 1
ALORS  Message → Astreinte
       Titre   : ALERTE
       Message : fuite d'eau détectée dans la cuisine
```

Le titre et le message sont concaténés dans le SMS. Un SMS standard fait
160 caractères ; au-delà, le message est facturé en plusieurs crédits.

## Formats de numéro acceptés

`0601020304`, `33601020304`, `+33 6 01 02 03 04` — les espaces, points et
tirets sont ignorés. Plusieurs numéros se saisissent séparés par des
virgules : chacun reçoit le message.

## Mode test

La case **Envoi à blanc** de la configuration ajoute `test=o` à chaque
appel : l'API répond comme pour un envoi réel (code 92), mais rien n'est
envoyé ni débité. Parfait pour mettre au point un scénario.

## Journal

Chaque envoi est tracé dans le journal `sms123` (**Analyse › Logs**) avec
le code retour de l'API :

| Code | Signification |
|---|---|
| 80 | Le message a été envoyé |
| 81 | Enregistré pour un envoi en différé |
| 92 | Test d'envoi concluant (rien n'est envoyé ni débité) |
| 82 | Identifiant et/ou clé API invalides |
| 83 | Crédit insuffisant |
| 84 | Numéro de mobile invalide |
| 97 | Sender-ID invalide ou non déclaré |
| 101 | Numéro sur liste noire (désinscription STOP) |

Un envoi en échec lève une exception : le scénario s'arrête et l'erreur
est visible, plutôt que de disparaître silencieusement.

## Bon à savoir

- Avec un **Sender-ID**, le destinataire ne peut pas répondre au SMS.
- Pour les scénarios critiques (intrusion, coupure), doublez le SMS d'une
  alerte locale : l'envoi a besoin d'Internet pour sortir.
- Les crédits n'expirent pas : une réserve d'alerte reste disponible des
  années, sans abonnement à payer pour des alertes rares.

## Support

- Guide complet : <https://www.123-sms.net/envoyer-sms-jeedom.php>
- Code source : <https://github.com/123-smsnet/api-sms-exemples>
- Contact : <contact@123-sms.net>
