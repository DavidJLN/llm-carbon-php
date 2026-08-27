<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use LlmCarbon\EcartCalculator;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorComplet;
use LlmCarbon\FootprintCalculatorSimplifie;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\TestCase;

/**
 * Un test par terme nouveau (écart total en Wh et en %, part serveur en Wh et en %, part cartes en
 * Wh et en %), chacun sabotable indépendamment. Chaque test a été saboté manuellement pendant le
 * développement (en modifiant temporairement EcartCalculator::calculate) pour vérifier qu'il échoue
 * bien quand le calcul est faux.
 */
final class EcartCalculatorTest extends TestCase
{
    private function calculer(LanguageModel $modele): array
    {
        $simplifie = (new FootprintCalculatorSimplifie())->calculate($modele, EmissionFactor::france(), 500);
        $complet = (new FootprintCalculatorComplet())->calculate($modele, EmissionFactor::france(), 500);

        return [$simplifie, $complet];
    }

    public function testEcartTotalWhEstLaDifferenceDesDeuxEnergiesTotales(): void
    {
        [$simplifie, $complet] = $this->calculer(LanguageModel::llama31_70b());

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(1.63416667, $ecart->ecartTotalWh, 0.00001);
    }

    public function testEcartTotalPourcentEstRelatifAuModeleSimplifie(): void
    {
        [$simplifie, $complet] = $this->calculer(LanguageModel::llama31_70b());

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(35.52381781, $ecart->ecartTotalPourcent, 0.00001);
    }

    public function testEcartMultiplicateurNestPasLePourcentageDivisePar100(): void
    {
        // Piège classique : « +449,5 % » ne veut pas dire « x 4,495 » mais « x 5,495 »
        // (= 1 + 449,5/100). Verrouille GPT-4o, où l'écart est assez grand pour rendre l'erreur
        // flagrante si quelqu'un remplaçait par erreur le multiplicateur par le pourcentage brut.
        $simplifie = (new FootprintCalculatorSimplifie())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);
        $complet = (new FootprintCalculatorComplet())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(5.494904, $ecart->ecartMultiplicateur, 0.00001);
        self::assertEqualsWithDelta(
            $ecart->ecartMultiplicateur,
            1 + $ecart->ecartTotalPourcent / 100,
            0.00001,
            'Le multiplicateur doit être cohérent avec le pourcentage : x = 1 + %/100.'
        );
    }

    public function testEcartServeurEgaleLecartTotalQuandUneSeuleCarteEstNecessaire(): void
    {
        // Llama 70B ne nécessite qu'une seule carte GPU : la totalité de l'écart avec le modèle
        // simplifié vient donc du terme serveur, la part « cartes » doit être quasi nulle.
        [$simplifie, $complet] = $this->calculer(LanguageModel::llama31_70b());

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(1.63416667, $ecart->ecartServeurWh, 0.00001);
        self::assertEqualsWithDelta(35.52381781, $ecart->ecartServeurPourcent, 0.00001);
        self::assertEqualsWithDelta(0.0, $ecart->ecartCartesWh, 0.0001);
        self::assertEqualsWithDelta(0.0, $ecart->ecartCartesPourcent, 0.0001);
    }

    public function testEcartCartesRepresente1300PourcentPourGpt4Qui14Cartes(): void
    {
        // GPT-4 nécessite 14 cartes : la part « cartes » de l'écart correspond exactement aux 13
        // cartes supplémentaires par rapport au modèle simplifié (qui n'en compte implicitement
        // qu'une seule), soit 13 x 100 % = 1300 % de l'énergie totale simplifiée.
        [$simplifie, $complet] = $this->calculer(LanguageModel::gpt4());

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(133.47048, $ecart->ecartCartesWh, 0.00001);
        self::assertEqualsWithDelta(1300.0, $ecart->ecartCartesPourcent, 0.0001);
    }

    public function testEcartServeurPourcentPourGpt4(): void
    {
        [$simplifie, $complet] = $this->calculer(LanguageModel::gpt4());

        $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

        self::assertEqualsWithDelta(47.6735, $ecart->ecartServeurWh, 0.00001);
        self::assertEqualsWithDelta(464.33900590, $ecart->ecartServeurPourcent, 0.00001);
    }

    public function testLesDeuxPartsSadditionnentExactementALecartTotal(): void
    {
        foreach (LanguageModel::toutes() as $modele) {
            [$simplifie, $complet] = $this->calculer($modele);

            $ecart = (new EcartCalculator())->calculate($simplifie, $complet);

            self::assertEqualsWithDelta(
                $ecart->ecartTotalWh,
                $ecart->ecartServeurWh + $ecart->ecartCartesWh,
                0.0001,
                sprintf('La décomposition de %s doit s\'additionner exactement à l\'écart total.', $modele->nom)
            );
            self::assertEqualsWithDelta(
                $ecart->ecartTotalPourcent,
                $ecart->ecartServeurPourcent + $ecart->ecartCartesPourcent,
                0.0001,
                sprintf('La décomposition en %% de %s doit s\'additionner exactement à l\'écart total.', $modele->nom)
            );
        }
    }
}
