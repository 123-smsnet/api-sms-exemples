# -*- coding: utf-8 -*-
# Licence MIT : réutilisation libre (github.com/123-smsnet/api-sms-exemples)
import logging
import re

import requests

from odoo import _, api, models
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)

URL_API = "https://www.123-sms.net/http.php"

LIBELLES = {
    "80": "Le message a été envoyé.",
    "81": "Enregistré pour un envoi en différé.",
    "82": "Identifiant et/ou clé API invalides.",
    "83": "Crédit insuffisant : rechargez votre compte.",
    "84": "Numéro de mobile invalide.",
    "91": "Doublon : même message déjà envoyé à ce numéro sous 24 h.",
    "97": "Sender-ID invalide ou non déclaré.",
}


class Sms123Api(models.AbstractModel):
    """Appel de l'API 123-SMS.net — réutilisable partout :
    self.env['sms123.api'].envoyer('0601020304', 'Bonjour !')"""

    _name = "sms123.api"
    _description = "API 123-SMS.net"

    @api.model
    def normaliser(self, numero):
        """06/07 -> 336/337, sans espaces ; tirets = destinataires multiples."""
        parts = []
        for n in str(numero or "").split("-"):
            n = re.sub(r"[^0-9+]", "", n)
            if n.startswith("+"):
                n = n[1:]
            if n.startswith("00"):
                n = n[2:]
            if len(n) == 10 and n.startswith("0"):
                n = "33" + n[1:]
            if n:
                parts.append(n)
        return "-".join(parts)

    @api.model
    def envoyer(self, numero, message):
        """Envoie un SMS et renvoie le code retour API (80 = envoyé)."""
        get_param = self.env["ir.config_parameter"].sudo().get_param
        identifiant = get_param("sms123.identifiant")
        cleapi = get_param("sms123.cleapi")
        if not identifiant or not cleapi:
            raise UserError(_(
                "Renseignez l'identifiant et la clé API 123-SMS "
                "(Configuration > Paramètres généraux > 123-SMS). "
                "Ils sont transmis par e-mail à l'inscription sur 123-sms.net."))
        champs = {
            "email": identifiant,  # nom du paramètre historique de l'API
            "pass": cleapi,
            "numero": self.normaliser(numero),
            "message": message,
        }
        sender = get_param("sms123.sender")
        if sender:
            champs["sender"] = sender
        try:
            reponse = requests.post(URL_API, data=champs, timeout=15)
            reponse.raise_for_status()
        except requests.RequestException as exc:
            _logger.warning("123-SMS : appel API impossible (%s)", exc)
            raise UserError(_("Appel de l'API 123-SMS impossible : %s") % exc)
        code = reponse.text.strip()
        _logger.info("123-SMS : envoi vers %s -> code %s", champs["numero"], code)
        return code

    @api.model
    def libelle(self, code):
        return LIBELLES.get(code, _("Code retour : %s") % code)
