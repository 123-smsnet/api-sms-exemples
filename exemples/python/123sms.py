#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# =====================================================================
#  Envoi de SMS via l'API 123-SMS.net  (Python 3, sans dependance)
#  Documentation : https://www.123-sms.net/developpeurs-api.php
#  (c) 123-SMS.net - licence MIT, reutilisation libre
# =====================================================================
import urllib.parse
import urllib.request

API_URL = "https://www.123-sms.net/http.php"

# --- Vos identifiants (espace client 123-SMS.net) --------------------
EMAIL   = "votre-email@exemple.fr"   # e-mail du compte
CLE_API = "XXXXXX"                   # cle API : espace client > API > Generer Cle API


def envoyer_sms(numero: str, message: str) -> str:
    """Envoie un SMS et retourne le code reponse de l'API."""
    params = urllib.parse.urlencode({
        "email": EMAIL,
        "pass": CLE_API,
        "numero": numero,
        "message": message,
    })
    with urllib.request.urlopen(API_URL + "?" + params, timeout=15) as reponse:
        return reponse.read().decode("utf-8", "replace").strip()


if __name__ == "__main__":
    code = envoyer_sms("0612345678", "Test 123-SMS depuis Python !")
    print("Reponse de l'API :", code)
    # Le detail des codes reponse figure dans la documentation technique :
    # https://www.123-sms.net/documentations.php
