# O3 App — Contrats & Onboarding Client

Ce dossier contient les documents légaux et le workflow d'onboarding d'un nouveau client (tenant).

## 📁 Contenu

| Fichier | Rôle | Édité directement ? |
|---|---|---|
| `contrat-services-saas.md` | **Source** du contrat de services SaaS | ✅ oui — c'est la source |
| `contrat-services-saas.docx` | Version Word, e-sig-ready (placeholders surlignés jaune, ancres de signature) | ❌ non — régénéré |
| `fiche-souscription-client.md` | **Source** de la fiche d'intake (1 page) | ✅ oui |
| `fiche-souscription-client.docx` | Version Word de la fiche | ❌ non — régénéré |
| `build/md_to_docx.py` | Script de conversion MD → DOCX | ✅ oui (si tu veux ajuster le rendu) |

## 🔄 Régénérer les .docx après modification

Modifie le `.md` correspondant, puis :

```bash
python docs/legal/build/md_to_docx.py            # régénère tous les fichiers
python docs/legal/build/md_to_docx.py docs/legal/contrat-services-saas.md   # un seul
```

Ne modifie **jamais** les `.docx` à la main : ils sont écrasés à chaque rebuild. La source de vérité c'est le `.md`.

---

## 🚀 Workflow complet — d'un prospect à la mise en service

```
1. Premier contact            → envoyer fiche-souscription-client.docx
                                 + contrat-services-saas.docx (CGS pour info)

2. Client retourne la fiche   → réception par email avec infos remplies

3. Préparer le contrat        → ouvrir contrat-services-saas.docx dans Word
                                 Ctrl+H sur les placeholders jaunes
                                 fixer la grille tarifaire (Annexe 1.C)
                                 sauver en PDF "contrat-CLIENT-AAAA-MM.pdf"

4. Envoyer pour e-signature   → uploader le PDF sur Yousign / DocuSign / Adobe Sign
                                 le service détecte automatiquement les ancres :
                                   {{sig:prestataire}} et {{sig:client}}
                                 envoyer au client (et à toi en copie)

5. Signature du client        → notification email automatique

6. Contre-signature           → tu signes à ton tour côté Prestataire

7. Réception PDF signé        → archiver dans Drive / sauvegarde locale
                                 référence à conserver minimum 10 ans

8. Provisionner le tenant     → créer le tenant via /central/tenants/create
                                 utiliser EXACTEMENT les infos de la fiche :
                                   - sous-domaine
                                   - modules cochés
                                   - utilisateurs initiaux
                                   - flags POS / eCom / paiement_bl

9. Email de bienvenue         → URL du tenant + identifiants admin initial
                                 + lien vers documentation utilisateur
                                 + délais de support / coordonnées

10. Première facture          → émise après mise en service (J+1 typique)
```

---

## ✏️ Repérer les zones à remplir dans le .docx

Toutes les zones à compléter par le Prestataire sont **surlignées en jaune** dans le `.docx`. Dans Word :

- Utilise **Ctrl+H** (Rechercher / Remplacer) pour chaque placeholder type
- Ou clique simplement sur la zone jaune et tape par-dessus
- Le surlignage **disparaît automatiquement** quand tu remplaces le texte (c'est appliqué au caractère, pas au paragraphe)

Placeholders les plus fréquents à remplacer dans le contrat :

| Placeholder | Source | Exemple |
|---|---|---|
| `[CTR-AAAA-NNN]` | Numéro de contrat interne | `CTR-2026-001` |
| `[Raison sociale du Client]` | Fiche §1 | `STÉ TELIPHONI SARL` |
| `[N° RC]`, `[ICE]`, `[IF]` | Fiche §1 | … |
| `[xxx].o3app.ma` | Fiche §4 | `teliphoni.o3app.ma` |
| `[12]` mois durée initiale | Décision commerciale | `12` (ou `6` pour pilote) |
| `[30]` jours essai | Décision commerciale | `30` ou supprimer Article 5 |
| Annexe 1.C — toutes les lignes | Décision commerciale | Tarifs MAD HT |

---

## 🖋️ Services e-signature compatibles

Les ancres `{{sig:prestataire}}` et `{{sig:client}}` insérées dans le `.docx` sont reconnues par la plupart des plateformes :

| Service | Marché | Reconnaissance ancres | Conformité loi 53-05 (Maroc) |
|---|---|---|---|
| **Yousign** | France / Maroc | ✅ « anchor strings » | ✅ eIDAS + reconnu |
| **DocuSign** | International | ✅ « anchor tags » | ✅ |
| **Adobe Sign** | International | ✅ « text tags » | ✅ |
| **Barid eSign** | Maroc (Barid Al Maghrib) | À vérifier | ✅ DGSSI agréé |

**Recommandation** : Yousign (FR) ou Adobe Sign pour démarrer. Barid eSign est l'option locale officielle pour les marchés publics ou clients institutionnels exigeant un certificat marocain.

> ⚠️ Avant le premier envoi en signature électronique, **fais relire le contrat par un avocat / juriste local**. Investissement 1 500–3 000 MAD pour 1–2h, valable à vie ensuite.

---

## 🔒 Cycle de vie d'un contrat signé

| Phase | Durée | Action |
|---|---|---|
| Contrat actif | Durée du contrat (12 mois + reconductions) | Conservation chiffrée + accessible |
| Archivage post-résiliation | 5 ans | Déplacer vers archive froide |
| Conservation légale fiscale | 10 ans (Maroc) | Conserver l'original signé |
| Purge | Après 10 ans | Suppression définitive |

Stocke chaque contrat signé sous : `docs/legal/clients/[ICE-CLIENT]/contrat-CTR-AAAA-NNN.pdf`
*(ce dossier n'est PAS commité — `.gitignore` le filtre)*

---

## 📌 Convention de nommage

- Numéro de contrat : `CTR-AAAA-NNN` (ex. `CTR-2026-001`)
- Numéro de fiche : `FS-AAAA-NNN`
- Version des CGS : `CGS-vAAAA.MM` (ex. `CGS-v2026.04`) — incrémenter à chaque révision
- Nom de fichier signé : `contrat-[ICE]-CTR-AAAA-NNN-signed.pdf`

---

## 🛠️ Évolution des Conditions Générales (CGS)

Quand tu modifies `contrat-services-saas.md` :

1. Incrémente la version CGS dans le fichier (`CGS-v2026.05` → `CGS-v2026.07` par exemple)
2. Régénère le `.docx`
3. Commit avec un message clair : `legal: CGS v2026.07 — précise SLA + ajoute clause force majeure`
4. **Les contrats déjà signés restent figés sur leur version d'origine**. Pour appliquer une nouvelle version aux clients existants, il faut un avenant signé (Article 25).
