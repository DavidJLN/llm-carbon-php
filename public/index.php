<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LlmCarbon\DifferenceCalculator;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;

/**
 * Display language of the page: French, English or German. Chosen via the ?lang= query
 * parameter (see the language switcher in the page header), defaults to French. This only
 * affects the UI chrome (labels, headings, badges) — the Provenance notes are never translated,
 * since they are verbatim characterizations of what a cited source states.
 */
const SUPPORTED_LANGUAGES = ['fr', 'en', 'de'];

/**
 * Looks up a UI string for the given language, falling back to French then to the key itself.
 * Static dictionary local to this function: this page has no build step and no external i18n
 * library, so the translations live here rather than in separate files.
 */
function t(string $lang, string $key): string
{
    static $translations = [
        'fr' => [
            'page_title' => "Empreinte carbone d'une requête LLM",
            'heading' => "Empreinte carbone d'une requête à un modèle de langage",
            'table_model' => 'Modèle',
            'table_active_params' => 'Paramètres actifs',
            'table_total_params' => 'Paramètres totaux',
            'billions_suffix' => 'milliards',
            'table_tokens' => 'Tokens générés',
            'table_zone' => "Zone d'hébergement",
            'section_models_comparison' => 'Modèle simplifié vs modèle complet',
            'card_simplified_title' => 'Modèle simplifié',
            'card_full_title' => 'Modèle complet',
            'energy_label' => 'Énergie',
            'emissions_label' => 'Émissions',
            'gpu_cards_label' => 'Cartes GPU',
            'difference_heading' => 'Écart (complet − simplifié)',
            'of_which_server' => 'dont serveur',
            'of_which_cards' => 'dont cartes',
            'details_simplified_heading' => 'Détail du calcul — modèle simplifié',
            'details_full_heading' => 'Détail du calcul — modèle complet',
            'step_gpu_energy_per_token' => 'Énergie GPU par token généré (régression EcoLogits) :',
            'step_total_energy_pue' => 'Énergie totale, en tenant compte du PUE du datacenter :',
            'step_emissions' => "Émissions de CO2eq, avec le facteur d'émission du mix électrique de la zone d'hébergement (%s, %s gCO2eq/kWh) :",
            'step_required_memory' => 'Mémoire requise pour charger le modèle (paramètres totaux, quantification 4 bits, surcharge ×1,2) :',
            'step_gpu_cards_needed' => "Nombre de cartes GPU nécessaires (mémoire d'une carte : 80 %s) :",
            'step_generation_duration' => 'Durée de génération (régression EcoLogits sur les paramètres actifs) :',
            'step_server_energy' => 'Énergie du serveur hors GPU (durée convertie en heures, puissance 1000 W, au prorata des cartes utilisées) :',
            'step_gpu_energy_per_card' => 'Énergie GPU par carte (même régression EcoLogits que le modèle simplifié) :',
            'step_total_energy_full' => 'Énergie totale, cartes GPU comptées et PUE appliqué :',
            'card_unit' => 'carte(s)',
            'gb_unit' => 'Go',
            'section_zone_comparison' => "Comparaison par zone d'hébergement, à énergie identique",
            'th_zone' => 'Zone',
            'th_emission_factor' => "Facteur d'émission",
            'th_energy_simplified' => 'Énergie (simplifié)',
            'th_energy_full' => 'Énergie (complet)',
            'th_emissions_simplified' => 'Émissions (simplifié)',
            'th_emissions_full' => 'Émissions (complet)',
            'section_model_comparison' => "Comparaison par modèle, à zone d'hébergement identique (France)",
            'th_difference_total' => 'Écart total',
            'th_difference_server' => 'Écart dont serveur',
            'th_difference_cards' => 'Écart dont cartes',
            'footer_methodology' => 'Méthodologie et sources :',
            'footer_ecologits_link' => "EcoLogits 0.4.0 — méthodologie d'estimation de l'énergie (modèles simplifié et complet)",
            'provenance_model_active' => 'Modèle : %s (paramètres actifs)',
            'provenance_model_total' => 'Modèle : %s (paramètres totaux)',
            'provenance_emission_factor' => "Facteur d'émission : %s",
            'badge_hypothesis' => '⚠ Hypothèse',
            'badge_measured' => '✓ Mesuré et publié',
        ],
        'en' => [
            'page_title' => 'Carbon footprint of an LLM request',
            'heading' => 'Carbon footprint of a request to a language model',
            'table_model' => 'Model',
            'table_active_params' => 'Active parameters',
            'table_total_params' => 'Total parameters',
            'billions_suffix' => 'billion',
            'table_tokens' => 'Generated tokens',
            'table_zone' => 'Hosting zone',
            'section_models_comparison' => 'Simplified model vs full model',
            'card_simplified_title' => 'Simplified model',
            'card_full_title' => 'Full model',
            'energy_label' => 'Energy',
            'emissions_label' => 'Emissions',
            'gpu_cards_label' => 'GPU cards',
            'difference_heading' => 'Difference (full − simplified)',
            'of_which_server' => 'of which server',
            'of_which_cards' => 'of which cards',
            'details_simplified_heading' => 'Calculation detail — simplified model',
            'details_full_heading' => 'Calculation detail — full model',
            'step_gpu_energy_per_token' => 'GPU energy per generated token (EcoLogits regression):',
            'step_total_energy_pue' => 'Total energy, accounting for the datacenter PUE:',
            'step_emissions' => 'CO2eq emissions, using the emission factor of the electricity mix of the hosting zone (%s, %s gCO2eq/kWh):',
            'step_required_memory' => 'Required memory to load the model (total parameters, 4-bit quantization, ×1.2 overhead):',
            'step_gpu_cards_needed' => 'Number of GPU cards required (memory of one card: 80 %s):',
            'step_generation_duration' => 'Generation duration (EcoLogits regression on active parameters):',
            'step_server_energy' => 'Non-GPU server energy (duration converted to hours, 1000 W power, prorated by cards used):',
            'step_gpu_energy_per_card' => 'GPU energy per card (same EcoLogits regression as the simplified model):',
            'step_total_energy_full' => 'Total energy, GPU cards counted and PUE applied:',
            'card_unit' => 'card(s)',
            'gb_unit' => 'GB',
            'section_zone_comparison' => 'Comparison by hosting zone, at equal energy',
            'th_zone' => 'Zone',
            'th_emission_factor' => 'Emission factor',
            'th_energy_simplified' => 'Energy (simplified)',
            'th_energy_full' => 'Energy (full)',
            'th_emissions_simplified' => 'Emissions (simplified)',
            'th_emissions_full' => 'Emissions (full)',
            'section_model_comparison' => 'Comparison by model, at equal hosting zone (France)',
            'th_difference_total' => 'Total difference',
            'th_difference_server' => 'Difference, of which server',
            'th_difference_cards' => 'Difference, of which cards',
            'footer_methodology' => 'Methodology and sources:',
            'footer_ecologits_link' => 'EcoLogits 0.4.0 — energy estimation methodology (simplified and full models)',
            'provenance_model_active' => 'Model: %s (active parameters)',
            'provenance_model_total' => 'Model: %s (total parameters)',
            'provenance_emission_factor' => 'Emission factor: %s',
            'badge_hypothesis' => '⚠ Hypothesis',
            'badge_measured' => '✓ Measured and published',
        ],
        'de' => [
            'page_title' => 'CO2-Fußabdruck einer LLM-Anfrage',
            'heading' => 'CO2-Fußabdruck einer Anfrage an ein Sprachmodell',
            'table_model' => 'Modell',
            'table_active_params' => 'Aktive Parameter',
            'table_total_params' => 'Gesamtparameter',
            'billions_suffix' => 'Milliarden',
            'table_tokens' => 'Generierte Tokens',
            'table_zone' => 'Hosting-Zone',
            'section_models_comparison' => 'Vereinfachtes Modell vs. vollständiges Modell',
            'card_simplified_title' => 'Vereinfachtes Modell',
            'card_full_title' => 'Vollständiges Modell',
            'energy_label' => 'Energie',
            'emissions_label' => 'Emissionen',
            'gpu_cards_label' => 'GPU-Karten',
            'difference_heading' => 'Abweichung (vollständig − vereinfacht)',
            'of_which_server' => 'davon Server',
            'of_which_cards' => 'davon Karten',
            'details_simplified_heading' => 'Berechnungsdetail — vereinfachtes Modell',
            'details_full_heading' => 'Berechnungsdetail — vollständiges Modell',
            'step_gpu_energy_per_token' => 'GPU-Energie pro generiertem Token (EcoLogits-Regression):',
            'step_total_energy_pue' => 'Gesamtenergie, unter Berücksichtigung des Rechenzentrums-PUE:',
            'step_emissions' => 'CO2eq-Emissionen, mit dem Emissionsfaktor des Strommixes der Hosting-Zone (%s, %s gCO2eq/kWh):',
            'step_required_memory' => 'Benötigter Speicher zum Laden des Modells (Gesamtparameter, 4-Bit-Quantisierung, ×1,2-Aufschlag):',
            'step_gpu_cards_needed' => 'Anzahl der benötigten GPU-Karten (Speicher einer Karte: 80 %s):',
            'step_generation_duration' => 'Generierungsdauer (EcoLogits-Regression auf aktive Parameter):',
            'step_server_energy' => 'Nicht-GPU-Serverenergie (Dauer in Stunden umgerechnet, Leistung 1000 W, anteilig nach genutzten Karten):',
            'step_gpu_energy_per_card' => 'GPU-Energie pro Karte (gleiche EcoLogits-Regression wie das vereinfachte Modell):',
            'step_total_energy_full' => 'Gesamtenergie, GPU-Karten gezählt und PUE angewendet:',
            'card_unit' => 'Karte(n)',
            'gb_unit' => 'GB',
            'section_zone_comparison' => 'Vergleich nach Hosting-Zone, bei gleicher Energie',
            'th_zone' => 'Zone',
            'th_emission_factor' => 'Emissionsfaktor',
            'th_energy_simplified' => 'Energie (vereinfacht)',
            'th_energy_full' => 'Energie (vollständig)',
            'th_emissions_simplified' => 'Emissionen (vereinfacht)',
            'th_emissions_full' => 'Emissionen (vollständig)',
            'section_model_comparison' => 'Vergleich nach Modell, bei gleicher Hosting-Zone (Frankreich)',
            'th_difference_total' => 'Gesamtabweichung',
            'th_difference_server' => 'Abweichung, davon Server',
            'th_difference_cards' => 'Abweichung, davon Karten',
            'footer_methodology' => 'Methodik und Quellen:',
            'footer_ecologits_link' => 'EcoLogits 0.4.0 — Methodik zur Energieschätzung (vereinfachtes und vollständiges Modell)',
            'provenance_model_active' => 'Modell: %s (aktive Parameter)',
            'provenance_model_total' => 'Modell: %s (Gesamtparameter)',
            'provenance_emission_factor' => 'Emissionsfaktor: %s',
            'badge_hypothesis' => '⚠ Hypothese',
            'badge_measured' => '✓ Gemessen und veröffentlicht',
        ],
    ];

    return $translations[$lang][$key] ?? $translations['fr'][$key] ?? $key;
}

/**
 * Locale-aware formatting for computed values (decimal and thousands separators only — the
 * underlying number is never altered). The regression coefficients quoted in the calculation
 * detail (8.91e-5, 1.2, etc.) are deliberately left in plain scientific notation regardless of
 * language, since that is how they are cited in the EcoLogits source itself.
 */
function fmt(string $lang, float $value, int $decimals): string
{
    return match ($lang) {
        'en' => number_format($value, $decimals, '.', ','),
        'de' => number_format($value, $decimals, ',', '.'),
        default => number_format($value, $decimals, ',', ' '),
    };
}

/**
 * Display-only translation of the zone label carried by EmissionFactor::$zone. The underlying
 * value (used elsewhere, e.g. in tests) is never changed — only what is printed on the page is.
 */
function zoneLabel(string $zone, string $lang): string
{
    static $labels = [
        'États-Unis' => ['en' => 'United States', 'de' => 'Vereinigte Staaten'],
        'Monde' => ['en' => 'World', 'de' => 'Welt'],
    ];

    return $labels[$zone][$lang] ?? $zone;
}

/**
 * Visual badge distinguishing a measured/published provenance (reliable as-is) from a hypothesis
 * (estimate for lack of published data): a hypothesis must never be visually confused with a
 * measurement.
 */
function provenanceBadge(Provenance $provenance, string $lang): string
{
    if ($provenance->type === ProvenanceType::Hypothesis) {
        return '<span class="badge badge-hypothese">' . t($lang, 'badge_hypothesis') . '</span>';
    }

    return '<span class="badge badge-mesure">' . t($lang, 'badge_measured') . '</span>';
}

/**
 * Badge for a quantity of the FULL model that depends on both the active parameters and the
 * total parameters (for example the total energy, which mixes the regression on active
 * parameters and the number of GPU cards derived from the totals): the full model must never
 * appear more reliable than the less reliable of its two inputs — if one of them is a
 * Hypothesis, the quantity that depends on it is a Hypothesis, even if the other input is
 * measured and published.
 */
function worstProvenanceBadge(Provenance $activeProvenance, Provenance $totalProvenance, string $lang): string
{
    return provenanceBadge(
        $activeProvenance->type === ProvenanceType::Hypothesis ? $activeProvenance : $totalProvenance,
        $lang
    );
}

function provenanceDetail(string $label, Provenance $provenance, string $lang): string
{
    return '<li>'
        . '<strong>' . htmlspecialchars($label) . '</strong> '
        . provenanceBadge($provenance, $lang)
        . '<br><a href="' . htmlspecialchars($provenance->url) . '">' . htmlspecialchars($provenance->url) . '</a>'
        . ' — ' . htmlspecialchars($provenance->yearOrConsultationDate)
        . '<br><span class="note">' . htmlspecialchars($provenance->note) . '</span>'
        . '</li>';
}

$lang = $_GET['lang'] ?? 'fr';
if (!is_string($lang) || !in_array($lang, SUPPORTED_LANGUAGES, true)) {
    $lang = 'fr';
}

// --- Scenario under study (hardcoded) ---

$model = LanguageModel::llama31_70b();

$generatedTokens = 500;

$simplifiedCalculator = new FootprintCalculatorSimplified();
$fullCalculator = new FootprintCalculatorFull();
$differenceCalculator = new DifferenceCalculator();

// --- Footprint for hosting in France (reference scenario), according to both models ---

$franceEmissionFactor = EmissionFactor::france();
$franceSimplifiedFootprint = $simplifiedCalculator->calculate($model, $franceEmissionFactor, $generatedTokens);
$franceFullFootprint = $fullCalculator->calculate($model, $franceEmissionFactor, $generatedTokens);
$franceDifference = $differenceCalculator->calculate($franceSimplifiedFootprint, $franceFullFootprint);

// --- Comparison by hosting zone, at equal energy ---

$emissionFactorsByZone = EmissionFactor::all();

$footprintsByZone = [];
foreach ($emissionFactorsByZone as $factor) {
    $footprintsByZone[] = [
        'factor' => $factor,
        'simplified' => $simplifiedCalculator->calculate($model, $factor, $generatedTokens),
        'full' => $fullCalculator->calculate($model, $factor, $generatedTokens),
    ];
}

// --- Comparison by model, at equal hosting zone (France) ---

$footprintsByModel = [];
foreach (LanguageModel::all() as $catalogModel) {
    $modelSimplifiedFootprint = $simplifiedCalculator->calculate($catalogModel, $franceEmissionFactor, $generatedTokens);
    $modelFullFootprint = $fullCalculator->calculate($catalogModel, $franceEmissionFactor, $generatedTokens);

    $footprintsByModel[] = [
        'model' => $catalogModel,
        'simplified' => $modelSimplifiedFootprint,
        'full' => $modelFullFootprint,
        'difference' => $differenceCalculator->calculate($modelSimplifiedFootprint, $modelFullFootprint),
    ];
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(t($lang, 'page_title')) ?></title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 1100px;
            margin: 3rem auto;
            padding: 0 1rem;
            line-height: 1.5;
            color: #1a1a1a;
        }
        h1 { font-size: 1.4rem; }
        h2.section { font-size: 1.1rem; margin-top: 2.5rem; }
        .lang-switch {
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .lang-switch a, .lang-switch strong {
            margin-right: 0.75rem;
        }
        .table-scroll {
            overflow-x: auto;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 640px;
            margin: 1.5rem 0;
        }
        td, th {
            text-align: left;
            padding: 0.5rem;
            border-bottom: 1px solid #ddd;
        }
        .result {
            font-size: 1.1rem;
            font-weight: bold;
        }
        footer {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #555;
        }
        .details {
            margin-top: 1.5rem;
            padding: 1rem 1.25rem;
            background: #f7f7f7;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .details h2 {
            font-size: 1rem;
            margin-top: 0;
        }
        .details ol {
            padding-left: 1.25rem;
        }
        .details li {
            margin-bottom: 0.75rem;
        }
        .details code {
            display: block;
            margin-top: 0.25rem;
            font-family: ui-monospace, Menlo, monospace;
            color: #333;
        }
        .badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: bold;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }
        .badge-mesure {
            color: #1a7a3c;
            background: #e6f4ea;
            border-color: #b6e0c2;
        }
        .badge-hypothese {
            color: #9a5b00;
            background: #fff3e0;
            border-color: #f0c98a;
        }
        .sources li {
            margin-bottom: 1rem;
        }
        .sources .note {
            display: block;
            margin-top: 0.15rem;
            color: #555;
        }
        .comparaison {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 1.5rem 0;
        }
        .comparaison .carte {
            flex: 1 1 260px;
            padding: 1rem 1.25rem;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .comparaison .carte h3 {
            margin-top: 0;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #555;
        }
        .ecart {
            flex: 1 1 100%;
            padding: 0.75rem 1.25rem;
            background: #eef4fb;
            border-radius: 6px;
            border: 1px solid #cddcec;
        }
    </style>
</head>
<body>
    <nav class="lang-switch">
        <?php foreach (['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'] as $code => $label): ?>
            <?php if ($code === $lang): ?>
                <strong><?= htmlspecialchars($label) ?></strong>
            <?php else: ?>
                <a href="?lang=<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <h1><?= htmlspecialchars(t($lang, 'heading')) ?></h1>

    <table>
        <tr><th><?= htmlspecialchars(t($lang, 'table_model')) ?></th><td><?= htmlspecialchars($model->name) ?></td></tr>
        <tr>
            <th><?= htmlspecialchars(t($lang, 'table_active_params')) ?></th>
            <td><?= $model->activeParametersBillions ?> <?= htmlspecialchars(t($lang, 'billions_suffix')) ?> <?= provenanceBadge($model->provenance, $lang) ?></td>
        </tr>
        <tr>
            <th><?= htmlspecialchars(t($lang, 'table_total_params')) ?></th>
            <td>
                <?= $model->totalParametersBillions ?> <?= htmlspecialchars(t($lang, 'billions_suffix')) ?>
                <?= provenanceBadge($model->totalParametersProvenance, $lang) ?>
            </td>
        </tr>
        <tr><th><?= htmlspecialchars(t($lang, 'table_tokens')) ?></th><td><?= $generatedTokens ?></td></tr>
        <tr><th><?= htmlspecialchars(t($lang, 'table_zone')) ?></th><td><?= htmlspecialchars(zoneLabel($franceEmissionFactor->zone, $lang)) ?></td></tr>
    </table>

    <h2 class="section"><?= htmlspecialchars(t($lang, 'section_models_comparison')) ?></h2>
    <div class="comparaison">
        <div class="carte">
            <h3><?= htmlspecialchars(t($lang, 'card_simplified_title')) ?></h3>
            <p class="result">
                <?= htmlspecialchars(t($lang, 'energy_label')) ?> : <?= fmt($lang, $franceSimplifiedFootprint->totalEnergyWh, 4) ?> Wh
                <?= provenanceBadge($model->provenance, $lang) ?>
            </p>
            <p class="result">
                <?= htmlspecialchars(t($lang, 'emissions_label')) ?> : <?= fmt($lang, $franceSimplifiedFootprint->emissionsGco2eq, 4) ?> gCO2eq
                <?= provenanceBadge($model->provenance, $lang) ?>
            </p>
        </div>
        <div class="carte">
            <h3><?= htmlspecialchars(t($lang, 'card_full_title')) ?></h3>
            <p class="result">
                <?= htmlspecialchars(t($lang, 'energy_label')) ?> : <?= fmt($lang, $franceFullFootprint->totalEnergyWh, 4) ?> Wh
                <?= worstProvenanceBadge($model->provenance, $model->totalParametersProvenance, $lang) ?>
            </p>
            <p class="result">
                <?= htmlspecialchars(t($lang, 'emissions_label')) ?> : <?= fmt($lang, $franceFullFootprint->emissionsGco2eq, 4) ?> gCO2eq
                <?= worstProvenanceBadge($model->provenance, $model->totalParametersProvenance, $lang) ?>
            </p>
            <p>
                <?= htmlspecialchars(t($lang, 'gpu_cards_label')) ?> : <?= $franceFullFootprint->gpuCards ?>
                <?= provenanceBadge($model->totalParametersProvenance, $lang) ?>
            </p>
        </div>
        <div class="ecart">
            <?= htmlspecialchars(t($lang, 'difference_heading')) ?> :
            <strong>
                <?= $franceDifference->totalDifferenceWh >= 0 ? '+' : '' ?><?= fmt($lang, $franceDifference->totalDifferenceWh, 4) ?> Wh
                (<?= $franceDifference->totalDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $franceDifference->totalDifferencePercent, 1) ?> %,
                ×<?= fmt($lang, $franceDifference->differenceMultiplier, 2) ?>)
            </strong>
            <br>
            <?= htmlspecialchars(t($lang, 'of_which_server')) ?> :
            <?= $franceDifference->serverDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $franceDifference->serverDifferencePercent, 1) ?> %,
            <?= htmlspecialchars(t($lang, 'of_which_cards')) ?> :
            <?= $franceDifference->cardsDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $franceDifference->cardsDifferencePercent, 1) ?> %
        </div>
    </div>

    <div class="details">
        <h2><?= htmlspecialchars(t($lang, 'details_simplified_heading')) ?></h2>
        <ol>
            <li>
                <?= htmlspecialchars(t($lang, 'step_gpu_energy_per_token')) ?>
                <code>
                    <?= fmt($lang, $franceSimplifiedFootprint->energyPerTokenWh, 8) ?> Wh/token
                </code>
            </li>
            <li>
                <?= htmlspecialchars(t($lang, 'step_total_energy_pue')) ?>
                <code>
                    <?= fmt($lang, $franceSimplifiedFootprint->energyPerTokenWh, 8) ?> × <?= $generatedTokens ?>
                    = <?= fmt($lang, $franceSimplifiedFootprint->totalEnergyWh, 4) ?> Wh
                </code>
            </li>
            <li>
                <?= htmlspecialchars(sprintf(
                    t($lang, 'step_emissions'),
                    zoneLabel($franceEmissionFactor->zone, $lang),
                    (string) $franceEmissionFactor->gCo2eqPerKwh
                )) ?>
                <code>
                    (<?= fmt($lang, $franceSimplifiedFootprint->totalEnergyWh, 4) ?> / 1000) × <?= $franceEmissionFactor->gCo2eqPerKwh ?>
                    = <?= fmt($lang, $franceSimplifiedFootprint->emissionsGco2eq, 4) ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <div class="details">
        <h2><?= htmlspecialchars(t($lang, 'details_full_heading')) ?></h2>
        <ol>
            <li>
                <?= htmlspecialchars(t($lang, 'step_required_memory')) ?>
                <code>
                    1.2 × <?= $model->totalParametersBillions ?> × 4 / 8
                    = <?= fmt($lang, $franceFullFootprint->requiredMemoryGb, 4) ?> <?= htmlspecialchars(t($lang, 'gb_unit')) ?>
                </code>
            </li>
            <li>
                <?= htmlspecialchars(sprintf(t($lang, 'step_gpu_cards_needed'), t($lang, 'gb_unit'))) ?>
                <code>
                    ceil(<?= fmt($lang, $franceFullFootprint->requiredMemoryGb, 4) ?> / 80)
                    = <?= $franceFullFootprint->gpuCards ?> <?= htmlspecialchars(t($lang, 'card_unit')) ?>
                </code>
            </li>
            <li>
                <?= htmlspecialchars(t($lang, 'step_generation_duration')) ?>
                <code>
                    (8.02e-4 × <?= $model->activeParametersBillions ?> + 2.23e-2) × <?= $generatedTokens ?>
                    = <?= fmt($lang, $franceFullFootprint->durationSeconds, 4) ?> s
                </code>
            </li>
            <li>
                <?= htmlspecialchars(t($lang, 'step_server_energy')) ?>
                <code>
                    (<?= fmt($lang, $franceFullFootprint->durationSeconds, 4) ?> / 3600) × 1000 ×
                    (<?= $franceFullFootprint->gpuCards ?> / 8)
                    = <?= fmt($lang, $franceFullFootprint->serverEnergyWh, 4) ?> Wh
                </code>
            </li>
            <li>
                <?= htmlspecialchars(t($lang, 'step_gpu_energy_per_card')) ?>
                <code>
                    (8.91e-5 × <?= $model->activeParametersBillions ?> + 1.43e-3) × <?= $generatedTokens ?>
                    = <?= fmt($lang, $franceFullFootprint->gpuEnergyPerCardWh, 4) ?> Wh
                </code>
            </li>
            <li>
                <?= htmlspecialchars(t($lang, 'step_total_energy_full')) ?>
                <code>
                    1.2 × (<?= fmt($lang, $franceFullFootprint->serverEnergyWh, 4) ?>
                    + <?= $franceFullFootprint->gpuCards ?> × <?= fmt($lang, $franceFullFootprint->gpuEnergyPerCardWh, 4) ?>)
                    = <?= fmt($lang, $franceFullFootprint->totalEnergyWh, 4) ?> Wh
                </code>
            </li>
            <li>
                <?= htmlspecialchars(sprintf(
                    t($lang, 'step_emissions'),
                    zoneLabel($franceEmissionFactor->zone, $lang),
                    (string) $franceEmissionFactor->gCo2eqPerKwh
                )) ?>
                <code>
                    (<?= fmt($lang, $franceFullFootprint->totalEnergyWh, 4) ?> / 1000) × <?= $franceEmissionFactor->gCo2eqPerKwh ?>
                    = <?= fmt($lang, $franceFullFootprint->emissionsGco2eq, 4) ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <h2 class="section"><?= htmlspecialchars(t($lang, 'section_zone_comparison')) ?></h2>
    <table>
        <tr>
            <th><?= htmlspecialchars(t($lang, 'th_zone')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_emission_factor')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_energy_simplified')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_energy_full')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_emissions_simplified')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_emissions_full')) ?></th>
        </tr>
        <?php foreach ($footprintsByZone as $row): ?>
        <tr>
            <td><?= htmlspecialchars(zoneLabel($row['factor']->zone, $lang)) ?></td>
            <td>
                <?= fmt($lang, $row['factor']->gCo2eqPerKwh, 2) ?> gCO2eq/kWh
                <?= provenanceBadge($row['factor']->provenance, $lang) ?>
            </td>
            <td><?= fmt($lang, $row['simplified']->totalEnergyWh, 4) ?> Wh</td>
            <td><?= fmt($lang, $row['full']->totalEnergyWh, 4) ?> Wh</td>
            <td><?= fmt($lang, $row['simplified']->emissionsGco2eq, 4) ?> gCO2eq</td>
            <td><?= fmt($lang, $row['full']->emissionsGco2eq, 4) ?> gCO2eq</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2 class="section"><?= htmlspecialchars(t($lang, 'section_model_comparison')) ?></h2>
    <div class="table-scroll">
    <table>
        <tr>
            <th><?= htmlspecialchars(t($lang, 'table_model')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'table_active_params')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'table_total_params')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'gpu_cards_label')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_energy_simplified')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_energy_full')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_difference_total')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_difference_server')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_difference_cards')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_emissions_simplified')) ?></th>
            <th><?= htmlspecialchars(t($lang, 'th_emissions_full')) ?></th>
        </tr>
        <?php foreach ($footprintsByModel as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['model']->name) ?></td>
            <td>
                <?= $row['model']->activeParametersBillions ?> <?= htmlspecialchars(t($lang, 'billions_suffix')) ?>
                <?= provenanceBadge($row['model']->provenance, $lang) ?>
            </td>
            <td>
                <?= $row['model']->totalParametersBillions ?> <?= htmlspecialchars(t($lang, 'billions_suffix')) ?>
                <?= provenanceBadge($row['model']->totalParametersProvenance, $lang) ?>
            </td>
            <td>
                <?= $row['full']->gpuCards ?>
                <?= provenanceBadge($row['model']->totalParametersProvenance, $lang) ?>
            </td>
            <td>
                <?= fmt($lang, $row['simplified']->totalEnergyWh, 4) ?> Wh
                <?= provenanceBadge($row['model']->provenance, $lang) ?>
            </td>
            <td>
                <?= fmt($lang, $row['full']->totalEnergyWh, 4) ?> Wh
                <?= worstProvenanceBadge($row['model']->provenance, $row['model']->totalParametersProvenance, $lang) ?>
            </td>
            <td>
                <?= $row['difference']->totalDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $row['difference']->totalDifferencePercent, 1) ?> %
                (×<?= fmt($lang, $row['difference']->differenceMultiplier, 2) ?>)
            </td>
            <td>
                <?= $row['difference']->serverDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $row['difference']->serverDifferencePercent, 1) ?> %
            </td>
            <td>
                <?= $row['difference']->cardsDifferencePercent >= 0 ? '+' : '' ?><?= fmt($lang, $row['difference']->cardsDifferencePercent, 1) ?> %
            </td>
            <td>
                <?= fmt($lang, $row['simplified']->emissionsGco2eq, 4) ?> gCO2eq
                <?= provenanceBadge($row['model']->provenance, $lang) ?>
            </td>
            <td>
                <?= fmt($lang, $row['full']->emissionsGco2eq, 4) ?> gCO2eq
                <?= worstProvenanceBadge($row['model']->provenance, $row['model']->totalParametersProvenance, $lang) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <footer>
        <p><?= htmlspecialchars(t($lang, 'footer_methodology')) ?></p>
        <ul class="sources">
            <li><a href="https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md"><?= htmlspecialchars(t($lang, 'footer_ecologits_link')) ?></a></li>
            <?php foreach (LanguageModel::all() as $catalogModel): ?>
            <?= provenanceDetail(sprintf(t($lang, 'provenance_model_active'), $catalogModel->name), $catalogModel->provenance, $lang) ?>
            <?= provenanceDetail(sprintf(t($lang, 'provenance_model_total'), $catalogModel->name), $catalogModel->totalParametersProvenance, $lang) ?>
            <?php endforeach; ?>
            <?php foreach ($emissionFactorsByZone as $factor): ?>
            <?= provenanceDetail(sprintf(t($lang, 'provenance_emission_factor'), zoneLabel($factor->zone, $lang)), $factor->provenance, $lang) ?>
            <?php endforeach; ?>
        </ul>
    </footer>
</body>
</html>
