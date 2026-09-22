<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\TestCase;

final class LanguageModelTest extends TestCase
{
    private function testProvenance(): Provenance
    {
        return new Provenance(ProvenanceType::MeasuredAndPublished, 'https://example.org', '2024', 'Note.');
    }

    public function testBuildsAModelWithItsFiveAttributes(): void
    {
        $model = new LanguageModel(
            'Llama 3.1 70B',
            70.0,
            $this->testProvenance(),
            70.0,
            $this->testProvenance(),
        );

        self::assertSame('Llama 3.1 70B', $model->name);
        self::assertEqualsWithDelta(70.0, $model->activeParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
        self::assertEqualsWithDelta(70.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->totalParametersProvenance->type);
    }

    public function testZeroActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 0.0, $this->testProvenance(), 10.0, $this->testProvenance());
    }

    public function testNegativeActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', -1.0, $this->testProvenance(), 10.0, $this->testProvenance());
    }

    public function testZeroTotalParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->testProvenance(), 0.0, $this->testProvenance());
    }

    public function testTotalParametersBelowActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->testProvenance(), 9.0, $this->testProvenance());
    }

    public function testDenseEnforcesEqualityBetweenActiveAndTotalParameters(): void
    {
        $provenance = $this->testProvenance();
        $model = LanguageModel::dense('Modèle dense', 42.0, $provenance);

        self::assertEqualsWithDelta(42.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(42.0, $model->totalParametersBillions, 0.0001);
        self::assertSame($provenance, $model->provenance);
        self::assertSame($provenance, $model->totalParametersProvenance);
    }

    public function testLlama3170bIsMeasuredAndPublished(): void
    {
        $model = LanguageModel::llama31_70b();

        self::assertSame('Llama 3.1 70B', $model->name);
        self::assertEqualsWithDelta(70.0, $model->activeParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
    }

    public function testGpt4IsAHypothesis(): void
    {
        $model = LanguageModel::gpt4();

        self::assertSame(ProvenanceType::Hypothesis, $model->provenance->type);
        self::assertGreaterThan(0, $model->activeParametersBillions);
    }

    public function testGpt4IsAMoeWithFewerActiveThanTotalParameters(): void
    {
        $model = LanguageModel::gpt4();

        self::assertEqualsWithDelta(176.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(1800.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::Hypothesis, $model->totalParametersProvenance->type);
        self::assertLessThan($model->totalParametersBillions, $model->activeParametersBillions);
    }

    public function testGpt4JustifiesTheHypothesisAndGivesTheUpperBound(): void
    {
        $note = LanguageModel::gpt4()->provenance->note;

        self::assertStringContainsString(
            'propriétaire',
            $note,
            "La note doit expliquer pourquoi la valeur n'est pas publiée (modèle propriétaire)."
        );
        self::assertStringContainsString(
            '528',
            $note,
            'La note doit citer la borne haute chiffrée de l\'estimation (528 milliards).'
        );
    }

    public function testLlama3170bNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // public/index.php affiche cette note telle quelle dans le pied de page : un utilisateur
        // qui vérifie la source des paramètres de Llama 3.1 70B doit lire exactement ce que
        // l'annonce Meta affirme, pas un texte tronqué ou réordonné par une régression silencieuse.
        self::assertSame(
            'Annonce officielle Meta : la famille Llama 3.1 comprend des variantes de 8, 70 et '
            . '405 milliards de paramètres ; la variante 70B est un modèle dense (tous ses '
            . 'paramètres sont actifs à chaque token), soit 70 milliards de paramètres actifs et '
            . '70 milliards de paramètres totaux.',
            LanguageModel::llama31_70b()->provenance->note
        );
    }

    public function testGpt4ActiveParametersNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem Llama : un utilisateur qui vérifie l'hypothèse des paramètres actifs de GPT-4 doit
        // lire le raisonnement complet (fourchette, borne haute, mise en garde), pas un extrait
        // amputé d'un des maillons de l'argumentation.
        self::assertSame(
            'GPT-4 est un modèle propriétaire : OpenAI n\'a jamais publié son nombre de '
            . 'paramètres, contrairement à Meta pour Llama. En l\'absence de publication, '
            . 'EcoLogits (la méthodologie retenue par ce projet) reconstitue une estimation à '
            . 'partir d\'une architecture Mixture-of-Experts ayant fuité (environ 1,8 billion de '
            . 'paramètres au total) et d\'un ratio d\'activation MoE typique de 10 % à 30 %, ce '
            . 'qui donne une fourchette de 176 à 528 milliards de paramètres actifs. La valeur '
            . 'retenue ici (176 milliards) est la borne basse de cette fourchette, la plus '
            . 'conservatrice ; la borne haute (528 milliards, exactement 3 fois plus de paramètres '
            . 'actifs) donne environ 2,8 fois plus d\'énergie par token dans la seule régression '
            . 'EcoLogits ((8,91e-5 × 528 + 1,43e-3) / (8,91e-5 × 176 + 1,43e-3) ≈ 2,83). '
            . 'ATTENTION, une fourchette d\'entrée n\'est pas une fourchette de sortie : avec le '
            . 'modèle COMPLET (mémoire et cartes GPU inchangées, déterminées par les paramètres '
            . 'TOTAUX, pas actifs), passer de la borne basse à la borne haute ne multiplie '
            . 'l\'énergie totale que par environ 2,81, ni par 3 ni par 2,83 — la régression est '
            . 'affine (terme constant β), et le modèle complet en combine deux (latence et '
            . 'énergie GPU) sous un même PUE.',
            LanguageModel::gpt4()->provenance->note
        );
    }

    public function testGpt4TotalParametersNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem : un utilisateur qui vérifie l'hypothèse des paramètres totaux de GPT-4 doit lire
        // la citation complète de la fuite invoquée, pas un extrait amputé.
        self::assertSame(
            'OpenAI n\'a jamais publié le nombre total de paramètres de GPT-4. EcoLogits reprend '
            . 'une fuite largement relayée (tweet de Yam Peleg, archivé sur '
            . 'https://archive.ph/2RQ8X) selon laquelle GPT-4 serait un modèle Mixture-of-Experts '
            . 'totalisant environ 1 800 milliards (1,8 billion) de paramètres au total. Cette '
            . 'valeur n\'est ni mesurée ni publiée officiellement : c\'est une reconstitution à '
            . 'partir d\'une fuite non vérifiable de façon indépendante, retenue ici faute de '
            . 'meilleure source ; EcoLogits ne publie pas de borne haute distincte pour ce chiffre '
            . '(contrairement à la fourchette d\'activation ci-dessus).',
            LanguageModel::gpt4()->totalParametersProvenance->note
        );
    }

    public function testGpt4oIsAMoeWithFewerActiveThanTotalParameters(): void
    {
        $model = LanguageModel::gpt4o();

        self::assertEqualsWithDelta(44.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(440.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::Hypothesis, $model->provenance->type);
        self::assertSame(ProvenanceType::Hypothesis, $model->totalParametersProvenance->type);
    }

    public function testGpt4oActiveParametersNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem GPT-4 : un utilisateur qui vérifie l'hypothèse des paramètres actifs de GPT-4o
        // doit lire le raisonnement complet, pas un extrait amputé d'un des maillons.
        self::assertSame(
            'GPT-4o est un modèle propriétaire : OpenAI n\'a jamais publié son nombre de '
            . 'paramètres. EcoLogits 0.11.1, fichier models.json, entrée « gpt-4o » : '
            . 'architecture MoE, "active": {"min": 44, "max": 132} (milliards), avertissement '
            . '"model-arch-not-released" (architecture non publiée, donc estimation). La valeur '
            . 'retenue ici (44 milliards) est la borne basse de cette fourchette, la plus '
            . 'conservatrice, par cohérence avec le choix fait pour GPT-4 ; la borne haute (132 '
            . 'milliards, exactement 3 fois plus de paramètres actifs) donne environ 2,5 fois '
            . 'plus d\'énergie par token dans la seule régression EcoLogits ((8,91e-5 × 132 + '
            . '1,43e-3) / (8,91e-5 × 44 + 1,43e-3) ≈ 2,47). ATTENTION, une fourchette d\'entrée '
            . 'n\'est pas une fourchette de sortie : avec le modèle COMPLET (mémoire et cartes '
            . 'GPU inchangées, déterminées par les paramètres TOTAUX, pas actifs), passer de la '
            . 'borne basse à la borne haute ne multiplie l\'énergie totale que par environ 2,40, '
            . 'ni par 3 ni par 2,47 — la régression est affine (terme constant β), et le modèle '
            . 'complet en combine deux (latence et énergie GPU) sous un même PUE.',
            LanguageModel::gpt4o()->provenance->note
        );
    }

    public function testGpt4oTotalParametersNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem : un utilisateur qui vérifie l'hypothèse des paramètres totaux de GPT-4o doit lire
        // la citation complète d'EcoLogits, pas un extrait amputé.
        self::assertSame(
            'OpenAI n\'a jamais publié le nombre total de paramètres de GPT-4o. EcoLogits 0.11.1, '
            . 'fichier models.json, entrée « gpt-4o » : "total": 440 (milliards), avertissement '
            . '"model-arch-not-released". Cette valeur n\'est ni mesurée ni publiée '
            . 'officiellement : c\'est l\'estimation retenue par EcoLogits, à défaut de meilleure '
            . 'source.',
            LanguageModel::gpt4o()->totalParametersProvenance->note
        );
    }

    public function testQwen3235bA22bIsMeasuredAndPublishedWithTotalDistinctFromActive(): void
    {
        $model = LanguageModel::qwen3_235b_a22b();

        self::assertEqualsWithDelta(22.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(235.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->totalParametersProvenance->type);
    }

    public function testQwen3235bA22bNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem : un utilisateur qui vérifie la source de Qwen3-235B-A22B doit lire exactement la
        // citation de l'annonce officielle, pas un texte altéré.
        self::assertSame(
            'Annonce officielle Qwen3 : « Qwen3-235B-A22B, a large model with 235 billion total '
            . 'parameters and 22 billion activated parameters » ; le tableau des architectures '
            . 'confirme 128 experts au total dont 8 activés par token, pour ce même modèle.',
            LanguageModel::qwen3_235b_a22b()->provenance->note
        );
    }

    public function testAllReturnsFourModels(): void
    {
        self::assertCount(4, LanguageModel::all());
    }

    public function testEachModelFromAllCarriesANonEmptyProvenance(): void
    {
        foreach (LanguageModel::all() as $model) {
            self::assertNotSame(
                '',
                trim($model->provenance->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres actifs.', $model->name)
            );
            self::assertNotSame(
                '',
                trim($model->totalParametersProvenance->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres totaux.', $model->name)
            );
        }
    }
}
