"""
Convertisseur Devis → Facture + Bon de Livraison
Auteur : JAWAD LIGHT
"""
import os
import re
import sys
from datetime import datetime
from pathlib import Path

import pdfplumber
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    HRFlowable, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle,
)

# ---------------------------------------------------------------------------
# Polices
# ---------------------------------------------------------------------------
FONTS_DIR = "C:/Windows/Fonts"

def _register_font(name, file_regular, file_bold=None):
    try:
        pdfmetrics.registerFont(TTFont(name, os.path.join(FONTS_DIR, file_regular)))
        if file_bold:
            pdfmetrics.registerFont(TTFont(f"{name}-Bold", os.path.join(FONTS_DIR, file_bold)))
        return True
    except Exception:
        return False

_register_font("Arial", "arial.ttf", "arialbd.ttf")
BASE_FONT = "Arial"
BOLD_FONT = "Arial-Bold"

# ---------------------------------------------------------------------------
# Couleurs
# ---------------------------------------------------------------------------
BLUE_DARK   = colors.HexColor("#1a3a5c")
BLUE_MID    = colors.HexColor("#2563a8")
BLUE_LIGHT  = colors.HexColor("#dbeafe")
GRAY_LINE   = colors.HexColor("#e2e8f0")
GRAY_TEXT   = colors.HexColor("#64748b")
WHITE       = colors.white
BLACK       = colors.black

W, H = A4

# ---------------------------------------------------------------------------
# Parser du devis PDF
# ---------------------------------------------------------------------------
class DevisParser:

    def __init__(self, pdf_path: str):
        self.path = pdf_path
        self.data = {}

    def parse(self) -> dict:
        with pdfplumber.open(self.path) as pdf:
            full_text = ""
            tables_raw = []
            for page in pdf.pages:
                full_text += (page.extract_text() or "") + "\n"
                tables_raw.extend(page.extract_tables())

        self.data = self._extract_meta(full_text)
        self.data["items"] = self._extract_items(tables_raw)
        self.data["totals"] = self._extract_totals(tables_raw)
        return self.data

    def _clean(self, s: str) -> str:
        if not s:
            return ""
        replacements = {
            "Dénomination": "Dénomination", "désignation": "désignation",
            "�": "é",
        }
        for k, v in replacements.items():
            s = s.replace(k, v)
        return s.strip()

    def _extract_meta(self, text: str) -> dict:
        def g(pattern, default=""):
            m = re.search(pattern, text, re.IGNORECASE)
            return m.group(1).strip() if m else default

        return {
            "fournisseur_nom": "JAWAD LIGHT",
            "fournisseur_tel": "0620696967",
            "fournisseur_email": "jawadlight1@gmail.com",
            "fournisseur_rib": "022270000052002865532668",
            "fournisseur_adresse": "8 av zerktouni V.N, FES, Maroc",
            "fournisseur_tp": "13204762",
            "fournisseur_ice": "003167048000054",
            "client_nom": "Commune STI FADMA",
            "reference_devis": g(r"[Rr]f?rence\s*:\s*([^\n]+)") or "06/2026/CSF",
            "objet": "ACHAT DES MATERIELS POUR ENTRETIEN D'ECLAIRAGE PUBLIC",
            "lieu_livraison": "AL HAOUZ",
            "delai_livraison": "10 Jour(s)",
        }

    def _parse_amount(self, s: str) -> float:
        if not s:
            return 0.0
        s = re.sub(r"[^\d,\.]", "", s).replace(",", ".")
        try:
            return float(s)
        except ValueError:
            return 0.0

    def _extract_items(self, tables_raw: list) -> list:
        items = []
        for table in tables_raw:
            if not table or len(table) < 2:
                continue
            header = table[0]
            if header and any(
                h and re.search(r"D.signation|Designation|D.s", h, re.I)
                for h in header
            ):
                for row in table[1:]:
                    if not row or len(row) < 5:
                        continue
                    num, designation, qty_raw, pu_raw, pt_raw = (
                        row[0], row[1], row[2], row[3], row[4]
                    )
                    if not num or not re.match(r"^\d+$", str(num).strip()):
                        continue
                    # Première ligne de la désignation uniquement
                    if designation:
                        desig_lines = designation.strip().split("\n")
                        desig_short = desig_lines[0].strip()
                    else:
                        desig_short = ""

                    qty_str = str(qty_raw or "").strip()
                    m = re.match(r"([\d\s]+)", qty_str)
                    qty = float(m.group(1).replace(" ", "")) if m else 0
                    unit = re.sub(r"[\d\s]", "", qty_str).strip("()").strip() or "u"

                    items.append({
                        "num": str(num).strip(),
                        "designation": desig_short,
                        "quantite": qty,
                        "unite": unit,
                        "prix_unitaire": self._parse_amount(pu_raw),
                        "prix_total": self._parse_amount(pt_raw),
                    })
        return items

    def _extract_totals(self, tables_raw: list) -> dict:
        for table in tables_raw:
            for row in (table or []):
                if not row:
                    continue
                cell = str(row[0] or "")
                if "total HT" in cell or "Montant total HT" in cell:
                    nums = re.findall(r"[\d\s]+[,\.][\d]+", cell)
                    if len(nums) >= 3:
                        return {
                            "ht": self._parse_amount(nums[0]),
                            "tva": self._parse_amount(nums[1]),
                            "ttc": self._parse_amount(nums[2]),
                            "tva_rate": 20,
                        }
        return {"ht": 75861.00, "tva": 15172.20, "ttc": 91033.20, "tva_rate": 20}


# ---------------------------------------------------------------------------
# Helpers de rendu
# ---------------------------------------------------------------------------
def fmt_mad(amount: float) -> str:
    return f"{amount:,.2f} MAD".replace(",", " ")


def num_to_words_fr(n: float) -> str:
    """Conversion basique d'un montant en lettres (français)."""
    entier = int(n)
    cents = round((n - entier) * 100)

    units = ["", "un", "deux", "trois", "quatre", "cinq", "six", "sept",
             "huit", "neuf", "dix", "onze", "douze", "treize", "quatorze",
             "quinze", "seize", "dix-sept", "dix-huit", "dix-neuf"]
    tens  = ["", "dix", "vingt", "trente", "quarante", "cinquante",
             "soixante", "soixante-dix", "quatre-vingt", "quatre-vingt-dix"]

    def below_100(n):
        if n < 20:
            return units[n]
        t, u = divmod(n, 10)
        if t == 7:
            return "soixante-" + units[10 + u]
        if t == 9:
            return "quatre-vingt-" + units[10 + u]
        sep = "-" if u else ""
        et  = "-et" if u == 1 and t in (2, 3, 4, 5, 6) else sep
        return tens[t] + (et + "-" if et else "") + (units[u] if u else "")

    def below_1000(n):
        if n < 100:
            return below_100(n)
        h, r = divmod(n, 100)
        prefix = ("cent" if h == 1 else units[h] + "-cent")
        if r == 0 and h > 1:
            return prefix + "s"
        return prefix + ("-" + below_100(r) if r else "")

    def convert(n):
        if n == 0:
            return "zéro"
        parts = []
        m = n // 1_000_000
        if m:
            parts.append(below_1000(m) + " million" + ("s" if m > 1 else ""))
            n %= 1_000_000
        k = n // 1000
        if k:
            parts.append(("mille" if k == 1 else below_1000(k) + " mille"))
            n %= 1000
        if n:
            parts.append(below_1000(n))
        return " ".join(parts)

    words = convert(entier).capitalize() + " dirhams"
    if cents:
        words += " et " + below_100(cents) + " centimes"
    return words


def make_style(font=BASE_FONT, size=10, color=BLACK, align="LEFT", bold=False):
    f = BOLD_FONT if bold else font
    alignment = {"LEFT": 0, "CENTER": 1, "RIGHT": 2, "JUSTIFY": 4}.get(align, 0)
    return ParagraphStyle("s", fontName=f, fontSize=size, textColor=color,
                          alignment=alignment, leading=size * 1.4)


# ---------------------------------------------------------------------------
# Bloc en-tête commun
# ---------------------------------------------------------------------------
def build_header_table(data: dict, doc_type: str, doc_num: str, doc_date: str) -> Table:
    label_color = BLUE_DARK
    s_title = make_style(size=22, color=WHITE, bold=True, align="CENTER")
    s_label = make_style(size=8, color=GRAY_TEXT)
    s_val   = make_style(size=9, color=BLACK, bold=True)
    s_ref   = make_style(size=9, color=label_color)

    left_lines = [
        Paragraph(f"<b>{data['fournisseur_nom']}</b>", make_style(size=13, color=BLUE_DARK, bold=True)),
        Spacer(1, 2*mm),
        Paragraph(data["fournisseur_adresse"], make_style(size=8, color=GRAY_TEXT)),
        Paragraph(f"Tél : {data['fournisseur_tel']}", make_style(size=8, color=GRAY_TEXT)),
        Paragraph(f"Email : {data['fournisseur_email']}", make_style(size=8, color=GRAY_TEXT)),
        Spacer(1, 2*mm),
        Paragraph(f"TP : {data['fournisseur_tp']}", make_style(size=7, color=GRAY_TEXT)),
        Paragraph(f"ICE : {data['fournisseur_ice']}", make_style(size=7, color=GRAY_TEXT)),
        Paragraph(f"RIB : {data['fournisseur_rib']}", make_style(size=7, color=GRAY_TEXT)),
    ]

    right_lines = [
        Paragraph(doc_type, s_title),
        Spacer(1, 4*mm),
        Paragraph(f"N° : <b>{doc_num}</b>", make_style(size=10, color=BLUE_DARK, bold=True, align="CENTER")),
        Paragraph(f"Date : <b>{doc_date}</b>", make_style(size=9, color=BLACK, align="CENTER")),
        Spacer(1, 4*mm),
        Paragraph(f"Réf. Devis : {data['reference_devis']}", s_ref),
        Paragraph(f"Client : <b>{data['client_nom']}</b>", make_style(size=9, color=BLACK, bold=True)),
        Paragraph(f"Livraison : {data['lieu_livraison']}", make_style(size=8, color=GRAY_TEXT)),
        Paragraph(f"Délai : {data['delai_livraison']}", make_style(size=8, color=GRAY_TEXT)),
    ]

    tbl = Table([[left_lines, right_lines]], colWidths=[95*mm, 90*mm])
    tbl.setStyle(TableStyle([
        ("VALIGN",      (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND",  (1, 0), (1, 0),  BLUE_DARK),
        ("ROUNDEDCORNERS", [4]),
        ("LEFTPADDING",  (0, 0), (0, 0), 0),
        ("RIGHTPADDING", (0, 0), (0, 0), 6),
        ("LEFTPADDING",  (1, 0), (1, 0), 8),
        ("RIGHTPADDING", (1, 0), (1, 0), 8),
        ("TOPPADDING",   (1, 0), (1, 0), 8),
        ("BOTTOMPADDING",(1, 0), (1, 0), 8),
    ]))
    return tbl


# ---------------------------------------------------------------------------
# Génération Facture
# ---------------------------------------------------------------------------
def generate_facture(data: dict, output_path: str, num: str = None, date_str: str = None):
    num      = num      or f"FAC-{datetime.now().strftime('%Y%m')}-001"
    date_str = date_str or datetime.now().strftime("%d/%m/%Y")

    doc = SimpleDocTemplate(
        output_path, pagesize=A4,
        leftMargin=15*mm, rightMargin=15*mm,
        topMargin=15*mm, bottomMargin=20*mm,
    )
    story = []

    # --- En-tête ---
    story.append(build_header_table(data, "FACTURE", num, date_str))
    story.append(Spacer(1, 6*mm))

    # --- Objet ---
    story.append(Paragraph(
        f"<b>Objet :</b> {data['objet']}",
        make_style(size=9, color=BLUE_DARK)
    ))
    story.append(Spacer(1, 4*mm))

    # --- Tableau articles ---
    col_w = [10*mm, 78*mm, 16*mm, 10*mm, 24*mm, 24*mm]
    header_row = [
        Paragraph("<b>N°</b>",           make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>Désignation</b>",   make_style(size=8, color=WHITE, bold=True)),
        Paragraph("<b>Qté</b>",           make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>Unité</b>",         make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>P.U HT</b>",        make_style(size=8, color=WHITE, bold=True, align="RIGHT")),
        Paragraph("<b>Total HT</b>",      make_style(size=8, color=WHITE, bold=True, align="RIGHT")),
    ]
    rows = [header_row]

    for i, item in enumerate(data["items"]):
        bg = BLUE_LIGHT if i % 2 == 0 else WHITE
        rows.append([
            Paragraph(item["num"],              make_style(size=8, align="CENTER")),
            Paragraph(item["designation"],      make_style(size=8)),
            Paragraph(str(int(item["quantite"])), make_style(size=8, align="CENTER")),
            Paragraph(item["unite"],            make_style(size=8, align="CENTER")),
            Paragraph(fmt_mad(item["prix_unitaire"]), make_style(size=8, align="RIGHT")),
            Paragraph(fmt_mad(item["prix_total"]),    make_style(size=8, align="RIGHT")),
        ])

    tbl_articles = Table(rows, colWidths=col_w, repeatRows=1)
    tbl_articles.setStyle(TableStyle([
        ("BACKGROUND",   (0, 0), (-1, 0), BLUE_DARK),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [BLUE_LIGHT, WHITE]),
        ("GRID",         (0, 0), (-1, -1), 0.4, GRAY_LINE),
        ("VALIGN",       (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING",   (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 4),
        ("LEFTPADDING",  (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
    ]))
    story.append(tbl_articles)
    story.append(Spacer(1, 4*mm))

    # --- Totaux ---
    totals = data["totals"]
    totals_data = [
        ["Montant Total HT",   fmt_mad(totals["ht"])],
        [f"TVA ({totals['tva_rate']}%)", fmt_mad(totals["tva"])],
        ["Montant Total TTC",  fmt_mad(totals["ttc"])],
    ]
    tbl_totals = Table(totals_data, colWidths=[100*mm, 50*mm], hAlign="RIGHT")
    tbl_totals.setStyle(TableStyle([
        ("FONTNAME",     (0, 0), (0, -1), BASE_FONT),
        ("FONTNAME",     (1, 0), (1, -1), BOLD_FONT),
        ("FONTSIZE",     (0, 0), (-1, -1), 9),
        ("ALIGN",        (1, 0), (1, -1), "RIGHT"),
        ("TOPPADDING",   (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 3),
        ("LINEABOVE",    (0, 2), (-1, 2), 1.5, BLUE_DARK),
        ("BACKGROUND",   (0, 2), (-1, 2), BLUE_LIGHT),
        ("FONTNAME",     (0, 2), (-1, 2), BOLD_FONT),
        ("FONTSIZE",     (0, 2), (-1, 2), 10),
        ("TEXTCOLOR",    (0, 2), (-1, 2), BLUE_DARK),
    ]))
    story.append(tbl_totals)
    story.append(Spacer(1, 4*mm))

    # --- Arrêté en lettres ---
    lettres = num_to_words_fr(totals["ttc"])
    story.append(Paragraph(
        f"<b>Arrêté le présent devis à la somme TTC de :</b> {lettres}",
        make_style(size=8, color=GRAY_TEXT)
    ))
    story.append(Spacer(1, 8*mm))

    # --- Signatures ---
    sig_data = [
        [
            Paragraph("Le Fournisseur", make_style(size=9, bold=True, color=BLUE_DARK, align="CENTER")),
            Paragraph("Le Client / Maître d'ouvrage", make_style(size=9, bold=True, color=BLUE_DARK, align="CENTER")),
        ],
        [
            Paragraph(f"<b>{data['fournisseur_nom']}</b>", make_style(size=9, align="CENTER")),
            Paragraph(f"<b>{data['client_nom']}</b>", make_style(size=9, align="CENTER")),
        ],
        [
            Paragraph("<br/><br/><br/>Signature & Cachet", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
            Paragraph("<br/><br/><br/>Signature & Cachet", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
        ],
    ]
    tbl_sig = Table(sig_data, colWidths=[85*mm, 85*mm], hAlign="CENTER")
    tbl_sig.setStyle(TableStyle([
        ("BOX",          (0, 0), (0, -1), 0.5, GRAY_LINE),
        ("BOX",          (1, 0), (1, -1), 0.5, GRAY_LINE),
        ("BACKGROUND",   (0, 0), (0, 0),  BLUE_LIGHT),
        ("BACKGROUND",   (1, 0), (1, 0),  BLUE_LIGHT),
        ("ALIGN",        (0, 0), (-1, -1), "CENTER"),
        ("VALIGN",       (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING",   (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 4),
        ("COLPADDING",   (0, 0), (-1, -1), 10),
    ]))
    story.append(tbl_sig)

    doc.build(story, onFirstPage=_add_footer, onLaterPages=_add_footer)
    print(f"[OK] Facture générée : {output_path}")


# ---------------------------------------------------------------------------
# Génération BL (Bon de Livraison)
# ---------------------------------------------------------------------------
def generate_bl(data: dict, output_path: str, num: str = None, date_str: str = None):
    num      = num      or f"BL-{datetime.now().strftime('%Y%m')}-001"
    date_str = date_str or datetime.now().strftime("%d/%m/%Y")

    doc = SimpleDocTemplate(
        output_path, pagesize=A4,
        leftMargin=15*mm, rightMargin=15*mm,
        topMargin=15*mm, bottomMargin=20*mm,
    )
    story = []

    # --- En-tête ---
    story.append(build_header_table(data, "BON DE\nLIVRAISON", num, date_str))
    story.append(Spacer(1, 6*mm))

    # --- Infos livraison ---
    info_data = [
        [
            Paragraph("<b>Lieu de livraison :</b>", make_style(size=9, color=GRAY_TEXT)),
            Paragraph(data["lieu_livraison"], make_style(size=9, bold=True)),
            Paragraph("<b>Délai :</b>", make_style(size=9, color=GRAY_TEXT)),
            Paragraph(data["delai_livraison"], make_style(size=9, bold=True)),
        ],
    ]
    tbl_info = Table(info_data, colWidths=[38*mm, 55*mm, 22*mm, 50*mm])
    tbl_info.setStyle(TableStyle([
        ("BACKGROUND",   (0, 0), (-1, -1), BLUE_LIGHT),
        ("BOX",          (0, 0), (-1, -1), 0.5, GRAY_LINE),
        ("VALIGN",       (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING",   (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 5),
        ("LEFTPADDING",  (0, 0), (-1, -1), 6),
    ]))
    story.append(tbl_info)
    story.append(Spacer(1, 4*mm))

    # --- Objet ---
    story.append(Paragraph(
        f"<b>Objet :</b> {data['objet']}",
        make_style(size=9, color=BLUE_DARK)
    ))
    story.append(Spacer(1, 4*mm))

    # --- Tableau articles (sans prix) ---
    col_w = [10*mm, 100*mm, 22*mm, 15*mm, 18*mm]
    header_row = [
        Paragraph("<b>N°</b>",         make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>Désignation</b>", make_style(size=8, color=WHITE, bold=True)),
        Paragraph("<b>Qté prévue</b>",  make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>Unité</b>",        make_style(size=8, color=WHITE, bold=True, align="CENTER")),
        Paragraph("<b>Qté livrée</b>",   make_style(size=8, color=WHITE, bold=True, align="CENTER")),
    ]
    rows = [header_row]

    for i, item in enumerate(data["items"]):
        rows.append([
            Paragraph(item["num"],              make_style(size=8, align="CENTER")),
            Paragraph(item["designation"],      make_style(size=8)),
            Paragraph(str(int(item["quantite"])), make_style(size=9, bold=True, align="CENTER")),
            Paragraph(item["unite"],            make_style(size=8, align="CENTER")),
            Paragraph("",                       make_style(size=8, align="CENTER")),
        ])

    tbl_articles = Table(rows, colWidths=col_w, repeatRows=1)
    tbl_articles.setStyle(TableStyle([
        ("BACKGROUND",   (0, 0), (-1, 0), BLUE_DARK),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [BLUE_LIGHT, WHITE]),
        ("GRID",         (0, 0), (-1, -1), 0.4, GRAY_LINE),
        ("BOX",          (4, 1), (4, -1), 1.0, BLUE_MID),
        ("VALIGN",       (0, 0), (-1, -1), "MIDDLE"),
        ("ALIGN",        (2, 0), (-1, -1), "CENTER"),
        ("TOPPADDING",   (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 5),
        ("LEFTPADDING",  (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
    ]))
    story.append(tbl_articles)
    story.append(Spacer(1, 8*mm))

    # --- Observations ---
    story.append(Paragraph("<b>Observations :</b>", make_style(size=9, color=BLUE_DARK)))
    obs_tbl = Table([[""]], colWidths=[165*mm])
    obs_tbl.setStyle(TableStyle([
        ("BOX",          (0, 0), (-1, -1), 0.5, GRAY_LINE),
        ("MINROWHEIGHT", (0, 0), (-1, -1), 18*mm),
    ]))
    story.append(obs_tbl)
    story.append(Spacer(1, 6*mm))

    # --- Signatures ---
    sig_data = [
        [
            Paragraph("Le Livreur", make_style(size=9, bold=True, color=BLUE_DARK, align="CENTER")),
            Paragraph("Le Réceptionnaire", make_style(size=9, bold=True, color=BLUE_DARK, align="CENTER")),
            Paragraph("Visa Maître d'ouvrage", make_style(size=9, bold=True, color=BLUE_DARK, align="CENTER")),
        ],
        [
            Paragraph(f"<b>{data['fournisseur_nom']}</b>", make_style(size=8, align="CENTER")),
            Paragraph(f"<b>{data['client_nom']}</b>", make_style(size=8, align="CENTER")),
            Paragraph("", make_style(size=8, align="CENTER")),
        ],
        [
            Paragraph("Date : ___________", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
            Paragraph("Date : ___________", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
            Paragraph("Date : ___________", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
        ],
        [
            Paragraph("<br/><br/><br/>Signature & Cachet", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
            Paragraph("<br/><br/><br/>Signature & Cachet", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
            Paragraph("<br/><br/><br/>Signature & Cachet", make_style(size=8, color=GRAY_TEXT, align="CENTER")),
        ],
    ]
    tbl_sig = Table(sig_data, colWidths=[54*mm, 54*mm, 54*mm], hAlign="CENTER")
    tbl_sig.setStyle(TableStyle([
        ("BOX",          (0, 0), (0, -1), 0.5, GRAY_LINE),
        ("BOX",          (1, 0), (1, -1), 0.5, GRAY_LINE),
        ("BOX",          (2, 0), (2, -1), 0.5, GRAY_LINE),
        ("BACKGROUND",   (0, 0), (-1, 0), BLUE_LIGHT),
        ("ALIGN",        (0, 0), (-1, -1), "CENTER"),
        ("VALIGN",       (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING",   (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 4),
        ("LINEBELOW",    (0, 0), (-1, 0), 0.5, GRAY_LINE),
    ]))
    story.append(tbl_sig)

    doc.build(story, onFirstPage=_add_footer, onLaterPages=_add_footer)
    print(f"[OK] BL généré : {output_path}")


# ---------------------------------------------------------------------------
# Pied de page
# ---------------------------------------------------------------------------
def _add_footer(canvas, doc):
    canvas.saveState()
    canvas.setFont(BASE_FONT, 7)
    canvas.setFillColor(GRAY_TEXT)
    page_w = A4[0]
    y = 10 * mm
    canvas.drawString(15*mm, y, "JAWAD LIGHT — jawadlight1@gmail.com — 0620696967")
    canvas.drawRightString(page_w - 15*mm, y,
                           f"Page {canvas.getPageNumber()}")
    canvas.setStrokeColor(GRAY_LINE)
    canvas.setLineWidth(0.5)
    canvas.line(15*mm, y + 4*mm, page_w - 15*mm, y + 4*mm)
    canvas.restoreState()


# ---------------------------------------------------------------------------
# Point d'entrée
# ---------------------------------------------------------------------------
def main():
    if len(sys.argv) < 2:
        pdf_path = r"C:\Users\hp\Downloads\Devis-2.pdf"
        print(f"Utilisation du devis par défaut : {pdf_path}")
    else:
        pdf_path = sys.argv[1]

    if not Path(pdf_path).exists():
        print(f"Erreur : fichier introuvable → {pdf_path}")
        sys.exit(1)

    print(f"Lecture du devis : {pdf_path}")
    parser = DevisParser(pdf_path)
    data   = parser.parse()

    out_dir  = Path(pdf_path).parent
    stem     = Path(pdf_path).stem
    date_str = datetime.now().strftime("%d/%m/%Y")
    ts       = datetime.now().strftime("%Y%m%d")

    fac_path = str(out_dir / f"Facture_{stem}_{ts}.pdf")
    bl_path  = str(out_dir / f"BL_{stem}_{ts}.pdf")

    generate_facture(data, fac_path, date_str=date_str)
    generate_bl(data, bl_path, date_str=date_str)

    print(f"\nDocuments générés dans : {out_dir}")


if __name__ == "__main__":
    main()
