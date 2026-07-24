# -*- coding: utf-8 -*-
{
    "name": "123-SMS - Envoi de SMS",
    "summary": "Envoyez des SMS via 123-SMS.net : relances, rappels, alertes - "
               "credits prepayes sans abonnement (service francais depuis 2002).",
    "description": """
Envoi de SMS professionnels via 123-SMS.net
===========================================
- Assistant d'envoi accessible depuis les contacts (numero prerempli) ;
- Classe d'API reutilisable dans vos actions automatisees et crons :
  self.env['sms123.api'].envoyer(numero, message) ;
- Reglages dans Configuration (identifiant, cle API, sender-ID) ;
- Codes retour traces dans le journal Odoo.

Credits prepayes sans abonnement ni date d'expiration.
Inscription gratuite (5 SMS offerts) sur https://www.123-sms.net
""",
    "author": "123-SMS.net (CLIC-EVENT)",
    "website": "https://www.123-sms.net/developpeurs-api-123-sms-pro-odoo.php",
    "category": "Marketing",
    "version": "17.0.1.0.1",
    "license": "Other OSI approved licence",
    "depends": ["base", "base_setup"],
    "data": [
        "security/ir.model.access.csv",
        "views/res_config_settings_views.xml",
        "wizard/send_sms_wizard_views.xml",
    ],
    "images": ["static/description/banner.png"],
    "installable": True,
    "application": False,
}
