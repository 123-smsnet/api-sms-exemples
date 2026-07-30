# Plugin 123-SMS

Envoi de SMS depuis Jeedom par l'API **123-SMS.net**, sans matériel, sans
carte SIM et sans démon : un simple appel HTTPS.

Utile quand les notifications d'application ne suffisent pas — pour
prévenir un proche non équipé, un gardien, une astreinte — et pour les
box qui n'émettent plus depuis l'extinction de la 2G.

# Configuration du plugin

Après activation, ouvrez la **Configuration** du plugin :

- **Identifiant du compte** et **Clé API** : transmis par e-mail à
  l'inscription sur [123-sms.net](https://www.123-sms.net/), rubrique
  *espace client › API*.
- **Expéditeur (Sender-ID)** : facultatif, 11 caractères, affiche le nom
  de votre marque à la place du numéro. Attention : le destinataire ne
  peut alors pas répondre au SMS.
- **Envoi à blanc (mode test)** : l'API répond comme pour un envoi réel
  (code 92) mais rien n'est envoyé ni débité.

# Configuration des équipements

Un équipement = un destinataire (ou un groupe de destinataires).

- **Nom du destinataire** : Astreinte, Famille, Gardien…
- **Numéro(s) de mobile** : un ou plusieurs numéros séparés par des
  virgules. Formats acceptés : `0601020304`, `33601020304`,
  `+33 6 01 02 03 04`.

Trois commandes sont créées automatiquement :

| Commande | Type | Usage |
|---|---|---|
| Envoyer un SMS | action / message | scénarios, centre de messages, notifications |
| Solde | info / numérique | SMS restants, rafraîchi toutes les 30 minutes |
| Rafraîchir le solde | action | mise à jour immédiate |

# Utilisation dans un scénario

Ajoutez un bloc **Message**, choisissez l'équipement 123-SMS comme
destinataire, puis renseignez le titre et le message. Les deux sont
concaténés dans le SMS.

```
SI  binaire[Capteur fuite cuisine] == 1
ALORS  Message → Astreinte
       Titre   : ALERTE
       Message : fuite d'eau détectée dans la cuisine
```

Un SMS standard fait 160 caractères ; au-delà, plusieurs crédits sont
consommés. Les emoji font tomber la limite à 70 caractères.

# Journal et codes retour

Chaque envoi est tracé dans le journal `sms123` (**Analyse › Logs**) :

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
est visible.

# FAQ

**Faut-il un abonnement ?**
Non : des crédits prépayés, de 0,23 € à 0,075 € HT le SMS selon le
volume, sans date d'expiration. L'inscription offre 5 SMS pour tester.

**Que se passe-t-il si Internet est coupé ?**
L'envoi ne peut pas sortir. Pour les scénarios critiques, doublez le SMS
d'une alerte locale (sirène). Côté destinataire, en revanche, seul le
réseau mobile est nécessaire.

**Peut-on prévenir plusieurs personnes ?**
Oui : plusieurs numéros dans le même équipement, ou plusieurs
équipements appelés par le scénario.
