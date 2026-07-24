# -*- coding: utf-8 -*-
from odoo import fields, models


class ResConfigSettings(models.TransientModel):
    _inherit = "res.config.settings"

    sms123_identifiant = fields.Char(
        string="Identifiant 123-SMS",
        config_parameter="sms123.identifiant",
        help="Transmis par e-mail à l'inscription sur 123-sms.net "
             "(espace client > API).")
    sms123_cleapi = fields.Char(
        string="Clé API 123-SMS",
        config_parameter="sms123.cleapi")
    sms123_sender = fields.Char(
        string="Sender-ID (optionnel)",
        config_parameter="sms123.sender",
        help="Nom d'expéditeur personnalisé (11 caractères max), "
             "à déclarer auprès de 123-SMS.")
