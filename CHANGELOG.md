# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

Tant que la version majeure reste à `0`, l'interface publique (signatures des
classes de `src/`) peut encore changer sans préavis — voir [la clause 4 de
Semantic Versioning](https://semver.org/lang/fr/#spec-item-4).

## [Unreleased]

### Changed

- **Changement cassant.** Renommage en anglais de l'ensemble des identifiants de code de `src/`,
  `tests/` et `public/index.php` (classes, méthodes, propriétés, constantes), pour la lecture à
  l'international : `FootprintCalculatorSimplifie` → `FootprintCalculatorSimplified`,
  `FootprintCalculatorComplet` → `FootprintCalculatorFull`, `FootprintComplet` → `FootprintFull`,
  `EcartCalculator` → `DifferenceCalculator`, `EcartModeles` → `ModelsDifference`, ainsi que les
  propriétés et méthodes associées (`energieTotaleWh` → `totalEnergyWh`,
  `parametresActifsMilliards` → `activeParametersBillions`, `EmissionFactor::etatsUnis()` →
  `unitedStates()`, `EmissionFactor::monde()` → `world()`, `toutes()` → `all()`,
  `ProvenanceType::MesureeEtPubliee`/`Hypothese` → `MeasuredAndPublished`/`Hypothesis`, etc.). Le
  texte affiché par `public/index.php` reste en français ; seuls les identifiants de code
  changent.

## [0.1.0] - 2026-08-28

### Added

- Calcul de l'énergie consommée et des émissions de CO2eq d'une requête
  d'inférence à un LLM, selon la méthodologie
  [EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md) :
  modèle simplifié (`FootprintCalculatorSimplifie`) et modèle complet
  (`FootprintCalculatorComplet`, qui ajoute la mémoire GPU requise et le
  nombre de cartes nécessaires), avec décomposition chiffrée de l'écart entre
  les deux (`EcartCalculator`).
- Catalogue de modèles de langage avec provenance obligatoire par valeur
  (`LanguageModel` : Llama 3.1 70B, GPT-4, GPT-4o, Qwen3-235B-A22B) et de
  zones d'hébergement (`EmissionFactor` : France, Europe, États-Unis, Monde).
- Traçabilité systématique des sources : toute valeur numérique du calcul
  porte une `Provenance` typée (`ProvenanceType::MesureeEtPubliee` ou
  `Hypothese`), non nullable à la construction.
- Page de démonstration HTML (`public/index.php`) : calcul détaillé,
  tableaux comparatifs par zone et par modèle, badge visuel distinguant
  mesure et hypothèse.
- Suite de tests PHPUnit (`tests/`) et configuration stricte
  (`phpunit.xml` : `failOnWarning`, `failOnRisky`, `failOnNotice`).
- Relèvement de la version de PHP minimale requise à 8.4 (en raison de sa maintenabilité à plus long terme, jusque fin 2028).
- Intégration continue GitHub Actions (`.github/workflows/tests.yml`) :
  matrice de versions PHP déduite automatiquement de la contrainte
  `require.php` de `composer.json`, exécutant `composer validate --strict`
  puis la suite de tests sur chaque version.
- Licence MIT (`LICENSE`).
- Documentation : `README.md` (installation et usage du paquet Composer) et
  `AUDIT.md`.

[0.1.0]: https://github.com/DavidJLN/llm-carbon-php/releases/tag/v0.1.0
