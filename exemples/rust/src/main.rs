// main.rs — Envoi de SMS professionnel via l'API 123-SMS.net
// Copyright (C) 123-Sms.net — licence MIT
// Dependance unique : ureq (TLS rustls integre, pas d'OpenSSL requis).
//
// Execution :  cargo run
// Identifiants : espace client https://www.123-sms.net > rubrique API.

const IDENTIFIANT: &str = "votre_identifiant";
const CLE_API: &str = "CLEAPI";
const API_URL: &str = "https://www.123-sms.net/http.php";

/// Envoie un SMS et renvoie le code retour de l'API (80 = envoye).
fn envoyer_sms(numero: &str, message: &str) -> Result<String, ureq::Error> {
    // POST en HTTPS : la cle API ne transite jamais en clair,
    // send_form gere l'encodage URL (accents compris).
    let reponse = ureq::post(API_URL)
        .timeout(std::time::Duration::from_secs(15))
        .send_form(&[
            ("email", IDENTIFIANT),
            ("pass", CLE_API),
            ("numero", numero),
            ("message", message),
        ])?;
    Ok(reponse.into_string()?.trim().to_string())
}

fn main() {
    match envoyer_sms("33601020304", "Bonjour, ceci est un test 123-SMS.") {
        Ok(code) => match code.as_str() {
            "80" => println!("80 : le message a ete envoye."),
            "81" => println!("81 : enregistre pour un envoi differe."),
            "82" => println!("82 : identifiants invalides."),
            "83" => println!("83 : credit insuffisant."),
            "84" => println!("84 : numero invalide."),
            autre => println!("Code retour API : {autre}"),
        },
        Err(e) => eprintln!("Erreur reseau : {e}"),
    }
}
