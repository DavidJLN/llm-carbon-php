<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LlmCarbon\EcartCalculator;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorComplet;
use LlmCarbon\FootprintCalculatorSimplifie;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;

/**
 * Badge visuel distinguant une provenance mesurée/publiée (fiable telle quelle) d'une hypothèse
 * (estimation faute de donnée publiée) : une hypothèse ne doit jamais se confondre visuellement
 * avec une mesure.
 */
function badgeProvenance(Provenance $provenance): string
{
    if ($provenance->type === ProvenanceType::Hypothese) {
        return '<span class="badge badge-hypothese">⚠ Hypothèse</span>';
    }

    return '<span class="badge badge-mesure">✓ Mesuré et publié</span>';
}

/**
 * Badge d'une grandeur du modèle COMPLET qui dépend à la fois des paramètres actifs et des
 * paramètres totaux (par exemple l'énergie totale, qui mêle la régression sur les actifs et le
 * nombre de cartes GPU dérivé des totaux) : le modèle complet ne doit jamais paraître plus fiable
 * que la moins fiable de ses deux entrées — s'il en existe une Hypothèse, la grandeur qui en
 * dépend est une Hypothèse, même si l'autre entrée est mesurée et publiée.
 */
function badgeProvenancePire(Provenance $provenanceActifs, Provenance $provenanceTotaux): string
{
    return badgeProvenance(
        $provenanceActifs->type === ProvenanceType::Hypothese ? $provenanceActifs : $provenanceTotaux
    );
}

function detailProvenance(string $intitule, Provenance $provenance): string
{
    return '<li>'
        . '<strong>' . htmlspecialchars($intitule) . '</strong> '
        . badgeProvenance($provenance)
        . '<br><a href="' . htmlspecialchars($provenance->url) . '">' . htmlspecialchars($provenance->url) . '</a>'
        . ' — ' . htmlspecialchars($provenance->millesimeOuDateDeConsultation)
        . '<br><span class="note">' . htmlspecialchars($provenance->note) . '</span>'
        . '</li>';
}

// --- Scénario étudié (en dur) ---

$modele = LanguageModel::llama31_70b();

$tokensGeneres = 500;

$calculateurSimplifie = new FootprintCalculatorSimplifie();
$calculateurComplet = new FootprintCalculatorComplet();
$calculateurEcart = new EcartCalculator();

// --- Empreinte pour l'hébergement en France (scénario de référence), selon les deux modèles ---

$facteurFrance = EmissionFactor::france();
$empreinteSimplifieeFrance = $calculateurSimplifie->calculate($modele, $facteurFrance, $tokensGeneres);
$empreinteCompleteFrance = $calculateurComplet->calculate($modele, $facteurFrance, $tokensGeneres);
$ecartFrance = $calculateurEcart->calculate($empreinteSimplifieeFrance, $empreinteCompleteFrance);

// --- Comparaison par zone d'hébergement, à énergie identique ---

$facteursParZone = EmissionFactor::toutes();

$empreintesParZone = [];
foreach ($facteursParZone as $facteur) {
    $empreintesParZone[] = [
        'facteur' => $facteur,
        'simplifiee' => $calculateurSimplifie->calculate($modele, $facteur, $tokensGeneres),
        'complete' => $calculateurComplet->calculate($modele, $facteur, $tokensGeneres),
    ];
}

// --- Comparaison par modèle, à zone d'hébergement identique (France) ---

$empreintesParModele = [];
foreach (LanguageModel::toutes() as $modeleCatalogue) {
    $simplifieeModele = $calculateurSimplifie->calculate($modeleCatalogue, $facteurFrance, $tokensGeneres);
    $completeModele = $calculateurComplet->calculate($modeleCatalogue, $facteurFrance, $tokensGeneres);

    $empreintesParModele[] = [
        'modele' => $modeleCatalogue,
        'simplifiee' => $simplifieeModele,
        'complete' => $completeModele,
        'ecart' => $calculateurEcart->calculate($simplifieeModele, $completeModele),
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
            max-width: 1100px;
            margin: 3rem auto;
            padding: 0 1rem;
            line-height: 1.5;
            color: #1a1a1a;
        }
        h1 { font-size: 1.4rem; }
        h2.section { font-size: 1.1rem; margin-top: 2.5rem; }
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
    <h1>Empreinte carbone d'une requête à un modèle de langage</h1>

    <table>
        <tr><th>Modèle</th><td><?= htmlspecialchars($modele->nom) ?></td></tr>
        <tr>
            <th>Paramètres actifs</th>
            <td><?= $modele->parametresActifsMilliards ?> milliards <?= badgeProvenance($modele->provenance) ?></td>
        </tr>
        <tr>
            <th>Paramètres totaux</th>
            <td>
                <?= $modele->parametresTotauxMilliards ?> milliards
                <?= badgeProvenance($modele->provenanceParametresTotaux) ?>
            </td>
        </tr>
        <tr><th>Tokens générés</th><td><?= $tokensGeneres ?></td></tr>
        <tr><th>Zone d'hébergement</th><td><?= htmlspecialchars($facteurFrance->zone) ?></td></tr>
    </table>

    <h2 class="section">Modèle simplifié vs modèle complet</h2>
    <div class="comparaison">
        <div class="carte">
            <h3>Modèle simplifié</h3>
            <p class="result">
                Énergie : <?= number_format($empreinteSimplifieeFrance->energieTotaleWh, 4, ',', ' ') ?> Wh
                <?= badgeProvenance($modele->provenance) ?>
            </p>
            <p class="result">
                Émissions : <?= number_format($empreinteSimplifieeFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                <?= badgeProvenance($modele->provenance) ?>
            </p>
        </div>
        <div class="carte">
            <h3>Modèle complet</h3>
            <p class="result">
                Énergie : <?= number_format($empreinteCompleteFrance->energieTotaleWh, 4, ',', ' ') ?> Wh
                <?= badgeProvenancePire($modele->provenance, $modele->provenanceParametresTotaux) ?>
            </p>
            <p class="result">
                Émissions : <?= number_format($empreinteCompleteFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                <?= badgeProvenancePire($modele->provenance, $modele->provenanceParametresTotaux) ?>
            </p>
            <p>
                Cartes GPU : <?= $empreinteCompleteFrance->cartesGpu ?>
                <?= badgeProvenance($modele->provenanceParametresTotaux) ?>
            </p>
        </div>
        <div class="ecart">
            Écart (complet − simplifié) :
            <strong>
                <?= $ecartFrance->ecartTotalWh >= 0 ? '+' : '' ?><?= number_format($ecartFrance->ecartTotalWh, 4, ',', ' ') ?> Wh
                (<?= $ecartFrance->ecartTotalPourcent >= 0 ? '+' : '' ?><?= number_format($ecartFrance->ecartTotalPourcent, 1, ',', ' ') ?> %,
                soit ×<?= number_format($ecartFrance->ecartMultiplicateur, 2, ',', ' ') ?>)
            </strong>
            <br>
            dont serveur :
            <?= $ecartFrance->ecartServeurPourcent >= 0 ? '+' : '' ?><?= number_format($ecartFrance->ecartServeurPourcent, 1, ',', ' ') ?> %,
            dont cartes :
            <?= $ecartFrance->ecartCartesPourcent >= 0 ? '+' : '' ?><?= number_format($ecartFrance->ecartCartesPourcent, 1, ',', ' ') ?> %
        </div>
    </div>

    <div class="details">
        <h2>Détail du calcul — modèle simplifié</h2>
        <ol>
            <li>
                Énergie GPU par token généré (régression EcoLogits) :
                <code>
                    <?= number_format($empreinteSimplifieeFrance->energieParTokenWh, 8, ',', ' ') ?> Wh/token
                </code>
            </li>
            <li>
                Énergie totale, en tenant compte du PUE du datacenter :
                <code>
                    <?= number_format($empreinteSimplifieeFrance->energieParTokenWh, 8, ',', ' ') ?> × <?= $tokensGeneres ?>
                    = <?= number_format($empreinteSimplifieeFrance->energieTotaleWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Émissions de CO2eq, avec le facteur d'émission du mix électrique de la zone
                d'hébergement (<?= htmlspecialchars($facteurFrance->zone) ?>, <?= $facteurFrance->gCo2eqParKwh ?> gCO2eq/kWh) :
                <code>
                    (<?= number_format($empreinteSimplifieeFrance->energieTotaleWh, 4, ',', ' ') ?> / 1000) × <?= $facteurFrance->gCo2eqParKwh ?>
                    = <?= number_format($empreinteSimplifieeFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <div class="details">
        <h2>Détail du calcul — modèle complet</h2>
        <ol>
            <li>
                Mémoire requise pour charger le modèle (paramètres totaux, quantification 4 bits,
                surcharge ×1,2) :
                <code>
                    1,2 × <?= $modele->parametresTotauxMilliards ?> × 4 / 8
                    = <?= number_format($empreinteCompleteFrance->memoireRequiseGo, 4, ',', ' ') ?> Go
                </code>
            </li>
            <li>
                Nombre de cartes GPU nécessaires (mémoire d'une carte : 80 Go) :
                <code>
                    plafond(<?= number_format($empreinteCompleteFrance->memoireRequiseGo, 4, ',', ' ') ?> / 80)
                    = <?= $empreinteCompleteFrance->cartesGpu ?> carte(s)
                </code>
            </li>
            <li>
                Durée de génération (régression EcoLogits sur les paramètres actifs) :
                <code>
                    (8,02e-4 × <?= $modele->parametresActifsMilliards ?> + 2,23e-2) × <?= $tokensGeneres ?>
                    = <?= number_format($empreinteCompleteFrance->dureeSecondes, 4, ',', ' ') ?> s
                </code>
            </li>
            <li>
                Énergie du serveur hors GPU (durée convertie en heures, puissance 1000 W, au
                prorata des cartes utilisées) :
                <code>
                    (<?= number_format($empreinteCompleteFrance->dureeSecondes, 4, ',', ' ') ?> / 3600) × 1000 ×
                    (<?= $empreinteCompleteFrance->cartesGpu ?> / 8)
                    = <?= number_format($empreinteCompleteFrance->energieServeurWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Énergie GPU par carte (même régression EcoLogits que le modèle simplifié) :
                <code>
                    (8,91e-5 × <?= $modele->parametresActifsMilliards ?> + 1,43e-3) × <?= $tokensGeneres ?>
                    = <?= number_format($empreinteCompleteFrance->energieGpuParCarteWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Énergie totale, cartes GPU comptées et PUE appliqué :
                <code>
                    1,2 × (<?= number_format($empreinteCompleteFrance->energieServeurWh, 4, ',', ' ') ?>
                    + <?= $empreinteCompleteFrance->cartesGpu ?> × <?= number_format($empreinteCompleteFrance->energieGpuParCarteWh, 4, ',', ' ') ?>)
                    = <?= number_format($empreinteCompleteFrance->energieTotaleWh, 4, ',', ' ') ?> Wh
                </code>
            </li>
            <li>
                Émissions de CO2eq, avec le facteur d'émission du mix électrique de la zone
                d'hébergement (<?= htmlspecialchars($facteurFrance->zone) ?>, <?= $facteurFrance->gCo2eqParKwh ?> gCO2eq/kWh) :
                <code>
                    (<?= number_format($empreinteCompleteFrance->energieTotaleWh, 4, ',', ' ') ?> / 1000) × <?= $facteurFrance->gCo2eqParKwh ?>
                    = <?= number_format($empreinteCompleteFrance->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                </code>
            </li>
        </ol>
    </div>

    <h2 class="section">Comparaison par zone d'hébergement, à énergie identique</h2>
    <table>
        <tr>
            <th>Zone</th>
            <th>Facteur d'émission</th>
            <th>Énergie (simplifié)</th>
            <th>Énergie (complet)</th>
            <th>Émissions (simplifié)</th>
            <th>Émissions (complet)</th>
        </tr>
        <?php foreach ($empreintesParZone as $ligne): ?>
        <tr>
            <td><?= htmlspecialchars($ligne['facteur']->zone) ?></td>
            <td>
                <?= number_format($ligne['facteur']->gCo2eqParKwh, 2, ',', ' ') ?> gCO2eq/kWh
                <?= badgeProvenance($ligne['facteur']->provenance) ?>
            </td>
            <td><?= number_format($ligne['simplifiee']->energieTotaleWh, 4, ',', ' ') ?> Wh</td>
            <td><?= number_format($ligne['complete']->energieTotaleWh, 4, ',', ' ') ?> Wh</td>
            <td><?= number_format($ligne['simplifiee']->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq</td>
            <td><?= number_format($ligne['complete']->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2 class="section">Comparaison par modèle, à zone d'hébergement identique (France)</h2>
    <div class="table-scroll">
    <table>
        <tr>
            <th>Modèle</th>
            <th>Paramètres actifs</th>
            <th>Paramètres totaux</th>
            <th>Cartes GPU</th>
            <th>Énergie (simplifié)</th>
            <th>Énergie (complet)</th>
            <th>Écart total</th>
            <th>Écart dont serveur</th>
            <th>Écart dont cartes</th>
            <th>Émissions (simplifié)</th>
            <th>Émissions (complet)</th>
        </tr>
        <?php foreach ($empreintesParModele as $ligne): ?>
        <tr>
            <td><?= htmlspecialchars($ligne['modele']->nom) ?></td>
            <td>
                <?= $ligne['modele']->parametresActifsMilliards ?> milliards
                <?= badgeProvenance($ligne['modele']->provenance) ?>
            </td>
            <td>
                <?= $ligne['modele']->parametresTotauxMilliards ?> milliards
                <?= badgeProvenance($ligne['modele']->provenanceParametresTotaux) ?>
            </td>
            <td>
                <?= $ligne['complete']->cartesGpu ?>
                <?= badgeProvenance($ligne['modele']->provenanceParametresTotaux) ?>
            </td>
            <td>
                <?= number_format($ligne['simplifiee']->energieTotaleWh, 4, ',', ' ') ?> Wh
                <?= badgeProvenance($ligne['modele']->provenance) ?>
            </td>
            <td>
                <?= number_format($ligne['complete']->energieTotaleWh, 4, ',', ' ') ?> Wh
                <?= badgeProvenancePire($ligne['modele']->provenance, $ligne['modele']->provenanceParametresTotaux) ?>
            </td>
            <td>
                <?= $ligne['ecart']->ecartTotalPourcent >= 0 ? '+' : '' ?><?= number_format($ligne['ecart']->ecartTotalPourcent, 1, ',', ' ') ?> %
                (×<?= number_format($ligne['ecart']->ecartMultiplicateur, 2, ',', ' ') ?>)
            </td>
            <td>
                <?= $ligne['ecart']->ecartServeurPourcent >= 0 ? '+' : '' ?><?= number_format($ligne['ecart']->ecartServeurPourcent, 1, ',', ' ') ?> %
            </td>
            <td>
                <?= $ligne['ecart']->ecartCartesPourcent >= 0 ? '+' : '' ?><?= number_format($ligne['ecart']->ecartCartesPourcent, 1, ',', ' ') ?> %
            </td>
            <td>
                <?= number_format($ligne['simplifiee']->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                <?= badgeProvenance($ligne['modele']->provenance) ?>
            </td>
            <td>
                <?= number_format($ligne['complete']->emissionsGco2eq, 4, ',', ' ') ?> gCO2eq
                <?= badgeProvenancePire($ligne['modele']->provenance, $ligne['modele']->provenanceParametresTotaux) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <footer>
        <p>Méthodologie et sources :</p>
        <ul class="sources">
            <li><a href="https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md">EcoLogits 0.4.0 — méthodologie d'estimation de l'énergie (modèles simplifié et complet)</a></li>
            <?php foreach (LanguageModel::toutes() as $modeleCatalogue): ?>
            <?= detailProvenance('Modèle : ' . $modeleCatalogue->nom . ' (paramètres actifs)', $modeleCatalogue->provenance) ?>
            <?= detailProvenance('Modèle : ' . $modeleCatalogue->nom . ' (paramètres totaux)', $modeleCatalogue->provenanceParametresTotaux) ?>
            <?php endforeach; ?>
            <?php foreach ($facteursParZone as $facteur): ?>
            <?= detailProvenance("Facteur d'émission : " . $facteur->zone, $facteur->provenance) ?>
            <?php endforeach; ?>
        </ul>
    </footer>
</body>
</html>
