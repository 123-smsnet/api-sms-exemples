# Publier ce dépôt sur GitHub (10 minutes, une seule fois)

Le dépôt est prêt : code committé, README, licence MIT, .gitignore.

## 1. Créer le compte / l'organisation

- https://github.com/signup avec contact@123-sms.net ;
- (Recommandé) créez une **organisation** « 123-SMS-net » plutôt qu'un
  compte personnel : Settings > Organizations > New organization (plan
  Free suffit). C'est plus crédible et transmissible.

## 2. Créer le dépôt

- New repository, propriétaire : 123-SMS-net ;
- Nom suggéré : **api-sms-exemples** ;
- Description : « Exemples officiels de l'API SMS 123-SMS.net —
  13 langages, OpenAPI, Postman » ;
- Public, SANS cocher « Add a README » (le nôtre existe déjà) ;
- Website : https://www.123-sms.net/developpeurs-api.php ;
- Topics (après création, roue dentée) : sms, api, france, php, python…

## 3. Pousser le code

Depuis ce dossier (github-123-sms) :

```bash
git remote add origin https://github.com/123-SMS-net/api-sms-exemples.git
git push -u origin main
```

(GitHub demandera vos identifiants — un Personal Access Token fait
office de mot de passe : Settings > Developer settings > Tokens.)

## 4. Après publication

Donnez l'URL du dépôt à Claude : il ajoutera le lien GitHub sur le hub
developpeurs-api.php, dans llms.txt et sur les pages langage (badge
« Voir sur GitHub »). C'est un signal fort pour les développeurs ET un
backlink durable.

## Mise à jour future

Toute modification d'un exemple se fait dans les ZIP de dev-api/ puis
se répercute ici (le dépôt est reconstruit depuis les archives — source
de vérité unique). Demandez simplement à Claude de « resynchroniser le
dépôt GitHub ».
