# -*- coding: utf-8 -*-
from odoo import _, api, fields, models


class Sms123SendWizard(models.TransientModel):
    _name = "sms123.send.wizard"
    _description = "Envoyer un SMS via 123-SMS"

    numero = fields.Char(
        string="Destinataire(s)", required=True,
        help="0601020304 ou 33601020304 ; plusieurs numéros séparés par des tirets.")
    message = fields.Text(string="Message", required=True)

    @api.model
    def default_get(self, fields_list):
        res = super().default_get(fields_list)
        if self.env.context.get("active_model") == "res.partner":
            partner = self.env["res.partner"].browse(
                self.env.context.get("active_id"))
            if partner.exists() and (partner.mobile or partner.phone):
                res.setdefault("numero", partner.mobile or partner.phone)
        return res

    def action_envoyer(self):
        self.ensure_one()
        code = self.env["sms123.api"].envoyer(self.numero, self.message)
        ok = code in ("80", "81")
        return {
            "type": "ir.actions.client",
            "tag": "display_notification",
            "params": {
                "title": _("123-SMS"),
                "message": self.env["sms123.api"].libelle(code),
                "type": "success" if ok else "danger",
                "sticky": not ok,
            },
        }
