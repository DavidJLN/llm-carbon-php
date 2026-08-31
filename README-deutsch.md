# llm-carbon-php — Verwendung als Paket

Dieses Dokument beschreibt `davidjln/llm-carbon-php` als **Composer-Paket
zur Installation im eigenen Code**. Für die eigenständige Web-Demo dieses
Repositories (HTML-Seite mit einem fest codierten Szenario) siehe
[README-demo-deutsch.md](README-demo-deutsch.md).

## Was dieses Paket macht, und für wen

Dieses Paket berechnet den verbrauchten Energieaufwand und die
CO2eq-Emissionen einer Inferenzanfrage an ein Sprachmodell (LLM), ausgehend
von drei Eingaben: einem Modell (Anzahl der Parameter), einem
Emissionsfaktor (geografische Hosting-Zone des Rechenzentrums) und einer
Anzahl generierter Tokens. Die Berechnung folgt der Methodik von
[EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md).

Es richtet sich an PHP-Entwickler, die eine **CO2-Schätzung in ihre eigene
Anwendung integrieren** möchten (Dashboard, Logging, Reporting), anstatt
die in diesem Repository bereitgestellte Demo-Seite zu verwenden — zum
Beispiel um bei jedem LLM-Aufruf ihrer Anwendung die damit verbundene
Energie und Emissionen zu berechnen.

## Installation

Erforderliche PHP-Mindestversion: **8.4** (das Paket verwendet
`readonly`-Eigenschaften).

```bash
composer require davidjln/llm-carbon-php
```

Dieses Paket hat keine Laufzeitabhängigkeit: `composer require`
installiert nur das Paket selbst.

## Minimale Verwendung

```php
<?php

require 'vendor/autoload.php';

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

$footprint = (new FootprintCalculatorSimplified())->calculate(
    LanguageModel::llama31_70b(),
    EmissionFactor::france(),
    500, // Anzahl der in der Antwort generierten Tokens
);

echo $footprint->emissionsGco2eq, ' gCO2eq';
```

`FootprintCalculatorSimplified::calculate()` gibt ein `Footprint`-Objekt
zurück (`src/Footprint.php`), das drei Werte liefert: `energyPerTokenWh`,
`totalEnergyWh` und `emissionsGco2eq`. `LanguageModel` und
`EmissionFactor` bieten jeweils eine Factory-Methode pro Katalogwert (siehe
`all()` in jeder Klasse für die vollständige Liste: Modelle
`llama31_70b()`, `gpt4()`, `gpt4o()`, `qwen3_235b_a22b()`; Zonen
`france()`, `europe()`, `unitedStates()`, `world()`).

Eine zweite Implementierung, `FootprintCalculatorFull`, hat dieselbe
`calculate()`-Signatur und berücksichtigt zusätzlich den benötigten
GPU-Speicher sowie die Anzahl der zum Laden des Modells erforderlichen
Karten; siehe [README-demo-deutsch.md](README-demo-deutsch.md#methodik) für
Details zum Unterschied zwischen beiden.

## Was die Berechnung abdeckt

Der Umfang beschränkt sich strikt auf die **Inferenz**, ausgehend allein
von der **Anzahl der als Ausgabe generierten Tokens**:

- die GPU-Energie, die zur Generierung der Antwort-Tokens verbraucht wird
  (EcoLogits-Regression auf Basis der aktiven Parameter des Modells);
- nur mit `FootprintCalculatorFull`: die Nicht-GPU-Serverenergie, die
  mit derselben Generierung verbunden ist;
- die Umrechnung dieser Energie in CO2eq-Emissionen, anhand des
  Emissionsfaktors des Strommixes der gewählten Zone.

## Was die Berechnung nicht abdeckt

- **Eingabe-Tokens**: der an das Modell gesendete Prompt fließt in keiner
  Weise in die Berechnung ein; verwendet wird nur die Anzahl der
  *generierten* Tokens.
- **Training des Modells**: die mit dem Training (oder Fine-Tuning)
  verbundene Energie und Emissionen werden nicht berücksichtigt — nur die
  Inferenz wird erfasst.
- **Herstellung der Hardware**: Emissionen im Zusammenhang mit der
  Herstellung von GPUs und Servern (die „verkörperte" Wirkung, vor deren
  Inbetriebnahme) werden nicht berücksichtigt — nur die während der
  Ausführung der Anfrage verbrauchte Energie wird erfasst.
- **Speicherung und Netzwerk**: weder die Energie für die Speicherung der
  Modellgewichte noch die für den Netzwerktransport der Anfrage oder
  Antwort wird berücksichtigt.
- **Unsicherheit ist kein statistisches Konfidenzintervall**: jeder
  Eingabewert zitiert seine `Provenance` (gemessen und veröffentlicht vom
  Modellanbieter, oder eine rekonstruierte Hypothese mangels
  Veröffentlichung — siehe `src/ProvenanceType.php`), und das davon
  abhängige Ergebnis erbt diesen Status, aber das Paket berechnet keine
  Fehlermarge oder Ergebnisspanne: für die beiden proprietären Modelle im
  Katalog (GPT-4, GPT-4o), deren Parameter nicht veröffentlicht sind, wird
  eine konservative Hypothese (untere Grenze) statt einer Spanne
  verwendet.

## Quellen und Datenstände

- **GPU-Energieregression (α, β) und Rechenzentrums-PUE (1,2)**:
  [EcoLogits-v0.4.0-Methodik](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md)
  und [exakte
  Werte](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py)
  — Version 0.4.0.
- **Emissionsfaktor Frankreich** (81,3 gCO2eq/kWh): [electricity_mixes.csv
  von EcoLogits
  v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
  derselbe Wert wird auch von der [ADEME Base
  Empreinte](https://base-empreinte.ademe.fr/) veröffentlicht.
- **Emissionsfaktoren Europa, USA, Welt**: [Boavizta-Stromdatensatz](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
  Daten von 2011 (Quelle: ADEME Base IMPACTS®).
- **Llama 3.1 70B** (70 Milliarden Parameter, dicht): [offizielle
  Ankündigung von Meta, 23.07.2024](https://ai.meta.com/blog/meta-llama-3-1/).
- **Qwen3-235B-A22B** (235 Milliarden Gesamtparameter, 22 Milliarden
  aktiviert): [offizielle Qwen3-Ankündigung,
  29.04.2025](https://qwenlm.github.io/blog/qwen3/).
- **GPT-4 und GPT-4o** (von OpenAI nicht veröffentlichte Parameter, Werte
  vom Typ `Hypothesis`): [EcoLogits-Methodik für proprietäre
  Modelle](https://ecologits.ai/latest/methodology/proprietary_models/)
  und [Modell-Datensatz von EcoLogits
  0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

Die vollständigen Details zu jeder Quelle (URL, Datenstand, was sie genau
behauptet) sind über den Code zugänglich, via `LanguageModel::$provenance`
/ `$totalParametersProvenance` und `EmissionFactor::$provenance` — siehe
[README-demo-deutsch.md](README-demo-deutsch.md#herkunft-der-werte) für die
Zusammenfassung und die detaillierten Einschränkungen.
