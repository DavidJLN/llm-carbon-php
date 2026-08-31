# llm-carbon-php

[![Tests](https://github.com/DavidJLN/llm-carbon-php/actions/workflows/tests.yml/badge.svg)](https://github.com/DavidJLN/llm-carbon-php/actions/workflows/tests.yml)

Kleines PHP-Skript, das den verbrauchten Energieaufwand und die
CO2eq-Emissionen einer Anfrage an ein Sprachmodell (LLM) schätzt, ausgehend
von fest codierten Eingabewerten (Modell, Anzahl aktiver Parameter, Anzahl
generierter Tokens).

## Verwendung

```bash
composer install
php -S localhost:8000 -t public
```

Anschließend http://localhost:8000 in einem Browser öffnen. `composer install` lädt keine Abhängigkeit herunter: es erzeugt lediglich den PSR-4-Autoloader für den Namespace `LlmCarbon\`.

## Methodik

Die Berechnung folgt der Methodik von [EcoLogits
v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md),
die zwei Modellierungsebenen anbietet. Beide existieren in diesem
Repository nebeneinander (`FootprintCalculatorSimplified` und
`FootprintCalculatorFull`), um ihre Ergebnisse vergleichen zu können.

**Vereinfachtes Modell** (`src/FootprintCalculatorSimplified.php`):

1. **GPU-Energie pro Token**: lineare Regression zwischen der Anzahl der
   aktiven Parameter des Modells (in Milliarden) und der pro generiertem
   Token verbrauchten Energie, `Energie = α × Parameter + β`.
2. **Gesamtenergie**: die Energie pro Token wird mit der Anzahl der
   generierten Tokens multipliziert, dann mit dem PUE (Power Usage
   Effectiveness) eines Hyperscale-Rechenzentrums oder Supercomputers, den
   EcoLogits v0.4.0 zugrunde legt (1,2), um die Nebenenergie (Kühlung,
   Infrastruktur) zu berücksichtigen — siehe „Einschränkungen" weiter
   unten: das ist kein branchenweiter Durchschnitt.
3. **CO2eq-Emissionen**: die Gesamtenergie (in kWh umgerechnet) wird mit
   dem Emissionsfaktor des Strommixes der Hosting-Zone des Rechenzentrums
   multipliziert.

**Vollständiges Modell** (`src/FootprintCalculatorFull.php`):
verwendet dieselbe Regression für die GPU-Energie, bettet sie aber in eine
detailliertere Berechnung ein, die auch die tatsächlich zum Hosten des
Modells benötigte Hardware berücksichtigt:

1. **Benötigter Speicher**, ausgehend von den GESAMTPARAMETERN des Modells
   (nicht nur den aktiven Parametern) und einer Quantisierungs-Annahme
   (standardmäßig 4 Bit, mangels Veröffentlichung durch den Anbieter), mit
   einem ×1,2-Aufschlag.
2. **Anzahl der GPU-Karten**, die zum Laden des Modells benötigt werden
   (benötigter Speicher geteilt durch den Speicher einer Referenzkarte —
   80 GB — aufgerundet).
3. **Generierungsdauer**, eine Regression auf den aktiven Parametern.
4. **Nicht-GPU-Serverenergie**, proportional zur Dauer und zur Anzahl der
   verwendeten Karten (von den 8 Karten des Referenzservers).
5. **GPU-Energie**, wie im vereinfachten Modell, jedoch multipliziert mit
   der Anzahl der benötigten Karten.
6. **Gesamtenergie und Emissionen**, wie im vereinfachten Modell, aus der
   Summe der beiden vorherigen Energien.

Das vollständige Modell liefert ein höheres Ergebnis als das vereinfachte
Modell, sobald ein Modell mehrere GPU-Karten benötigt (die GPU-Energie
wird dann mehrfach gezählt); bei einem dichten Modell, das auf eine
einzige Karte passt, stammt die Abweichung ausschließlich von der
Nicht-GPU-Serverenergie, die im vereinfachten Modell fehlt.
`src/DifferenceCalculator.php` zerlegt diese Abweichung in zwei Teile, die sich
exakt aufsummieren (kein Rest): den „Server"-Anteil (der im vereinfachten
Modell fehlende Term) und den „Karten"-Anteil (der Mehrbetrag durch die
mehrfache Zählung der GPU-Energie). Die Seite zeigt beide Ergebnisse
nebeneinander sowie die Gesamtabweichung und deren Zerlegung.

Die detaillierten Zahlen zu jedem Schritt beider Modelle werden auf der
Seite unterhalb der Ergebnisse angezeigt, zusammen mit zwei
Vergleichstabellen bei sonst gleichen Parametern: nach Hosting-Zone
(Frankreich, Europa, USA, Welt) und nach Katalogmodell (Llama 3.1 70B,
GPT-4, GPT-4o, Qwen3-235B-A22B) — diese zweite Tabelle zeigt zusätzlich
die Anzahl der benötigten GPU-Karten und die Abweichung (gesamt, davon
Server, davon Karten) pro Modell. Der französische Emissionsfaktor hat
eine doppelte Zuordnung: [ADEME Base
Empreinte](https://base-empreinte.ademe.fr/) und [electricity_mixes.csv
von EcoLogits
v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
die denselben Wert veröffentlichen. Die anderen drei Zonen stammen aus dem
[Boavizta-Stromdatensatz](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
mit Quelle ADEME Base IMPACTS® (Daten von 2011).

## Herkunft der Werte

Jeder `EmissionFactor` trägt eine `Provenance` (`src/Provenance.php`), und
jedes `LanguageModel` trägt zwei — eine für seine aktiven Parameter, eine
für seine Gesamtparameter, die unterschiedliche Ursprünge haben können
(siehe GPT-4 unten). Eine `Provenance` ist bei der Konstruktion
verpflichtend: Typ (`MeasuredAndPublished` oder `Hypothesis`), Quell-URL,
Datenstand oder Abrufdatum, und eine Notiz, die genau angibt, was die
Quelle behauptet. Es ist unmöglich, eine dieser beiden Klassen ohne
vollständige Provenance zu erzeugen — der Konstruktor erzwingt dies. Für
ein dichtes Modell (aktive Parameter = Gesamtparameter, wie Llama 3.1
70B) legt die Factory `LanguageModel::dense()` diese Gleichheit einmalig
fest, anstatt den Wert und seine Provenance zu wiederholen.

Die meisten Katalogwerte sind gemessen und von ihrer Quelle veröffentlicht
(grünes Abzeichen „✓ Gemessen und veröffentlicht"), einschließlich
**Qwen3-235B-A22B** (Alibaba), einem offenen Modell, für das das
Qwen-Team offiziell die 235 Milliarden Gesamtparameter und die 22
Milliarden pro Token aktivierten Parameter veröffentlicht. Die beiden
Ausnahmen betreffen OpenAI-Modelle (oranges Abzeichen „⚠ Hypothese"),
deren Architektur nie veröffentlicht wird:

- **GPT-4** — aktive Parameter (176 Milliarden angenommen): folgt der
  EcoLogits-Methode für proprietäre Modelle — ausgehend von einer
  durchgesickerten Mixture-of-Experts-Architektur (~1,8 Billionen
  Gesamtparameter) und einem typischen MoE-Aktivierungsverhältnis von 10 %
  bis 30 % schätzt EcoLogits eine Spanne von 176 bis 528 Milliarden
  aktiven Parametern (genau x3); dieses Projekt legt die untere, also
  konservativste Grenze zugrunde. Die obere Grenze würde die Energie pro
  Token allein in der EcoLogits-Regression um etwa das 2,8-Fache
  vervielfachen — und die **Gesamt**-Energie des vollständigen Modells nur
  um etwa das 2,81-Fache (siehe Warnung unten). Gesamtparameter (1.800
  Milliarden angenommen): das oben erwähnte Leak bezieht sich direkt auf
  diese Zahl; es gibt keine separat veröffentlichte obere Grenze.
- **GPT-4o** — aktive Parameter (44 Milliarden angenommen) und
  Gesamtparameter (440 Milliarden): EcoLogits schätzt dieses Modell auf
  genau ein Viertel der für GPT-4 angenommenen Parameter (1760/4, 176/4,
  528/4), ohne eine eigenständige Begründung für dieses genaue Verhältnis
  über den eigenen Datensatz hinaus zu veröffentlichen. Auch hier legt
  dieses Projekt die untere Grenze zugrunde (44 Milliarden aktive
  Parameter); die obere Grenze (132 Milliarden, genau x3) vervielfacht die
  Energie pro Token der Regression um etwa das 2,47-Fache, und die
  **Gesamt**-Energie des vollständigen Modells nur um etwa das 2,40-Fache.

**Eine Eingabespanne ist keine Ausgabespanne.** Die beiden obigen
Beispiele (x3 bei den aktiven Parametern → x2,8/x2,5 bei der Regression
allein → x2,81/x2,40 bei der Gesamtenergie des vollständigen Modells)
zeigen, dass diese drei Verhältnisse niemals übereinstimmen: die
EcoLogits-Regression ist affin (sie hat einen konstanten Term β, der sich
nicht mit den Parametern ändert, sodass nichts darin streng proportional
ist), und das vollständige Modell kombiniert zwei davon (Dauer,
GPU-Energie) unter demselben PUE, was ein drittes Verhältnis ergibt.
Siehe den Docblock von `src/FootprintCalculatorFull.php` und die
zugehörigen Tests in `tests/FootprintCalculatorFullTest.php`.

Siehe [EcoLogits-Methodik für proprietäre
Modelle](https://ecologits.ai/latest/methodology/proprietary_models/) und
den [Modell-Datensatz von EcoLogits
0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

## Einschränkungen

- **Fest codierte Eingabewerte**: das Modell, die Anzahl der Parameter und
  die Anzahl der Tokens sind nicht dynamisch konfigurierbar.
- **Schätzung, keine Messung**: die EcoLogits-Regression ist eine
  empirische Näherung, keine direkte Messung des tatsächlichen
  GPU-Stromverbrauchs für eine bestimmte Anfrage.
- **Nur GPU-Energie (vereinfachtes Modell) oder GPU + Nicht-GPU-Server
  (vollständiges Modell)**: in beiden Fällen deckt die Berechnung nicht
  die Energie für Speicherung, Netzwerk oder Training des Modells ab —
  nur die Inferenz wird geschätzt.
- **Angenommene, nicht veröffentlichte Quantisierung**: das vollständige
  Modell nimmt eine Standard-Quantisierung von 4 Bit pro Parameter an
  (mangels von den Anbietern veröffentlichter Informationen zur
  tatsächlich in der Produktion verwendeten Quantisierung), die den
  benötigten GPU-Speicher und damit die Anzahl der Karten bestimmt.
- **Ein einziger PUE-Wert, der auf alle Modelle angewendet wird, kein
  branchenweiter Durchschnitt**: 1,2 ist der von EcoLogits v0.4.0
  zugrunde gelegte Wert, ohne ihn nach Anbieter aufzuschlüsseln; neuere
  Versionen von EcoLogits (in diesem Projekt nicht verwendet) zeigen, dass
  1,2 speziell OpenAI/Azure entspricht (≈1,09 für Anthropic/Cohere/Google,
  1,09-1,14 für HuggingFace, 1,16 für Mistral). Dieses Projekt wendet 1,2
  daher einheitlich an, auch auf Llama 3.1 70B, das nicht von OpenAI
  gehostet wird.
- **Hosting-Zone wird nicht automatisch erkannt**: die Tabelle vergleicht
  mehrere Zonen (Frankreich, Europa, USA, Welt), weiß aber nicht, wo sich
  das Rechenzentrum, das die Anfrage verarbeitet hat, tatsächlich
  befindet.
- **Kontext wird nicht berücksichtigt** (Eingabe-Tokens, Prompt-Größe), es
  wird nur die Anzahl der generierten Tokens verwendet.
- **Parameter von GPT-4 und GPT-4o nicht veröffentlicht**: diese Werte
  sind Hypothesen (siehe „Herkunft der Werte" oben), keine Messungen — die
  für diese beiden Modelle angezeigten Ergebnisse erben diese
  Unsicherheit.
- **Das vollständige Modell sollte nicht zuverlässiger wirken als seine
  Eingaben**: die Anzahl der GPU-Karten (`gpuCards`) wird **ausschließlich**
  durch die GESAMTPARAMETER und die angenommene Quantisierung bestimmt —
  keine weiteren Daten bestätigen sie. Für GPT-4o sind es also die
  440 Milliarden *angenommenen* Gesamtparameter (eine Hypothese, keine
  Messung), die diese Kartenanzahl allein festlegen, und damit auch einen
  großen Teil der Gesamtenergie des vollständigen Modells. Die Seite
  spiegelt das visuell wider: `gpuCards`, die Energie und die Emissionen
  des vollständigen Modells tragen das Abzeichen „⚠ Hypothese", sobald
  eine ihrer beiden Eingaben (aktiv oder gesamt) eine Hypothese ist — sie
  tragen niemals das Abzeichen „✓ Gemessen und veröffentlicht", wenn das
  vereinfachte Modell in derselben Zeile es nicht bereits selbst trägt.
