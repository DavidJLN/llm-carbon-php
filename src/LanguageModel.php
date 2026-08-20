<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Modèle de langage étudié : son nombre de paramètres actifs détermine, via
 * FootprintCalculator, l'énergie GPU consommée par token généré.
 */
final class LanguageModel
{
    public function __construct(
        public readonly string $nom,
        public readonly float $parametresActifsMilliards,
        public readonly Provenance $provenance,
    ) {
        if ($this->parametresActifsMilliards <= 0) {
            throw new InvalidArgumentException(
                'Le nombre de paramètres actifs (en milliards) doit être strictement positif.'
            );
        }
    }

    /**
     * Llama 3.1 70B (Meta), modèle ouvert : Meta publie officiellement le nombre de paramètres de
     * chaque variante de la famille (8B, 70B, 405B).
     */
    public static function llama31_70b(): self
    {
        return new self(
            'Llama 3.1 70B',
            70,
            new Provenance(
                ProvenanceType::MesureeEtPubliee,
                'https://ai.meta.com/blog/meta-llama-3-1/',
                '2024-07-23',
                "Annonce officielle Meta : la famille Llama 3.1 comprend des variantes de 8, 70 "
                . 'et 405 milliards de paramètres ; la variante 70B est un modèle dense (tous ses '
                . 'paramètres sont actifs à chaque token), soit 70 milliards de paramètres actifs.'
            )
        );
    }

    /**
     * GPT-4 (OpenAI), modèle propriétaire : OpenAI ne publie ni l'architecture ni le nombre de
     * paramètres. La valeur retenue ici est une hypothèse, pas une mesure — voir la note de sa
     * Provenance pour le raisonnement et la borne haute de l'estimation.
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
                . 'la plus conservatrice ; la borne haute est de 528 milliards de paramètres '
                . 'actifs, soit environ 2,8 fois plus d\'énergie par token dans la régression '
                . 'EcoLogits que la valeur retenue ici.'
            )
        );
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
        ];
    }
}
