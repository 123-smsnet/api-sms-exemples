// 123sms.ts — Envoi de SMS professionnel via l'API 123-SMS.net
// Copyright (C) 123-Sms.net — licence MIT
// Node.js 18+ (fetch natif), Deno ou Bun. Aucune dependance npm.
//
// Execution :  npx tsx 123sms.ts     (ou : deno run --allow-net 123sms.ts)
// Identifiants : espace client https://www.123-sms.net > rubrique API.

const EMAIL = "votre-email@exemple.fr";
const CLE_API = "CLEAPI";
const API_URL = "https://www.123-sms.net/http.php";

/** Codes retour documentes de l'API 123-SMS. */
type CodeRetour = "80" | "81" | "82" | "83" | "84" | string;

const LIBELLES: Record<string, string> = {
  "80": "le message a ete envoye.",
  "81": "enregistre pour un envoi differe.",
  "82": "identifiants invalides.",
  "83": "credit insuffisant.",
  "84": "numero invalide.",
};

/** Envoie un SMS et renvoie le code retour de l'API (80 = envoye). */
export async function envoyerSms(
  numero: string,
  message: string,
  options?: { sender?: string; refaccuse?: string },
): Promise<CodeRetour> {
  const corps = new URLSearchParams({
    email: EMAIL,
    pass: CLE_API,
    numero,
    message,
    ...(options?.sender ? { sender: options.sender } : {}),
    ...(options?.refaccuse ? { refaccuse: options.refaccuse } : {}),
  });
  // POST en HTTPS : la cle API ne transite jamais en clair
  const reponse = await fetch(API_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: corps.toString(),
    signal: AbortSignal.timeout(15_000),
  });
  return (await reponse.text()).trim();
}

// Demonstration
const code = await envoyerSms("33601020304", "Bonjour, ceci est un test 123-SMS.");
console.log(`Code ${code} : ${LIBELLES[code] ?? "voir la documentation."}`);
