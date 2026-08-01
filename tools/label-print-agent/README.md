# Agent d'impression d'étiquettes (TSPL)

Petit service local qui reçoit du TSPL depuis l'application O3 et le pousse
dans l'imprimante thermique (WD8210 ou tout autre modèle TSPL/TSC).

## Pourquoi

L'application tourne sur un VPS, l'imprimante est sur le réseau local de la
boutique : le serveur ne peut pas la joindre, et un navigateur ne sait pas
ouvrir de socket TCP brut. L'agent fait le pont, sur le poste de caisse.

Si l'application tourne sur le même réseau que l'imprimante (Laragon sur le PC
de la boutique, installation on-premise), l'agent est inutile : passez le
transport à `server` dans les réglages et Laravel enverra le job lui-même.

## Installation

Node.js 18+ suffit, aucune dépendance à installer.

```bash
node tools/label-print-agent/agent.cjs
```

### Imprimante réseau (LAN / Wi-Fi)

```bash
PRINTER_HOST=192.168.1.50 PRINTER_PORT=9100 node agent.cjs
```

### Imprimante choisie dans la page (recommandé, Windows)

Sans aucune variable, l'agent expose la liste des imprimantes du poste ;
l'utilisateur en choisit une dans la page Étiquettes et le travail part vers
cette file, en datatype `RAW` du spouleur. Fonctionne pour l'USB comme pour le
réseau, sans partage à configurer.

### Imprimante USB partagée

```bash
PRINTER_SHARE=\\localhost\WD8210 node agent.cjs
```

## Variables d'environnement

| Variable | Défaut | Rôle |
|---|---|---|
| `PRINTER_HOST` | `192.168.1.100` | IP de l'imprimante réseau |
| `PRINTER_PORT` | `9100` | port JetDirect |
| `PRINTER_SHARE` | – | partage Windows ; prioritaire sur le TCP |
| `AGENT_PORT` | `9110` | port d'écoute local |
| `ALLOWED_ORIGINS` | `*.o3app.ma` + localhost | origines autorisées à imprimer |

## Démarrage automatique (Windows)

Créez un raccourci dans `shell:startup` (Win+R) pointant sur :

```bash
cmd /c "set PRINTER_HOST=192.168.1.50&& node C:\chemin\vers\agent.cjs"
```

## Routes

| Route | Rôle |
|---|---|
| `GET /ping` | l'agent est vivant, et sur quelle cible |
| `GET /printers` | inventaire des imprimantes du poste : formats papier, résolutions, zone imprimable. C'est cette route qui alimente le sélecteur et cale l'aperçu — aucune API navigateur ne donne accès aux imprimantes système. |
| `POST /print` | `{ payload_base64, printer }`. Le TSPL est encodé en CP1252 (accents), donc transporté en base64. `printer` nomme une file Windows ; sans lui, l'agent retombe sur `PRINTER_SHARE` puis sur la cible TCP. |

## Vérification

```bash
curl http://127.0.0.1:9110/ping -H "Origin: https://boutique.o3app.ma"
```

Doit répondre `{"ok":true,...}` avec la cible configurée.

## Sécurité

- L'agent n'écoute qu'en `127.0.0.1` : il n'est joignable que depuis ce poste.
- Seules les origines de l'application peuvent lui parler, sinon n'importe
  quel site ouvert dans le navigateur pourrait faire cracher du papier.
- Il ne fait que transporter des octets vers l'imprimante — aucune commande
  système n'est construite à partir du contenu reçu.
