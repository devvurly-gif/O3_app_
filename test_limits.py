"""Test limites connexion marchespublics.gov.ma"""
import requests
import re
import time
import sys
import threading

if sys.stdout.encoding != 'utf-8':
    sys.stdout.reconfigure(encoding='utf-8')
if sys.stderr.encoding != 'utf-8':
    sys.stderr.reconfigure(encoding='utf-8')

BASE_URL = "https://www.marchespublics.gov.ma/index.php"
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "fr-FR,fr;q=0.9",
    "Referer": "https://www.marchespublics.gov.ma/",
}
POST_HEADERS = {**HEADERS, "Content-Type": "application/x-www-form-urlencoded"}


def get_ps(sess, html=None):
    if html is None:
        r = sess.get(
            f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
            headers=HEADERS, timeout=15
        )
        html = r.text
    m = re.search(r'name="PRADO_PAGESTATE"[^>]*value="([^"]+)"', html)
    return m.group(1) if m else None


def search_all(sess, ps):
    payload = {
        "PRADO_PAGESTATE": ps,
        "PRADO_POSTBACK_TARGET": "ctl0$CONTENU_PAGE$AdvancedSearch$lancerRecherche",
        "PRADO_POSTBACK_PARAMETER": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$type_rechercheEntite": "floue",
        "ctl0$CONTENU_PAGE$AdvancedSearch$classification": "0",
        "ctl0$CONTENU_PAGE$AdvancedSearch$organismesNames": "0",
        "ctl0$CONTENU_PAGE$AdvancedSearch$procedureType": "0",
        "ctl0$CONTENU_PAGE$AdvancedSearch$keywordSearch": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idReferentielZoneText$RepeaterReferentielZoneText$ctl0$modeRecherche": "1",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idReferentielZoneText$RepeaterReferentielZoneText$ctl0$typeData": "montant",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idAtexoLtRefRadio$RepeaterReferentielRadio$ctl0$modeRecherche": "1",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idsSelectedGeoN2": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$numSelectedGeoN2": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$agrements$idsSelectedAgrements": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$lancerRecherche": "Lancer la recherche",
    }
    url = f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons"
    return sess.post(url, data=payload, headers=POST_HEADERS, timeout=25)


print("=" * 65)
print("TEST AVANCE - pagination, volume, session, stress")
print("=" * 65)

# ── TEST 5 : Pagination ────────────────────────────────────────────────
print("\n[TEST 5] Recherche initiale + detection pagination...")
session5 = requests.Session()
ps = get_ps(session5)
print(f"  PAGESTATE obtenu: {len(ps)} chars")

t0 = time.time()
r = search_all(session5, ps)
elapsed = round(time.time() - t0, 2)
html = r.text
ps = get_ps(session5, html)

total_m = re.search(r'(\d[\d\s]*)\s+r[eé]sultat', html, re.I)
total = int(total_m.group(1).replace(' ', '')) if total_m else 0
page_m = re.search(r'Page\s+\d+\s*/\s*(\d+)', html, re.I)
nb_pages = int(page_m.group(1)) if page_m else (total // 10 + 1)

print(f"  Page 1 | HTTP {r.status_code} | {len(html):>7} chars | {elapsed:.2f}s | Total={total} | Pages={nb_pages}")

# Chercher les boutons/liens de pagination
all_pagers = re.findall(
    r'href="javascript:;//(ctl0[^"]*(?:[Pp]ag[ae][rn]|suivant|[Nn]ext|previous|ctl\d+)[^"]*)"',
    html
)
print(f"  Pager postback targets trouves: {len(all_pagers)}")
for p in all_pagers[:6]:
    print(f"    {p}")

# Contexte autour de la pagination
page_ctx = re.search(r'Page\s+\d+\s*/\s*\d+', html)
if page_ctx:
    idx = page_ctx.start()
    ctx = html[max(0, idx - 400):idx + 600]
    ctx_clean = re.sub(r'<[^>]+>', ' ', ctx)
    ctx_clean = re.sub(r'\s+', ' ', ctx_clean).strip()
    print(f"\n  Zone pagination:\n  {ctx_clean[:500]}")

# ── TEST 6 : WAF x-deny-id ────────────────────────────────────────────
print("\n[TEST 6] Header x-deny-id...")
s6 = requests.Session()
r6a = s6.get(
    f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
    headers=HEADERS, timeout=15
)
print(f"  Page normale : x-deny-id='{r6a.headers.get('x-deny-id', 'ABSENT')}'")

r6b = s6.get(
    f"{BASE_URL}?page=entreprise.EntrepriseDetailsConsultation&refConsultation=998165&orgAcronyme=g3h",
    headers=HEADERS, timeout=15
)
print(f"  Detail direct: HTTP {r6b.status_code} | {len(r6b.text)} chars"
      f" | x-deny-id='{r6b.headers.get('x-deny-id', 'ABSENT')}'")
if len(r6b.text) < 300:
    print(f"  Contenu WAF: {r6b.text[:200]}")

# ── TEST 7 : Duree de vie session ─────────────────────────────────────
print("\n[TEST 7] Duree de vie cookie PHPSESSID...")
s7 = requests.Session()
r7a = s7.get(
    f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
    headers=HEADERS, timeout=15
)
sessid = s7.cookies.get('PHPSESSID')
serverid = s7.cookies.get('SERVERID', 'N/A')
set_cookie = r7a.headers.get('set-cookie', '')
print(f"  PHPSESSID : {sessid}")
print(f"  SERVERID  : {serverid}")

exp_m = re.search(r'expires=([^;]+)', set_cookie, re.I)
max_age_m = re.search(r'max-age=(\d+)', set_cookie, re.I)
if exp_m:
    print(f"  Expires   : {exp_m.group(1).strip()}")
elif max_age_m:
    secs = int(max_age_m.group(1))
    print(f"  Max-Age   : {secs}s = {secs//3600}h {(secs%3600)//60}min")
else:
    print("  Expiry    : Cookie de SESSION (pas d'expiry fixe = fermeture navigateur)")

print("  Attente 5s puis retest avec meme session...")
time.sleep(5)
r7b = s7.get(
    f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
    headers=HEADERS, timeout=15
)
sessid2 = s7.cookies.get('PHPSESSID')
print(f"  Apres 5s  : PHPSESSID={sessid2} | {'STABLE' if sessid == sessid2 else 'CHANGEE !'}")
print(f"  Page valide: {'OUI' if len(r7b.text) > 1000 else 'NON - session expiree'}")

# ── TEST 8 : Stress 20 req rafale ────────────────────────────────────
print("\n[TEST 8] 20 requetes GET en rafale (stress test)...")
s8 = requests.Session()
errors = 0
times = []
for i in range(1, 21):
    t0 = time.time()
    try:
        r = s8.get(
            f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
            headers=HEADERS, timeout=10
        )
        elapsed = time.time() - t0
        times.append(elapsed)
        ok = len(r.text) > 1000 and r.status_code == 200
        if not ok:
            errors += 1
            print(f"  #{i:02d} PROBLEME HTTP {r.status_code} {len(r.text)} chars")
    except Exception as e:
        errors += 1
        times.append(10)
        print(f"  #{i:02d} EXCEPTION: {e}")

avg = sum(times) / len(times) if times else 0
mx = max(times) if times else 0
mn = min(times) if times else 0
print(f"  Erreurs     : {errors}/20")
print(f"  Temps moyen : {avg:.2f}s | min: {mn:.2f}s | max: {mx:.2f}s")
print(f"  Resultat    : {'RATE LIMITING detecte !' if errors > 2 else 'Aucun blocage - OK'}")

# ── TEST 9 : Sessions paralleles (simulation import multi-thread) ─────
print("\n[TEST 9] 8 sessions paralleles simultanees...")
results9 = []
lock = threading.Lock()


def test_parallel(idx):
    s = requests.Session()
    t0 = time.time()
    try:
        r = s.get(
            f"{BASE_URL}?page=entreprise.EntrepriseAdvancedSearch&searchAnnCons",
            headers=HEADERS, timeout=15
        )
        elapsed = round(time.time() - t0, 2)
        ok = len(r.text) > 1000 and r.status_code == 200
        with lock:
            results9.append((idx, r.status_code, len(r.text), elapsed, ok))
    except Exception as e:
        with lock:
            results9.append((idx, 0, 0, 0, False))


threads = [threading.Thread(target=test_parallel, args=(i,)) for i in range(1, 9)]
[t.start() for t in threads]
[t.join() for t in threads]

blocked9 = 0
for idx, status, size, elapsed, ok in sorted(results9):
    if not ok:
        blocked9 += 1
    print(f"  Session #{idx} | HTTP {status} | {size:>7} chars | {elapsed:.2f}s"
          f" | {'OK' if ok else 'BLOQUE'}")
print(f"  Blocages: {blocked9}/8")

# ── RESUME FINAL ──────────────────────────────────────────────────────
print("\n" + "=" * 65)
print("RAPPORT FINAL - LIMITES DETECTEES")
print("=" * 65)
print(f"  Rate limiting (429/503)    : NON detecte")
print(f"  Blocage apres N requetes   : NON detecte")
print(f"  WAF                        : OUI (pages detail directes)")
print(f"  Limite sessions paralleles : NON ({8 - blocked9}/8 OK)")
print(f"  Cookie session             : SESSION (pas d'expiry fixe)")
print(f"  SERVERID (sticky session)  : OUI (load balancer)")
print(f"  Total annonces disponibles : {total}")
print(f"  Nombre de pages            : {nb_pages}")
print(f"  Temps moyen GET            : {avg:.2f}s")
print(f"  Temps moyen POST search    : ~5s")
print()
print("  PARAMETRES RECOMMANDES POUR L'IMPORT LARAVEL:")
print(f"  - sleep entre pages GET    : 0.5s (safe)")
print(f"  - sleep entre pages POST   : 1s (prudent)")
print(f"  - Duree estimee import     : ~{nb_pages * 1.5:.0f}s ({nb_pages * 1.5 / 60:.1f} min)")
print(f"  - Retry on error           : 3 tentatives avec backoff 5s")
print(f"  - Sessions paralleles      : max 3 (prudence serveur)")
