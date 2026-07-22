// =====================================================================
//  Envoi de SMS via l'API 123-SMS.net  (Node.js 18+, fetch natif)
//  Documentation : https://www.123-sms.net/developpeurs-api.php
//  (c) 123-SMS.net - licence MIT, reutilisation libre
// =====================================================================

const API_URL = "https://www.123-sms.net/http.php";

// --- Vos identifiants (espace client 123-SMS.net) --------------------
const EMAIL   = "votre-email@exemple.fr";  // e-mail du compte
const CLE_API = "XXXXXX";                  // cle API : espace client > API

async function envoyerSMS(numero, message) {
  const params = new URLSearchParams({
    email: EMAIL,
    pass: CLE_API,
    numero: numero,
    message: message,
  });
  const reponse = await fetch(API_URL + "?" + params.toString());
  return (await reponse.text()).trim();
}

// --- Exemple ---------------------------------------------------------
envoyerSMS("0612345678", "Test 123-SMS depuis Node.js !")
  .then(code => console.log("Reponse de l'API :", code))
  .catch(err => console.error("Erreur :", err));
