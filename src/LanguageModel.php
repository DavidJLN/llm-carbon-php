<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Language model under study. Its number of active parameters determines, via
 * FootprintCalculatorSimplified and FootprintCalculatorFull, the GPU energy consumed per
 * generated token; its number of TOTAL parameters determines, via FootprintCalculatorFull, the
 * GPU memory required to load the model (hence the number of cards needed).
 *
 * For a dense model, active parameters and total parameters are equal by definition (all
 * parameters participate in every token): use the dense() factory rather than repeating the
 * value and its provenance twice. For a Mixture-of-Experts (MoE) model, only a fraction of the
 * total parameters is activated per token: the two values differ and each has its own provenance
 * (see gpt4()).
 */
final class LanguageModel
{
    public function __construct(
        public readonly string $name,
        public readonly float $activeParametersBillions,
        public readonly Provenance $provenance,
        public readonly float $totalParametersBillions,
        public readonly Provenance $totalParametersProvenance,
    ) {
        if ($this->activeParametersBillions <= 0) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres actifs (en milliards) doit être strictement positif.'
            );
        }

        if ($this->totalParametersBillions <= 0) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres totaux (en milliards) doit être strictement positif.'
            );
        }

        if ($this->totalParametersBillions < $this->activeParametersBillions) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres totaux ne peut pas être inférieur au nombre de '
                . 'paramètres actifs (un modèle ne peut pas activer plus de paramètres qu\'il '
                . "n'en possède)."
            );
        }
    }

    /**
     * Factory for a dense model: all parameters are active on every token, so active parameters
     * and total parameters are the same value, with the same provenance. Establishes this
     * equality once and for all rather than leaving it to each caller.
     */
    public static function dense(string $name, float $parametersBillions, Provenance $provenance): self
    {
        return new self($name, $parametersBillions, $provenance, $parametersBillions, $provenance);
    }

    /**
     * Llama 3.1 70B (Meta), open model: Meta officially publishes the parameter count of each
     * variant of the family (8B, 70B, 405B).
     */
    public static function llama31_70b(): self
    {
        return self::dense(
            'Llama 3.1 70B',
            70,
            new Provenance(
                ProvenanceType::MeasuredAndPublished,
                'https://ai.meta.com/blog/meta-llama-3-1/',
                '2024-07-23',
                "Annonce officielle Meta : la famille Llama 3.1 comprend des variantes de 8, 70 "
                . 'et 405 milliards de paramètres ; la variante 70B est un modèle dense (tous ses '
                . 'paramètres sont actifs à chaque token), soit 70 milliards de paramètres actifs '
                . 'et 70 milliards de paramètres totaux.'
            )
        );
    }

    /**
     * GPT-4 (OpenAI), proprietary model: OpenAI publishes neither the architecture nor the
     * parameter count. Both values (active and total) retained here are hypotheses, not
     * measurements — see the note of each Provenance for the reasoning and, for the active
     * parameters, the upper bound of the estimate.
     */
    public static function gpt4(): self
    {
        return new self(
            'GPT-4 (OpenAI)',
            176,
            new Provenance(
                ProvenanceType::Hypothesis,
                'https://ecologits.ai/latest/methodology/proprietary_models/',
                'Consulté le 2026-08-20',
                'GPT-4 est un modèle propriétaire : OpenAI n\'a jamais publié son nombre de '
                . 'paramètres, contrairement à Meta pour Llama. En l\'absence de publication, '
                . "EcoLogits (la méthodologie retenue par ce projet) reconstitue une estimation à "
                . "partir d'une architecture Mixture-of-Experts ayant fuité (environ 1,8 billion "
                . 'de paramètres au total) et d\'un ratio d\'activation MoE typique de 10 % à '
                . "30 %, ce qui donne une fourchette de 176 à 528 milliards de paramètres actifs. "
                . 'La valeur retenue ici (176 milliards) est la borne basse de cette fourchette, '
                . 'la plus conservatrice ; la borne haute (528 milliards, exactement 3 fois plus de '
                . 'paramètres actifs) donne environ 2,8 fois plus d\'énergie par token dans la '
                . 'seule régression EcoLogits ((8,91e-5 × 528 + 1,43e-3) / '
                . '(8,91e-5 × 176 + 1,43e-3) ≈ 2,83). ATTENTION, une fourchette d\'entrée n\'est '
                . 'pas une fourchette de sortie : avec le modèle COMPLET (mémoire et cartes GPU '
                . 'inchangées, déterminées par les paramètres TOTAUX, pas actifs), passer de la '
                . 'borne basse à la borne haute ne multiplie l\'énergie totale que par environ '
                . '2,81, ni par 3 ni par 2,83 — la régression est affine (terme constant β), et le '
                . 'modèle complet en combine deux (latence et énergie GPU) sous un même PUE.'
            ),
            1800,
            new Provenance(
                ProvenanceType::Hypothesis,
                'https://ecologits.ai/latest/methodology/proprietary_models/',
                'Consulté le 2026-08-27',
                "OpenAI n'a jamais publié le nombre total de paramètres de GPT-4. EcoLogits "
                . "reprend une fuite largement relayée (tweet de Yam Peleg, archivé sur "
                . "https://archive.ph/2RQ8X) selon laquelle GPT-4 serait un modèle "
                . 'Mixture-of-Experts totalisant environ 1 800 milliards (1,8 billion) de '
                . "paramètres au total. Cette valeur n'est ni mesurée ni publiée officiellement : "
                . "c'est une reconstitution à partir d'une fuite non vérifiable de façon "
                . 'indépendante, retenue ici faute de meilleure source ; EcoLogits ne publie pas '
                . 'de borne haute distincte pour ce chiffre (contrairement à la fourchette '
                . "d'activation ci-dessus)."
            )
        );
    }

    /**
     * GPT-4o (OpenAI), proprietary model: like GPT-4, OpenAI publishes neither the architecture
     * nor the parameter count. Both values retained here are hypotheses, not measurements.
     * EcoLogits estimates GPT-4o at exactly one quarter of the parameters it retains for GPT-4
     * (1760/4 = 440 billion total, 176/4 = 44 and 528/4 = 132 billion active parameters): this
     * proportionality suggests a MoE architecture of the same family scaled down by a factor of
     * 4, but EcoLogits does not publish independent detailed reasoning for this precise ratio
     * beyond the dataset itself (see the note of the total parameters Provenance).
     */
    public static function gpt4o(): self
    {
        return new self(
            'GPT-4o (OpenAI)',
            44,
            new Provenance(
                ProvenanceType::Hypothesis,
                'https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json',
                '0.11.1',
                "GPT-4o est un modèle propriétaire : OpenAI n'a jamais publié son nombre de "
                . 'paramètres. EcoLogits 0.11.1, fichier models.json, entrée « gpt-4o » : '
                . 'architecture MoE, "active": {"min": 44, "max": 132} (milliards), avertissement '
                . '"model-arch-not-released" (architecture non publiée, donc estimation). La valeur '
                . 'retenue ici (44 milliards) est la borne basse de cette fourchette, la plus '
                . 'conservatrice, par cohérence avec le choix fait pour GPT-4 ; la borne haute '
                . '(132 milliards, exactement 3 fois plus de paramètres actifs) donne environ 2,5 '
                . 'fois plus d\'énergie par token dans la seule régression EcoLogits '
                . '((8,91e-5 × 132 + 1,43e-3) / (8,91e-5 × 44 + 1,43e-3) ≈ 2,47). ATTENTION, une '
                . 'fourchette d\'entrée n\'est pas une fourchette de sortie : avec le modèle COMPLET '
                . '(mémoire et cartes GPU inchangées, déterminées par les paramètres TOTAUX, pas '
                . 'actifs), passer de la borne basse à la borne haute ne multiplie l\'énergie '
                . 'totale que par environ 2,40, ni par 3 ni par 2,47 — la régression est affine '
                . '(terme constant β), et le modèle complet en combine deux (latence et énergie '
                . 'GPU) sous un même PUE.'
            ),
            440,
            new Provenance(
                ProvenanceType::Hypothesis,
                'https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json',
                '0.11.1',
                "OpenAI n'a jamais publié le nombre total de paramètres de GPT-4o. EcoLogits "
                . '0.11.1, fichier models.json, entrée « gpt-4o » : "total": 440 (milliards), '
                . 'avertissement "model-arch-not-released". Cette valeur n\'est ni mesurée ni '
                . "publiée officiellement : c'est l'estimation retenue par EcoLogits, à défaut de "
                . 'meilleure source.'
            )
        );
    }

    /**
     * Qwen3-235B-A22B (Alibaba/Qwen), open Mixture-of-Experts model: the Qwen team officially
     * publishes, in its Qwen3 announcement, the total parameter count and the number of
     * parameters activated per token — unlike GPT-4 and GPT-4o, these are not hypotheses but
     * values measured and published by the model provider.
     */
    public static function qwen3_235b_a22b(): self
    {
        $provenance = new Provenance(
            ProvenanceType::MeasuredAndPublished,
            'https://qwenlm.github.io/blog/qwen3/',
            '2025-04-29',
            'Annonce officielle Qwen3 : « Qwen3-235B-A22B, a large model with 235 billion total '
            . 'parameters and 22 billion activated parameters » ; le tableau des architectures '
            . 'confirme 128 experts au total dont 8 activés par token, pour ce même modèle.'
        );

        return new self('Qwen3-235B-A22B', 22, $provenance, 235, $provenance);
    }

    /**
     * All models in the catalog, for comparative display or verification (e.g. every model must
     * cite a provenance).
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::llama31_70b(),
            self::gpt4(),
            self::gpt4o(),
            self::qwen3_235b_a22b(),
        ];
    }
}
