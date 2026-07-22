// Sms123.java — Envoi de SMS professionnel via l'API 123-SMS.net
// Copyright (C) 123-Sms.net — licence MIT
// Java 11 ou plus recent, aucune dependance (java.net.http standard).
//
// Compilation :  javac Sms123.java
// Execution   :  java Sms123
// Identifiants : espace client https://www.123-sms.net > rubrique API.

import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;

public class Sms123 {

    static final String IDENTIFIANT   = "votre_identifiant";
    static final String CLE_API = "CLEAPI";
    static final String API_URL = "https://www.123-sms.net/http.php";

    /** Envoie un SMS et renvoie le code retour de l'API (80 = envoye). */
    public static String envoyerSms(String numero, String message) throws Exception {
        String corps = "email="   + enc(IDENTIFIANT)
                     + "&pass="   + enc(CLE_API)
                     + "&numero=" + enc(numero)
                     + "&message="+ enc(message);
        HttpClient client = HttpClient.newBuilder()
                .connectTimeout(Duration.ofSeconds(10)).build();
        HttpRequest requete = HttpRequest.newBuilder(URI.create(API_URL))
                .timeout(Duration.ofSeconds(15))
                .header("Content-Type", "application/x-www-form-urlencoded")
                .POST(HttpRequest.BodyPublishers.ofString(corps))
                .build();
        // POST en HTTPS : la cle API ne transite jamais en clair
        return client.send(requete, HttpResponse.BodyHandlers.ofString())
                     .body().trim();
    }

    private static String enc(String s) {
        return URLEncoder.encode(s, StandardCharsets.UTF_8);
    }

    public static void main(String[] args) throws Exception {
        String code = envoyerSms("33601020304", "Bonjour, ceci est un test 123-SMS.");
        switch (code) {
            case "80": System.out.println("80 : le message a ete envoye."); break;
            case "81": System.out.println("81 : enregistre pour un envoi differe."); break;
            case "82": System.out.println("82 : identifiants invalides."); break;
            case "83": System.out.println("83 : credit insuffisant."); break;
            case "84": System.out.println("84 : numero invalide."); break;
            default:   System.out.println("Code retour API : " + code);
        }
    }
}
