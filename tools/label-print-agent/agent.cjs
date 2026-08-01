#!/usr/bin/env node
/**
 * O3 — agent d'impression d'étiquettes (TSPL).
 *
 * Pourquoi cet agent existe : l'application tourne sur un VPS, l'imprimante
 * est sur le réseau local de la boutique. Le serveur ne peut donc pas joindre
 * l'imprimante, et un navigateur ne sait pas ouvrir de socket TCP brut. Cet
 * agent tourne sur le poste de caisse : la page lui envoie le TSPL généré par
 * Laravel, il le pousse tel quel dans l'imprimante.
 *
 * Lancement :
 *   node agent.js
 *
 * Configuration (variables d'environnement, toutes facultatives) :
 *   PRINTER_HOST     IP de l'imprimante réseau        (défaut 192.168.1.100)
 *   PRINTER_PORT     port JetDirect                   (défaut 9100)
 *   PRINTER_SHARE    partage Windows, ex. \\\\localhost\\WD8210 — utilisé à la
 *                    place du TCP quand il est défini (imprimante USB)
 *   AGENT_PORT       port d'écoute local              (défaut 9110)
 *   ALLOWED_ORIGINS  origines autorisées, séparées par des virgules
 *                    (défaut : tous les sous-domaines *.o3app.ma + localhost)
 */

const http = require('node:http')
const net = require('node:net')
const os = require('node:os')
const fs = require('node:fs')
const path = require('node:path')
const { execFile } = require('node:child_process')

const PS_LIST = path.join(__dirname, 'windows-printers.ps1')
const PS_RAW = path.join(__dirname, 'windows-rawprint.ps1')

const PRINTER_HOST = process.env.PRINTER_HOST || '192.168.1.100'
const PRINTER_PORT = Number(process.env.PRINTER_PORT || 9100)
const PRINTER_SHARE = process.env.PRINTER_SHARE || ''
const AGENT_PORT = Number(process.env.AGENT_PORT || 9110)

const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || '')
  .split(',')
  .map((o) => o.trim())
  .filter(Boolean)

/**
 * L'agent a accès à l'imprimante du poste : n'importe quelle page web ouverte
 * par l'utilisateur pourrait sinon lui faire cracher du papier. On n'accepte
 * donc que les origines de l'application.
 */
function originAllowed(origin) {
  if (!origin) return false
  if (ALLOWED_ORIGINS.length) return ALLOWED_ORIGINS.includes(origin)
  try {
    const { protocol, hostname } = new URL(origin)
    if (hostname === 'localhost' || hostname === '127.0.0.1') return true
    return protocol === 'https:' && (hostname === 'o3app.ma' || hostname.endsWith('.o3app.ma'))
  } catch {
    return false
  }
}

function cors(req, res) {
  const origin = req.headers.origin
  if (!originAllowed(origin)) return false

  res.setHeader('Access-Control-Allow-Origin', origin)
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type')
  // Chrome (Private Network Access) exige cet en-tête pour qu'une page HTTPS
  // publique puisse appeler une adresse privée comme 127.0.0.1.
  res.setHeader('Access-Control-Allow-Private-Network', 'true')
  res.setHeader('Access-Control-Max-Age', '86400')
  return true
}

/** Envoi TCP brut (JetDirect / port 9100). */
function sendTcp(payload, host, port) {
  return new Promise((resolve, reject) => {
    const socket = net.createConnection({ host, port })
    socket.setTimeout(8000)
    socket.on('connect', () => socket.end(payload))
    socket.on('close', resolve)
    socket.on('timeout', () => {
      socket.destroy()
      reject(new Error(`Timeout de connexion à ${host}:${port}`))
    })
    socket.on('error', (err) => reject(new Error(`${host}:${port} — ${err.message}`)))
  })
}

/**
 * Envoi vers un partage Windows (imprimante USB partagée localement).
 * `copy /b` pousse les octets bruts sans passer par le pilote, ce qui est
 * exactement ce qu'attend une imprimante TSPL.
 */
function sendShare(payload, share) {
  return withTempFile(payload, (tmp) =>
    run('cmd', ['/c', 'copy', '/b', tmp, share])
  )
}

/**
 * Envoi vers une file d'impression Windows choisie dans la page. Passe par le
 * datatype RAW du spouleur : le pilote ne doit surtout pas réinterpréter le
 * TSPL. Voir windows-rawprint.ps1.
 */
function sendQueue(payload, printerName) {
  return withTempFile(payload, (tmp) =>
    powershell([PS_RAW, '-PrinterName', printerName, '-Path', tmp])
  )
}

async function withTempFile(payload, fn) {
  const tmp = path.join(os.tmpdir(), `o3-label-${Date.now()}-${process.pid}.prn`)
  fs.writeFileSync(tmp, payload)
  try {
    await fn(tmp)
  } finally {
    fs.unlink(tmp, () => {})
  }
}

function run(cmd, args) {
  return new Promise((resolve, reject) => {
    execFile(cmd, args, { maxBuffer: 16 * 1024 * 1024 }, (err, stdout, stderr) => {
      if (err) return reject(new Error((stderr || err.message).trim()))
      resolve(stdout)
    })
  })
}

function powershell(args) {
  return run('powershell', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', ...args])
}

/**
 * Inventaire des imprimantes du poste — la seule façon d'y arriver : aucune
 * API navigateur n'expose les imprimantes système, seul un programme local
 * peut les interroger.
 */
async function listPrinters() {
  if (process.platform !== 'win32') {
    throw new Error("L'inventaire des imprimantes n'est disponible que sous Windows.")
  }
  return JSON.parse(await powershell([PS_LIST]))
}

function readBody(req, limit = 4 * 1024 * 1024) {
  return new Promise((resolve, reject) => {
    let data = ''
    req.on('data', (chunk) => {
      data += chunk
      if (data.length > limit) {
        reject(new Error('Travail trop volumineux'))
        req.destroy()
      }
    })
    req.on('end', () => resolve(data))
    req.on('error', reject)
  })
}

const server = http.createServer(async (req, res) => {
  const json = (code, body) => {
    res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' })
    res.end(JSON.stringify(body))
  }

  if (!cors(req, res)) {
    return json(403, { ok: false, error: `Origine non autorisée : ${req.headers.origin || 'inconnue'}` })
  }

  if (req.method === 'OPTIONS') {
    res.writeHead(204)
    return res.end()
  }

  if (req.method === 'GET' && req.url === '/ping') {
    return json(200, { ok: true, agent: 'o3-label-print', target: PRINTER_SHARE || `${PRINTER_HOST}:${PRINTER_PORT}` })
  }

  if (req.method === 'GET' && req.url.startsWith('/printers')) {
    try {
      return json(200, { ok: true, printers: await listPrinters() })
    } catch (err) {
      console.error(`[${new Date().toISOString()}] inventaire :`, err.message)
      return json(500, { ok: false, error: err.message })
    }
  }

  if (req.method !== 'POST' || !req.url.startsWith('/print')) {
    return json(404, { ok: false, error: 'Route inconnue' })
  }

  try {
    const raw = await readBody(req)
    const body = raw.trim().startsWith('{') ? JSON.parse(raw) : { payload: raw }

    // Le TSPL est encodé en CP1252 par le serveur (accents), ce qui n'est pas
    // de l'UTF-8 valide : il voyage donc en base64. `payload` en clair reste
    // accepté pour les tests au curl.
    const payload =
      typeof body.payload_base64 === 'string'
        ? Buffer.from(body.payload_base64, 'base64')
        : typeof body.payload === 'string'
          ? Buffer.from(body.payload, 'latin1')
          : null

    if (!payload || payload.length === 0) {
      return json(422, { ok: false, error: "Travail d'impression vide" })
    }

    // Ordre de priorité : l'imprimante choisie dans la page, sinon le partage
    // configuré au lancement, sinon la cible réseau.
    const target = typeof body.printer === 'string' && body.printer.trim() !== '' ? body.printer.trim() : ''

    if (target) {
      await sendQueue(payload, target)
    } else if (PRINTER_SHARE) {
      await sendShare(payload, PRINTER_SHARE)
    } else {
      await sendTcp(payload, PRINTER_HOST, PRINTER_PORT)
    }

    console.log(`[${new Date().toISOString()}] ${payload.length} octets → ${target || PRINTER_SHARE || `${PRINTER_HOST}:${PRINTER_PORT}`}`)
    return json(200, { ok: true })
  } catch (err) {
    console.error(`[${new Date().toISOString()}] échec :`, err.message)
    return json(502, { ok: false, error: err.message })
  }
})

// Écoute uniquement en loopback : l'agent n'est joignable que depuis ce poste.
server.listen(AGENT_PORT, '127.0.0.1', () => {
  console.log(`Agent d'impression O3 sur http://127.0.0.1:${AGENT_PORT}`)
  console.log(`Cible : ${PRINTER_SHARE || `${PRINTER_HOST}:${PRINTER_PORT}`}`)
})
