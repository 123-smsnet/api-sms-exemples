# 123-SMS pour GLPI

Envoyer des SMS depuis GLPI : alerte de l'astreinte à la création d'un
ticket, information du demandeur à la résolution, et envoi manuel depuis
le menu **Outils**.

- **Licence** : MIT (réutilisation libre, y compris commerciale)
- **Compatibilité** : GLPI 10.0 et 11.0
- **Dépendances** : aucune — un simple appel HTTPS vers l'API 123-SMS
- **Compte** : crédits prépayés, sans abonnement, sans date d'expiration
  ([inscription gratuite, 5 SMS offerts](https://www.123-sms.net/))

## Ce que fait le plugin

| Déclencheur | Destinataire | Réglable |
|---|---|---|
| Création d'un ticket | les numéros d'astreinte | seuil d'urgence, modèle du message |
| Ticket résolu ou clos | le mobile du demandeur | activable séparément, modèle dédié |
| Envoi manuel | jusqu'à 200 numéros | page **Outils › Envoyer un SMS** |

Chaque envoi est historisé : date, numéro, message, origine, ticket lié,
code retour de l'API et son explication en clair.

## Installation

1. Décompressez l'archive dans le dossier `plugins/` de GLPI — vous devez
   obtenir `plugins/sms123/setup.php`.
2. **Configuration › Plugins** : installez puis activez « 123-SMS ».
3. Cliquez sur la roue dentée pour ouvrir la configuration.
4. Renseignez l'**identifiant** et la **clé API** (transmis par e-mail à
   l'inscription, espace client › API), puis **Tester la connexion** :
   le solde du compte s'affiche.
5. Cochez **Activer les alertes**, saisissez les numéros d'astreinte et
   choisissez le seuil d'urgence.

Le droit `plugin_sms123` est créé à l'installation et accordé aux profils
qui peuvent modifier la configuration. Ajoutez-le aux autres profils
depuis **Administration › Profils** pour leur ouvrir l'envoi manuel.

## Variables des modèles

`{id}` `{titre}` `{urgence}` `{demandeur}` `{entite}` `{categorie}`

Exemple : `Ticket #{id} ({urgence}) : {titre} - {demandeur}`

Le message est tronqué à 160 caractères, la longueur d'un SMS standard.

## Vérifier qu'un SMS est parti

La configuration et la page d'envoi affichent l'historique avec le code
retour : **80** = message parti, **92** = envoi à blanc concluant (mode
test). Les échecs sont aussi tracés dans le journal GLPI `sms123`.

Codes les plus fréquents :

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

## Mode test

La case **Envoi à blanc** ajoute `test=o` à chaque appel : l'API répond
comme pour un envoi réel (code 92), mais rien n'est envoyé ni débité.
Idéal pour valider une configuration sans consommer de crédits.

## RGPD

Les SMS envoyés par ce plugin sont des messages de service liés à
l'exploitation (astreinte, suivi de ticket). Informez les personnes
concernées de l'usage de leur numéro et offrez-leur un moyen simple de
s'y opposer. N'utilisez pas ces numéros pour de la prospection.

## Désinstallation

**Configuration › Plugins › 123-SMS › Désinstaller** : la table
d'historique, les réglages et le droit sont supprimés.

## Support

- Guide complet : <https://www.123-sms.net/developpeurs-api-123-sms-pro-glpi.php>
- Code source : <https://github.com/123-smsnet/api-sms-exemples>
- Contact : <contact@123-sms.net>
