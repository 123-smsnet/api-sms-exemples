#!/usr/bin/env python3
# -*- coding: utf-8 -*-
#  123-sms_odoo.py - Envoyer des SMS aux contacts Odoo via 123-SMS.net
#  (c) 123-SMS.net - licence MIT, reutilisation libre
#
#  Fonctionne SANS installer de module dans Odoo (API XML-RPC standard),
#  y compris sur Odoo Online (SaaS). Python 3, aucune dependance.
#
#  1. Renseignez la configuration ci-dessous ;
#     - cle API Odoo : avatar > Mon profil > Securite du compte >
#       Cles API (Odoo 14+) ; sinon le mot de passe fonctionne aussi ;
#     - identifiant + cle API 123-SMS : transmis par e-mail a
#       l'inscription (espace client > API).
#  2. python3 123-sms_odoo.py
#     -> envoie MESSAGE a tous les contacts de l'etiquette ETIQUETTE
#        qui ont un numero de mobile.

import urllib.parse
import urllib.request
import xmlrpc.client

# --- Odoo ------------------------------------------------------------
ODOO_URL = "https://votre-societe.odoo.com"
ODOO_DB = "votre-societe"
ODOO_USER = "vous@societe.fr"
ODOO_CLE = "cle-api-odoo"

# --- 123-SMS ---------------------------------------------------------
SMS_IDENTIFIANT = "votre_identifiant"
SMS_CLEAPI = "CLEAPI"

# --- Envoi -----------------------------------------------------------
ETIQUETTE = "Clients SMS"   # etiquette de contact Odoo a cibler
MESSAGE = "Bonjour {nom}, ceci est un message de test 123-SMS."


def envoyer_sms(numero, message):
    """Appelle l'API 123-SMS et renvoie le code retour (80 = envoye)."""
    corps = urllib.parse.urlencode({
        "email": SMS_IDENTIFIANT,  # nom du parametre historique de l'API
        "pass": SMS_CLEAPI,
        "numero": numero,
        "message": message,
    }).encode()
    req = urllib.request.Request("https://www.123-sms.net/http.php", data=corps)
    with urllib.request.urlopen(req, timeout=15) as reponse:
        return reponse.read().decode().strip()


def normaliser(numero):
    """0601020304 -> 33601020304 ; espaces et points retires."""
    n = "".join(c for c in str(numero) if c.isdigit() or c == "+")
    if n.startswith("+"):
        n = n[1:]
    if n.startswith("00"):
        n = n[2:]
    if len(n) == 10 and n.startswith("0"):
        n = "33" + n[1:]
    return n


def main():
    commun = xmlrpc.client.ServerProxy(ODOO_URL + "/xmlrpc/2/common")
    uid = commun.authenticate(ODOO_DB, ODOO_USER, ODOO_CLE, {})
    if not uid:
        raise SystemExit("Connexion Odoo refusee : verifiez URL, base, utilisateur, cle.")
    modeles = xmlrpc.client.ServerProxy(ODOO_URL + "/xmlrpc/2/object")

    contacts = modeles.execute_kw(
        ODOO_DB, uid, ODOO_CLE,
        "res.partner", "search_read",
        [[["category_id.name", "=", ETIQUETTE], ["mobile", "!=", False]]],
        {"fields": ["name", "mobile"]})
    print(len(contacts), "contact(s) avec mobile dans l'etiquette", repr(ETIQUETTE))

    for c in contacts:
        code = envoyer_sms(normaliser(c["mobile"]), MESSAGE.format(nom=c["name"]))
        print("%-30s %-15s code %s%s" % (
            c["name"], c["mobile"], code, " (envoye)" if code == "80" else ""))


if __name__ == "__main__":
    main()
