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
        public readonly string $urlSource,
    ) {
    }

    /**
     * Mix électrique français, en gCO2eq par kWh.
     * Source : https://base-empreinte.ademe.fr/ (Base Empreinte ADEME, facteur d'émission de
     * l'électricité consommée en France).
     */
    public static function france(): self
    {
        return new self('France', 81.3, 'https://base-empreinte.ademe.fr/');
    }

    /**
     * Mix électrique européen (zone EEE — Espace économique européen), en gCO2eq par kWh.
     * Valeur d'origine : 0,509427 kgCO2eq/kWh, soit 509,4 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()).
     * Source : https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv
     * (ligne « Climate change », colonne « EEE », sourcée ADEME Base IMPACTS ® — données 2011).
     */
    public static function europe(): self
    {
        return new self(
            'Europe',
            509.4,
            'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv'
        );
    }

    /**
     * Mix électrique des États-Unis, en gCO2eq par kWh.
     * Valeur d'origine : 0,67978 kgCO2eq/kWh, soit 679,8 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()).
     * Source : https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv
     * (ligne « Climate change », colonne « USA », sourcée ADEME Base IMPACTS ® — données 2011).
     */
    public static function etatsUnis(): self
    {
        return new self(
            'États-Unis',
            679.8,
            'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv'
        );
    }

    /**
     * Mix électrique mondial moyen, en gCO2eq par kWh.
     * Valeur d'origine : 0,590478 kgCO2eq/kWh, soit 590,4 gCO2eq/kWh une fois convertie et
     * arrondie à une décimale (précision de france()).
     * Source : https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv
     * (ligne « Climate change », colonne « WOR », sourcée ADEME Base IMPACTS ® — données 2011).
     */
    public static function monde(): self
    {
        return new self(
            'Monde',
            590.4,
            'https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv'
        );
    }

    /**
     * Toutes les zones géographiques disponibles, pour affichage comparatif ou vérification
     * (par exemple : chaque zone doit citer une source).
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
