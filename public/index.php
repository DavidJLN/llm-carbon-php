<?php

declare(strict_types=1);

// --- Valeurs d'entrée (en dur) ---

/** Nom du modèle étudié. */
const MODEL_NAME = 'Llama 3.1 70B';

/** Nombre de paramètres actifs du modèle, en milliards. */
const ACTIVE_PARAMETERS_BILLIONS = 70;

/** Nombre de tokens générés par la requête. */
const GENERATED_TOKENS = 500;

// --- Constantes de la méthodologie EcoLogits ---

/**
 * Coefficient multiplicatif (pente) reliant les paramètres actifs (en
 * milliards) à l'énergie GPU consommée par token généré, en Wh.
 * Source : https://ecologits.ai/latest/methodology/energy/
 */
const ECOLOGITS_ENERGY_ALPHA = 8.91e-5;

/**
 * Terme constant (ordonnée à l'origine) de l'énergie GPU consommée par
 * token généré, en Wh.
 * Source : https://ecologits.ai/latest/methodology/energy/
 */
const ECOLOGITS_ENERGY_BETA = 1.43e-3;

/**
 * PUE (Power Usage Effectiveness) moyen retenu par EcoLogits pour les
 * centres de données des fournisseurs de services d'IA.
 * Source : https://ecologits.ai/latest/methodology/energy/
 */
const DATACENTER_PUE = 1.2;

/**
 * Facteur d'émission du mix électrique français, en gCO2eq par kWh.
 * Source : https://base-empreinte.ademe.fr/ (Base Empreinte ADEME,
 * facteur d'émission de l'électricité consommée en France), tel que
 * repris par la méthodologie EcoLogits.
 */
const FRANCE_GRID_EMISSION_FACTOR = 81.3;

// --- Calculs ---

$energyPerTokenWh = ECOLOGITS_ENERGY_ALPHA * ACTIVE_PARAMETERS_BILLIONS + ECOLOGITS_ENERGY_BETA;

$totalEnergyWh = $energyPerTokenWh * GENERATED_TOKENS * DATACENTER_PUE;

$emissionsGco2eq = ($totalEnergyWh / 1000) * FRANCE_GRID_EMISSION_FACTOR;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Empreinte carbone d'une requête LLM</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 640px;
            margin: 3rem auto;
            padding: 0 1rem;
            line-height: 1.5;
            color: #1a1a1a;
        }
        h1 { font-size: 1.4rem; }
        table {
            border-collapse: collapse;
            width: 100%;
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
    </style>
</head>
<body>
    <h1>Empreinte carbone d'une requête à un modèle de langage</h1>

    <table>
        <tr><th>Modèle</th><td><?= htmlspecialchars(MODEL_NAME) ?></td></tr>
        <tr><th>Paramètres actifs</th><td><?= ACTIVE_PARAMETERS_BILLIONS ?> milliards</td></tr>
        <tr><th>Tokens générés</th><td><?= GENERATED_TOKENS ?></td></tr>
    </table>

    <p class="result">Énergie consommée : <?= number_format($totalEnergyWh, 4, ',', ' ') ?> Wh</p>
    <p class="result">Émissions estimées : <?= number_format($emissionsGco2eq, 4, ',', ' ') ?> gCO2eq</p>

    <div class="details">
        <h2>Détail du calcul</h2>
        <ol>
            <li>
                Énergie GPU par token généré (régression EcoLogits) :
                <code>
                    <?= ECOLOGITS_ENERGY_ALPHA ?> × <?= ACTIVE_PARAMETERS_BILLIONS ?> + <?= ECOLOGITS_ENERGY_BETA ?>
                    = <?= number_format($energyPerTokenWh, 8, ',', ' ') ?> Wh/token
                </code>
            </li>
            <li>
                Énergie totale, en tenant compte du PUE du datacenter :
                <code>
                    <?= number_format($energyPerTokenWh, 8, ',', ' ') ?> × <?= GENERATED_TOKENS ?> × <?= DATACENTER_PUE ?>
                    = <?= number_format($totalEnergyWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Émissions de CO2eq, avec le facteur d'émission du mix électrique français :
                <code>
                    (<?= number_format($totalEnergyWh, 4, ',', ' ') ?> / 1000) × <?= FRANCE_GRID_EMISSION_FACTOR ?>
                    = <?= number_format($emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <footer>
        <p>Méthodologie et sources :</p>
        <ul>
            <li><a href="https://ecologits.ai/latest/methodology/energy/">EcoLogits — méthodologie d'estimation de l'énergie</a></li>
            <li><a href="https://base-empreinte.ademe.fr/">ADEME Base Empreinte — facteur d'émission du mix électrique français</a></li>
        </ul>
    </footer>
</body>
</html>
