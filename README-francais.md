# llm-carbon-php — utilisation en tant que paquet

Ce document décrit `davidjln/llm-carbon-php` comme **paquet Composer à
installer dans votre propre code**. Pour la démo web autonome de ce dépôt
(page HTML qui affiche un scénario codé en dur), voir [README-demo.md](README-demo.md).

## Ce que ce paquet fait, et pour qui

Ce paquet calcule l'énergie consommée et les émissions de CO2eq d'une
requête d'inférence à un modèle de langage (LLM), à partir de trois entrées :
un modèle (nombre de paramètres), un facteur d'émission (zone géographique
d'hébergement du datacenter) et un nombre de tokens générés. Le calcul suit
la méthodologie [EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md).

Il s'adresse aux développeurs PHP qui veulent **intégrer une estimation
carbone à leur propre application** (dashboard, logging, reporting) plutôt
qu'utiliser la page de démonstration fournie par ce dépôt — par exemple pour
calculer, à chaque appel à un LLM effectué par leur application, l'énergie et
les émissions associées à cet appel précis.

## Installation

Version minimale de PHP requise : **8.4** (le paquet utilise des propriétés
`readonly`).

```bash
composer require davidjln/llm-carbon-php
```

Ce paquet n'a aucune dépendance d'exécution : `composer require` n'installe
que le paquet lui-même.

## Usage minimal

```php
<?php

require 'vendor/autoload.php';

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

$footprint = (new FootprintCalculatorSimplified())->calculate(
    LanguageModel::llama31_70b(),
    EmissionFactor::france(),
    500, // nombre de tokens générés par la réponse
);

echo $footprint->emissionsGco2eq, ' gCO2eq';
```

`FootprintCalculatorSimplified::calculate()` retourne un `Footprint`
(`src/Footprint.php`) exposant trois valeurs : `energyPerTokenWh`,
`totalEnergyWh` et `emissionsGco2eq`. `LanguageModel` et `EmissionFactor`
exposent chacun une fabrique par valeur du catalogue (voir `all()` sur
chaque classe pour la liste complète : modèles `llama31_70b()`, `gpt4()`,
`gpt4o()`, `qwen3_235b_a22b()` ; zones `france()`, `europe()`, `unitedStates()`,
`world()`).

Une seconde implémentation, `FootprintCalculatorFull`, a la même
signature `calculate()` et prend en compte en plus la mémoire GPU requise et
le nombre de cartes nécessaires pour charger le modèle ; voir
[README-demo.md](README-demo.md#méthodologie) pour le détail de la différence entre
les deux.

## Ce que le calcul couvre

Le périmètre est strictement celui de **l'inférence**, à partir du seul
**nombre de tokens générés en sortie** :

- l'énergie GPU consommée pour générer les tokens de la réponse (régression
  EcoLogits sur les paramètres actifs du modèle) ;
- avec `FootprintCalculatorFull` uniquement, l'énergie du serveur hors
  GPU associée à cette même génération ;
- la conversion de cette énergie en émissions de CO2eq, via le facteur
  d'émission du mix électrique de la zone choisie.

## Ce que le calcul ne couvre pas

- **Les tokens d'entrée** : le prompt envoyé au modèle n'entre dans le
  calcul à aucun titre ; seul le nombre de tokens *générés* est utilisé.
- **L'entraînement du modèle** : l'énergie et les émissions liées à
  l'entraînement (ou au fine-tuning) ne sont pas comptées — seule
  l'inférence l'est.
- **La fabrication du matériel** : les émissions liées à la fabrication des
  GPU et des serveurs (impact « embarqué », en amont de leur mise en
  service) ne sont pas comptées — seule l'énergie consommée pendant
  l'exécution de la requête l'est.
- **Le stockage et le réseau** : ni l'énergie de stockage des poids du
  modèle, ni celle du transport réseau de la requête ou de la réponse, ne
  sont comptées.
- **L'incertitude n'est pas un intervalle de confiance statistique** :
  chaque valeur d'entrée cite sa `Provenance` (mesurée et publiée par le
  fournisseur du modèle, ou hypothèse reconstituée faute de publication —
  voir `src/ProvenanceType.php`) et le résultat qui en dépend hérite de ce
  statut, mais le paquet ne calcule aucune marge d'erreur ni fourchette de
  sortie : pour les deux modèles propriétaires du catalogue (GPT-4, GPT-4o),
  dont les paramètres ne sont pas publiés, une hypothèse conservatrice
  (borne basse) est retenue plutôt qu'une fourchette.

## Sources et millésimes

- **Régression énergie GPU (α, β) et PUE datacenter (1,2)** : [méthodologie
  EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md)
  et [valeurs exactes](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py)
  — version 0.4.0.
- **Facteur d'émission France** (81,3 gCO2eq/kWh) : [electricity_mixes.csv
  d'EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
  même valeur également publiée par la [Base Empreinte de
  l'ADEME](https://base-empreinte.ademe.fr/).
- **Facteurs d'émission Europe, États-Unis, Monde** : [jeu de données
  électriques de
  Boavizta](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
  données 2011 (sourcées ADEME Base IMPACTS®).
- **Llama 3.1 70B** (70 milliards de paramètres, dense) : [annonce
  officielle Meta, 2024-07-23](https://ai.meta.com/blog/meta-llama-3-1/).
- **Qwen3-235B-A22B** (235 milliards de paramètres totaux, 22 milliards
  activés) : [annonce officielle Qwen3,
  2025-04-29](https://qwenlm.github.io/blog/qwen3/).
- **GPT-4 et GPT-4o** (paramètres non publiés par OpenAI, valeurs typées
  `Hypothesis`) : [méthodologie EcoLogits pour les modèles
  propriétaires](https://ecologits.ai/latest/methodology/proprietary_models/)
  et [jeu de données des modèles d'EcoLogits
  0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

Le détail complet de chaque source (URL, millésime, ce qu'elle affirme
exactement) est accessible par le code via `LanguageModel::$provenance` /
`$totalParametersProvenance` et `EmissionFactor::$provenance` — voir
[README-demo.md](README-demo.md#provenance-des-valeurs) pour la synthèse et les
limites détaillées.
