#!/usr/bin/env bash
# =====================================================================
#  Envoi de SMS via l'API 123-SMS.net  (bash + curl)
#  Documentation : https://www.123-sms.net/developpeurs-api.php
#  (c) 123-SMS.net - licence MIT, reutilisation libre
# =====================================================================

# --- Vos identifiants (espace client 123-SMS.net) --------------------
EMAIL="votre-email@exemple.fr"   # e-mail du compte
CLE_API="XXXXXX"                 # cle API : espace client > API

NUMERO="${1:?Usage: $0 <numero> <message>}"
MESSAGE="${2:?Usage: $0 <numero> <message>}"

curl -sS --get "https://www.123-sms.net/http.php" \
  --data-urlencode "email=${EMAIL}" \
  --data-urlencode "pass=${CLE_API}" \
  --data-urlencode "numero=${NUMERO}" \
  --data-urlencode "message=${MESSAGE}"
echo
