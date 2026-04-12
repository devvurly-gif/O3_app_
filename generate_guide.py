#!/usr/bin/env python3
"""
O3 App - Guide de Formation Client
Generates a professional PDF training guide with illustrations
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm, cm
from reportlab.lib.colors import HexColor, white, black
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, Image, Flowable
)
from reportlab.pdfgen import canvas
from reportlab.graphics.shapes import Drawing, Rect, Circle, Line, String, Group, Polygon
from reportlab.graphics import renderPDF
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import os
import math

# ── Colors ──────────────────────────────────────────────
BLUE_PRIMARY   = HexColor('#2563eb')
BLUE_DARK      = HexColor('#1e40af')
BLUE_LIGHT     = HexColor('#dbeafe')
BLUE_50        = HexColor('#eff6ff')
GREEN          = HexColor('#059669')
GREEN_LIGHT    = HexColor('#d1fae5')
ORANGE         = HexColor('#ea580c')
ORANGE_LIGHT   = HexColor('#ffedd5')
RED            = HexColor('#dc2626')
RED_LIGHT      = HexColor('#fee2e2')
PURPLE         = HexColor('#7c3aed')
PURPLE_LIGHT   = HexColor('#ede9fe')
GRAY_900       = HexColor('#111827')
GRAY_700       = HexColor('#374151')
GRAY_500       = HexColor('#6b7280')
GRAY_400       = HexColor('#9ca3af')
GRAY_300       = HexColor('#d1d5db')
GRAY_200       = HexColor('#e5e7eb')
GRAY_100       = HexColor('#f3f4f6')
GRAY_50        = HexColor('#f9fafb')
INDIGO         = HexColor('#4f46e5')
AMBER          = HexColor('#d97706')

WIDTH, HEIGHT = A4

# ── Custom Flowables ────────────────────────────────────

class RoundedBox(Flowable):
    """A rounded rectangle with optional icon, title and description"""
    def __init__(self, width, height, bg_color, border_color, icon_text, title, desc, icon_bg=None):
        Flowable.__init__(self)
        self.box_width = width
        self.box_height = height
        self.bg_color = bg_color
        self.border_color = border_color
        self.icon_text = icon_text
        self.title = title
        self.desc = desc
        self.icon_bg = icon_bg or BLUE_PRIMARY
        self.width = width
        self.height = height

    def draw(self):
        c = self.canv
        # Background
        c.setFillColor(self.bg_color)
        c.setStrokeColor(self.border_color)
        c.setLineWidth(1)
        c.roundRect(0, 0, self.box_width, self.box_height, 8, fill=1, stroke=1)

        # Icon circle
        cx, cy = 30, self.box_height - 28
        c.setFillColor(self.icon_bg)
        c.circle(cx, cy, 14, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 12)
        c.drawCentredString(cx, cy - 4, self.icon_text)

        # Title
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 11)
        c.drawString(52, self.box_height - 23, self.title)

        # Description
        c.setFillColor(GRAY_700)
        c.setFont("Helvetica", 9)
        lines = self._wrap_text(self.desc, self.box_width - 65, 9)
        y = self.box_height - 40
        for line in lines[:3]:
            c.drawString(52, y, line)
            y -= 13


    def _wrap_text(self, text, max_width, font_size):
        words = text.split()
        lines = []
        current = ""
        for w in words:
            test = current + " " + w if current else w
            from reportlab.pdfbase.pdfmetrics import stringWidth
            if stringWidth(test, "Helvetica", font_size) < max_width:
                current = test
            else:
                if current:
                    lines.append(current)
                current = w
        if current:
            lines.append(current)
        return lines


class IconCard(Flowable):
    """Small card with icon number and text"""
    def __init__(self, width, height, number, title, desc, color):
        Flowable.__init__(self)
        self.box_width = width
        self.box_height = height
        self.number = number
        self.title = title
        self.desc = desc
        self.color = color
        self.width = width
        self.height = height

    def draw(self):
        c = self.canv
        # Border
        c.setStrokeColor(GRAY_200)
        c.setFillColor(white)
        c.setLineWidth(1)
        c.roundRect(0, 0, self.box_width, self.box_height, 8, fill=1, stroke=1)

        # Number badge
        c.setFillColor(self.color)
        c.circle(28, self.box_height - 28, 16, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 14)
        c.drawCentredString(28, self.box_height - 33, str(self.number))

        # Title
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 10)
        c.drawString(52, self.box_height - 25, self.title)

        # Desc
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica", 8)
        lines = self._wrap_text(self.desc, self.box_width - 65, 8)
        y = self.box_height - 40
        for line in lines[:2]:
            c.drawString(52, y, line)
            y -= 12

    def _wrap_text(self, text, max_width, font_size):
        words = text.split()
        lines = []
        current = ""
        for w in words:
            test = current + " " + w if current else w
            from reportlab.pdfbase.pdfmetrics import stringWidth
            if stringWidth(test, "Helvetica", font_size) < max_width:
                current = test
            else:
                if current:
                    lines.append(current)
                current = w
        if current:
            lines.append(current)
        return lines


class ScreenMockup(Flowable):
    """A simplified screen mockup illustration"""
    def __init__(self, width, height, title, elements=None, mockup_type="dashboard"):
        Flowable.__init__(self)
        self.box_width = width
        self.box_height = height
        self.title = title
        self.elements = elements or []
        self.mockup_type = mockup_type
        self.width = width
        self.height = height

    def draw(self):
        c = self.canv
        # Window frame
        c.setStrokeColor(GRAY_300)
        c.setFillColor(white)
        c.setLineWidth(1.5)
        c.roundRect(0, 0, self.box_width, self.box_height, 10, fill=1, stroke=1)

        # Title bar
        c.setFillColor(GRAY_100)
        c.roundRect(0, self.box_height - 30, self.box_width, 30, 10, fill=1, stroke=0)
        c.setFillColor(GRAY_300)
        c.rect(0, self.box_height - 30, self.box_width, 10, fill=1, stroke=0)

        # Window dots
        for i, color in enumerate([RED, AMBER, GREEN]):
            c.setFillColor(color)
            c.circle(16 + i * 16, self.box_height - 15, 4, fill=1, stroke=0)

        # Title in bar
        c.setFillColor(GRAY_700)
        c.setFont("Helvetica-Bold", 8)
        c.drawCentredString(self.box_width / 2, self.box_height - 18, self.title)

        if self.mockup_type == "dashboard":
            self._draw_dashboard(c)
        elif self.mockup_type == "table":
            self._draw_table(c)
        elif self.mockup_type == "pos":
            self._draw_pos(c)
        elif self.mockup_type == "document":
            self._draw_document(c)
        elif self.mockup_type == "settings":
            self._draw_settings(c)

    def _draw_dashboard(self, c):
        bh = self.box_height
        bw = self.box_width
        # Sidebar
        c.setFillColor(GRAY_900)
        c.rect(0, 0, 60, bh - 30, fill=1, stroke=0)
        # Sidebar items
        for i in range(6):
            c.setFillColor(HexColor('#374151'))
            c.roundRect(8, bh - 65 - i * 22, 44, 16, 4, fill=1, stroke=0)
        # Active item
        c.setFillColor(BLUE_PRIMARY)
        c.roundRect(8, bh - 65, 44, 16, 4, fill=1, stroke=0)

        # KPI cards
        card_w = (bw - 60 - 40) / 4
        for i in range(4):
            x = 70 + i * (card_w + 7)
            c.setFillColor(white)
            c.setStrokeColor(GRAY_200)
            c.roundRect(x, bh - 80, card_w, 40, 4, fill=1, stroke=1)
            c.setFillColor(GRAY_400)
            c.roundRect(x + 6, bh - 52, card_w * 0.6, 6, 2, fill=1, stroke=0)
            c.setFillColor(BLUE_PRIMARY if i == 0 else GREEN if i == 1 else ORANGE if i == 2 else PURPLE)
            c.roundRect(x + 6, bh - 66, card_w * 0.4, 8, 2, fill=1, stroke=0)

        # Chart area
        c.setFillColor(white)
        c.setStrokeColor(GRAY_200)
        chart_w = (bw - 60 - 30) * 0.6
        c.roundRect(70, 20, chart_w, bh - 110, 4, fill=1, stroke=1)
        # Chart bars
        bar_count = 7
        bar_w = (chart_w - 30) / bar_count
        for i in range(bar_count):
            h = 15 + (i * 7 + 20) % 50
            c.setFillColor(BLUE_PRIMARY)
            c.setFillAlpha(0.7)
            c.roundRect(80 + i * bar_w, 30, bar_w * 0.6, h, 2, fill=1, stroke=0)
        c.setFillAlpha(1)

        # Side panel
        sp_x = 70 + chart_w + 10
        sp_w = bw - sp_x - 10
        c.setFillColor(white)
        c.setStrokeColor(GRAY_200)
        c.roundRect(sp_x, 20, sp_w, bh - 110, 4, fill=1, stroke=1)
        for i in range(4):
            c.setFillColor(GRAY_100)
            c.roundRect(sp_x + 8, bh - 120 - i * 22, sp_w - 16, 16, 3, fill=1, stroke=0)

    def _draw_table(self, c):
        bh = self.box_height
        bw = self.box_width
        # Sidebar
        c.setFillColor(GRAY_900)
        c.rect(0, 0, 60, bh - 30, fill=1, stroke=0)
        for i in range(6):
            c.setFillColor(HexColor('#374151'))
            c.roundRect(8, bh - 65 - i * 22, 44, 16, 4, fill=1, stroke=0)

        # Search bar
        c.setFillColor(white)
        c.setStrokeColor(GRAY_300)
        c.roundRect(70, bh - 65, bw - 160, 25, 6, fill=1, stroke=1)

        # Add button
        c.setFillColor(BLUE_PRIMARY)
        c.roundRect(bw - 80, bh - 65, 70, 25, 6, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 7)
        c.drawCentredString(bw - 45, bh - 57, "+ Ajouter")

        # Table header
        c.setFillColor(GRAY_50)
        c.rect(70, bh - 95, bw - 80, 22, fill=1, stroke=0)
        c.setStrokeColor(GRAY_200)
        c.line(70, bh - 95, bw - 10, bh - 95)

        cols = ["Ref", "Client", "Date", "Montant", "Statut", "Actions"]
        col_w = (bw - 80) / len(cols)
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica-Bold", 7)
        for i, col in enumerate(cols):
            c.drawString(75 + i * col_w, bh - 88, col)

        # Table rows
        for row in range(5):
            y = bh - 95 - (row + 1) * 22
            if row % 2 == 0:
                c.setFillColor(white)
            else:
                c.setFillColor(GRAY_50)
            c.rect(70, y, bw - 80, 22, fill=1, stroke=0)
            c.setStrokeColor(GRAY_200)
            c.line(70, y, bw - 10, y)

            # Row content placeholders
            c.setFillColor(GRAY_400)
            for i in range(5):
                w = [35, 55, 40, 35, 30][i]
                c.roundRect(75 + i * col_w, y + 7, w, 8, 2, fill=1, stroke=0)
            # Status badge
            c.setFillColor(GREEN_LIGHT)
            c.roundRect(75 + 4 * col_w, y + 5, 35, 12, 4, fill=1, stroke=0)
            c.setFillColor(GREEN)
            c.setFont("Helvetica", 6)
            c.drawString(79 + 4 * col_w, y + 8, "Actif")

    def _draw_pos(self, c):
        bh = self.box_height
        bw = self.box_width

        divider = bw * 0.6

        # Left panel (products)
        c.setFillColor(GRAY_50)
        c.rect(0, 0, divider, bh - 30, fill=1, stroke=0)

        # Category tabs
        tabs = ["Tous", "Electronique", "Accessoires"]
        tx = 10
        for i, tab in enumerate(tabs):
            color = BLUE_PRIMARY if i == 0 else GRAY_200
            c.setFillColor(color)
            tw = 50 + len(tab) * 2
            c.roundRect(tx, bh - 60, tw, 20, 4, fill=1, stroke=0)
            c.setFillColor(white if i == 0 else GRAY_500)
            c.setFont("Helvetica", 7)
            c.drawCentredString(tx + tw/2, bh - 54, tab)
            tx += tw + 5

        # Product grid
        card_size = (divider - 30) / 3
        for row in range(2):
            for col in range(3):
                x = 8 + col * (card_size + 3)
                y = bh - 80 - row * (card_size + 5) - card_size
                c.setFillColor(white)
                c.setStrokeColor(GRAY_200)
                c.roundRect(x, y, card_size, card_size, 4, fill=1, stroke=1)
                # Image placeholder
                c.setFillColor(GRAY_100)
                c.roundRect(x + 4, y + card_size * 0.35, card_size - 8, card_size * 0.55, 3, fill=1, stroke=0)
                # Text placeholders
                c.setFillColor(GRAY_400)
                c.roundRect(x + 4, y + card_size * 0.15, card_size * 0.7, 6, 2, fill=1, stroke=0)
                c.setFillColor(BLUE_PRIMARY)
                c.roundRect(x + 4, y + 4, card_size * 0.4, 6, 2, fill=1, stroke=0)

        # Right panel (cart)
        c.setFillColor(white)
        c.rect(divider, 0, bw - divider, bh - 30, fill=1, stroke=0)
        c.setStrokeColor(GRAY_200)
        c.line(divider, 0, divider, bh - 30)

        # Cart header
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 9)
        c.drawString(divider + 10, bh - 50, "Panier (3)")

        # Cart items
        for i in range(3):
            y = bh - 75 - i * 28
            c.setFillColor(GRAY_100)
            c.roundRect(divider + 8, y, bw - divider - 18, 22, 3, fill=1, stroke=0)
            c.setFillColor(GRAY_500)
            c.roundRect(divider + 14, y + 8, 50, 6, 2, fill=1, stroke=0)
            c.setFillColor(BLUE_PRIMARY)
            c.roundRect(bw - 50, y + 8, 30, 6, 2, fill=1, stroke=0)

        # Total
        c.setFillColor(BLUE_PRIMARY)
        c.roundRect(divider + 8, 10, bw - divider - 18, 30, 6, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 9)
        c.drawCentredString(divider + (bw - divider) / 2, 21, "Valider 1,250.00 MAD")

    def _draw_document(self, c):
        bh = self.box_height
        bw = self.box_width
        # Sidebar
        c.setFillColor(GRAY_900)
        c.rect(0, 0, 60, bh - 30, fill=1, stroke=0)

        # Document header
        c.setFillColor(white)
        c.setStrokeColor(GRAY_200)
        c.roundRect(70, bh - 95, bw - 80, 55, 6, fill=1, stroke=1)

        # Type badge
        c.setFillColor(BLUE_LIGHT)
        c.roundRect(78, bh - 58, 60, 14, 4, fill=1, stroke=0)
        c.setFillColor(BLUE_PRIMARY)
        c.setFont("Helvetica-Bold", 7)
        c.drawString(84, bh - 55, "Facture")

        # Ref
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 10)
        c.drawString(145, bh - 57, "FA-2026-0042")

        # Client info
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica", 8)
        c.drawString(78, bh - 78, "Client: Jadever Fes")
        c.drawString(200, bh - 78, "Date: 31/03/2026")

        # Status
        c.setFillColor(GREEN_LIGHT)
        c.roundRect(bw - 80, bh - 60, 50, 16, 6, fill=1, stroke=0)
        c.setFillColor(GREEN)
        c.setFont("Helvetica-Bold", 7)
        c.drawCentredString(bw - 55, bh - 55, "Paye")

        # Lines table
        c.setFillColor(GRAY_50)
        c.rect(70, bh - 120, bw - 80, 18, fill=1, stroke=0)
        headers = ["Produit", "Qte", "P.U.", "Remise", "Total HT"]
        col_w = (bw - 80) / 5
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica-Bold", 7)
        for i, h in enumerate(headers):
            c.drawString(75 + i * col_w, bh - 115, h)

        for row in range(3):
            y = bh - 120 - (row + 1) * 18
            c.setStrokeColor(GRAY_200)
            c.line(70, y, bw - 10, y)
            c.setFillColor(GRAY_400)
            for i in range(5):
                c.roundRect(75 + i * col_w, y + 5, [55, 20, 30, 25, 35][i], 7, 2, fill=1, stroke=0)

        # Totals
        y_tot = bh - 190
        c.setFillColor(GRAY_50)
        c.roundRect(bw - 150, y_tot, 140, 50, 4, fill=1, stroke=0)
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica", 7)
        c.drawString(bw - 145, y_tot + 36, "Total HT:")
        c.drawString(bw - 145, y_tot + 22, "TVA 20%:")
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 8)
        c.drawString(bw - 145, y_tot + 6, "Total TTC:")

    def _draw_settings(self, c):
        bh = self.box_height
        bw = self.box_width
        # Sidebar
        c.setFillColor(GRAY_900)
        c.rect(0, 0, 60, bh - 30, fill=1, stroke=0)

        # Settings form
        fields = ["Nom de l'entreprise", "Telephone", "Email", "ICE", "Adresse"]
        for i, field in enumerate(fields):
            y = bh - 70 - i * 35
            c.setFillColor(GRAY_500)
            c.setFont("Helvetica", 7)
            c.drawString(75, y + 15, field)
            c.setFillColor(white)
            c.setStrokeColor(GRAY_300)
            c.roundRect(75, y - 5, bw - 100, 18, 4, fill=1, stroke=1)
            c.setFillColor(GRAY_300)
            c.roundRect(80, y, bw * 0.4, 8, 2, fill=1, stroke=0)

        # Save button
        c.setFillColor(BLUE_PRIMARY)
        c.roundRect(75, 15, 80, 25, 6, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 8)
        c.drawCentredString(115, 24, "Enregistrer")


class SectionHeader(Flowable):
    """A section header with number badge and colored line"""
    def __init__(self, width, number, title, color=BLUE_PRIMARY):
        Flowable.__init__(self)
        self.box_width = width
        self.number = number
        self.title = title
        self.color = color
        self.width = width
        self.height = 35

    def draw(self):
        c = self.canv
        # Number badge
        c.setFillColor(self.color)
        c.circle(17, 17, 17, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 14)
        c.drawCentredString(17, 12, str(self.number))

        # Title
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 16)
        c.drawString(42, 10, self.title)

        # Line
        c.setStrokeColor(self.color)
        c.setLineWidth(2)
        from reportlab.pdfbase.pdfmetrics import stringWidth
        tw = stringWidth(self.title, "Helvetica-Bold", 16)
        c.line(42, 6, 42 + tw, 6)


class FeatureRow(Flowable):
    """A feature description with icon"""
    def __init__(self, width, icon_char, title, desc, color=BLUE_PRIMARY):
        Flowable.__init__(self)
        self.box_width = width
        self.icon_char = icon_char
        self.title = title
        self.desc = desc
        self.color = color
        self.width = width
        self.height = 45

    def draw(self):
        c = self.canv
        # Icon
        c.setFillColor(self.color)
        c.setFillAlpha(0.1)
        c.roundRect(0, 5, 36, 36, 8, fill=1, stroke=0)
        c.setFillAlpha(1)
        c.setFillColor(self.color)
        c.setFont("Helvetica-Bold", 16)
        c.drawCentredString(18, 16, self.icon_char)

        # Title
        c.setFillColor(GRAY_900)
        c.setFont("Helvetica-Bold", 10)
        c.drawString(46, 28, self.title)

        # Desc
        c.setFillColor(GRAY_500)
        c.setFont("Helvetica", 8)
        # Wrap text
        words = self.desc.split()
        lines = []
        current = ""
        for w in words:
            test = current + " " + w if current else w
            from reportlab.pdfbase.pdfmetrics import stringWidth
            if stringWidth(test, "Helvetica", 8) < self.box_width - 55:
                current = test
            else:
                if current: lines.append(current)
                current = w
        if current: lines.append(current)
        y = 15
        for line in lines[:2]:
            c.drawString(46, y, line)
            y -= 11


class TipBox(Flowable):
    """A tip/note box with colored left border"""
    def __init__(self, width, text, tip_type="info"):
        Flowable.__init__(self)
        self.box_width = width
        self.text = text
        self.tip_type = tip_type
        colors = {
            "info": (BLUE_PRIMARY, BLUE_50),
            "success": (GREEN, GREEN_LIGHT),
            "warning": (ORANGE, ORANGE_LIGHT),
            "danger": (RED, RED_LIGHT),
        }
        self.accent, self.bg = colors.get(tip_type, colors["info"])
        self.width = width
        self.height = 50

    def draw(self):
        c = self.canv
        # Background
        c.setFillColor(self.bg)
        c.roundRect(0, 0, self.box_width, self.height, 6, fill=1, stroke=0)
        # Left accent
        c.setFillColor(self.accent)
        c.rect(0, 0, 4, self.height, fill=1, stroke=0)

        # Icon
        icons = {"info": "i", "success": "!", "warning": "!", "danger": "X"}
        c.setFillColor(self.accent)
        c.circle(22, self.height / 2, 10, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 12)
        c.drawCentredString(22, self.height / 2 - 4, icons.get(self.tip_type, "i"))

        # Text
        c.setFillColor(GRAY_700)
        c.setFont("Helvetica", 9)
        words = self.text.split()
        lines = []
        current = ""
        for w in words:
            test = current + " " + w if current else w
            from reportlab.pdfbase.pdfmetrics import stringWidth
            if stringWidth(test, "Helvetica", 9) < self.box_width - 50:
                current = test
            else:
                if current: lines.append(current)
                current = w
        if current: lines.append(current)
        y = self.height / 2 + (len(lines) - 1) * 6
        for line in lines[:3]:
            c.drawString(40, y, line)
            y -= 13


# ── Page Templates ──────────────────────────────────────

def cover_page(canvas_obj, doc):
    """Draw the cover page"""
    c = canvas_obj
    w, h = A4

    # Background gradient effect
    c.setFillColor(BLUE_DARK)
    c.rect(0, h * 0.45, w, h * 0.55, fill=1, stroke=0)

    # Decorative circles
    c.setFillColor(BLUE_PRIMARY)
    c.setFillAlpha(0.3)
    c.circle(w * 0.85, h * 0.75, 80, fill=1, stroke=0)
    c.circle(w * 0.15, h * 0.55, 50, fill=1, stroke=0)
    c.circle(w * 0.7, h * 0.9, 30, fill=1, stroke=0)
    c.setFillAlpha(0.15)
    c.circle(w * 0.3, h * 0.85, 120, fill=1, stroke=0)
    c.setFillAlpha(1)

    # O3 Logo
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 48)
    c.drawCentredString(w / 2, h * 0.78, "O3")
    c.setFont("Helvetica", 18)
    c.drawCentredString(w / 2, h * 0.73, "App")

    # Decorative line
    c.setStrokeColor(white)
    c.setLineWidth(2)
    c.setStrokeAlpha(0.5)
    c.line(w * 0.3, h * 0.69, w * 0.7, h * 0.69)
    c.setStrokeAlpha(1)

    # Title
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 28)
    c.drawCentredString(w / 2, h * 0.62, "Guide de Formation")
    c.setFont("Helvetica", 16)
    c.setFillAlpha(0.85)
    c.drawCentredString(w / 2, h * 0.58, "Application de Gestion Commerciale")
    c.setFillAlpha(1)

    # Bottom section (white)
    c.setFillColor(white)
    c.rect(0, 0, w, h * 0.45, fill=1, stroke=0)

    # Feature icons in bottom
    features = [
        ("Ventes", BLUE_PRIMARY, "V"),
        ("Achats", GREEN, "A"),
        ("Stock", ORANGE, "S"),
        ("POS", PURPLE, "P"),
        ("Rapports", INDIGO, "R"),
    ]

    icon_y = h * 0.32
    spacing = w / (len(features) + 1)
    for i, (label, color, letter) in enumerate(features):
        x = spacing * (i + 1)
        # Circle
        c.setFillColor(color)
        c.setFillAlpha(0.1)
        c.circle(x, icon_y, 25, fill=1, stroke=0)
        c.setFillAlpha(1)
        c.setFillColor(color)
        c.circle(x, icon_y, 18, fill=1, stroke=0)
        c.setFillColor(white)
        c.setFont("Helvetica-Bold", 14)
        c.drawCentredString(x, icon_y - 5, letter)
        # Label
        c.setFillColor(GRAY_700)
        c.setFont("Helvetica", 9)
        c.drawCentredString(x, icon_y - 35, label)

    # Version info
    c.setFillColor(GRAY_400)
    c.setFont("Helvetica", 10)
    c.drawCentredString(w / 2, h * 0.08, "Version 1.0 — Mars 2026")
    c.setFont("Helvetica", 9)
    c.drawCentredString(w / 2, h * 0.05, "www.o3app.ma")


def header_footer(canvas_obj, doc):
    """Standard page header/footer"""
    c = canvas_obj
    w, h = A4

    # Header line
    c.setStrokeColor(BLUE_PRIMARY)
    c.setLineWidth(2)
    c.line(30, h - 35, w - 30, h - 35)

    # Header text
    c.setFillColor(BLUE_PRIMARY)
    c.setFont("Helvetica-Bold", 9)
    c.drawString(30, h - 28, "O3 App")
    c.setFillColor(GRAY_400)
    c.setFont("Helvetica", 8)
    c.drawRightString(w - 30, h - 28, "Guide de Formation")

    # Footer
    c.setStrokeColor(GRAY_200)
    c.setLineWidth(0.5)
    c.line(30, 35, w - 30, 35)

    c.setFillColor(GRAY_400)
    c.setFont("Helvetica", 8)
    c.drawString(30, 22, "O3 App — Gestion Commerciale")
    c.drawRightString(w - 30, 22, f"Page {doc.page}")


# ── Build PDF ───────────────────────────────────────────

def build_pdf():
    output_path = os.path.join(os.path.dirname(__file__), "Guide_Formation_O3App.pdf")

    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        topMargin=45,
        bottomMargin=50,
        leftMargin=35,
        rightMargin=35,
    )

    content_width = WIDTH - 70  # 35mm margins each side

    styles = getSampleStyleSheet()

    # Custom styles
    title_style = ParagraphStyle(
        'CustomTitle', parent=styles['Title'],
        fontName='Helvetica-Bold', fontSize=22, textColor=GRAY_900,
        spaceAfter=6, alignment=TA_LEFT
    )

    h1_style = ParagraphStyle(
        'H1', parent=styles['Heading1'],
        fontName='Helvetica-Bold', fontSize=18, textColor=GRAY_900,
        spaceBefore=15, spaceAfter=8
    )

    h2_style = ParagraphStyle(
        'H2', parent=styles['Heading2'],
        fontName='Helvetica-Bold', fontSize=14, textColor=BLUE_DARK,
        spaceBefore=12, spaceAfter=6
    )

    h3_style = ParagraphStyle(
        'H3', parent=styles['Heading3'],
        fontName='Helvetica-Bold', fontSize=11, textColor=GRAY_700,
        spaceBefore=8, spaceAfter=4
    )

    body_style = ParagraphStyle(
        'Body', parent=styles['Normal'],
        fontName='Helvetica', fontSize=10, textColor=GRAY_700,
        spaceAfter=6, leading=14, alignment=TA_JUSTIFY
    )

    subtitle_style = ParagraphStyle(
        'Subtitle', parent=styles['Normal'],
        fontName='Helvetica', fontSize=12, textColor=GRAY_500,
        spaceAfter=12
    )

    bullet_style = ParagraphStyle(
        'Bullet', parent=styles['Normal'],
        fontName='Helvetica', fontSize=9, textColor=GRAY_700,
        leftIndent=20, spaceAfter=4, leading=13,
        bulletIndent=8, bulletFontName='Helvetica', bulletFontSize=9
    )

    small_style = ParagraphStyle(
        'Small', parent=styles['Normal'],
        fontName='Helvetica', fontSize=8, textColor=GRAY_500,
        spaceAfter=3
    )

    story = []

    # ═══════════════════════════════════════════════════
    # COVER PAGE (content drawn by onFirstPage callback)
    # ═══════════════════════════════════════════════════
    story.append(Spacer(1, 1))
    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # TABLE OF CONTENTS
    # ═══════════════════════════════════════════════════
    story.append(Paragraph("Table des Matieres", title_style))
    story.append(Spacer(1, 10))

    toc_items = [
        ("1", "Introduction a O3 App", "3"),
        ("2", "Premiers Pas — Connexion et Navigation", "4"),
        ("3", "Tableau de Bord", "5"),
        ("4", "Gestion du Catalogue", "6"),
        ("5", "Gestion des Partenaires", "8"),
        ("6", "Documents de Vente", "10"),
        ("7", "Documents d'Achat", "12"),
        ("8", "Gestion du Stock", "13"),
        ("9", "Point de Vente (POS)", "15"),
        ("10", "Rapports et Analyses", "16"),
        ("11", "Parametres et Administration", "17"),
        ("12", "Astuces et Bonnes Pratiques", "19"),
    ]

    for num, title, page in toc_items:
        toc_data = [[
            Paragraph(f'<b><font color="#{BLUE_PRIMARY.hexval()[2:]}">{num}.</font></b>  {title}',
                      ParagraphStyle('toc', fontName='Helvetica', fontSize=11, textColor=GRAY_700)),
            Paragraph(page, ParagraphStyle('tocpage', fontName='Helvetica', fontSize=11, textColor=GRAY_400, alignment=2)),
        ]]
        toc_table = Table(toc_data, colWidths=[content_width - 40, 40])
        toc_table.setStyle(TableStyle([
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
            ('LINEBELOW', (0, 0), (-1, -1), 0.5, GRAY_200),
        ]))
        story.append(toc_table)

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 1. INTRODUCTION
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 1, "Introduction a O3 App"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "O3 App est une solution de gestion commerciale complete, concue pour les entreprises "
        "marocaines et francophones. Elle couvre l'ensemble du cycle commercial : de la gestion des "
        "produits et partenaires jusqu'a la facturation, en passant par les achats, le stock et le "
        "point de vente.",
        body_style
    ))
    story.append(Spacer(1, 8))

    # Feature cards
    features = [
        ("V", "Gestion des Ventes", "Devis, bons de commande, bons de livraison, factures, avoirs et retours.", BLUE_PRIMARY),
        ("A", "Gestion des Achats", "Commandes fournisseurs, receptions, factures d'achat et avoirs.", GREEN),
        ("S", "Gestion du Stock", "Entrees, sorties, ajustements, transferts et suivi des mouvements.", ORANGE),
        ("P", "Point de Vente", "Caisse intuitive avec gestion de sessions, tickets et cloture.", PURPLE),
        ("R", "Rapports", "Analyses detaillees des ventes, achats et stock en PDF.", INDIGO),
        ("C", "Multi-tenant SaaS", "Chaque client dispose de sa propre base de donnees isolee et securisee.", AMBER),
    ]

    for icon, title, desc, color in features:
        story.append(FeatureRow(content_width, icon, title, desc, color))

    story.append(Spacer(1, 10))
    story.append(TipBox(content_width,
        "O3 App est accessible depuis n'importe quel navigateur web (Chrome, Firefox, Edge). "
        "Aucune installation n'est requise sur votre ordinateur.", "info"))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 2. CONNEXION ET NAVIGATION
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 2, "Premiers Pas"))
    story.append(Spacer(1, 10))

    story.append(Paragraph("Connexion a l'application", h2_style))
    story.append(Paragraph(
        "Pour acceder a votre espace O3 App, ouvrez votre navigateur et saisissez l'adresse "
        "de votre domaine (ex: <b>votreentreprise.o3app.ma</b>). Vous arriverez sur la page de connexion.",
        body_style
    ))
    story.append(Spacer(1, 5))

    steps = [
        ("1", "Ouvrir le navigateur", "Saisissez l'URL fournie par l'administrateur dans la barre d'adresse.", BLUE_PRIMARY),
        ("2", "Entrer vos identifiants", "Renseignez votre adresse email et votre mot de passe dans le formulaire.", GREEN),
        ("3", "Cliquer sur Se connecter", "Vous serez redirige vers le tableau de bord principal de l'application.", ORANGE),
    ]

    for num, title, desc, color in steps:
        story.append(IconCard(content_width, 55, num, title, desc, color))
        story.append(Spacer(1, 5))

    story.append(Spacer(1, 10))
    story.append(Paragraph("Navigation dans l'application", h2_style))
    story.append(Paragraph(
        "L'interface se compose de trois zones principales :",
        body_style
    ))

    nav_items = [
        "<b>Barre laterale (sidebar)</b> — Menu principal a gauche avec les modules : Catalogue, Partenaires, Ventes, Achats, Stock, POS, Marketing, Parametres.",
        "<b>Zone de contenu</b> — La partie centrale ou s'affichent les pages, formulaires et tableaux.",
        "<b>Barre superieure</b> — Contient la recherche globale, le selecteur de langue (FR/EN), les notifications et votre profil.",
    ]
    for item in nav_items:
        story.append(Paragraph(f"&bull;  {item}", bullet_style))

    story.append(Spacer(1, 10))
    story.append(TipBox(content_width,
        "La sidebar se replie automatiquement sur mobile. Cliquez sur l'icone hamburger pour l'ouvrir.",
        "info"))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 3. TABLEAU DE BORD
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 3, "Tableau de Bord"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "Le tableau de bord est votre page d'accueil. Il offre une vue d'ensemble en temps reel "
        "de l'activite de votre entreprise avec des indicateurs cles actualises automatiquement.",
        body_style
    ))
    story.append(Spacer(1, 8))

    # Dashboard mockup
    story.append(ScreenMockup(content_width, 200, "Tableau de Bord — O3 App", mockup_type="dashboard"))
    story.append(Spacer(1, 12))

    story.append(Paragraph("Indicateurs affiches :", h3_style))

    kpis = [
        ("Chiffre d'affaires mensuel", "Montant total des ventes du mois en cours."),
        ("Achats du mois", "Total des achats effectues aupres des fournisseurs."),
        ("Paiements recus", "Somme des paiements clients encaisses."),
        ("Solde impaye", "Montant restant a percevoir des clients."),
    ]

    kpi_data = []
    for title, desc in kpis:
        kpi_data.append([
            Paragraph(f'<b>{title}</b>', ParagraphStyle('kpi', fontName='Helvetica-Bold', fontSize=9, textColor=GRAY_900)),
            Paragraph(desc, ParagraphStyle('kpid', fontName='Helvetica', fontSize=9, textColor=GRAY_500)),
        ])

    kpi_table = Table(kpi_data, colWidths=[content_width * 0.35, content_width * 0.65])
    kpi_table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('LINEBELOW', (0, 0), (-1, -2), 0.5, GRAY_200),
        ('BACKGROUND', (0, 0), (0, -1), BLUE_50),
    ]))
    story.append(kpi_table)

    story.append(Spacer(1, 10))
    story.append(Paragraph(
        "Le tableau de bord inclut egalement des graphiques de tendance (courbe de CA mensuel, "
        "comparaison ventes/achats) et la liste des dernieres transactions.",
        body_style
    ))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 4. GESTION DU CATALOGUE
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 4, "Gestion du Catalogue"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "Le catalogue regroupe tout ce que vous vendez ou achetez. Il se compose de trois modules : "
        "Produits, Categories et Marques.",
        body_style
    ))

    # --- Products ---
    story.append(Paragraph("4.1 Produits", h2_style))
    story.append(Paragraph(
        "Le module Produits est le coeur de votre catalogue. Chaque produit contient ses informations "
        "de base, ses prix, son stock et son image.",
        body_style
    ))

    story.append(ScreenMockup(content_width, 180, "Liste des Produits", mockup_type="table"))
    story.append(Spacer(1, 10))

    story.append(Paragraph("Creer un produit :", h3_style))
    prod_steps = [
        "Cliquez sur le bouton <b>+ Ajouter</b> en haut a droite.",
        "Remplissez les champs : <b>Titre</b>, <b>Code/SKU</b>, <b>Categorie</b>, <b>Marque</b>.",
        "Definissez le <b>Prix de vente</b> et le <b>Prix d'achat</b>.",
        "Ajoutez une <b>image</b> du produit (optionnel).",
        "Definissez le <b>stock initial</b> et le <b>depot</b> par defaut.",
        "Cliquez sur <b>Enregistrer</b>.",
    ]
    for step in prod_steps:
        story.append(Paragraph(f"&bull;  {step}", bullet_style))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Vous pouvez importer vos produits en masse via un fichier Excel. "
        "Allez dans Parametres > Imports > onglet Produits.", "success"))

    # --- Categories ---
    story.append(Spacer(1, 10))
    story.append(Paragraph("4.2 Categories", h2_style))
    story.append(Paragraph(
        "Les categories permettent d'organiser vos produits par familles (ex: Electronique, Alimentaire, "
        "Accessoires). Chaque categorie a un code unique et peut etre activee ou desactivee.",
        body_style
    ))

    cat_fields = [
        ("Code", "Identifiant unique de la categorie (ex: ELEC, ALIM)"),
        ("Nom", "Libelle affiche dans les listes et filtres"),
        ("Description", "Description optionnelle pour plus de details"),
        ("Statut", "Active ou Inactive — les categories inactives n'apparaissent pas dans les formulaires"),
    ]

    cat_data = [[
        Paragraph('<b>Champ</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
        Paragraph('<b>Description</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
    ]]
    for field, desc in cat_fields:
        cat_data.append([
            Paragraph(f'<b>{field}</b>', ParagraphStyle('td', fontName='Helvetica-Bold', fontSize=9, textColor=GRAY_900)),
            Paragraph(desc, ParagraphStyle('td', fontName='Helvetica', fontSize=9, textColor=GRAY_700)),
        ])

    cat_table = Table(cat_data, colWidths=[content_width * 0.25, content_width * 0.75])
    cat_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), BLUE_PRIMARY),
        ('TEXTCOLOR', (0, 0), (-1, 0), white),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('LINEBELOW', (0, 1), (-1, -1), 0.5, GRAY_200),
        ('BACKGROUND', (0, 1), (-1, -1), white),
        ('GRID', (0, 0), (-1, 0), 0, BLUE_PRIMARY),
        ('ROUNDEDCORNERS', [6, 6, 0, 0]),
    ]))
    story.append(cat_table)

    # --- Marques ---
    story.append(Spacer(1, 10))
    story.append(Paragraph("4.3 Marques", h2_style))
    story.append(Paragraph(
        "Gerez les marques de vos produits. Similaire aux categories, chaque marque a un code, "
        "un nom et un statut. Utile pour filtrer les produits par fabricant.",
        body_style
    ))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 5. GESTION DES PARTENAIRES
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 5, "Gestion des Partenaires"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "Les partenaires sont vos clients et fournisseurs. O3 App offre une gestion complete "
        "avec suivi des credits, des paiements et des informations fiscales.",
        body_style
    ))

    # Clients
    story.append(Paragraph("5.1 Clients", h2_style))
    story.append(Paragraph(
        "Chaque fiche client contient ses coordonnees, ses informations fiscales (ICE, RC, IF) "
        "et son seuil de credit. Le systeme surveille automatiquement les depassements de credit.",
        body_style
    ))

    client_features = [
        ("C", "Fiche Client Complete", "Nom, telephone, email, adresse, ville, ICE, RC, Patente, IF.", BLUE_PRIMARY),
        ("$", "Gestion du Credit", "Seuil de credit configurable. Alerte visuelle en cas de depassement (rouge).", RED),
        ("P", "Paiements", "Enregistrement des paiements recus. Historique complet par client.", GREEN),
        ("E", "Export Excel", "Exportez la liste clients vers Excel pour analyse externe.", INDIGO),
    ]
    for icon, title, desc, color in client_features:
        story.append(FeatureRow(content_width, icon, title, desc, color))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Le code couleur du credit : Vert = credit disponible, Orange = pas de credit, "
        "Rouge = credit depasse. Surveillez les indicateurs dans la liste des clients.", "warning"))

    # Fournisseurs
    story.append(Spacer(1, 10))
    story.append(Paragraph("5.2 Fournisseurs", h2_style))
    story.append(Paragraph(
        "La gestion des fournisseurs fonctionne de maniere similaire aux clients. "
        "Vous pouvez suivre les paiements effectues, les credits fournisseurs et exporter les donnees.",
        body_style
    ))

    # Depots
    story.append(Spacer(1, 8))
    story.append(Paragraph("5.3 Depots (Entrepots)", h2_style))
    story.append(Paragraph(
        "Gerez vos lieux de stockage. Chaque depot a un code, un nom et un statut. "
        "Vous pouvez consulter le stock disponible par depot a tout moment.",
        body_style
    ))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 6. DOCUMENTS DE VENTE
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 6, "Documents de Vente"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "O3 App gere l'ensemble du cycle de vente grace a une chaine documentaire complete. "
        "Chaque document peut etre converti au type suivant dans le cycle.",
        body_style
    ))
    story.append(Spacer(1, 8))

    # Document flow
    doc_flow = [
        ("Devis", "Proposition commerciale envoyee au client", GRAY_500),
        ("Bon de Commande", "Confirmation de la commande par le client", BLUE_PRIMARY),
        ("Bon de Livraison", "Confirmation de la livraison des marchandises", ORANGE),
        ("Facture", "Document fiscal pour le paiement", GREEN),
        ("Avoir", "Correction ou remboursement partiel/total", RED),
        ("Bon de Retour", "Retour de marchandises par le client", PURPLE),
    ]

    for i, (doc_type, desc, color) in enumerate(doc_flow):
        story.append(IconCard(content_width, 50, i + 1, doc_type, desc, color))
        if i < len(doc_flow) - 1:
            story.append(Spacer(1, 2))

    story.append(Spacer(1, 10))
    story.append(Paragraph("Creer un document de vente :", h3_style))

    vente_steps = [
        "Allez dans <b>Ventes > Documents de Vente</b>.",
        "Cliquez sur <b>+ Nouveau</b>.",
        "Selectionnez le <b>type de document</b> (Devis, BC, BL, Facture...).",
        "Choisissez le <b>client</b> dans la liste deroulante.",
        "Ajoutez les <b>lignes produits</b> : produit, quantite, prix unitaire, remise.",
        "Les totaux (HT, TVA 20%, TTC) sont calcules automatiquement.",
        "Cliquez sur <b>Enregistrer</b> (brouillon) ou <b>Confirmer</b>.",
    ]
    for step in vente_steps:
        story.append(Paragraph(f"&bull;  {step}", bullet_style))

    story.append(Spacer(1, 8))

    # Document mockup
    story.append(ScreenMockup(content_width, 200, "Facture FA-2026-0042", mockup_type="document"))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Chaque document peut etre imprime ou telecharge en PDF. Le PDF inclut automatiquement "
        "le logo de votre entreprise, les informations fiscales et les conditions de paiement.", "info"))

    story.append(PageBreak())

    # Statuts des documents
    story.append(Paragraph("Statuts des documents", h2_style))
    story.append(Paragraph(
        "Chaque document passe par differents statuts au cours de son cycle de vie :",
        body_style
    ))

    statuts = [
        ("Brouillon", "Document en cours de creation, modifiable", GRAY_500, GRAY_100),
        ("Confirme", "Document valide, pret a etre traite", BLUE_PRIMARY, BLUE_LIGHT),
        ("Converti", "Document transforme en type suivant", INDIGO, PURPLE_LIGHT),
        ("Livre", "Marchandises expediees/livrees", ORANGE, ORANGE_LIGHT),
        ("Paye", "Paiement recu integralement", GREEN, GREEN_LIGHT),
        ("Partiel", "Paiement partiel recu", AMBER, ORANGE_LIGHT),
        ("Annule", "Document annule", RED, RED_LIGHT),
    ]

    stat_data = [[
        Paragraph('<b>Statut</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
        Paragraph('<b>Description</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
    ]]
    for name, desc, color, bg in statuts:
        stat_data.append([
            Paragraph(f'<font color="#{color.hexval()[2:]}"><b>{name}</b></font>',
                      ParagraphStyle('td', fontName='Helvetica-Bold', fontSize=9)),
            Paragraph(desc, ParagraphStyle('td', fontName='Helvetica', fontSize=9, textColor=GRAY_700)),
        ])

    stat_table = Table(stat_data, colWidths=[content_width * 0.25, content_width * 0.75])
    stat_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), BLUE_PRIMARY),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('LINEBELOW', (0, 1), (-1, -1), 0.5, GRAY_200),
        ('BACKGROUND', (0, 1), (-1, -1), white),
    ]))
    story.append(stat_table)

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 7. DOCUMENTS D'ACHAT
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 7, "Documents d'Achat"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "La gestion des achats suit le meme principe que les ventes, mais du cote fournisseur. "
        "Les types de documents sont adaptes au cycle d'approvisionnement.",
        body_style
    ))
    story.append(Spacer(1, 8))

    achat_docs = [
        ("BC Fournisseur", "Commande envoyee au fournisseur pour approvisionnement."),
        ("Bon de Reception", "Confirmation de la reception des marchandises commandees."),
        ("Facture Achat", "Document fiscal recu du fournisseur pour paiement."),
        ("Avoir Fournisseur", "Correction ou credit accorde par le fournisseur."),
        ("Bon de Retour", "Retour de marchandises defectueuses au fournisseur."),
    ]

    achat_data = [[
        Paragraph('<b>Type</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
        Paragraph('<b>Description</b>', ParagraphStyle('th', fontName='Helvetica-Bold', fontSize=9, textColor=white)),
    ]]
    for doc_type, desc in achat_docs:
        achat_data.append([
            Paragraph(f'<b>{doc_type}</b>', ParagraphStyle('td', fontName='Helvetica-Bold', fontSize=9, textColor=GREEN)),
            Paragraph(desc, ParagraphStyle('td', fontName='Helvetica', fontSize=9, textColor=GRAY_700)),
        ])

    achat_table = Table(achat_data, colWidths=[content_width * 0.3, content_width * 0.7])
    achat_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), GREEN),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('LINEBELOW', (0, 1), (-1, -1), 0.5, GRAY_200),
        ('BACKGROUND', (0, 1), (-1, -1), white),
    ]))
    story.append(achat_table)

    story.append(Spacer(1, 10))
    story.append(Paragraph(
        "Le processus de creation est identique aux documents de vente : selectionnez le type, "
        "le fournisseur, ajoutez les lignes de produits et validez.",
        body_style
    ))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Les bons de reception mettent automatiquement a jour le stock. "
        "Verifiez toujours les quantites recues avant de confirmer.", "warning"))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 8. GESTION DU STOCK
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 8, "Gestion du Stock"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "Le module Stock permet de suivre avec precision les mouvements de marchandises "
        "dans vos depots. Quatre types de documents sont disponibles :",
        body_style
    ))
    story.append(Spacer(1, 8))

    stock_types = [
        ("E", "Entree de Stock", "Ajout de marchandises dans un depot (hors achats).", GREEN),
        ("S", "Sortie de Stock", "Retrait de marchandises d'un depot (hors ventes).", RED),
        ("A", "Ajustement", "Correction du stock apres inventaire physique.", INDIGO),
        ("T", "Transfert", "Deplacement de stock entre deux depots.", BLUE_PRIMARY),
    ]
    for icon, title, desc, color in stock_types:
        story.append(FeatureRow(content_width, icon, title, desc, color))

    story.append(Spacer(1, 10))
    story.append(Paragraph("Mouvements de Stock", h2_style))
    story.append(Paragraph(
        "La page Mouvements de Stock affiche l'historique complet de tous les mouvements "
        "(entrees et sorties) avec la date, le produit, le depot et la quantite. "
        "Utilisez les filtres pour retrouver un mouvement specifique.",
        body_style
    ))

    story.append(Spacer(1, 8))
    story.append(Paragraph("Consultation du stock par depot :", h3_style))
    story.append(Paragraph(
        "Pour voir le stock disponible dans un depot specifique, allez dans <b>Partenaires > Depots</b> "
        "et cliquez sur l'icone de stock a cote du depot. Un tableau detaille s'affiche avec la quantite "
        "de chaque produit.",
        body_style
    ))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Effectuez des inventaires reguliers et utilisez les ajustements pour corriger les ecarts. "
        "Le rapport de stock PDF vous aide a comparer le stock theorique au stock reel.", "success"))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 9. POINT DE VENTE (POS)
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 9, "Point de Vente (POS)"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "Le module POS transforme votre ordinateur ou tablette en caisse enregistreuse. "
        "Interface intuitive optimisee pour la rapidite d'encaissement.",
        body_style
    ))
    story.append(Spacer(1, 8))

    # POS mockup
    story.append(ScreenMockup(content_width, 200, "Point de Vente — O3 App", mockup_type="pos"))
    story.append(Spacer(1, 12))

    story.append(Paragraph("Utilisation du POS :", h3_style))
    pos_steps = [
        ("1", "Ouvrir une session", "Connectez-vous au terminal POS et ouvrez une session de caisse.", BLUE_PRIMARY),
        ("2", "Ajouter des produits", "Parcourez les categories ou recherchez par nom/code-barre. Cliquez pour ajouter au panier.", GREEN),
        ("3", "Ajuster les quantites", "Modifiez les quantites directement dans le panier.", ORANGE),
        ("4", "Encaisser", "Cliquez sur Valider, choisissez le mode de paiement et confirmez.", PURPLE),
    ]
    for num, title, desc, color in pos_steps:
        story.append(IconCard(content_width, 50, num, title, desc, color))
        story.append(Spacer(1, 3))

    story.append(Spacer(1, 8))
    story.append(Paragraph("Cloture de session", h3_style))
    story.append(Paragraph(
        "En fin de journee, cloturez votre session POS. Un rapport de cloture est genere "
        "automatiquement avec le recapitulatif des ventes, les modes de paiement utilises "
        "et le montant total de la caisse.",
        body_style
    ))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 10. RAPPORTS
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 10, "Rapports et Analyses"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "O3 App genere des rapports detailles en PDF pour analyser l'activite de votre entreprise. "
        "Trois types de rapports sont disponibles :",
        body_style
    ))
    story.append(Spacer(1, 8))

    reports = [
        ("V", "Rapport des Ventes", "Analyse detaillee du chiffre d'affaires par periode, avec ventilation par produit, client et mode de paiement.", BLUE_PRIMARY),
        ("A", "Rapport des Achats", "Suivi des depenses d'approvisionnement par fournisseur et par produit, avec evolution mensuelle.", GREEN),
        ("S", "Rapport du Stock", "Etat des stocks par depot et par produit. Ideal pour l'inventaire et le suivi des ruptures.", ORANGE),
    ]
    for icon, title, desc, color in reports:
        story.append(FeatureRow(content_width, icon, title, desc, color))
        story.append(Spacer(1, 3))

    story.append(Spacer(1, 8))
    story.append(Paragraph("Generer un rapport :", h3_style))
    report_steps = [
        "Allez dans <b>Rapports</b> depuis le menu lateral.",
        "Selectionnez le <b>type de rapport</b> (Ventes, Achats ou Stock).",
        "Definissez la <b>periode</b> (date de debut et date de fin).",
        "Pour le rapport de stock, selectionnez le <b>depot</b> souhaite.",
        "Cliquez sur <b>Generer le PDF</b>.",
        "Le rapport s'ouvre dans un nouvel onglet. Vous pouvez le telecharger ou l'imprimer.",
    ]
    for step in report_steps:
        story.append(Paragraph(f"&bull;  {step}", bullet_style))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 11. PARAMETRES
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 11, "Parametres et Administration"))
    story.append(Spacer(1, 10))

    story.append(Paragraph(
        "La section Parametres est reservee aux administrateurs. Elle permet de configurer "
        "tous les aspects de l'application.",
        body_style
    ))

    # Settings mockup
    story.append(Spacer(1, 5))
    story.append(ScreenMockup(content_width, 180, "Parametres — O3 App", mockup_type="settings"))
    story.append(Spacer(1, 10))

    settings_sections = [
        ("Informations Entreprise", "Nom, logo, telephone, email, adresse, numeros fiscaux (ICE, RC, IF). Ces informations apparaissent sur tous les documents PDF imprimes."),
        ("Utilisateurs", "Creez et gerez les comptes utilisateurs. Attribuez des roles (Admin, Manager, Caissier, Magasinier) avec des permissions specifiques."),
        ("Roles et Permissions", "Definissez des roles personnalises et attribuez des permissions granulaires pour controler l'acces a chaque fonctionnalite."),
        ("Numeroteurs", "Configurez le format de numerotation automatique des documents (ex: FA-2026-0001) et des entites (produits, clients, etc.)."),
        ("Modules", "Activez ou desactivez les modules optionnels (POS, Marketing) avec les cles de licence."),
        ("Imports", "Importez en masse des produits, clients ou fournisseurs depuis des fichiers Excel/CSV."),
        ("Piste d'Audit", "Consultez l'historique complet de toutes les actions effectuees dans l'application (creation, modification, suppression)."),
    ]

    for title, desc in settings_sections:
        story.append(Paragraph(f"<b>{title}</b>", h3_style))
        story.append(Paragraph(desc, body_style))

    story.append(Spacer(1, 8))
    story.append(TipBox(content_width,
        "Le logo de votre entreprise (uploade dans Parametres > Info) apparait automatiquement "
        "sur toutes les factures, bons de livraison et autres documents PDF.", "info"))

    story.append(PageBreak())

    # ═══════════════════════════════════════════════════
    # 12. ASTUCES ET BONNES PRATIQUES
    # ═══════════════════════════════════════════════════
    story.append(SectionHeader(content_width, 12, "Astuces et Bonnes Pratiques"))
    story.append(Spacer(1, 10))

    tips = [
        ("Configurez d'abord", "Avant de commencer, remplissez les informations de votre entreprise, "
         "creez vos categories, et importez vos produits en masse via Excel.", "info"),
        ("Utilisez les filtres", "Chaque liste dispose de filtres (recherche, statut, type). "
         "Utilisez-les pour retrouver rapidement un document ou un produit.", "info"),
        ("Sauvegardez en brouillon", "Creez vos documents en brouillon d'abord. Vous pouvez les modifier "
         "autant de fois que necessaire avant de les confirmer.", "success"),
        ("Surveillez les credits", "Configurez les seuils de credit pour vos clients. Le systeme vous "
         "alertera visuellement en cas de depassement.", "warning"),
        ("Inventaires reguliers", "Utilisez les ajustements de stock apres chaque inventaire physique. "
         "Le rapport de stock vous aide a identifier les ecarts.", "warning"),
        ("Exportez vos donnees", "Utilisez la fonction Export Excel pour analyser vos donnees dans un tableur "
         "ou pour des rapports personnalises.", "info"),
        ("Gerez les permissions", "Creez des roles adaptes a chaque poste. Un caissier n'a pas besoin "
         "d'acceder aux parametres ou aux rapports financiers.", "danger"),
        ("Cloturez vos sessions POS", "En fin de journee, cloturez toujours votre session de caisse. "
         "Le rapport de cloture est indispensable pour la comptabilite.", "success"),
    ]

    for title, desc, tip_type in tips:
        story.append(Paragraph(f"<b>{title}</b>", h3_style))
        story.append(TipBox(content_width, desc, tip_type))
        story.append(Spacer(1, 6))

    # ═══════════════════════════════════════════════════
    # LAST PAGE - CONTACT
    # ═══════════════════════════════════════════════════
    story.append(PageBreak())
    story.append(Spacer(1, 40))

    # Contact card
    contact_data = [
        [Paragraph('<b><font size="16" color="#2563eb">Besoin d\'aide ?</font></b>',
                    ParagraphStyle('contact_title', alignment=TA_CENTER))],
        [Paragraph(
            '<br/><br/>'
            "N'hesitez pas a contacter notre equipe de support pour toute question "
            "ou demande d'assistance.<br/><br/>"
            '<b>Email :</b> support@o3app.ma<br/>'
            '<b>Site web :</b> www.o3app.ma<br/>'
            '<b>Demo :</b> demo.o3app.ma<br/><br/>'
            '<font color="#6b7280" size="9">Identifiants de demo : demo@o3app.ma / demo1234</font>',
            ParagraphStyle('contact_body', fontName='Helvetica', fontSize=11,
                          textColor=GRAY_700, alignment=TA_CENTER, leading=16)
        )],
    ]

    contact_table = Table(contact_data, colWidths=[content_width * 0.8])
    contact_table.setStyle(TableStyle([
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 15),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 15),
        ('BACKGROUND', (0, 0), (-1, -1), BLUE_50),
        ('ROUNDEDCORNERS', [12, 12, 12, 12]),
        ('BOX', (0, 0), (-1, -1), 1.5, BLUE_PRIMARY),
    ]))

    # Center the table
    wrapper = Table([[contact_table]], colWidths=[content_width])
    wrapper.setStyle(TableStyle([('ALIGN', (0, 0), (-1, -1), 'CENTER')]))
    story.append(wrapper)

    story.append(Spacer(1, 30))
    story.append(Paragraph(
        '<font color="#9ca3af" size="9">O3 App v1.0 — Guide de Formation — Mars 2026<br/>'
        'Ce document est la propriete de O3 App. Tous droits reserves.</font>',
        ParagraphStyle('footer', alignment=TA_CENTER)
    ))

    # ── Build ──
    def first_page(canvas_obj, doc):
        cover_page(canvas_obj, doc)

    def later_pages(canvas_obj, doc):
        header_footer(canvas_obj, doc)

    doc.build(story, onFirstPage=first_page, onLaterPages=later_pages)
    print(f"PDF genere avec succes : {output_path}")
    return output_path


if __name__ == "__main__":
    build_pdf()
