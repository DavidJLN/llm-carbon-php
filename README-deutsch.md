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
`llama31_70b()`, `gpt4()`, `gpt4o()`, `qwen3_235b_a22b()`,
`kimiK2()`, `mistralLarge2()`, `grok1()`, `claudeSonnet46()`, `claudeOpus48()`, `claudeHaiku45()`; Zonen
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
  Fehlermarge oder Ergebnisspanne: für die proprietären Modelle im Katalog
  (GPT-4, GPT-4o, Claude Sonnet 4.6, Claude Opus 4.8, Claude Haiku 4.5), deren Parameter nicht veröffentlicht sind, wird
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
- **Kimi K2** (1.000 Milliarden Gesamtparameter, 32 Milliarden aktiviert):
  [offizielles Repository von Moonshot AI, 07.2025](https://github.com/moonshotai/kimi-k2).
- **Mistral Large 2** (123 Milliarden Parameter, dicht): [offizielle
  Ankündigung von Mistral AI, 24.07.2024](https://mistral.ai/news/mistral-large-2407).
- **Grok-1** (314 Milliarden Gesamtparameter, davon 25 % aktiv, also 78,5
  Milliarden): [offizielle Ankündigung von xAI, 17.03.2024](https://x.ai/news/grok-os).
  Grok-1 ist ein Basismodell von 2023, **nicht** das derzeit von xAI
  angebotene Grok (Grok 3, Grok 4…), dessen Parameter nicht veröffentlicht sind.
- **Claude Sonnet 4.6, Claude Opus 4.8, Claude Haiku 4.5** (Anthropic
  veröffentlicht weder Parameter noch Energie pro Anfrage, Werte vom Typ
  `Hypothesis`): [Modell-Datensatz von EcoLogits
  0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).
- **GPT-4 und GPT-4o** (von OpenAI nicht veröffentlichte Parameter, Werte
  vom Typ `Hypothesis`): [EcoLogits-Methodik für proprietäre
  Modelle](https://ecologits.ai/latest/methodology/proprietary_models/)
  und [Modell-Datensatz von EcoLogits
  0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

Unter den Anbietern des Katalogs veröffentlicht nur Mistral AI einen
gemessenen Fußabdruck: seine [Lebenszyklusanalyse von Mistral Large 2,
22.07.2025](https://mistral.ai/news/our-contribution-to-a-global-environmental-standard-for-ai) (mit Carbone 4 und ADEME) nennt 1,14 gCO2eq, 45 mL Wasser
und 0,16 mg Sb eq für eine Antwort von 400 Tokens, ohne Endgeräte der
Nutzer. Sie wird hier nur zum Vergleich zitiert, nicht als Eingabewert der
Berechnung: ihr Umfang (gesamter Lebenszyklus, einschließlich
Hardwareherstellung und Training) unterscheidet sich von dem dieses
Projekts (nur Inferenzenergie).

Die vollständigen Details zu jeder Quelle (URL, Datenstand, was sie genau
behauptet) sind über den Code zugänglich, via `LanguageModel::$provenance`
/ `$totalParametersProvenance` und `EmissionFactor::$provenance` — siehe
[README-demo-deutsch.md](README-demo-deutsch.md#herkunft-der-werte) für die
Zusammenfassung und die detaillierten Einschränkungen.
