"""
Scraper pour marchespublics.gov.ma
Recherche d'annonces de marchés publics marocains via formulaire PRADO
Usage: python marchespublics_scraper.py [mot-clé]
"""

import requests
import re
import json
import sys
from datetime import datetime

# Fix encoding pour Windows
if sys.stdout.encoding != 'utf-8':
    sys.stdout.reconfigure(encoding='utf-8')
    sys.stderr.reconfigure(encoding='utf-8')


BASE_URL = "https://www.marchespublics.gov.ma/index.php"
SEARCH_PAGE = "entreprise.EntrepriseAdvancedSearch"

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                  "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "fr-FR,fr;q=0.9",
    "Referer": "https://www.marchespublics.gov.ma/",
}

# ─── Types de procédure ───────────────────────────────────────────────────────
PROCEDURE_TYPES = {
    "0":  "Tous",
    "1":  "Appel d'offres ouvert",
    "2":  "Appel d'offres restreint",
    "37": "Appel à manifestation d'intérêt",
    "34": "Appel d'offres avec préselection - Phase 1",
    "50": "Appel d'offres ouvert simplifié",
}

# ─── Classifications ──────────────────────────────────────────────────────────
CLASSIFICATIONS = {
    "0": "Toutes",
    "1": "Etat",
    "2": "Collectivité locale",
    "3": "Etablissement public",
}


def clean_text(html: str) -> str:
    """Supprime le HTML et normalise les espaces."""
    text = re.sub(r'<[^>]+>', ' ', html)
    text = re.sub(r'&nbsp;', ' ', text)
    text = re.sub(r'&amp;', '&', text)
    text = re.sub(r'&lt;', '<', text)
    text = re.sub(r'&gt;', '>', text)
    text = re.sub(r'\s+', ' ', text)
    return text.strip()


def get_page_state(session: requests.Session) -> str:
    """Récupère la page de recherche et extrait le PRADO_PAGESTATE."""
    url = f"{BASE_URL}?page={SEARCH_PAGE}&searchAnnCons"
    response = session.get(url, headers=HEADERS, timeout=30)
    response.raise_for_status()

    match = re.search(r'name="PRADO_PAGESTATE"[^>]*value="([^"]+)"', response.text)
    if not match:
        raise ValueError("PRADO_PAGESTATE introuvable dans la page")

    return match.group(1)


def search_annonces(
    session: requests.Session,
    pagestate: str,
    keyword: str = "",
    procedure_type: str = "0",
    classification: str = "0",
    organisme: str = "0",
) -> str:
    """
    Soumet le formulaire de recherche et retourne le HTML de résultats.

    procedure_type: "0"=Tous, "1"=AO ouvert, "2"=AO restreint, etc.
    classification:  "0"=Toutes, "1"=Etat, "2"=Collectivité locale
    organisme:       "0"=Tous, ou code organisme
    """
    url = f"{BASE_URL}?page={SEARCH_PAGE}&searchAnnCons"

    payload = {
        "PRADO_PAGESTATE": pagestate,
        "PRADO_POSTBACK_TARGET": "ctl0$CONTENU_PAGE$AdvancedSearch$lancerRecherche",
        "PRADO_POSTBACK_PARAMETER": "",

        "ctl0$CONTENU_PAGE$AdvancedSearch$type_rechercheEntite": "floue",
        "ctl0$CONTENU_PAGE$AdvancedSearch$classification": classification,
        "ctl0$CONTENU_PAGE$AdvancedSearch$organismesNames": organisme,
        "ctl0$CONTENU_PAGE$AdvancedSearch$procedureType": procedure_type,

        # Guillemets = recherche expression exacte, sinon recherche floue
        "ctl0$CONTENU_PAGE$AdvancedSearch$keywordSearch": f'"{keyword}"',
        "ctl0$CONTENU_PAGE$AdvancedSearch$exact": "on",

        # Champs cachés obligatoires
        "ctl0$CONTENU_PAGE$AdvancedSearch$idReferentielZoneText$RepeaterReferentielZoneText$ctl0$modeRecherche": "1",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idReferentielZoneText$RepeaterReferentielZoneText$ctl0$typeData": "montant",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idAtexoLtRefRadio$RepeaterReferentielRadio$ctl0$modeRecherche": "1",
        "ctl0$CONTENU_PAGE$AdvancedSearch$idsSelectedGeoN2": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$numSelectedGeoN2": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$agrements$idsSelectedAgrements": "",
        "ctl0$CONTENU_PAGE$AdvancedSearch$lancerRecherche": "Lancer la recherche",
    }

    post_headers = {**HEADERS, "Content-Type": "application/x-www-form-urlencoded"}
    response = session.post(url, data=payload, headers=post_headers, timeout=30)
    response.raise_for_status()
    return response.text


def extract_annonces(html: str) -> list:
    """
    Parse le HTML de résultats et retourne la liste des annonces structurées.
    Chaque annonce est un dict avec les clés: ref, objet, procedure, categorie, date_fin, lieu, url
    """
    annonces = []

    # Extraire les colonnes disponibles (10 résultats par page par défaut)
    intitules  = re.findall(r'headers="cons_intitule"[^>]*>(.*?)</td>', html, re.DOTALL)
    refs       = re.findall(r'headers="cons_ref"[^>]*>(.*?)</td>', html, re.DOTALL)
    dates_end  = re.findall(r'headers="cons_dateEnd"[^>]*>(.*?)</td>', html, re.DOTALL)
    lieux      = re.findall(r'headers="cons_lieuExe"[^>]*>(.*?)</td>', html, re.DOTALL)
    types_proc = re.findall(
        r'id="identification_cons_\d+_type_procedure"[^>]*>(.*?)</div>', html, re.DOTALL
    )
    categories = re.findall(r'panelBlocCategorie"[^>]*>(.*?)</div>', html, re.DOTALL)

    # Chercher les liens détail par ctl number
    detail_ctls = re.findall(
        r'href="javascript:;//(ctl0_CONTENU_PAGE_resultSearch_tableauResultSearch_ctl\d+_lienDetail[^"]*)"',
        html,
    )

    nb = len(intitules)

    for i in range(nb):
        # Extraire référence et objet depuis la colonne intitule
        intitule_clean = clean_text(intitules[i])
        # Format: "REF - ... Objet : OBJET ..."
        ref_match = re.match(r'^([^\-]+)-', intitule_clean)
        objet_match = re.search(r'Objet\s*:\s*(.*?)(?:\s*\.\.\.|$)', intitule_clean)

        ref_num = ref_match.group(1).strip() if ref_match else ""
        objet = objet_match.group(1).strip() if objet_match else intitule_clean[:100]

        # Date de fin - extraire seulement la date et heure
        date_fin = ""
        if i < len(dates_end):
            date_match = re.search(r'(\d{2}/\d{2}/\d{4})', clean_text(dates_end[i]))
            heure_match = re.search(r'(\d{2}:\d{2})', clean_text(dates_end[i]))
            if date_match:
                date_fin = date_match.group(1)
                if heure_match:
                    date_fin += f" {heure_match.group(1)}"

        # Lieu - nettoyer les &nbsp; et tirets
        lieu = ""
        if i < len(lieux):
            lieu = re.sub(r'&nbsp;', ' ', lieux[i])
            lieu = clean_text(lieu)
            lieu = re.sub(r'^\s*[\-\s]+', '', lieu)
            lieu = re.sub(r'\s+\.\.\..*', '', lieu)
            lieu = lieu.strip()

        annonce = {
            "numero":    i + 1,
            "reference": ref_num,
            "objet":     objet[:200],
            "procedure": clean_text(types_proc[i]) if i < len(types_proc) else "",
            "categorie": clean_text(categories[i]) if i < len(categories) else "",
            "date_fin":  date_fin,
            "lieu":      lieu[:100],
        }
        annonces.append(annonce)

    return annonces


def count_total(html: str) -> int:
    """Essaie d'extraire le nombre total de résultats."""
    # Chercher dans le pager "Page X / Y (Z résultats)"
    match = re.search(r'(\d+)\s+r.sultat', html, re.IGNORECASE)
    if match:
        return int(match.group(1))
    # Compter les lignes du tableau
    return len(re.findall(r'headers="cons_intitule"', html))


def display_results(annonces: list, keyword: str, total: int = 0):
    """Affiche les résultats formatés dans le terminal."""
    sep = "=" * 72
    print(f"\n{sep}")
    print(f"  MARCHES PUBLICS MAROC - Recherche : {keyword}")
    print(f"  {datetime.now().strftime('%d/%m/%Y %H:%M')}  |  Page affichee : {len(annonces)} annonce(s)")
    if total:
        print(f"  Total estime : {total} resultat(s)")
    print(sep)

    if not annonces:
        print("\n  Aucun résultat trouvé pour ce mot-clé.\n")
        return

    for a in annonces:
        print(f"\n  [{a['numero']:02d}] {a['reference']}")
        print(f"       Objet     : {a['objet']}")
        print(f"       Procédure : {a['procedure']}  |  Catégorie : {a['categorie']}")
        print(f"       Date fin  : {a['date_fin']}  |  Lieu : {a['lieu']}")

    print(f"\n{sep}\n")


def save_json(annonces: list, keyword: str, total: int) -> str:
    """Sauvegarde les résultats en JSON."""
    slug = re.sub(r'[^\w]', '_', keyword.lower())
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
    filename = f"mp_{slug}_{ts}.json"

    data = {
        "keyword": keyword,
        "timestamp": datetime.now().isoformat(),
        "total_estimated": total,
        "page_count": len(annonces),
        "annonces": annonces,
    }
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    return filename


def scrape(
    keyword: str,
    procedure_type: str = "0",
    classification: str = "0",
    save: bool = True,
) -> list:
    """
    Fonction principale.

    Args:
        keyword:        Mot-clé (ex: "éclairage public", "fournitures informatique")
        procedure_type: "0"=Tous, "1"=AO ouvert, "2"=AO restreint
        classification: "0"=Toutes, "1"=Etat, "2"=Collectivité locale
        save:           Sauvegarder les résultats en JSON

    Returns:
        Liste des annonces (dicts)
    """
    print(f"\n{'-'*50}")
    print(f"  Recherche : {keyword}")
    print(f"  Procedure : {PROCEDURE_TYPES.get(procedure_type, procedure_type)}")
    print(f"  Entite    : {CLASSIFICATIONS.get(classification, classification)}")
    print(f"{'-'*50}")

    session = requests.Session()

    # Étape 1 : Récupérer l'état PRADO
    print("[1/3] Chargement de la page...")
    pagestate = get_page_state(session)
    print(f"      PAGESTATE OK ({len(pagestate)} chars)")

    # Étape 2 : Soumettre la recherche
    print("[2/3] Soumission de la recherche...")
    html = search_annonces(
        session, pagestate,
        keyword=keyword,
        procedure_type=procedure_type,
        classification=classification,
    )
    print(f"      Réponse reçue ({len(html)} chars)")

    # Étape 3 : Extraire les annonces
    print("[3/3] Extraction des annonces...")
    annonces = extract_annonces(html)
    total = count_total(html)

    # Affichage
    display_results(annonces, keyword, total)

    # Sauvegarde
    if save and annonces:
        filename = save_json(annonces, keyword, total)
        print(f"  Résultats sauvegardés : {filename}")

    return annonces


# ─── Point d'entrée ───────────────────────────────────────────────────────────
if __name__ == "__main__":
    # Exemples d'utilisation :
    #   python marchespublics_scraper.py eclairage public
    #   python marchespublics_scraper.py fournitures informatique
    #   python marchespublics_scraper.py travaux route

    keyword = " ".join(sys.argv[1:]) if len(sys.argv) > 1 else "eclairage public"
    annonces = scrape(keyword, save=True)
    print(f"Terminé : {len(annonces)} annonce(s) extraite(s).")
