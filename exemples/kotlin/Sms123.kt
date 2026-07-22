// Sms123.kt — Envoi de SMS professionnel via l'API 123-SMS.net
// Copyright (C) 123-Sms.net — licence MIT
// Kotlin (JVM), aucune dependance : java.net.http du JDK 11+.
//
// Execution :  kotlinc Sms123.kt -include-runtime -d sms123.jar
//              java -jar sms123.jar
// Identifiants : espace client https://www.123-sms.net > rubrique API.

import java.net.URI
import java.net.URLEncoder
import java.net.http.HttpClient
import java.net.http.HttpRequest
import java.net.http.HttpResponse
import java.time.Duration

const val EMAIL = "votre-email@exemple.fr"
const val CLE_API = "CLEAPI"
const val API_URL = "https://www.123-sms.net/http.php"

fun enc(s: String): String = URLEncoder.encode(s, Charsets.UTF_8)

/** Envoie un SMS et renvoie le code retour de l'API (80 = envoye). */
fun envoyerSms(numero: String, message: String): String {
    val corps = "email=${enc(EMAIL)}&pass=${enc(CLE_API)}" +
                "&numero=${enc(numero)}&message=${enc(message)}"
    val client = HttpClient.newBuilder()
        .connectTimeout(Duration.ofSeconds(10)).build()
    val requete = HttpRequest.newBuilder(URI.create(API_URL))
        .timeout(Duration.ofSeconds(15))
        .header("Content-Type", "application/x-www-form-urlencoded")
        .POST(HttpRequest.BodyPublishers.ofString(corps))
        .build()
    // POST en HTTPS : la cle API ne transite jamais en clair
    return client.send(requete, HttpResponse.BodyHandlers.ofString()).body().trim()
}

fun main() {
    when (val code = envoyerSms("33601020304", "Bonjour, ceci est un test 123-SMS.")) {
        "80" -> println("80 : le message a ete envoye.")
        "81" -> println("81 : enregistre pour un envoi differe.")
        "82" -> println("82 : identifiants invalides.")
        "83" -> println("83 : credit insuffisant.")
        "84" -> println("84 : numero invalide.")
        else -> println("Code retour API : $code")
    }
}
