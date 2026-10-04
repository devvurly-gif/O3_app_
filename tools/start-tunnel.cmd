@echo off
REM Tunnel public vers le tenant LOCAL (http://o3_app_.test) pour recevoir les webhooks
REM WhatsApp / SMS (Infobip, Twilio). Usage local uniquement.
REM
REM Une seule fois, avec le jeton de VOTRE compte ngrok (https://dashboard.ngrok.com/get-started/your-authtoken) :
REM   ngrok config add-authtoken VOTRE_JETON
REM
REM --host-header : l'application identifie le tenant par le domaine ; sans cette option,
REM le nom public ngrok n'est rattache a aucun tenant et les webhooks recoivent une 404.
REM
REM Utilise le ngrok du PATH (version Microsoft Store) s'il existe, sinon celui de Laragon.
where ngrok >nul 2>nul
if %errorlevel%==0 (
  ngrok http 80 --host-header=o3_app_.test
) else (
  "C:\laragon\bin\ngrok\ngrok.exe" http 80 --host-header=o3_app_.test
)
