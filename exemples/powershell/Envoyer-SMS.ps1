# =====================================================================
#  Envoi de SMS via l'API 123-SMS.net  (PowerShell 5.1 ou superieur)
#  Documentation : https://www.123-sms.net/developpeurs-api.php
#  (c) 123-SMS.net - licence MIT, reutilisation libre
# =====================================================================

# --- Vos identifiants (espace client 123-SMS.net) --------------------
$Identifiant  = "votre_identifiant"   # identifiant du compte
$CleApi = "XXXXXX"                   # cle API : espace client > API

function Send-Sms123 {
    param(
        [Parameter(Mandatory)][string]$Numero,
        [Parameter(Mandatory)][string]$Message
    )
    $url = "https://www.123-sms.net/http.php?email={0}&pass={1}&numero={2}&message={3}" -f
        [uri]::EscapeDataString($Identifiant),
        [uri]::EscapeDataString($CleApi),
        [uri]::EscapeDataString($Numero),
        [uri]::EscapeDataString($Message)
    $reponse = Invoke-WebRequest -Uri $url -UseBasicParsing
    return $reponse.Content.Trim()
}

# --- Exemple ---------------------------------------------------------
$code = Send-Sms123 -Numero "0612345678" -Message "Test 123-SMS depuis PowerShell !"
Write-Host "Reponse de l'API : $code"
