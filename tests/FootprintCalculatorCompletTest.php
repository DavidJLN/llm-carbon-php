<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorComplet;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Un test par terme nouveau du modèle COMPLET (mémoire requise, cartes GPU, durée, énergie serveur,
 * énergie GPU, énergie totale, émissions), chacun isolé sur sa propre propriété de FootprintComplet
 * pour qu'une régression sur un seul terme fasse échouer un seul test ciblé. Chaque test a été
 * saboté manuellement pendant le développement (constante ou opérateur temporairement modifié dans
 * FootprintCalculatorComplet) pour vérifier qu'il échoue bien quand le calcul est faux.
 */
final class FootprintCalculatorCompletTest extends TestCase
{
    private function llama70b(): LanguageModel
    {
        return LanguageModel::llama31_70b();
    }

    private function provenanceDeTest(): Provenance
    {
        return new Provenance(ProvenanceType::MesureeEtPubliee, 'https://example.org', '2024', 'Note.');
    }

    public function testMemoireRequiseGoNeDependQueDesParametresTotauxEtDeLaQuantification(): void
    {
        // 1,2 x 70 x 4 / 8 = 42 Go, quels que soient la zone d'émission et le nombre de tokens.
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(42.0, $empreinte->memoireRequiseGo, 0.0001);
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function parametresTotauxEtCartesAttendues(): iterable
    {
        // memoire = 1,2 x P_total x 4 / 8 = 0,6 x P_total (Go)
        yield '100 milliards -> 60 Go -> 1 carte' => [100.0, 1];
        yield '200 milliards -> 120 Go -> 2 cartes (plafond, pas 1,5)' => [200.0, 2];
        yield '1800 milliards (GPT-4) -> 1080 Go -> 14 cartes' => [1800.0, 14];
    }

    #[DataProvider('parametresTotauxEtCartesAttendues')]
    public function testCartesGpuArrondiAuPlafond(float $parametresTotauxMilliards, int $cartesAttendues): void
    {
        $modele = new LanguageModel(
            'Modèle de test',
            1.0,
            $this->provenanceDeTest(),
            $parametresTotauxMilliards,
            $this->provenanceDeTest(),
        );

        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($modele, EmissionFactor::france(), 500);

        self::assertSame($cartesAttendues, $empreinte->cartesGpu);
    }

    public function testDureeSecondesDependDesParametresActifsEtDesTokens(): void
    {
        // (8,02e-4 x 70 + 2,23e-2) x 500 = 39,22 s
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(39.22, $empreinte->dureeSecondes, 0.0001);
    }

    public function testEnergieServeurWhConvertitLaDureeEnHeures(): void
    {
        // (39,22 s / 3600) x 1000 W x (1 carte / 8) = 1,361805... Wh
        // Ce test échoue si la conversion secondes -> heures (/ 3600) est oubliée : le résultat
        // serait alors 3600 fois trop grand (4902,5 Wh), ce qui est physiquement absurde pour
        // 500 tokens.
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(1.361805556, $empreinte->energieServeurWh, 0.000001);
    }

    public function testEnergieGpuParCarteWhUtiliseLaRegressionEcologits(): void
    {
        // (8,91e-5 x 70 + 1,43e-3) x 500 = 3,8335 Wh
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(3.8335, $empreinte->energieGpuParCarteWh, 0.0001);
    }

    public function testEnergieTotaleWhCombineServeurEtCartesFoisEnergieGpuSousPue(): void
    {
        // GPT-4 (14 cartes, pas 1) délibérément choisi ici plutôt que Llama 70B : avec 1 seule
        // carte, oublier le facteur « cartes x » sur l'énergie GPU serait invisible (cartes x X = X
        // quand cartes = 1). PUE x (energie_serveur + cartes x energie_gpu)
        // = 1,2 x (39,72791667 + 14 x 8,5558) = 191,41094 Wh.
        // Ce test échoue si le nombre de cartes n'est pas appliqué comme facteur multiplicatif de
        // l'énergie GPU, ou si le PUE n'est pas appliqué à la somme des deux termes.
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate(LanguageModel::gpt4(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(191.41094, $empreinte->energieTotaleWh, 0.00001);
    }

    /**
     * @return iterable<string, array{EmissionFactor, float}>
     */
    public static function zonesEtEmissionsAttendues(): iterable
    {
        yield 'France' => [EmissionFactor::france(), 0.506854];
        yield 'Europe' => [EmissionFactor::europe(), 3.175786];
    }

    #[DataProvider('zonesEtEmissionsAttendues')]
    public function testEmissionsGco2eqAppliqueLeFacteurDeLaZone(
        EmissionFactor $facteur,
        float $emissionsAttenduesGco2eq
    ): void {
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate($this->llama70b(), $facteur, 500);

        self::assertEqualsWithDelta($emissionsAttenduesGco2eq, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testGpt4oDonneLaValeurAttendue(): void
    {
        // Régression : 44 milliards d'actifs / 440 milliards de totaux -> 4 cartes GPU (264 Go
        // requis / 80 Go par carte, arrondi au plafond) -> 17,6400 Wh avec le modèle complet.
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        self::assertSame(4, $empreinte->cartesGpu);
        self::assertEqualsWithDelta(17.6400, $empreinte->energieTotaleWh, 0.0001);
    }

    public function testQwen3235bA22bDonneLaValeurAttendue(): void
    {
        // 22 milliards d'actifs / 235 milliards de totaux -> 141 Go requis -> 2 cartes GPU.
        $empreinte = (new FootprintCalculatorComplet())
            ->calculate(LanguageModel::qwen3_235b_a22b(), EmissionFactor::france(), 500);

        self::assertSame(2, $empreinte->cartesGpu);
        self::assertEqualsWithDelta(5.732573, $empreinte->energieTotaleWh, 0.00001);
    }

    public function testUneFourchetteDentreeNestPasUneFourchetteDeSortiePourGpt4o(): void
    {
        // Piège : les paramètres actifs de GPT-4o vont de 44 à 132 milliards (x3 exactement), mais
        // cela ne multiplie l'énergie totale du modèle complet que par ≈2,40 — ni par 3 (le ratio
        // d'entrée), ni par ≈2,47 (le ratio de la seule régression d'énergie GPU, voir
        // LanguageModel::gpt4o()). La régression est affine (terme constant β), et le modèle
        // complet en combine deux (durée, énergie GPU) sous un même PUE.
        $calculateur = new FootprintCalculatorComplet();
        $facteur = EmissionFactor::france();

        $modeleBasse = new LanguageModel('t', 44, $this->provenanceDeTest(), 440, $this->provenanceDeTest());
        $modeleHaute = new LanguageModel('t', 132, $this->provenanceDeTest(), 440, $this->provenanceDeTest());

        $energieBasse = $calculateur->calculate($modeleBasse, $facteur, 500)->energieTotaleWh;
        $energieHaute = $calculateur->calculate($modeleHaute, $facteur, 500)->energieTotaleWh;

        self::assertEqualsWithDelta(2.400188, $energieHaute / $energieBasse, 0.00001);
    }

    public function testUneFourchetteDentreeNestPasUneFourchetteDeSortiePourGpt4(): void
    {
        // Même piège pour GPT-4 : 176 à 528 milliards de paramètres actifs (x3 en entrée), mais
        // seulement x2,81 en énergie totale (contre x2,83 pour la seule régression d'énergie GPU,
        // voir LanguageModel::gpt4()) — les deux ratios divergent, même s'ils sont proches ici.
        $calculateur = new FootprintCalculatorComplet();
        $facteur = EmissionFactor::france();

        $modeleBasse = new LanguageModel('t', 176, $this->provenanceDeTest(), 1760, $this->provenanceDeTest());
        $modeleHaute = new LanguageModel('t', 528, $this->provenanceDeTest(), 1760, $this->provenanceDeTest());

        $energieBasse = $calculateur->calculate($modeleBasse, $facteur, 500)->energieTotaleWh;
        $energieHaute = $calculateur->calculate($modeleHaute, $facteur, 500)->energieTotaleWh;

        self::assertEqualsWithDelta(2.806530, $energieHaute / $energieBasse, 0.00001);
    }

    public function testZeroTokenGenereLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorComplet())->calculate($this->llama70b(), EmissionFactor::france(), 0);
    }

    public function testUnNombreDeTokensNegatifLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorComplet())->calculate($this->llama70b(), EmissionFactor::france(), -1);
    }
}
