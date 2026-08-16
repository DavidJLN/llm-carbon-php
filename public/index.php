<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculator;
use LlmCarbon\LanguageModel;

// --- Scénario étudié (en dur) ---

$modele = new LanguageModel(
    'Llama 3.1 70B',
    70,
    'https://ai.meta.com/blog/meta-llama-3-1/'
);

$tokensGeneres = 500;

$calculateur = new FootprintCalculator();

// --- Empreinte pour l'hébergement en France (scénario de référence) ---

$facteurFrance = EmissionFactor::france();
$empreinteFrance = $calculateur->calculate($modele, $facteurFrance, $tokensGeneres);

// --- Comparaison par zone d'hébergement, à énergie identique ---

$facteursParZone = [
    EmissionFactor::france(),
    EmissionFactor::europe(),
    EmissionFactor::etatsUnis(),
    EmissionFactor::monde(),
];

$empreintesParZone = [];
foreach ($facteursParZone as $facteur) {
    $empreintesParZone[] = [
        'facteur' => $facteur,
        'empreinte' => $calculateur->calculate($modele, $facteur, $tokensGeneres),
    ];
}

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
        h2.section { font-size: 1.1rem; margin-top: 2.5rem; }
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
        <tr><th>Modèle</th><td><?= htmlspecialchars($modele->nom) ?></td></tr>
        <tr><th>Paramètres actifs</th><td><?= $modele->parametresActifsMilliards ?> milliards</td></tr>
        <tr><th>Tokens générés</th><td><?= $tokensGeneres ?></td></tr>
        <tr><th>Zone d'hébergement</th><td><?= htmlspecialchars($facteurFrance->zone) ?></td></tr>
    </table>

    <p class="result">Énergie consommée : <?= number_format($empreinteFrance->energieTotaleWh, 4, ',', ' ') ?> Wh</p>
    <p class="result">Émissions estimées : <?= number_format($empreinteFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq</p>

    <div class="details">
        <h2>Détail du calcul</h2>
        <ol>
            <li>
                Énergie GPU par token généré (régression EcoLogits) :
                <code>
                    <?= number_format($empreinteFrance->energieParTokenWh, 8, ',', ' ') ?> Wh/token
                </code>
            </li>
            <li>
                Énergie totale, en tenant compte du PUE du datacenter :
                <code>
                    <?= number_format($empreinteFrance->energieParTokenWh, 8, ',', ' ') ?> × <?= $tokensGeneres ?>
                    = <?= number_format($empreinteFrance->energieTotaleWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Émissions de CO2eq, avec le facteur d'émission du mix électrique de la zone
                d'hébergement (<?= htmlspecialchars($facteurFrance->zone) ?>, <?= $facteurFrance->gCo2eqParKwh ?> gCO2eq/kWh) :
                <code>
                    (<?= number_format($empreinteFrance->energieTotaleWh, 4, ',', ' ') ?> / 1000) × <?= $facteurFrance->gCo2eqParKwh ?>
                    = <?= number_format($empreinteFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <h2 class="section">Comparaison par zone d'hébergement, à énergie identique</h2>
    <table>
        <tr>
            <th>Zone</th>
            <th>Facteur d'émission</th>
            <th>Énergie</th>
            <th>Émissions</th>
        </tr>
        <?php foreach ($empreintesParZone as $ligne): ?>
        <tr>
            <td><?= htmlspecialchars($ligne['facteur']->zone) ?></td>
            <td><?= number_format($ligne['facteur']->gCo2eqParKwh, 2, ',', ' ') ?> gCO2eq/kWh</td>
            <td><?= number_format($ligne['empreinte']->energieTotaleWh, 4, ',', ' ') ?> Wh</td>
            <td><?= number_format($ligne['empreinte']->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <footer>
        <p>Méthodologie et sources :</p>
        <ul>
            <li><a href="https://ecologits.ai/latest/methodology/energy/">EcoLogits — méthodologie d'estimation de l'énergie</a></li>
            <li><a href="<?= htmlspecialchars($modele->urlSource) ?>">Source du modèle : <?= htmlspecialchars($modele->nom) ?></a></li>
            <?php foreach ($empreintesParZone as $ligne): ?>
            <li><a href="<?= htmlspecialchars($ligne['facteur']->urlSource) ?>">Source du facteur d'émission : <?= htmlspecialchars($ligne['facteur']->zone) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </footer>
</body>
</html>
