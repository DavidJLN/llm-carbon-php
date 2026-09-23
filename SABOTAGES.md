# Sabotages sémantiques

Infection (voir `mutation-testing/`) mute la **syntaxe** : il inverse un opérateur, supprime un
fragment de chaîne, décale une borne. Il ne produira jamais les sabotages de ce document, qui
sont **plausibles** : un chiffre ou une source remplacé par un autre qui existe vraiment, qui a la
bonne forme et qu'un relecteur pressé laisserait passer. Ce sont pourtant exactement les erreurs
que la règle primordiale du dépôt (`CLAUDE.md`, « aucun chiffre sans source vérifiable ») cherche
à empêcher.

Pour chaque sabotage : la modification concrète, le ou les tests qui **doivent** rougir, et ce qui
a été **constaté**.

## Méthode

- Chaque sabotage a été appliqué seul sur une copie du dépôt, puis `vendor/bin/phpunit tests` a
  été lancé et le fichier restauré. Suite de référence : 69 tests, 139 assertions, tout vert.
  Date de l'exécution : 2026-09-23.
- 🔴 = au moins un test rougit (le test cité est le plus ciblé, pas la liste exhaustive).
- ⚪ = **toute la suite reste verte** : c'est un trou. La colonne donne alors le test à écrire
  (section 6).
- Les valeurs de remplacement sont **volontairement fausses** : elles servent à saboter, et ce
  document ne les présente jamais comme des valeurs publiées.

---

## 1. Remplacer un facteur d'émission par celui d'un autre millésime

| # | Sabotage | Test qui doit rougir | Constaté |
|---|---|---|---|
| 1.1 | `EmissionFactor::france()` : `81.3` → valeur d'un autre millésime (essai : `56.9`) | `EmissionFactorTest::testFrance` (+ 6 tests de calcul en zone France) | 🔴 7 échecs |
| 1.2 | `EmissionFactor::europe()` : `509.4` → autre millésime (essai : `420.0`) | `EmissionFactorTest::testEurope`, `…SimplifiedTest::testEachZone…@Europe`, `…FullTest::testEmissionsGco2eqAppliesTheZoneFactor@Europe` | 🔴 3 échecs |
| 1.3 | `EmissionFactor::world()` : `590.5` → `590.4` (bug réel déjà rencontré, voir `AUDIT.md` §1) | `EmissionFactorTest::testWorld`, `…SimplifiedTest::testEachZone…@Monde` | 🔴 2 échecs |
| 1.4 | Alpha de la régression EcoLogits (`FootprintCalculatorSimplified`) remplacé par celui d'une autre version d'EcoLogits (essai : `9.5e-5`) | `FootprintCalculatorSimplifiedTest::testTheReferenceCaseDoesNotMove` | 🔴 14 échecs |
| 1.5 | Bêta de latence (`FootprintCalculatorFull`) remplacé par une autre version (essai : `2.1e-2`) | `FootprintCalculatorFullTest::testDurationSecondsDependsOnActiveParametersAndTokens` | 🔴 14 échecs |
| 1.6 | PUE `1.2` (v0.4.0, valeur unique) → `1.09` (valeur par fournisseur des versions récentes, citée dans le docblock), dans `FootprintCalculatorFull` | `FootprintCalculatorFullTest::testTotalEnergyWhCombinesServerAndCardsTimesGpuEnergyUnderPue` | 🔴 11 échecs |
| 1.7 | Idem 1.6 dans `FootprintCalculatorSimplified` | `FootprintCalculatorSimplifiedTest::testTheReferenceCaseDoesNotMove` | 🔴 14 échecs |
| 1.8 | Millésime de la France `'0.4.0 (2024-08-29)'` → `'0.5.0 (2024-08-29)'`, valeur inchangée | `EmissionFactorTest::testFranceIsDoublyAttributedToEcoLogitsAndAdeme` | 🔴 1 échec |

**Bilan :** bien couvert. Toutes les valeurs numériques sont verrouillées à 0,0001 près, à la fois
directement et à travers le calcul.

---

## 2. Remplacer une source par une autre source plausible

| # | Sabotage | Test qui doit rougir | Constaté |
|---|---|---|---|
| 2.1 | URL de `europe()` : CSV Boavizta → CSV `electricity_mixes.csv` d'EcoLogits 0.4.0 (même famille de données, autre source) | `EmissionFactorTest::testEachFactorCitesItsExactProvenance` *(à écrire)* | ⚪ **vert** |
| 2.2 | URL de `france()` : `…/ecologits/blob/0.4.0/…` → `…/ecologits/blob/main/…` (lien qui dérivera dans le temps) | *idem* | ⚪ **vert** : `assertStringContainsString('github.com/mlco2/ecologits')` ne voit pas la différence |
| 2.3 | URL de `france()` → `https://base-empreinte.ademe.fr/` (seconde source réellement citée dans la note) | `EmissionFactorTest::testFranceIsDoublyAttributedToEcoLogitsAndAdeme` | 🔴 1 échec |
| 2.4 | URL de `llama31_70b()` : blog Meta → fiche Hugging Face `meta-llama/Llama-3.1-70B` | `LanguageModelTest::testEachModelCitesItsExactProvenance` *(à écrire)* | ⚪ **vert** |
| 2.5 | URL de `gpt4()` : page `proprietary_models/` → page `llm_inference/` d'EcoLogits | *idem* | ⚪ **vert** |
| 2.6 | URL de `qwen3_235b_a22b()` : blog Qwen3 → dépôt GitHub `QwenLM/Qwen3` | *idem* | ⚪ **vert** |
| 2.7 | URL de `gpt4o()` : `models.json` en `0.11.1` → `0.10.0` (la note continue de citer 0.11.1) | *idem* | ⚪ **vert** |
| 2.8 | Docblock de l'alpha (`FootprintCalculatorSimplified`) : `blob/0.4.0` → `blob/main` | Aucun test PHPUnit possible, voir §7 | ⚪ **vert** |
| 2.9 | Note de `europe()` : « sourcée ADEME Base IMPACTS® » → « sourcée Agence européenne pour l'environnement » | `EmissionFactorTest::testEuropeNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 2.10 | Type de la provenance « actifs » de `gpt4o()` : `Hypothesis` → `MeasuredAndPublished` | `LanguageModelTest::testGpt4oIsAMoeWithFewerActiveThanTotalParameters` | 🔴 1 échec |

**Bilan : trou majeur.** Les `note` sont verrouillées mot pour mot depuis le mutation testing,
mais pas les `url`. On peut donc faire pointer une valeur vers une autre source sans qu'aucun test
ne réagisse. La note annonce toujours ce que dit la source A alors que le lien cliquable du pied de
page mène à la source B. Seules les URL de la France sont partiellement vérifiées (par
sous-chaîne).

---

## 3. Changer l'unité d'une constante sans changer sa valeur

| # | Sabotage | Test qui doit rougir | Constaté |
|---|---|---|---|
| 3.1 | Note de `europe()` : `0,509427 kgCO2eq/kWh` → `0,509427 gCO2eq/kWh` (facteur ×1000 dans le texte cité) | `EmissionFactorTest::testEuropeNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 3.2 | Note de `france()` : `81,3 gCO2eq/kWh` → `81,3 kgCO2eq/kWh` | `EmissionFactorTest::testFranceNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 3.3 | Note de `llama31_70b()` : `70 milliards` → `70 millions` | `LanguageModelTest::testLlama3170bNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 3.4 | Note « actifs » de `gpt4()` : `1,8 billion` → `1,8 milliard` (faux-ami FR/EN, facteur ×1000) | `LanguageModelTest::testGpt4ActiveParametersNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 3.5 | Note « totaux » de `gpt4()` : `1 800 milliards (1,8 billion)` → `1 800 millions (1,8 milliard)` | `LanguageModelTest::testGpt4TotalParametersNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 3.6 | Renommer `NON_GPU_SERVER_POWER_W` en `…_KW`, `GPU_MEMORY_GB` en `…_GIB` et `LATENCY_BETA_S` en `…_MS` (valeurs inchangées, tous les usages renommés) | `FootprintCalculatorFullTest::testConstantsCarryTheirUnitInTheirName` *(à écrire, voir §6)* | ⚪ **vert** |
| 3.7 | Docblock de `EmissionFactor::france()` : « in gCO2eq per kWh » → « in kgCO2eq per kWh » | Aucun test PHPUnit possible, voir §7 | ⚪ **vert** |
| 3.8 | Docblock de `NON_GPU_SERVER_POWER_W` : « Expressed here in watts » → « in kilowatts » | Aucun test PHPUnit possible, voir §7 | ⚪ **vert** |
| 3.9 | Docblock de `GPU_MEMORY_GB` : « in GB » → « in GiB » | Aucun test PHPUnit possible, voir §7 | ⚪ **vert** |
| 3.10 | Docblock de `LATENCY_BETA_S` : « in seconds » → « in milliseconds » | Aucun test PHPUnit possible, voir §7 | ⚪ **vert** |
| 3.11 | Libellé d'unité affiché dans `public/index.php` (`gCO2eq/kWh` → `kgCO2eq/kWh`, `Wh` → `kWh`) | `IndexPageTest::testDisplayedUnitsMatchTheComputedQuantities` *(à écrire)* | ⚪ **vert** : aucun test ne charge `public/index.php` |

**Bilan :** l'unité est bien protégée quand elle se trouve dans une `note`, puisque les notes sont
comparées mot pour mot. Elle ne l'est pas quand elle est portée par un nom de constante, un
docblock ou le libellé affiché. Or c'est ce libellé que lit l'utilisateur final.

---

## 4. Vieillir un millésime d'un an

| # | Sabotage | Test qui doit rougir | Constaté |
|---|---|---|---|
| 4.1 | `france()` : `'0.4.0 (2024-08-29)'` → `'0.4.0 (2023-08-29)'` | `EmissionFactorTest::testEachFactorCitesItsExactProvenance` *(à écrire)* | ⚪ **vert** : le test existant ne vérifie que la présence de `0.4.0` |
| 4.2 | `europe()` : `yearOrConsultationDate` `'2011'` → `'2010'` (la note dit toujours « données 2011 ») | *idem* | ⚪ **vert** |
| 4.3 | `europe()` : note « (données 2011) » → « (données 2010) » | `EmissionFactorTest::testEuropeNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |
| 4.4 | `llama31_70b()` : `'2024-07-23'` → `'2023-07-23'` (antérieur à la sortie du modèle) | `LanguageModelTest::testEachModelCitesItsExactProvenance` *(à écrire)* | ⚪ **vert** |
| 4.5 | `gpt4()` actifs : `'Consulté le 2026-08-20'` → `'Consulté le 2025-08-20'` | *idem* | ⚪ **vert** |
| 4.6 | `gpt4()` totaux : `'Consulté le 2026-08-27'` → `'Consulté le 2025-08-27'` | *idem* | ⚪ **vert** |
| 4.7 | `qwen3_235b_a22b()` : `'2025-04-29'` → `'2024-04-29'` (antérieur à l'annonce) | *idem* | ⚪ **vert** |

**Bilan : trou total sur le champ `yearOrConsultationDate`.** Un millésime n'est protégé que s'il
est répété dans la note. Le champ dédié, celui qu'affiche `provenanceBadge()` après le lien, n'est
vérifié par égalité exacte pour aucune des huit provenances. Pour les zones Boavizta, on peut
même obtenir un champ qui contredit sa propre note (4.2) sans qu'aucun test ne rougisse.

---

## 5. Remplacer un nombre de paramètres par celui d'un modèle voisin

| # | Sabotage | Test qui doit rougir | Constaté |
|---|---|---|---|
| 5.1 | `llama31_70b()` : `70` → `405` (variante voisine de la même famille, citée dans la note) | `LanguageModelTest::testLlama3170bIsMeasuredAndPublished` | 🔴 15 échecs |
| 5.2 | `llama31_70b()` : `70` → `8` (autre variante voisine) | *idem* | 🔴 15 échecs |
| 5.3 | Nom `'Llama 3.1 70B'` → `'Llama 3.3 70B'` (modèle voisin, même taille, donc même calcul) | `LanguageModelTest::testLlama3170bIsMeasuredAndPublished` (assertion sur le nom) | 🔴 1 échec, et c'est le seul filet |
| 5.4 | `gpt4()` actifs : `176` → `132` (borne haute de GPT-4o) | `LanguageModelTest::testGpt4IsAMoeWithFewerActiveThanTotalParameters` | 🔴 5 échecs |
| 5.5 | `gpt4()` totaux : `1800` → `1760` (chiffre GPT-4 qu'EcoLogits utilise selon le docblock de `gpt4o()`) | `LanguageModelTest::testGpt4IsAMoeWithFewerActiveThanTotalParameters` | 🔴 1 échec, et c'est le seul filet : 1 800 et 1 760 donnent tous deux 14 cartes, donc aucun test de calcul ne bouge |
| 5.6 | `gpt4o()` actifs : `44` → `176` (valeur de GPT-4) | `LanguageModelTest::testGpt4oIsAMoeWithFewerActiveThanTotalParameters` | 🔴 4 échecs |
| 5.7 | `gpt4o()` totaux : `440` → `1760` | *idem* | 🔴 3 échecs |
| 5.8 | `qwen3_235b_a22b()` actifs : `22` → `3` (Qwen3-30B-A3B, voisin de la même annonce) | `LanguageModelTest::testQwen3235bA22bIsMeasuredAndPublishedWithTotalDistinctFromActive` | 🔴 3 échecs |
| 5.9 | `qwen3_235b_a22b()` totaux : `235` → `30` (idem) | *idem* | 🔴 2 échecs |
| 5.10 | Note de Qwen : « 235 billion total » → « 22 billion total » | `LanguageModelTest::testQwen3235bA22bNoteMatchesExactlyWhatTheSourceStates` | 🔴 1 échec |

**Bilan :** bien couvert. Deux sabotages (5.3 et 5.5) ne sont toutefois attrapés que par
l'assertion littérale du test de catalogue, parce qu'ils ne changent aucun résultat de calcul.
Ces deux assertions ne doivent pas être supprimées au motif qu'elles « font doublon » avec les
tests de calcul.

---

## 6. Tests à écrire pour fermer les trous

| Test proposé | Ce qu'il verrouille | Sabotages fermés |
|---|---|---|
| `EmissionFactorTest::testEachFactorCitesItsExactProvenance` | `assertSame` sur `url` **et** `yearOrConsultationDate` de chacune des 4 zones (DataProvider) | 2.1, 2.2, 4.1, 4.2 |
| `LanguageModelTest::testEachModelCitesItsExactProvenance` | `assertSame` sur `url` **et** `yearOrConsultationDate` des 8 provenances (actifs + totaux × 4 modèles) | 2.4 à 2.7, 4.4 à 4.7 |
| `FootprintCalculatorFullTest::testConstantsCarryTheirUnitInTheirName` | Via `ReflectionClassConstant`, vérifie que `NON_GPU_SERVER_POWER_W` vaut 1000, `GPU_MEMORY_GB` vaut 80, `LATENCY_BETA_S` vaut 2.23e-2, etc. Renommer un suffixe d'unité fait alors échouer le test. | 3.6 |
| `IndexPageTest::testDisplayedUnitsMatchTheComputedQuantities` | Capture la sortie de `public/index.php` (`ob_start` + `include`) et vérifie les libellés `gCO2eq/kWh`, `Wh` et `gCO2eq` à côté des valeurs attendues | 3.11 |

Ces tests restent compatibles avec la règle « aucune dépendance externe » : ils n'utilisent que
PHPUnit, déjà présent, et la Reflection native de PHP.

---

## 7. Ce qu'aucun test ne peut attraper

Il faut le dire explicitement pour que personne ne croie la suite plus protectrice qu'elle ne
l'est :

- **Les commentaires et les docblocks** (2.8, 3.7 à 3.10). Pour les coefficients privés des
  calculateurs, `CLAUDE.md` fait du docblock l'unique porteur de la source. Un changement de
  version ou d'unité dans ce texte n'est donc visible qu'en relecture.
- **La concordance entre la valeur et la source réelle.** Tous les tests ci-dessus comparent le
  code à une copie de lui-même écrite dans le test. Si quelqu'un modifie la valeur **et** le test
  dans le même commit, la suite reste verte. Seul un humain qui ouvre l'URL et relit la ligne citée
  peut vérifier que `81.3` correspond bien à la ligne `FRA` d'EcoLogits 0.4.0.
- **Un sabotage cohérent sur les trois champs.** Si l'on change à la fois la valeur, la note et le
  millésime vers un autre millésime réel, les tests rougissent tant qu'ils ne sont pas mis à jour.
  Une fois mis à jour, ils ne prouvent plus que la bonne version de la source a été choisie. Seul
  le message de commit et la revue peuvent le justifier.

Conséquence pratique : toute modification d'un test de ce dépôt qui change un littéral numérique,
une URL, un millésime ou une note doit être relue comme une **modification de donnée sourcée**, pas
comme une simple correction de test.
