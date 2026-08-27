<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Facteur d'émission d'un mix électrique (zone géographique d'hébergement du datacenter).
 */
final class EmissionFactor
{
    public function __construct(
        public readonly string $zone,
        public readonly float $gCo2eqParKwh,
        public readonly Provenance $provenance,
    ) {
    }

    /**
     * Mix électrique français, en gCO2eq par kWh.
     */
    public static function france(): self
    {
        return new self(
            'France',
            81.3,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
                'https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv',
                '0.4.0 (2024-08-29)',
                "EcoLogits v0.4.0, fichier electricity_mixes.csv, ligne « FRA » : gwp = 0,0813225 "
                . "kgCO2eq/kWh, soit 81,3 gCO2eq/kWh une fois converti et arrondi à une décimale "
                . '(précision retenue ici). Double attribution : cette même valeur est aussi '
                . "publiée par la Base Empreinte de l'ADEME (https://base-empreinte.ademe.fr/), "
                . 'sans que ce projet ait pu isoler sur cette base un millésime précis ni la '
                . 'formulation exacte du lien entre les deux sources.'
            )
        );
    }

    /**
     * Mix électrique européen (zone EEE — Espace économique européen), en gCO2eq par kWh.
     * Valeur d'origine : 0,509427 kgCO2eq/kWh, soit 509,4 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()).
     */
    public static function europe(): self
    {
        return new self(
            'Europe',
            509.4,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« EEE » : 0,509427 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * Mix électrique des États-Unis, en gCO2eq par kWh.
     * Valeur d'origine : 0,67978 kgCO2eq/kWh, soit 679,8 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()).
     */
    public static function etatsUnis(): self
    {
        return new self(
            'États-Unis',
            679.8,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« USA » : 0,67978 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * Mix électrique mondial moyen, en gCO2eq par kWh.
     * Valeur d'origine : 0,590478 kgCO2eq/kWh, soit 590,5 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()) : le deuxième chiffre après la virgule (7)
     * arrondit le premier (4) au-dessus, vers 590,5 — pas 590,4.
     */
    public static function monde(): self
    {
        return new self(
            'Monde',
            590.5,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« WOR » : 0,590478 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * Toutes les zones géographiques disponibles, pour affichage comparatif ou vérification
     * (par exemple : chaque zone doit citer une provenance).
     *
     * @return list<self>
     */
    public static function toutes(): array
    {
        return [
            self::france(),
            self::europe(),
            self::etatsUnis(),
            self::monde(),
        ];
    }
}
