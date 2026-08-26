# Charte graphique O3app

> **Statut : v0.3 — orientation arrêtée.** Orange confirmé comme couleur de
> marque, logo aligné, personnalisation par tenant et cible RGAA retenues,
> densité tranchée (§11). Reste le choix du jeu de palettes tenant.

---

## 0. Décision : l'orange est conservé

O3app garde son orange `#F97316`. Bonne nouvelle côté coût : pas de migration
de marque, et le `theme-color` de la page était déjà correct.

**Mais les règles d'usage doivent changer, parce que l'orange ne passe pas les
seuils de contraste là où il est employé aujourd'hui.**

| Motif relevé dans `resources/js` | Ratio | Seuil requis | Occurrences |
|---|---|---|---|
| `bg-orange-500` + `text-white` | **2,80:1** | 4,5:1 | 47 |
| `bg-orange-600` + `text-white` | **3,56:1** | 4,5:1 | 22 |
| `text-orange-500` sur fond clair | **2,80:1** | 4,5:1 texte · 3:1 icône | 118 |
| `text-orange-600` sur fond clair | 3,56:1 | 4,5:1 texte · 3:1 icône | 38 |

L'orange vif est une excellente couleur de marque et une mauvaise couleur de
texte. Ce n'est pas propre à O3app : c'est vrai de tous les oranges saturés,
dont la luminance est trop haute pour porter du blanc et trop basse pour porter
du noir. D'où la règle de §2.1 — **l'orange vif se pose, il ne s'écrit pas.**

### Et l'olive dans tout ça

Ta préférence pour les ambiances olive et jaune ne disparaît pas : elle devient
le **registre secondaire** (§2.2). Orange et olive sont deux terres, quasi
complémentaires, l'une vive et l'autre sourde. L'accord est solide, et il donne
enfin au produit une seconde couleur pour les mises en avant, les graphiques et
les catégories — là où aujourd'hui tout est orange.

### Le chantier reste le même

`resources/css/app.css` contient déjà les jetons sémantiques
(`--color-primary-*`…), correctement construits et presque pas utilisés : 83
usages contre 2 396 couleurs brutes. Migrer vers les jetons reste la
recommandation centrale — et c'est la condition technique de la
personnalisation par tenant (§9).

---

## 1. Identité de marque

### Ce que l'application est réellement

O3app n'est pas une application qu'on ouvre trois minutes. C'est un poste de
travail : une caissière y passe huit heures, un magasinier y saisit des entrées
de stock toute la matinée, un gérant y lit ses marges le soir. La densité
d'information est forte et assumée.

Une identité « startup » — dégradés, violets saturés, animations — serait
fausse ici, et fatigante à la dose où l'écran est regardé.

### Personnalité

| Axe | Position | Traduction visuelle |
|---|---|---|
| **Sobre** avant démonstratif | La couleur signale, elle ne décore pas | Surfaces neutres, couleur réservée aux actions et aux états |
| **Chaleureux** avant clinique | Un outil marocain, pas un tableur | Neutres tirant vers le chaud, jamais de gris bleuté |
| **Posé** avant dynamique | Huit heures d'affilée | Contrastes francs, animations courtes, aucun mouvement décoratif |
| **Constant** avant original | La confiance vient de la répétition | Un même composant se comporte pareil partout |

### Les trois valeurs à tenir

1. **Lisibilité d'abord.** Devant un arbitrage densité / lisibilité, la
   lisibilité gagne. Un chiffre mal lu coûte plus cher qu'une ligne de tableau
   en moins.
2. **La couleur porte du sens.** Si un élément est coloré, c'est qu'il est
   actionnable ou qu'il signale un état. Jamais pour égayer.
3. **Rien qui ne soit atteignable au clavier.** L'application est manipulée à
   la vitesse de la frappe. C'est autant de l'ergonomie que de l'accessibilité.

---

## 2. Palette

Quatre teintes chromatiques et une échelle neutre. Volontairement restreint :
chaque teinte supplémentaire est une occasion d'incohérence.

### 2.1 Orange — primaire

| Palier | HEX | RGB | Usage |
|---|---|---|---|
| 50 | `#FFF7ED` | 255, 247, 237 | Fond d'alerte avertissement, survol très léger |
| 100 | `#FFEDD5` | 255, 237, 213 | Ligne sélectionnée, bordure d'alerte |
| 200 | `#FED7AA` | 254, 215, 170 | Bordure douce |
| 300 | `#FDBA74` | 253, 186, 116 | **Texte et icônes sur fond sombre** |
| 400 | `#FB923C` | 251, 146, 60 | Accent sur fond sombre |
| **500** | **`#F97316`** | **249, 115, 22** | **Marque** — logo, aplats, `theme-color`. Jamais du texte. |
| **600** | **`#EA580C`** | **234, 88, 12** | Aplats, bordures, icônes ≥ 24 px (3,56:1) |
| **700** | **`#C2410C`** | **194, 65, 12** | **Bouton primaire** (blanc : 5,17:1) · **texte orange sur fond clair** |
| 800 | `#9A3412` | 154, 52, 18 | Bouton primaire survolé |
| 900 | `#7C2D12` | 124, 45, 18 | Bouton pressé, texte sur `orange-50` |
| 950 | `#431407` | 67, 20, 7 | — |

> ### La règle qui découle des chiffres
>
> **L'orange vif se pose, il ne s'écrit pas.**
>
> - `orange-500` est la couleur de marque : logo, aplats, grandes surfaces.
>   Elle ne porte **jamais** de texte, et ne sert **jamais** de couleur de
>   texte ou d'icône sur fond clair.
> - Le fond des boutons primaires est **`orange-700`** — premier palier qui
>   porte du blanc à 4,5:1.
> - Un texte ou une icône orange sur fond clair est **`orange-700`**.
> - Sur fond sombre la relation s'inverse : `orange-300` et `orange-400` sont
>   lisibles, `orange-700` ne l'est plus.
>
> Les 47 + 22 boutons et les 118 textes relevés en §0 relèvent tous de cette
> règle. C'est un remplacement mécanique, pas une refonte.

### 2.2 Olive — secondaire

Mises en avant qui ne sont pas des actions, séries de graphiques, catégories,
états « en cours ».

| Palier | HEX | RGB | Usage |
|---|---|---|---|
| 50 | `#F6F7F0` | 246, 247, 240 | Fond de bloc mis en avant |
| 100 | `#E9EDDC` | 233, 237, 220 | Fond de pastille |
| 200 | `#D4DCBB` | 212, 220, 187 | Bordure |
| 300 | `#B7C491` | 183, 196, 145 | Texte et icônes sur fond sombre |
| 400 | `#9AAA68` | 154, 170, 104 | Série de graphique |
| **500** | **`#7E8F4B`** | **126, 143, 75** | Aplats, grands textes (3,55:1) |
| **600** | **`#63723A`** | **99, 114, 58** | **Bouton secondaire** (blanc : 5,25:1) |
| 700 | `#4C592F` | 76, 89, 47 | Survol · **texte olive sur fond clair** |
| 900 | `#343C25` | 52, 60, 37 | Texte sur `olive-50` |

> L'olive est plus permissif que l'orange : `olive-600` porte déjà du blanc.
> C'est le bénéfice d'une teinte désaturée.

### 2.3 Menthe — succès

| Palier | HEX | RGB | Usage |
|---|---|---|---|
| 50 | `#ECFAF3` | 236, 250, 243 | Fond d'alerte succès |
| 100 | `#D2F2E2` | 210, 242, 226 | Bordure, fond de pastille |
| 300 | `#6ED0A2` | 110, 208, 162 | Icône sur fond sombre |
| 500 | `#12965F` | 18, 150, 95 | Pastille d'état |
| **600** | **`#0F7B52`** | **15, 123, 82** | **Bouton, texte sur fond clair** |
| 700 | `#0D6142` | 13, 97, 66 | Survol |
| 900 | `#0C3F2D` | 12, 63, 45 | Texte sur `menthe-50` |

> **Menthe et olive sont deux verts.** Ils se distinguent par la saturation, ce
> qui ne suffit pas pour un daltonien deutéranope. Voir §2.6.

### 2.4 Grenat — erreur et destruction

Tiré vers le carmin plutôt que vers le vermillon : un rouge orangé serait
indistinguable de la couleur primaire.

| Palier | HEX | RGB | Usage |
|---|---|---|---|
| 50 | `#FDF2F4` | 253, 242, 244 | Fond d'alerte erreur |
| 100 | `#FBE0E5` | 251, 224, 229 | Bordure d'alerte |
| 300 | `#EE9BA9` | 238, 155, 169 | Bordure de champ en erreur |
| 500 | `#D2384F` | 210, 56, 79 | Icône |
| **600** | **`#AF1D3C`** | **175, 29, 60** | **Bouton destructif** (blanc : 6,85:1) |
| 700 | `#94162F` | 148, 22, 47 | Survol |
| 900 | `#6B1223` | 107, 18, 35 | Texte d'erreur sur fond clair |

### 2.5 Pierre — neutres

Un gris légèrement chaud. Un gris bleuté jurerait avec l'orange.

| Palier | HEX | RGB | Usage |
|---|---|---|---|
| 0 | `#FFFFFF` | 255, 255, 255 | Surfaces, cartes, modales |
| 50 | `#FAF9F7` | 250, 249, 247 | Fond d'application (clair) |
| 100 | `#F4F2EF` | 244, 242, 239 | Survol de ligne, en-tête de tableau |
| 200 | `#E6E3DD` | 230, 227, 221 | **Bordures et séparateurs** |
| 300 | `#CDC9C1` | 205, 201, 193 | Bordure de champ au repos |
| 400 | `#A3A099` | 163, 160, 153 | **Désactivé et décoratif uniquement** — 2,6:1 |
| **500** | **`#74716A`** | **116, 113, 106** | **Texte secondaire** — 4,7:1, AA |
| 600 | `#5F5D57` | 95, 93, 87 | Texte tertiaire renforcé |
| 700 | `#494741` | 73, 71, 65 | Libellés de formulaire |
| 800 | `#34322E` | 52, 50, 46 | **Texte courant** |
| 900 | `#24231F` | 36, 35, 31 | Titres |
| 950 | `#151410` | 21, 20, 16 | Fond d'application (sombre) |

> **Le piège à éviter.** L'audit UI a relevé 389 `text-gray-400` sur fond clair
> — sous le seuil AA. `pierre-400` hérite du même risque : **réservé au
> désactivé et à l'ornement**, jamais à du texte à lire.

### 2.6 Les trois règles transverses

1. **Aucun état signalé par la couleur seule.** Menthe et olive sont deux
   verts, grenat et orange deux chaudes. Toujours couleur **+ icône** ou
   couleur **+ libellé**. C'est le critère WCAG 1.4.1.
2. **L'avertissement n'a pas de teinte propre.** Il emprunte la famille orange
   (`orange-50` en fond, `orange-900` en texte, triangle en icône) et
   n'apparaît **jamais sous forme de bouton** — ce qui le sépare sans ambiguïté
   d'une action primaire.
3. **Pas de couleur « info ».** Le bleu informatif actuel est utilisé zéro
   fois. Une information neutre se traite en `pierre-700` sur `pierre-100`,
   avec une icône.

---

## 3. Typographie

Trois rôles, deux familles, toutes deux sur Google Fonts.

| Rôle | Famille | Graisses | Pourquoi |
|---|---|---|---|
| **Titres** | **Archivo** | 600, 700 | Grotesque compacte et assurée aux grandes tailles |
| **Corps & interface** | **IBM Plex Sans** | 400, 500, 600 | Dessinée pour l'interface et la donnée, excellent français, chiffres tabulaires |
| **Chiffres & références** | **IBM Plex Mono** | 400, 500 | Références de documents, montants alignés en colonne |

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
```

### Échelle

L'application tourne aujourd'hui à 91 % en 12–14 px. Cette échelle remonte le
plancher sans perdre en densité.

| Jeton | Taille | Interligne | Graisse | Usage |
|---|---|---|---|---|
| `display` | 32 px | 1.15 | Archivo 700 | Titre de page unique |
| `h1` | 24 px | 1.2 | Archivo 700 | Titre de section |
| `h2` | 20 px | 1.3 | Archivo 600 | Sous-section, titre de carte |
| `h3` | 16 px | 1.4 | Plex 600 | Titre de bloc, en-tête de modale |
| **`body`** | **15 px** | **1.6** | Plex 400 | **Texte courant, cellules de tableau** |
| `input` | **16 px** | 1.5 | Plex 400 | **Champs de saisie — jamais moins** |
| `label` | 14 px | 1.4 | Plex 500 | Libellés de formulaire |
| `caption` | 13 px | 1.4 | Plex 400 | Métadonnées, horodatages |
| `mono` | 14 px | 1.5 | Plex Mono 400 | Références, montants |

> **Les 16 px des champs ne sont pas négociables.** En dessous, iOS zoome
> automatiquement à la prise de focus — très probablement ce qui a motivé le
> `user-scalable=no` actuel, lequel bloque le zoom pour tout le monde et
> constitue un échec WCAG 1.4.4. Corriger la taille permet de retirer la
> directive.

### Règles

- **Montants et quantités en `font-variant-numeric: tabular-nums`**, sans
  exception. Une colonne de prix qui ne s'aligne pas est illisible.
- **Une seule graisse par niveau.** Pas de gras décoratif dans le corps.
- **65–75 caractères** par ligne au maximum pour le texte suivi. Ne concerne
  pas les tableaux.
- **Pas de capitales au-delà de deux mots**, et `letter-spacing: 0.06em` quand
  il y en a.

---

## 4. Espacement et grille

### Unité de base : 4 px

Une seule échelle, alignée sur les pas Tailwind. **Aucune valeur hors échelle.**

| Pas | Valeur | Tailwind | Usage type |
|---|---|---|---|
| `1` | 4 px | `p-1` | Écart icône ↔ texte |
| `2` | 8 px | `p-2` | Padding interne d'un badge |
| `3` | 12 px | `p-3` | Padding vertical d'un champ |
| `4` | 16 px | `p-4` | Padding de carte, écart entre champs |
| `6` | 24 px | `p-6` | Carte large, écart entre blocs |
| `8` | 32 px | `p-8` | Écart entre sections |
| `12` | 48 px | `p-12` | Marge haute de page |
| `16` | 64 px | `p-16` | Séparation de grandes zones |

**Règle de proximité :** l'écart à l'intérieur d'un groupe est toujours plus
petit que celui qui le sépare du groupe voisin. Un libellé colle à son champ
(`2`), les champs s'espacent entre eux (`4`), les groupes davantage (`6`).

**Espacer par le conteneur, pas par l'enfant.** `flex`/`grid` + `gap`, jamais
une marge sur chaque élément — les marges se dédoublent et s'effondrent.

### Points de rupture

L'application saute aujourd'hui du mobile au bureau : 382 `sm:` contre 24
`md:`. **Le palier tablette est obligatoire**, c'est le format d'une caisse.

| Jeton | Largeur | Cible | Ce qui change |
|---|---|---|---|
| *(défaut)* | < 640 px | Téléphone | Une colonne, navigation en tiroir, actions empilées |
| `sm` | ≥ 640 px | Grand téléphone | Actions en ligne, modales centrées |
| **`md`** | **≥ 768 px** | **Tablette portrait — caisse** | **Deux colonnes, barre latérale en rail, tableaux complets** |
| `lg` | ≥ 1024 px | Tablette paysage, portable | Barre latérale dépliée, panneaux latéraux |
| `xl` | ≥ 1280 px | Bureau | Largeur plafonnée, troisième colonne |

### Rayons

Quatre rayons circulent aujourd'hui sans règle. Voici la règle.

| Rayon | Valeur | Réservé à |
|---|---|---|
| `sm` | 6 px | Pastilles, puces, petits badges |
| **`md`** | **8 px** | **Contrôles : boutons, champs, sélecteurs** |
| **`lg`** | **12 px** | **Conteneurs : cartes, panneaux, alertes** |
| `xl` | 16 px | Surfaces flottantes : modales, menus |
| `full` | 9999 px | Avatars, pastilles d'état, boutons ronds |

### Élévation

Trois niveaux. Au-delà, l'ombre devient du bruit.

| Niveau | Valeur | Usage |
|---|---|---|
| `flat` | aucune, bordure `pierre-200` | **Par défaut** — cartes, tableaux |
| `raised` | `0 1px 3px rgb(0 0 0 / .08), 0 1px 2px rgb(0 0 0 / .04)` | Survol, ligne active |
| `floating` | `0 12px 32px rgb(0 0 0 / .14)` | Modales, menus, popovers |

> En mode sombre l'ombre ne se voit pas : on élève par la **surface**.
> `pierre-950` pour le fond, `pierre-900` pour la carte, `pierre-800` pour la
> modale.

---

## 5. Implémentation Tailwind v4

Tout ce qui précède tient dans `resources/css/app.css`. **Rappel : le fichier
`tailwind.config.js` du dépôt n'est plus lu** — en v4 avec `@tailwindcss/vite`
la configuration vit ici. Il est à supprimer.

```css
@import "tailwindcss";

@custom-variant dark (&:where(.dark, .dark *));

@theme {
  /* ── Orange — primaire ────────────────────────────────── */
  --color-primary-50:  #FFF7ED;
  --color-primary-100: #FFEDD5;
  --color-primary-200: #FED7AA;
  --color-primary-300: #FDBA74;
  --color-primary-400: #FB923C;
  --color-primary-500: #F97316;  /* marque — ne porte jamais de texte */
  --color-primary-600: #EA580C;
  --color-primary-700: #C2410C;  /* fond de bouton, texte sur fond clair */
  --color-primary-800: #9A3412;
  --color-primary-900: #7C2D12;
  --color-primary-950: #431407;

  /* ── Olive — secondaire ───────────────────────────────── */
  --color-secondary-50:  #F6F7F0;
  --color-secondary-100: #E9EDDC;
  --color-secondary-200: #D4DCBB;
  --color-secondary-300: #B7C491;
  --color-secondary-400: #9AAA68;
  --color-secondary-500: #7E8F4B;
  --color-secondary-600: #63723A;
  --color-secondary-700: #4C592F;
  --color-secondary-800: #3D4728;
  --color-secondary-900: #343C25;
  --color-secondary-950: #1A2011;

  /* `warning` emprunte la famille primaire — voir §2.6 règle 2. */
  --color-warning-50:  var(--color-primary-50);
  --color-warning-100: var(--color-primary-100);
  --color-warning-500: var(--color-primary-600);
  --color-warning-900: var(--color-primary-900);

  /* ── Menthe — succès ──────────────────────────────────── */
  --color-success-50:  #ECFAF3;
  --color-success-100: #D2F2E2;
  --color-success-200: #A6E5C6;
  --color-success-300: #6ED0A2;
  --color-success-400: #34B57D;
  --color-success-500: #12965F;
  --color-success-600: #0F7B52;
  --color-success-700: #0D6142;
  --color-success-800: #0D4D36;
  --color-success-900: #0C3F2D;
  --color-success-950: #04231A;

  /* ── Grenat — erreur ──────────────────────────────────── */
  --color-danger-50:  #FDF2F4;
  --color-danger-100: #FBE0E5;
  --color-danger-200: #F6C4CD;
  --color-danger-300: #EE9BA9;
  --color-danger-400: #E36B80;
  --color-danger-500: #D2384F;
  --color-danger-600: #AF1D3C;
  --color-danger-700: #94162F;
  --color-danger-800: #7C1428;
  --color-danger-900: #6B1223;
  --color-danger-950: #3D060F;

  /* ── Pierre — neutres ─────────────────────────────────── */
  --color-stone-50:  #FAF9F7;
  --color-stone-100: #F4F2EF;
  --color-stone-200: #E6E3DD;
  --color-stone-300: #CDC9C1;
  --color-stone-400: #A3A099;
  --color-stone-500: #74716A;
  --color-stone-600: #5F5D57;
  --color-stone-700: #494741;
  --color-stone-800: #34322E;
  --color-stone-900: #24231F;
  --color-stone-950: #151410;

  /* ── Typographie ──────────────────────────────────────── */
  --font-display: "Archivo", ui-sans-serif, system-ui, sans-serif;
  --font-sans:    "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
  --font-mono:    "IBM Plex Mono", ui-monospace, monospace;

  --text-caption: 0.8125rem;   /* 13 */
  --text-label:   0.875rem;    /* 14 */
  --text-body:    0.9375rem;   /* 15 */
  --text-input:   1rem;        /* 16 */
  --text-h3:      1rem;        /* 16 */
  --text-h2:      1.25rem;     /* 20 */
  --text-h1:      1.5rem;      /* 24 */
  --text-display: 2rem;        /* 32 */

  /* ── Rayons ───────────────────────────────────────────── */
  --radius-sm: 6px;
  --radius-md: 8px;    /* contrôles */
  --radius-lg: 12px;   /* conteneurs */
  --radius-xl: 16px;   /* surfaces flottantes */

  /* ── Élévation ────────────────────────────────────────── */
  --shadow-raised:   0 1px 3px rgb(0 0 0 / .08), 0 1px 2px rgb(0 0 0 / .04);
  --shadow-floating: 0 12px 32px rgb(0 0 0 / .14);
}

@layer base {
  body {
    font-family: var(--font-sans);
    font-size: var(--text-body);
    line-height: 1.6;
    color: var(--color-stone-800);
    background: var(--color-stone-50);
  }
  h1, h2, .display { font-family: var(--font-display); letter-spacing: -0.02em; }

  /* Montants, quantités, références : chiffres alignés en colonne. */
  .tabular, td.num, input[type="number"] { font-variant-numeric: tabular-nums; }
}
```

---

## 6. Composants

Hauteur de contrôle : 40 px par défaut, 36 px en `sm`, **44 px en `lg` — la
taille à utiliser partout dans le POS**, c'est la cible tactile minimale.

### 6.1 Boutons

| Variante | Repos | Survol | Pressé | Focus clavier | Désactivé |
|---|---|---|---|---|---|
| **Primaire** | `primary-700`, texte blanc | `primary-800` | `primary-900` | anneau `primary-600`, 2 px, décalé de 2 px | `opacity-50`, curseur bloqué |
| **Secondaire** | blanc, bordure `stone-300`, texte `stone-700` | fond `stone-100` | `stone-200` | anneau `stone-400` | idem |
| **Fantôme** | transparent, texte `stone-600` | fond `stone-100` | `stone-200` | anneau `stone-400` | idem |
| **Destructif** | `danger-600`, texte blanc | `danger-700` | `danger-800` | anneau `danger-500` | idem |

```html
<!-- Primaire — noter primary-700, pas 500 : voir §2.1 -->
<button type="button" class="
  inline-flex items-center justify-center gap-2 h-10 px-4
  rounded-md font-medium text-label
  bg-primary-700 text-white
  hover:bg-primary-800 active:bg-primary-900
  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2
  disabled:opacity-50 disabled:cursor-not-allowed
  transition-colors duration-150
">
  Enregistrer
</button>

<!-- Destructif -->
<button type="button" class="
  inline-flex items-center justify-center gap-2 h-10 px-4
  rounded-md font-medium text-label
  bg-danger-600 text-white
  hover:bg-danger-700 active:bg-danger-800
  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2
  disabled:opacity-50 disabled:cursor-not-allowed
  transition-colors duration-150
">
  Supprimer
</button>

<!-- Icône seule : le nom accessible est obligatoire -->
<button type="button" aria-label="Fermer" class="
  inline-flex items-center justify-center w-10 h-10
  rounded-md text-stone-600
  hover:bg-stone-100 active:bg-stone-200
  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400
">
  <svg class="w-5 h-5" aria-hidden="true">…</svg>
</button>
```

**Trois règles :**

1. **`focus-visible:`, jamais `focus:`.** Avec `focus:`, l'anneau reste
   accroché après un clic souris. L'application en compte 1 052 contre 8 —
   c'est l'origine des anneaux qui « collent ».
2. **Un seul bouton primaire par zone.** S'il y en a deux, aucun n'est primaire.
3. **Sur mobile, l'action principale passe en premier sous le pouce** :
   `flex-col-reverse sm:flex-row`. `BaseModal` le fait déjà, c'est le modèle.

### 6.2 Formulaires

```html
<div class="flex flex-col gap-2">
  <label for="prix-vente" class="text-label font-medium text-stone-700">
    Prix de vente
    <span class="text-danger-600" aria-hidden="true">*</span>
  </label>

  <input
    id="prix-vente"
    type="number"
    required
    aria-describedby="prix-vente-aide"
    class="
      w-full h-10 px-3
      text-input tabular-nums
      rounded-md border border-stone-300 bg-white
      placeholder:text-stone-400
      focus:outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-300
      disabled:bg-stone-100 disabled:text-stone-500 disabled:cursor-not-allowed
      transition-colors duration-150
    "
  />

  <p id="prix-vente-aide" class="text-caption text-stone-500">
    Hors taxe, en MAD.
  </p>
</div>
```

**En erreur**, trois changements simultanés — la couleur ne suffit jamais :

```html
<input
  id="prix-vente"
  aria-invalid="true"
  aria-describedby="prix-vente-erreur"
  class="… border-danger-300 focus:border-danger-600 focus:ring-danger-300"
/>
<p id="prix-vente-erreur" role="alert" class="flex items-center gap-1.5 text-caption text-danger-900">
  <svg class="w-4 h-4 shrink-0" aria-hidden="true">…</svg>
  Le prix doit être supérieur à zéro.
</p>
```

**Non négociables :**

- **`for` / `id` sur chaque paire libellé–champ.** L'application en compte 252
  sans association — le libellé est alors invisible pour un lecteur d'écran, et
  son clic ne place pas le curseur.
- **`aria-invalid` et `aria-describedby`** relient le message d'erreur au
  champ. Sans eux, le message s'affiche mais n'est jamais annoncé.
- **`role="alert"`** sur le message, pour qu'il soit lu dès son apparition.
- **Champs à 16 px** (§3).

### 6.3 Cartes

```html
<section class="
  rounded-lg border border-stone-200 bg-white
  dark:border-stone-800 dark:bg-stone-900
">
  <header class="flex items-center justify-between gap-3 px-6 py-4 border-b border-stone-200 dark:border-stone-800">
    <h2 class="font-display text-h2 text-stone-900 dark:text-stone-50">Ventes du jour</h2>
    <button class="…">Exporter</button>
  </header>

  <div class="px-6 py-5">…</div>

  <footer class="px-6 py-3 border-t border-stone-200 bg-stone-50 dark:border-stone-800 dark:bg-stone-950/40 rounded-b-lg">
    …
  </footer>
</section>
```

- **Bordure plutôt qu'ombre** par défaut. L'ombre est réservée à ce qui flotte.
- **Padding `6`** (24 px) sur bureau, `4` (16 px) en dessous de `sm`.
- **Un seul niveau d'imbrication.** Une carte dans une carte signale presque
  toujours une hiérarchie mal posée.

### 6.4 Alertes

Quatre variantes, une structure. **Icône obligatoire dans chaque cas** — c'est
ce qui rend l'état lisible sans la couleur.

| Type | Fond | Bordure | Texte | Icône | Rôle ARIA |
|---|---|---|---|---|---|
| Succès | `success-50` | `success-100` | `success-900` | coche cerclée | `status` |
| Erreur | `danger-50` | `danger-100` | `danger-900` | triangle | `alert` |
| Avertissement | `primary-50` | `primary-100` | `primary-900` | point d'exclamation | `alert` |
| Information | `stone-100` | `stone-200` | `stone-700` | « i » cerclé | `status` |

```html
<div role="status" class="
  flex items-start gap-3 px-4 py-3
  rounded-lg border border-success-100 bg-success-50
  text-body text-success-900
">
  <svg class="w-5 h-5 shrink-0 mt-0.5" aria-hidden="true">…</svg>
  <div class="flex-1">
    <p class="font-medium">Ticket enregistré</p>
    <p class="text-caption opacity-90">Référence TCK-26-08-0042 — 1 250,00 MAD</p>
  </div>
  <button type="button" aria-label="Fermer" class="shrink-0 …">…</button>
</div>
```

### 6.5 Pastilles d'état

Les documents ont beaucoup d'états. **Couleur plus libellé**, toujours.

| État | Fond | Texte |
|---|---|---|
| Brouillon | `stone-100` | `stone-700` |
| Confirmé / Livré | `secondary-100` | `secondary-900` |
| Payé | `success-100` | `success-900` |
| Partiel | `primary-100` | `primary-900` |
| Annulé | `danger-100` | `danger-900` |

```html
<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-caption font-medium bg-success-100 text-success-900">
  <span class="w-1.5 h-1.5 rounded-full bg-success-600" aria-hidden="true"></span>
  Payé
</span>
```

---

## 7. Logo et favicon

**Appliqué** dans `resources/views/welcome.blade.php`.

| Élément | Avant | Après | Pourquoi |
|---|---|---|---|
| Disque | `#2563EB` (bleu) | **`#F97316`** | N'appartenait à aucune palette du produit |
| Sigle « O3 » | blanc | **`#1C1917`** | Blanc sur cet orange : 2,8:1. Sigle foncé : **6,3:1** |
| `favicon.ico` | déclaré | retiré | Le fichier fait 0 octet |

Le disque orange vif reste la marque ; le sigle foncé la rend lisible à 16 px.
C'est l'application directe de la règle §2.1 : l'orange se pose, il ne s'écrit
pas.

> **Reste à produire** : un vrai `favicon.ico` multi-résolutions (16/32/48) et
> les icônes PWA de `pwa-manifest.json`, à aligner sur le même dessin.

---

## 8. Accessibilité — cible RGAA / WCAG 2.1 AA

La conformité AA est retenue comme objectif. Ces règles font partie de la
charte au même titre que les couleurs.

| Critère | Règle | Où ça coince aujourd'hui |
|---|---|---|
| **1.4.3** Contraste | Texte ≥ 4,5:1, texte large et composants ≥ 3:1 | 47 + 22 boutons orange, 118 textes orange, 389 `gray-400` |
| **1.4.4** Redimensionnement | Zoom disponible jusqu'à 200 % | `user-scalable=no` à retirer |
| **1.4.1** Information par la couleur | Jamais la couleur seule | Icône ou libellé systématique |
| **1.3.1** Information et relations | `for`/`id` sur chaque champ | 252 libellés orphelins |
| **4.1.2** Nom, rôle, valeur | Tout bouton icône porte un `aria-label` | 71 sans nom accessible |
| **2.1.1** Clavier | Aucun `<div @click>` | 13 à convertir |
| **2.4.7** Focus visible | `focus-visible:ring-2` partout | 16 `focus:outline-none` orphelins |
| **2.4.1** Contournement de blocs | Lien d'évitement en tête de page | absent |
| **4.1.2** Dialogues | `role="dialog"`, `aria-modal`, Échap, piège de focus | `BaseModal` — 26 usages |
| **2.5.5** Taille de cible | ≥ 44 × 44 px sur le POS | boutons en taille `lg` |

> Cette liste couvre ce qui se vérifie dans les sources. Une conformité
> **déclarée** demande en plus un audit au lecteur d'écran et au clavier sur
> les parcours réels — l'analyse statique ne le remplace pas.

---

## 9. Personnalisation par tenant

Retenu. La mécanique est immédiate une fois les jetons adoptés : la couleur
primaire d'un tenant est une surcharge de variables CSS injectée à la racine.

```blade
{{-- Injecté par le layout tenant, après app.css --}}
<style>
  :root {
    --color-primary-500: {{ $tenant->brand_500 }};
    --color-primary-600: {{ $tenant->brand_600 }};
    --color-primary-700: {{ $tenant->brand_700 }};
    --color-primary-800: {{ $tenant->brand_800 }};
  }
</style>
```

**Mais pas de sélecteur de couleur libre.** Le problème documenté en §0 est
exactement celui qu'un tenant reproduirait : il choisirait un vif magnifique
qui ne porte pas de texte blanc, et l'application deviendrait illisible sans
que personne ne s'en aperçoive.

**Proposition : un jeu de palettes validées.** Six à huit thèmes préparés, dont
chacun respecte les seuils de §8, présentés au tenant comme un choix visuel et
non comme une valeur hexadécimale.

| Thème | 500 (marque) | 700 (bouton, texte) |
|---|---|---|
| Safran *(défaut)* | `#F97316` | `#C2410C` |
| Olive | `#7E8F4B` | `#4C592F` |
| Grenat | `#D2384F` | `#94162F` |
| Océan | `#0E7490` | `#155E75` |
| Prune | `#9333EA` | `#6B21A8` |
| Ardoise | `#475569` | `#334155` |

Chaque thème stocke ses quatre paliers utiles, pas une seule couleur — c'est ce
qui garantit que le contraste tient. Si tu tiens à la saisie libre, il faut y
adjoindre un calcul de ratio côté serveur qui refuse les valeurs sous 4,5:1 ;
c'est faisable, mais c'est un développement à part entière.

---

## 10. Ce qu'il reste à décider

1. **Le jeu de palettes de §9 te convient-il ?** Six thèmes, ou faut-il ouvrir
   la saisie libre avec garde-fou serveur ?
2. **Le dessin du logo lui-même.** Le sigle « O3 » en Arial dans un disque est
   un logotype par défaut, pas un logo. La charte le rend correct ; elle ne le
   rend pas distinctif.

---

## 11. Densité : arbitrage tranché

La question était de savoir si la densité actuelle — 91 % du texte à 14 px ou
moins — relevait du choix ou de la dérive. **Décision : les deux, selon
l'endroit.** Une règle unique appliquée partout serait fausse dans un sens ou
dans l'autre.

| Contexte | Taille | Raison |
|---|---|---|
| **Cellules de tableau, grilles de données** | **14 px** — inchangé | La densité y gagne sa place. Un inventaire de 200 lignes se lit mieux serré, et l'utilisateur est un habitué qui balaie plus qu'il ne lit. |
| **Champs de saisie** | **16 px** | Non négociable : en dessous, iOS zoome à la prise de focus. C'est ce qui permet de retirer le `user-scalable=no` sans effet de bord. |
| **Corps de texte, formulaires, modales, messages** | **15 px** | Ce sont des zones qu'on lit vraiment, souvent sous pression et parfois par-dessus l'épaule de quelqu'un. |
| **Libellés, métadonnées** | 14 / 13 px | Inchangé. |

Autrement dit : **la densité est conservée là où elle sert à comparer, et
relâchée là où il faut comprendre.** Les tableaux — le cœur d'un ERP — ne
s'allongent pas.

Ce découpage a un effet secondaire utile : le seul changement à portée large
est celui des champs, et c'est un remplacement mécanique de `text-sm` par
`text-input` sur les 325 `<input>`. Le reste suit naturellement l'adoption de
`BaseInput`.

---

*v0.3 — orange validé, logo appliqué, densité tranchée. Reste le jeu de palettes tenant.*
