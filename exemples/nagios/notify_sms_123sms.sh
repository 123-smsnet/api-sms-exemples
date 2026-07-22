#!/usr/bin/env bash
# =====================================================================
#  notify_sms_123sms.sh - Notifications Nagios / Icinga / Centreon
#  par SMS via l'API 123-SMS.net
#  Documentation : https://www.123-sms.net/developpeurs-api.php
#  (c) 123-SMS.net - licence MIT, reutilisation libre
# =====================================================================
#  Usage : notify_sms_123sms.sh <numero> <message>

EMAIL="votre-email@exemple.fr"   # e-mail du compte 123-SMS.net
CLE_API="XXXXXX"                 # cle API : espace client > API

NUMERO="${1:?Usage: $0 <numero> <message>}"
MESSAGE="${2:?Usage: $0 <numero> <message>}"

curl -sS --get "https://www.123-sms.net/http.php" \
  --data-urlencode "email=${EMAIL}" \
  --data-urlencode "pass=${CLE_API}" \
  --data-urlencode "numero=${NUMERO}" \
  --data-urlencode "message=${MESSAGE}" > /dev/null
