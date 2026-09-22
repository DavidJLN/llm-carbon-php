# Rapport de mutation testing (Infection)

Ce document est le rapport exploitable : que faire de chaque mutant échappé, pas seulement un
score. Voir `PREDICTION.md` pour l'écart chiffré avec la prédiction initiale, et
`escaped-mutants.txt` (versionné) pour le détail brut, régénéré à chaque exécution d'Infection,
des mutants échappés de la dernière exécution.

## 1. Configuration

- `infection.json5` : source = `src/`, framework de test = PHPUnit (`vendor/bin/phpunit`),
  journal texte versionné dans `mutation-testing/escaped-mutants.txt`, `threads: "max"` (Infection
  détecte lui-même le nombre de coeurs logiques disponibles via `fidry/cpu-core-counter`, sur
  n'importe quelle machine — pas de valeur codée en dur).
- Driver de couverture : `pcov` (plus léger que Xdebug pour Infection, qui exécute un process
  PHP par mutant).
- **Piège rencontré et à connaître** : ce dépôt, lorsqu'il est ouvert depuis un chemin
  `/mnt/c/...` sous WSL2 (disque Windows monté en 9p), rend chaque exécution de PHP coûteuse de
  plusieurs secondes en E/S pure (autoload), ce qui fait dépasser à presque tous les mutants le
  timeout par défaut d'Infection dès que plusieurs fils tournent en parallèle (observé : 225 à 232
  mutants sur 238 signalés « timeout », un faux négatif de mesure, pas un vrai résultat). Ce
  rapport a été produit en exécutant Infection depuis une copie du dépôt sur un filesystem Linux
  natif (ext4) ; sur un runner CI (filesystem natif) ou une machine Linux native, ce problème ne se
  pose pas.

## 2. Première exécution complète — écart avec la prédiction

Voir `PREDICTION.md`, section « Écart entre la prédiction et la première exécution réelle », pour
le détail. En résumé : 238 mutants générés (12× la prédiction de 20), 128 échappés, MSI code
couvert 46 % (contre 75 % prédit) — et les trous prédits (couverture zones/modèles dans
`DifferenceCalculatorTest`, `FootprintCalculatorFullTest`, `FootprintCalculatorSimplifiedTest`)
étaient déjà couverts à 100 %, tandis que le vrai trou (contenu exact du champ
`Provenance::$note`) n'avait pas été anticipé.

## 3. Classification des 128 mutants échappés (première exécution)

Chaque mutant est rattaché à l'un des 10 champs `note` concernés (pour les mutateurs
`Concat`/`ConcatOperandRemoval`, très nombreux par champ car chaque frontière entre deux fragments
concaténés produit plusieurs mutants) ou à la garde de constructeur concernée. Aucun mutant n'est
laissé non classé.

### 3.1 Trou de test réel — 121 mutants

**Description commune** : la classe `Provenance` porte un champ `note` obligatoire disant « ce que
la source affirme exactement » (règle primordiale du dépôt, `CLAUDE.md`), affiché tel quel dans le
pied de page de `public/index.php`. Avant correction, seul `EmissionFactor::france()->note` était
vérifié, et seulement par `assertStringContainsString` sur deux mots-clés — un contrôle qui ne
détecte ni un réordonnancement des fragments concaténés ni la suppression d'un fragment
n'incluant pas les mots-clés testés. Les 9 autres champs `note` (`EmissionFactor::europe/
unitedStates/world`, `LanguageModel::llama31_70b`, les deux notes de `gpt4()`, les deux notes de
`gpt4o()`, `qwen3_235b_a22b()`) n'avaient aucune assertion sur leur contenu.

**Utilisateur affecté** : une personne qui consulte le pied de page pour vérifier ce qu'une source
affirme réellement au sujet d'un facteur d'émission ou du nombre de paramètres d'un modèle lirait
une note tronquée ou dans le désordre — sans pouvoir se fier à ce que la note prétend citer. C'est
précisément le risque que `CLAUDE.md` désigne comme celui justifiant la règle primordiale du
projet.

| Champ `Provenance::$note` | Ligne (avant correction) | Mutants échappés |
|---|---|---|
| `EmissionFactor::france()` | `EmissionFactor.php:31` | 9 |
| `EmissionFactor::europe()` | `EmissionFactor.php:55` | 5 |
| `EmissionFactor::unitedStates()` | `EmissionFactor.php:76` | 5 |
| `EmissionFactor::world()` | `EmissionFactor.php:98` | 5 |
| `LanguageModel::llama31_70b()` | `LanguageModel.php:74` | 7 |
| `LanguageModel::gpt4()` (paramètres actifs) | `LanguageModel.php:97` | 30 |
| `LanguageModel::gpt4()` (paramètres totaux) | `LanguageModel.php:119` | 17 |
| `LanguageModel::gpt4o()` (paramètres actifs) | `LanguageModel.php:150` | 29 |
| `LanguageModel::gpt4o()` (paramètres totaux) | `LanguageModel.php:171` | 9 |
| `LanguageModel::qwen3_235b_a22b()` | `LanguageModel.php:192` | 5 |
| **Total** | | **121** |

**Correction appliquée** : ajout, pour chacun des 10 champs, d'un test dédié qui compare le
contenu du `note` par égalité exacte (`assertSame`) au texte source attendu, dans
`tests/EmissionFactorTest.php` (4 nouveaux tests) et `tests/LanguageModelTest.php` (6 nouveaux
tests). Chaque assertion protège le même comportement utilisateur : le texte affiché dans le pied
de page correspond mot pour mot à ce que la note prétend citer.

### 3.2 Code mort — 2 mutants

| Emplacement | Ligne | Mutants |
|---|---|---|
| `LanguageModel::__construct()`, garde `totalParametersBillions <= 0` | `LanguageModel.php:36` (condition) et `:37` (throw) | 2 (`LessThanOrEqualTo`, `Throw_`) |

**Analyse** : cette garde ne peut jamais produire un comportement distinct de la garde suivante
(`totalParametersBillions < activeParametersBillions`). En effet, au point où cette garde
s'exécute, la garde précédente (`activeParametersBillions <= 0`) a déjà garanti
`activeParametersBillions > 0`. Or `totalParametersBillions <= 0 < activeParametersBillions`
implique toujours `totalParametersBillions < activeParametersBillions` : tout cas que cette garde
attrape est donc systématiquement attrapé aussi par la garde suivante (avec un message différent,
mais une `InvalidArgumentException` est levée dans les deux cas). Affaiblir sa borne
(`<= 0` → `< 0`, mutant `LessThanOrEqualTo`) ou supprimer son `throw` (mutant `Throw_`) ne change
donc aucun comportement observable : c'est la définition même du code mort donnée pour cette
tâche.

**Correction appliquée** : la garde a été supprimée dans `src/LanguageModel.php`, avec un
commentaire expliquant pourquoi. Le test existant
`testZeroTotalParametersThrowsAnException` continue de passer (l'exception est désormais levée par
la garde suivante) car il ne vérifiait que le type de l'exception, pas son message — aucune
modification de test n'était nécessaire.

### 3.3 Détail d'implémentation — 5 mutants

| Emplacement | Ligne (avant correction) | Mutants |
|---|---|---|
| `LanguageModel::__construct()`, message de la garde `totalParametersBillions < activeParametersBillions` | `LanguageModel.php:44` | 5 (2 `Concat`, 3 `ConcatOperandRemoval`) |

**Analyse** : cette garde protège un vrai invariant (déjà testé : voir
`testTotalParametersBelowActiveParametersThrowsAnException`), mais le texte exact du message
d'exception qu'elle porte n'est observé par personne à l'extérieur du code — ce chemin ne se
déclenche jamais pour le catalogue actuel (`LanguageModel::all()`, vérifié par
`LanguageModelTest::testEachModelFromAllCarriesANonEmptyProvenance` et les tests dédiés à chaque
modèle) ; il ne peut être atteint qu'en construisant directement un `LanguageModel` avec des
paramètres incohérents, un cas déjà couvert par un test qui vérifie que l'exception est bien levée.
Verrouiller le libellé exact de ce message par une assertion reviendrait à figer un détail
d'implémentation sans pouvoir dire quel comportement utilisateur cela protège — ce que
l'énoncé de cette tâche interdit explicitement.

**Décision** : non corrigé, volontairement. Justification écrite ajoutée directement au-dessus du
`throw` dans `src/LanguageModel.php` (pas une simple annotation d'exclusion : une explication
complète de pourquoi aucun test n'est ajouté). Ces 5 mutants restent visibles dans
`mutation-testing/escaped-mutants.txt` à chaque exécution — c'est volontaire et documenté, pas un
oubli.

## 4. Résultat après correction

- 235 mutants générés (3 de moins qu'à la première exécution : la suppression de la garde morte
  élimine les 2 mutants qui lui étaient propres ; le comptage total d'Infection dépend aussi
  du découpage AST, d'où l'écart de 3 et non 2).
- 230 tués, **5 échappés** (les mutants « détail d'implémentation » de la section 3.3, laissés
  intentionnellement).
- **Mutation Code Coverage : 100 % — MSI (code couvert) : 97 %.**

## 5. Intégration continue

Job `mutation` ajouté à `.github/workflows/tests.yml` : installe les dépendances puis exécute
`vendor/bin/infection --no-progress --min-msi=97 --min-covered-msi=97` avec couverture `pcov`. Le
seuil (97 %) est la valeur atteinte à la fin de cette tâche, pas un objectif arbitraire : toute
régression en dessous fait échouer la CI. S'il devient possible de couvrir également les 5
mutants de la section 3.3 par un moyen qui protège un comportement utilisateur réel, remonter le
seuil en conséquence.
