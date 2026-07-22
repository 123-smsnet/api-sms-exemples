// 123sms.cs — Envoi de SMS professionnel via l'API 123-SMS.net
// Copyright (C) 123-Sms.net — licence MIT
// Compatible .NET 6/7/8 et .NET Framework 4.6+ (HttpClient).
//
// Compilation rapide (.NET SDK) :  dotnet run
// ou creer un projet console et coller ce fichier.
//
// Parametres (espace client https://www.123-sms.net > rubrique API) :
//   email   : e-mail du compte 123-SMS
//   pass    : cle API (6 caracteres)
//   numero  : destinataire(s), ex. "33601020304" (plusieurs : separes par -)
//   message : texte du SMS (160 caracteres GSM par SMS)
// Optionnels : sender (Sender-ID declare), refaccuse (accuse par HTTP)

using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Threading.Tasks;

class Sms123
{
    static async Task Main()
    {
        var parametres = new Dictionary<string, string>
        {
            ["email"]   = "votre-email@exemple.fr",
            ["pass"]    = "CLEAPI",
            ["numero"]  = "33601020304",
            ["message"] = "Bonjour, ceci est un test 123-SMS."
            // ["sender"]    = "MASOCIETE",
            // ["refaccuse"] = "commande-1234",
        };

        using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
        using var contenu = new FormUrlEncodedContent(parametres);

        // POST en HTTPS : les identifiants ne transitent jamais en clair
        var reponse = await client.PostAsync("https://www.123-sms.net/http.php", contenu);
        string code = (await reponse.Content.ReadAsStringAsync()).Trim();

        Console.WriteLine(code switch
        {
            "80" => "80 : le message a ete envoye.",
            "81" => "81 : enregistre pour un envoi en differe.",
            "82" => "82 : identifiants invalides.",
            "83" => "83 : credit insuffisant.",
            "84" => "84 : numero invalide.",
            _    => "Code retour API : " + code
        });
    }
}
