<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Modèle de langage étudié. Son nombre de paramètres actifs détermine, via
 * FootprintCalculatorSimplifie et FootprintCalculatorComplet, l'énergie GPU consommée par token
 * généré ; son nombre de paramètres TOTAUX détermine, via FootprintCalculatorComplet, la mémoire
 * GPU requise pour charger le modèle (donc le nombre de cartes nécessaires).
 *
 * Pour un modèle dense, paramètres actifs et paramètres totaux sont égaux par définition (tous les
 * paramètres participent à chaque token) : utiliser la fabrique dense() plutôt que répéter la
 * valeur et sa provenance deux fois. Pour un modèle Mixture-of-Experts (MoE), seule une fraction
 * des paramètres totaux est activée par token : les deux valeurs diffèrent et chacune a sa propre
 * provenance (voir gpt4()).
 */
final class LanguageModel
{
    public function __construct(
        public readonly string $nom,
        public readonly float $parametresActifsMilliards,
        public readonly Provenance $provenance,
        public readonly float $parametresTotauxMilliards,
        public readonly Provenance $provenanceParametresTotaux,
    ) {
        if ($this->parametresActifsMilliards <= 0) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres actifs (en milliards) doit être strictement positif.'
            );
        }

        if ($this->parametresTotauxMilliards <= 0) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres totaux (en milliards) doit être strictement positif.'
            );
        }

        if ($this->parametresTotauxMilliards < $this->parametresActifsMilliards) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres totaux ne peut pas être inférieur au nombre de '
                . 'paramètres actifs (un modèle ne peut pas activer plus de paramètres qu\'il '
                . "n'en possède)."
            );
        }
    }

    /**
     * Fabrique pour un modèle dense : tous les paramètres sont actifs à chaque token, donc
     * paramètres actifs et paramètres totaux sont la même valeur, avec la même provenance. Pose
     * cette égalité une fois pour toutes plutôt que de la laisser à la charge de chaque appelant.
     */
    public static function dense(string $nom, float $parametresMilliards, Provenance $provenance): self
    {
        return new self($nom, $parametresMilliards, $provenance, $parametresMilliards, $provenance);
    }

    /**
     * Llama 3.1 70B (Meta), modèle ouvert : Meta publie officiellement le nombre de paramètres de
     * chaque variante de la famille (8B, 70B, 405B).
     */
    public static function llama31_70b(): self
    {
        return self::dense(
            'Llama 3.1 70B',
            70,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
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
     * GPT-4 (OpenAI), modèle propriétaire : OpenAI ne publie ni l'architecture ni le nombre de
     * paramètres. Les deux valeurs (actifs et totaux) retenues ici sont des hypothèses, pas des
     * mesures — voir la note de chaque Provenance pour le raisonnement et, pour les paramètres
     * actifs, la borne haute de l'estimation.
     */
    public static function gpt4(): self
    {
        return new self(
            'GPT-4 (OpenAI)',
            176,
            new Provenance(
                ProvenanceType::Hypothese,
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
                ProvenanceType::Hypothese,
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
     * GPT-4o (OpenAI), modèle propriétaire : comme GPT-4, OpenAI ne publie ni l'architecture ni le
     * nombre de paramètres. Les deux valeurs retenues ici sont des hypothèses, pas des mesures.
     * EcoLogits estime GPT-4o à très exactement un quart des paramètres qu'il retient pour GPT-4
     * (1760/4 = 440 milliards au total, 176/4 = 44 et 528/4 = 132 milliards de paramètres actifs) :
     * cette proportionnalité suggère une architecture MoE de même famille réduite d'un facteur 4,
     * mais EcoLogits ne publie pas de raisonnement détaillé indépendant pour ce ratio précis au-delà
     * du jeu de données lui-même (voir la note de la Provenance des paramètres totaux).
     */
    public static function gpt4o(): self
    {
        return new self(
            'GPT-4o (OpenAI)',
            44,
            new Provenance(
                ProvenanceType::Hypothese,
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
                ProvenanceType::Hypothese,
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
     * Qwen3-235B-A22B (Alibaba/Qwen), modèle ouvert de type Mixture-of-Experts : l'équipe Qwen
     * publie officiellement, dans son annonce de Qwen3, le nombre total de paramètres et le nombre
     * de paramètres activés par token — contrairement à GPT-4 et GPT-4o, ce ne sont pas des
     * hypothèses mais des valeurs mesurées et publiées par le fournisseur du modèle.
     */
    public static function qwen3_235b_a22b(): self
    {
        $provenance = new Provenance(
            ProvenanceType::MesureeEtPubliee,
            'https://qwenlm.github.io/blog/qwen3/',
            '2025-04-29',
            'Annonce officielle Qwen3 : « Qwen3-235B-A22B, a large model with 235 billion total '
            . 'parameters and 22 billion activated parameters » ; le tableau des architectures '
            . 'confirme 128 experts au total dont 8 activés par token, pour ce même modèle.'
        );

        return new self('Qwen3-235B-A22B', 22, $provenance, 235, $provenance);
    }

    /**
     * Tous les modèles du catalogue, pour affichage comparatif ou vérification (par exemple :
     * chaque modèle doit citer une provenance).
     *
     * @return list<self>
     */
    public static function toutes(): array
    {
        return [
            self::llama31_70b(),
            self::gpt4(),
            self::gpt4o(),
            self::qwen3_235b_a22b(),
        ];
    }
}
