# Audit — llm-carbon-php

Audit mené par trois lectures indépendantes (justesse du calcul, sources, fragilité au
changement), puis chaque constat soumis à une tentative de réfutation adverse : chercher un
contre-exemple dans le code, et à défaut de trancher, écarter le constat par défaut. Seuls les
constats qui ont résisté à cette tentative sont retenus.

Date : 2026-08-21.

---

## 1. Le facteur d'émission « Monde » est mal arrondi

**Constat :** `EmissionFactor::monde()` retourne 590,4 gCO2eq/kWh au lieu de 590,5.

**Preuve :**
- `src/EmissionFactor.php:85` (docblock) — valeur d'origine citée : 0,590478 kgCO2eq/kWh.
- `src/EmissionFactor.php:92` — valeur codée : `590.4`.
- Calcul : 0,590478 × 1000 = 590,478, qui arrondit à une décimale sur 590,5 (écart de 0,022 à
  590,5 contre 0,078 à 590,4), selon la même règle qui produit correctement 81,3
  (`EmissionFactor.php:26`), 509,4 (`:50`) et 679,8 (`:71`) à partir de leurs valeurs d'origine
  respectives.

**Lentille :** Justesse (et Fragilité, ce même constat a été relevé indépendamment sous les deux
angles).

**Réfutation tentée :** cherché une justification alternative (troncature documentée, convention
d'arrondi bancaire, note expliquant l'écart) dans tout le dépôt. `tests/EmissionFactorTest.php:71`
ne fait qu'asserter 590,4 contre la valeur du code — comparaison circulaire, elle ne prouve rien.
Aucune source ni commentaire ne justifie 590,4 plutôt que 590,5.

**Verdict :** RETENU — bug d'arrondi confirmé, sans justification trouvée nulle part dans le code
ou les sources citées.

---

## 2. Le test de la zone « Monde » verrouille la valeur fausse

**Constat :** le test attend le résultat calculé à partir du facteur 590,4 erroné, pas de la
valeur correcte 590,5.

**Preuve :**
- `tests/FootprintCalculatorTest.php:39` — `yield 'Monde' => [EmissionFactor::monde(), 2.7160]`.
- `src/EmissionFactor.php:92` — source de la valeur 590,4 utilisée pour produire 2,7160.
- Avec 590,5, le résultat exact serait 2,71642 → 2,7164, hors de la tolérance
  `assertEqualsWithDelta(…, 0.0001)` de `tests/FootprintCalculatorTest.php:51`.

**Lentille :** Fragilité.

**Réfutation tentée :** cherché un commentaire expliquant une provenance indépendante de la valeur
2,7160 (comme il en existe un pour `testGpt4DonneLesValeursAttendues`, `:59-60`). Aucun trouvé :
la valeur attendue du test dérive silencieusement de la constante actuelle.

**Verdict :** RETENU — corriger le constat 1 sans corriger ce test ferait échouer la suite, preuve
que le test verrouille le bug plutôt que la méthodologie.

---

## 3. Les coefficients α/β de la régression citent une source morte et périmée

**Constat :** l'URL source des coefficients d'énergie GPU renvoie une erreur 404, et la
méthodologie qu'elle documentait a changé sans que le code ait été mis à jour ni versionné.

**Preuve :**
- `src/FootprintCalculator.php:19,26,33` — URL citée : `https://ecologits.ai/latest/methodology/energy/`, confirmée 404 par accès direct.
- `src/FootprintCalculator.php:21,28` — coefficients codés : α=8,91e-5, β=1,43e-3 (régression
  linéaire simple).
- Source externe actuelle (`https://ecologits.ai/latest/methodology/llm_inference/`) — formule
  différente : `f_E = α·e^(βB)·P_active + γ` avec α=1,17e-6, β=-1,12e-2, γ=4,05e-5, dépendante
  d'une taille de batch B absente du code.
- Contraste : `src/EmissionFactor.php:29-30` pointe un tag Git figé (`0.4.0`) pour une source
  comparable ; `FootprintCalculator.php` ne fait pas ce même effort de version pinnée.

**Lentille :** Sources (et Justesse).

**Réfutation tentée :** cherché dans le dépôt (README.md, docblocks) une justification explicite
du maintien volontaire sur une ancienne version d'EcoLogits. Aucune trouvée.

**Verdict :** RETENU — lien mort et formule non traçable à une version citée, en violation directe
de la règle CLAUDE.md « aucun chiffre sans source vérifiable ».

---

## 4. Le facteur « nombre de GPU requis » de la méthodologie est absent du calcul

**Constat :** le code ne multiplie jamais l'énergie GPU par le nombre de GPU nécessaires pour
charger le modèle en mémoire, contrairement à la méthodologie EcoLogits.

**Preuve :**
- `src/FootprintCalculator.php:37-51` (`calculate()`) — aucune notion de VRAM, quantification ou
  nombre de GPU ; `src/LanguageModel.php` n'a même pas de champ « paramètres totaux » pour porter
  cette information.
- Source EcoLogits v0.4.0 (`ecologits/impacts/llm.py`, la version dont les constantes du dépôt
  sont la copie exacte : `GPU_ENERGY_ALPHA=8.91e-8`, `GPU_ENERGY_BETA=1.43e-6` en kWh, soit 1000×
  les valeurs Wh du dépôt) — définit `gpu_required_count = ceil(memoire_modele / 80 Go)` et
  `energie_requete = PUE × (energie_serveur + gpu_required_count × energie_GPU)`.
- Calcul vérifié pour GPT-4 (1760 Md de paramètres totaux, quantification 4 bits par défaut) :
  14 GPU requis selon cette formule — un facteur bien supérieur au simple usage mono-GPU du code.

**Lentille :** Justesse.

**Réfutation tentée :** cherché une mention de ce scope mono-GPU assumé dans la section « Limites »
de `README.md:65-81`. Absente — ni comme choix de scope documenté, ni ailleurs.

**Verdict :** RETENU — avec une correction : l'exemple initial « Llama 3.1 70B » de l'audit est
inexact (70 Md à 4 bits tient sur 1 seul GPU par défaut), mais l'omission est confirmée et plus
grave pour GPT-4 (facteur ×14, pas ×2 à ×4).

---

## 5. Le terme d'énergie serveur hors-GPU est absent du calcul

**Constat :** le code n'additionne pas le terme d'énergie serveur non-GPU que documente la
méthodologie EcoLogits actuelle.

**Preuve :**
- `src/FootprintCalculator.php:43-48` — seule l'énergie GPU par token, multipliée par les tokens
  et le PUE, est calculée.
- Source externe actuelle (`https://ecologits.ai/latest/methodology/llm_inference/`) —
  `E_server = E_server\GPU + GPU × E_GPU` avec `E_server\GPU(ΔT) = ΔT × W_server\GPU × (GPU / #GPU_installés)`,
  `W_server\GPU = 1,2 kW`, `#GPU_installés = 8`, PUE appliqué à la somme entière, pas seulement au
  terme GPU.
- Estimation d'ordre de grandeur pour Llama 3.1 70B / 500 tokens : le terme serveur hors-GPU est
  du même ordre de grandeur que le terme GPU, donc non négligeable.

**Lentille :** Justesse.

**Réfutation tentée :** cherché une mention de cette limite dans `README.md:72-73` (« Énergie GPU
uniquement… »). La limite documentée parle de CPU/RAM/stockage/réseau/entraînement, jamais de ce
terme serveur spécifique de la méthodologie citée elle-même.

**Verdict :** RETENU — terme documenté par la source citée, omis du code, et non négligeable en
ordre de grandeur.

---

## 6. Le PUE=1,2 est présenté comme une moyenne alors qu'il est spécifique à un fournisseur

**Constat :** le docblock qualifie le PUE de « moyen » alors que 1,2 est la valeur spécifique
d'OpenAI/Azure dans la méthodologie citée, pas une moyenne sectorielle.

**Preuve :**
- `src/FootprintCalculator.php:31-33` (docblock) — « PUE … moyen retenu par EcoLogits ».
- `src/FootprintCalculator.php:35` — valeur codée : `1.2`.
- Source externe actuelle (`docs/methodology/llm_inference.md`, dépôt `mlco2/ecologits`) — PUE par
  fournisseur : Anthropic/Cohere/Google ≈1,09, HuggingFace 1,09-1,14, Mistral 1,16, OpenAI/Azure
  1,20. 1,2 correspond à OpenAI/Azure spécifiquement.
- Cette même valeur 1,2 est appliquée uniformément y compris au calcul de Llama 3.1 70B
  (`public/index.php:40`, non hébergé par OpenAI).

**Lentille :** Sources (et Justesse).

**Réfutation tentée :** vérifié le texte exact du docblock (le mot « moyen » y figure bel et bien,
pas une reformulation abusive de l'audit) et cherché une mention de cette simplification dans les
« Limites » de `README.md:65-81`. Absente.

**Verdict :** RETENU — qualification incorrecte confirmée mot pour mot contre la source citée, et
appliquée hors de son contexte d'origine (Llama 3.1 70B).

---

## 7. `badgeProvenance()` utilise un if/else non exhaustif sur l'enum

**Constat :** `badgeProvenance()` branche sur un if/else plutôt qu'un match exhaustif, ce qui
laisserait passer silencieusement un futur troisième cas de `ProvenanceType`.

**Preuve :**
- `public/index.php:18-25` — if/else confirmé (le code est fidèlement décrit).
- `src/ProvenanceType.php:16-19` — l'enum ne compte que 2 cas (`MesureeEtPubliee`, `Hypothese`).

**Lentille :** Fragilité.

**Réfutation tentée :** cherché un signe d'évolution prévue de l'enum (commentaire, ticket,
mention dans CLAUDE.md) qui rendrait le risque concret plutôt que théorique. Aucun trouvé : les 2
cas sont documentés comme les 2 natures exhaustives possibles d'une provenance.

**Verdict :** ÉCARTÉ — le défaut mécanique existe, mais le risque qu'il matérialise (un 3ᵉ cas
d'enum tombant dans le else) ne correspond à aucun scénario réaliste dans l'état actuel du projet.

---

## 8. `public/index.php` n'est couvert par aucun test

**Constat :** aucun test n'exécute ni ne vérifie le contenu de `public/index.php`, y compris sa
fonction `badgeProvenance()`.

**Preuve :**
- `public/index.php:18-25` — `badgeProvenance()`, la fonction qui porte la règle « hypothèse ≠
  mesure » jugée critique par CLAUDE.md.
- `tests/` — 5 fichiers (`EmissionFactorTest.php`, `FootprintCalculatorTest.php`,
  `FootprintTest.php`, `LanguageModelTest.php`, `ProvenanceTest.php`), aucun ne référence
  `index.php` ni `badgeProvenance`.
- Exécution réelle de `vendor/bin/phpunit tests` : 28 tests / 61 assertions, aucun issu de
  `public/`. Aucun `phpunit.xml` n'étend la portée au-delà de l'argument `tests` passé en CLI.

**Lentille :** Fragilité.

**Réfutation tentée :** cherché un test d'intégration qui inclurait `public/index.php` autrement.
Aucun trouvé ; confirmé par l'exécution réelle de la suite.

**Verdict :** RETENU — la fonction qui implémente la règle la plus critique du projet (ne jamais
afficher une hypothèse comme une mesure) n'a aucun filet de test.

---

## 9. `Provenance` n'impose pas le contenu qualitatif exigé pour une `Hypothese`

**Constat :** le constructeur de `Provenance` ne vérifie que la non-vacuité des champs, jamais que
la note d'une `Hypothese` explique l'absence de publication et donne la borne haute, comme
l'exige CLAUDE.md.

**Preuve :**
- `src/Provenance.php:21-42` — trois vérifications de non-vacuité (`url`, `millesimeOuDateDeConsultation`, `note`), identiques pour les deux types.
- `src/ProvenanceType.php:12-14` (docblock) et CLAUDE.md (règle « Mesure ou hypothèse — jamais
  l'inverse ») — exigence textuelle que la note d'une `Hypothese` justifie l'absence de donnée
  publiée et chiffre la borne haute.
- `new Provenance(ProvenanceType::Hypothese, 'https://x', '2024', 'x')` compile et s'exécute sans
  erreur.

**Lentille :** Fragilité.

**Réfutation tentée :** cherché dans `tests/ProvenanceTest.php` un test couvrant ce cas (note
insuffisante pour une Hypothese). Absent — seuls les trois cas de champ vide sont testés
(`:29-48`). Considéré aussi si une validation de contenu sémantique serait même faisable par du
code (regex fragile) : la difficulté de la solution n'invalide pas le constat sur l'absence de
garantie actuelle.

**Verdict :** RETENU — la règle est documentée mais non portée par le type, contrairement à ce que
prétend l'architecture « impossible de construire sans provenance complète ».

---

## 10. Couplage non testé entre le paramètre 176 et le texte de sa note

**Constat :** rien ne garantirait qu'un changement du nombre 176 dans le constructeur de `gpt4()`
sans toucher le texte de sa note serait détecté par les tests.

**Preuve :**
- `src/LanguageModel.php:56` — argument constructeur : `176`.
- `src/LanguageModel.php:61-70` — texte libre de la note citant « 176 » et « 528 ».
- `tests/LanguageModelTest.php:52-58,60-74` — seulement `assertGreaterThan(0, …)` et
  `assertStringContainsString('528', $note)`, aucune assertion stricte sur 176.
- `tests/FootprintCalculatorTest.php:54-63` — `testGpt4DonneLesValeursAttendues()`, avec le
  commentaire explicite (`:59-60`) « Verrouille les 176 milliards … un changement … doit faire
  échouer ce test », assertions à delta 0,0001 sur l'énergie et les émissions.

**Lentille :** Fragilité.

**Réfutation tentée :** calculé l'effet d'un changement 176→180 sur `energieTotaleWh` (environ
+2,3 %) contre la tolérance delta 0,0001 du test `FootprintCalculatorTest.php:61-62` : l'écart la
dépasse largement, ce test échouerait à coup sûr.

**Verdict :** ÉCARTÉ — un filet de sécurité existe bel et bien, intentionnel et documenté par
commentaire, même s'il vit dans un fichier différent de celui qu'on attendrait naturellement.

---

## 11. Duplication du récit GPT-4 entre le code et le README

**Constat :** le récit de l'hypothèse GPT-4 serait dupliqué mot pour mot entre `LanguageModel.php`
et `README.md`, sans mécanisme les reliant.

**Preuve :**
- `src/LanguageModel.php:61-70` (note de `gpt4()`).
- `README.md:52-63` (section « Provenance des valeurs »).
- Comparaison littérale : mêmes chiffres (1,8 billion, 10-30 %, 176-528 Md, facteur ×2,8) mais
  formulations et ordre de phrase différents — pas une copie mot pour mot.
- `git log -p --follow -- README.md` : les deux textes introduits ensemble dans le même commit
  (395b335), aucune modification de l'un sans l'autre depuis.

**Lentille :** Fragilité.

**Réfutation tentée :** cherché un cas réel de désynchronisation dans l'historique git plutôt
qu'un risque théorique. Aucun trouvé.

**Verdict :** ÉCARTÉ — les deux textes sont des reformulations indépendantes des mêmes faits
sourcés, pas une duplication verbatim, et l'historique ne montre aucune divergence jamais
matérialisée.

---

## 12. Incohérence arithmétique apparente dans la note GPT-4

**Constat :** « ~1,8 billion × [10 %,30 %] » devrait donner 180/540 milliards, pas 176/528 comme
cité dans la note.

**Preuve :**
- `src/LanguageModel.php:64-66` — texte exact : « environ 1,8 billion de paramètres au total » et
  ratio « 10 % à 30 % », donnant « 176 à 528 milliards ».
- Calcul avec le total exact sous-jacent (1,76 billion, soit 8 experts × 220 Md, chiffre de la
  fuite d'architecture citée par la source EcoLogits) : 1,76 × 10 % = 176, 1,76 × 30 % = 528 —
  exact.

**Lentille :** Sources (signalé comme non confirmé par son auteur).

**Réfutation tentée :** relu le texte complet et exact de la note (pas un résumé) : le mot
« environ » signale explicitement un arrondi de prose sur le total, pas une valeur exacte servant
de base de calcul. Vérifié que 1,76 (valeur exacte) × 10 %/30 % tombe exactement sur 176/528.

**Verdict :** ÉCARTÉ — pas d'incohérence : l'écart 1,76 vs « environ 1,8 » est un arrondi
d'affichage assumé, les valeurs finales 176/528 sont arithmétiquement exactes.

---

## 13. La précision d'arrondi « 1 décimale » n'existe nulle part en code

**Constat :** la règle d'arrondi à 1 décimale des facteurs d'émission n'est documentée que par
référence textuelle croisée entre docblocks, jamais par une constante ou fonction partagée.

**Preuve :**
- `src/EmissionFactor.php:26,50,71,92` — quatre littéraux `float` indépendants (81.3, 509.4,
  679.8, 590.4).
- `src/EmissionFactor.php:43-44,64-65,85-86` — docblocks de `europe()`, `etatsUnis()`, `monde()`
  renvoyant chacun au texte « précision de france() », sans code partagé.
- Aucune occurrence de `round(...)` ni de constante de précision dans `src/` ou `tests/`.

**Lentille :** Fragilité.

**Réfutation tentée :** cherché une fonction ou constante dérivant les 4 valeurs d'une même règle
(auquel cas le constat serait faux) — absente. Cherché un test de cohérence de précision entre
zones dans `tests/EmissionFactorTest.php` — chaque zone y est testée isolément (`:13-73`), aucune
comparaison inter-zones.

**Verdict :** RETENU — rien n'empêche une future édition manuelle de `france()` avec une précision
différente sans que les docblocks ou les tests ne le détectent.

---

## Résumé

| # | Constat | Lentille | Verdict |
|---|---|---|---|
| 1 | Arrondi Monde 590,4 au lieu de 590,5 | Justesse | **Retenu** |
| 2 | Test verrouille la valeur fausse | Fragilité | **Retenu** |
| 3 | URL morte, formule périmée (v0.4 non versionnée) | Sources | **Retenu** |
| 4 | Facteur nombre de GPU manquant | Justesse | **Retenu** |
| 5 | Terme d'énergie serveur hors-GPU manquant | Justesse | **Retenu** |
| 6 | PUE=1,2 qualifié à tort de « moyen » | Sources | **Retenu** |
| 7 | Badge if/else non exhaustif | Fragilité | Écarté |
| 8 | Zéro test sur `public/index.php` | Fragilité | **Retenu** |
| 9 | `Provenance` ne valide pas le contenu d'une Hypothese | Fragilité | **Retenu** |
| 10 | Couplage 176/note non testé | Fragilité | Écarté |
| 11 | Duplication README/LanguageModel | Fragilité | Écarté |
| 12 | Incohérence arithmétique 1,8T | Sources | Écarté |
| 13 | Précision d'arrondi non codée | Fragilité | **Retenu** |
