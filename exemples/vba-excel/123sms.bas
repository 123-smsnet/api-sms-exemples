Attribute VB_Name = "Module123SMS"
' =====================================================================
' 123-SMS.net - Envoi de SMS depuis Excel (VBA)
' Copyright (C) 123-Sms.net - licence MIT
' Necessite Excel 2013+ sous Windows (fonction ENCODEURL et MSXML2).
'
' 1. Renseignez SMS_IDENTIFIANT et SMS_CLEAPI ci-dessous
'    (cle API : espace client https://www.123-sms.net > rubrique API).
' 2. =EnvoyerSMS("33601020304";"Bonjour !")  depuis une cellule,
'    ou lancez la macro EnvoyerSMSListe (colonne A = numeros,
'    colonne B = messages, codes retour ecrits en colonne C).
'
' IMPORTANT : formatez la colonne des numeros en TEXTE (sinon Excel
' supprime le 0 initial). Formats acceptes : 0601020304 ou 33601020304.
' =====================================================================

Const SMS_IDENTIFIANT As String = "votre_identifiant"
Const SMS_CLEAPI As String = "CLEAPI"
Const SMS_URL As String = "https://www.123-sms.net/http.php"

' Envoie un SMS et renvoie le code retour de l'API (80 = envoye).
Public Function EnvoyerSMS(numero As String, message As String, _
                           Optional senderID As String = "") As String
    Dim http As Object, corps As String
    On Error GoTo Erreur
    Set http = CreateObject("MSXML2.XMLHTTP.6.0")
    corps = "email=" & EncodeParam(SMS_IDENTIFIANT) & _
            "&pass=" & EncodeParam(SMS_CLEAPI) & _
            "&numero=" & EncodeParam(numero) & _
            "&message=" & EncodeParam(message)
    If senderID <> "" Then corps = corps & "&sender=" & EncodeParam(senderID)
    ' POST en HTTPS : la cle API ne transite jamais en clair
    http.Open "POST", SMS_URL, False
    http.setRequestHeader "Content-Type", "application/x-www-form-urlencoded"
    http.send corps
    EnvoyerSMS = Trim$(http.responseText)
    Exit Function
Erreur:
    EnvoyerSMS = "ERREUR : " & Err.Description
End Function

' Encodage URL (accents, espaces, caracteres speciaux).
Private Function EncodeParam(texte As String) As String
    EncodeParam = Application.WorksheetFunction.EncodeURL(texte)
End Function

' Envoi en serie : col. A = numeros, col. B = messages (des la ligne 2).
' Le code retour est ecrit en colonne C (80 = envoye).
Public Sub EnvoyerSMSListe()
    Dim ligne As Long, code As String, envoyes As Long
    ligne = 2
    Do While Trim$(CStr(Cells(ligne, 1).Value)) <> ""
        code = EnvoyerSMS(CStr(Cells(ligne, 1).Value), CStr(Cells(ligne, 2).Value))
        Cells(ligne, 3).Value = code
        If code = "80" Or code = "81" Then envoyes = envoyes + 1
        ligne = ligne + 1
    Loop
    MsgBox envoyes & " SMS envoye(s). Codes retour en colonne C" & vbCrLf & _
           "(80 = envoye, 82 = identifiants invalides, 83 = credit insuffisant)", _
           vbInformation, "123-SMS.net"
End Sub
