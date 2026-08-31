<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Emission factor of an electricity mix (geographic hosting zone of the datacenter).
 */
final class EmissionFactor
{
    public function __construct(
        public readonly string $zone,
        public readonly float $gCo2eqPerKwh,
        public readonly Provenance $provenance,
    ) {
    }

    /**
     * French electricity mix, in gCO2eq per kWh.
     */
    public static function france(): self
    {
        return new self(
            'France',
            81.3,
            new Provenance(
                ProvenanceType::MeasuredAndPublished,
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
     * European electricity mix (EEA zone — European Economic Area), in gCO2eq per kWh.
     * Original value: 0.509427 kgCO2eq/kWh, i.e. 509.4 gCO2eq/kWh once converted and rounded to
     * one decimal (same precision as france()).
     */
    public static function europe(): self
    {
        return new self(
            'Europe',
            509.4,
            new Provenance(
                ProvenanceType::MeasuredAndPublished,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« EEE » : 0,509427 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * United States electricity mix, in gCO2eq per kWh.
     * Original value: 0.67978 kgCO2eq/kWh, i.e. 679.8 gCO2eq/kWh once converted and rounded to
     * one decimal (same precision as france()).
     */
    public static function unitedStates(): self
    {
        return new self(
            'États-Unis',
            679.8,
            new Provenance(
                ProvenanceType::MeasuredAndPublished,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« USA » : 0,67978 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * World average electricity mix, in gCO2eq per kWh.
     * Original value: 0.590478 kgCO2eq/kWh, i.e. 590.5 gCO2eq/kWh once converted and rounded to
     * one decimal (same precision as france()): the second digit after the decimal point (7)
     * rounds the first one (4) up, to 590.5 — not 590.4.
     */
    public static function world(): self
    {
        return new self(
            'Monde',
            590.5,
            new Provenance(
                ProvenanceType::MeasuredAndPublished,
                'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv',
                '2011',
                'Jeu de données électrique de Boavizta, ligne « Climate change », colonne '
                . '« WOR » : 0,590478 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® '
                . '(données 2011).'
            )
        );
    }

    /**
     * All available geographic zones, for comparative display or verification (e.g. every zone
     * must cite a provenance).
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::france(),
            self::europe(),
            self::unitedStates(),
            self::world(),
        ];
    }
}
