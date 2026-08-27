<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Implémente le modèle COMPLET de la méthodologie EcoLogits v0.4 : contrairement au modèle
 * SIMPLIFIÉ (FootprintCalculatorSimplifie), il calcule la mémoire GPU requise pour charger le
 * modèle à partir de ses paramètres TOTAUX (donc le nombre de cartes GPU nécessaires), l'énergie du
 * serveur hors GPU (proportionnelle à la durée de génération et au nombre de cartes utilisées), et
 * ajoute cette énergie serveur à l'énergie GPU (elle-même multipliée par le nombre de cartes,
 * puisque chaque carte consomme cette énergie). Seule cette classe porte les coefficients du modèle
 * complet.
 *
 * Source principale (formules et prose) :
 * https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
 * Source des valeurs numériques exactes (constantes Python de la même version) :
 * https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py
 *
 * ATTENTION — une fourchette d'entrée n'est pas une fourchette de sortie : energieTotaleWh n'est
 * PAS proportionnelle à parametresActifsMilliards. Deux raisons combinées : (1) les deux
 * régressions (durée, énergie GPU) sont affines, pas linéaires — chacune a un terme constant (β)
 * qui ne varie pas avec les paramètres, donc doubler/tripler les paramètres actifs ne
 * double/triple jamais exactement le résultat d'une régression ; (2) ce calculateur combine DEUX
 * régressions affines distinctes (durée et énergie GPU) sous un même PUE, ce qui produit un
 * troisième ratio, différent de celui de chaque régression prise isolément. Concrètement, pour
 * GPT-4o (44 à 132 milliards de paramètres actifs, soit x3 en entrée), l'énergie GPU par token
 * seule n'est multipliée que par ≈2,47, et l'énergie totale de ce calculateur (qui inclut aussi le
 * terme serveur) ne l'est que par ≈2,40 — ni x3, ni x2,47 (voir LanguageModel::gpt4o() et les tests
 * de FootprintCalculatorCompletTest qui verrouillent ces deux ratios).
 *
 * ATTENTION — ce modèle complet ne doit jamais paraître plus fiable que ses entrées, en particulier
 * parametresTotauxMilliards : cartesGpu (donc une part importante de energieTotaleWh, via
 * energieServeurWh ET via le facteur cartesGpu * energieGpuParCarteWh) est déterminé UNIQUEMENT par
 * parametresTotauxMilliards et par la quantification supposée — aucune autre donnée ne vient
 * corroborer ce nombre de cartes. Pour un modèle propriétaire dont les paramètres totaux sont une
 * Hypothese plutôt qu'une mesure (ex. GPT-4o : 440 milliards supposés, voir LanguageModel::gpt4o()),
 * ce chiffre unique fixe donc à lui seul une grandeur structurelle (un palier, via ceil()) sur
 * laquelle repose tout le reste du calcul, sans qu'un autre modèle simplifié ne vienne le
 * contredire ou le corroborer. Le modèle complet ne doit donc jamais afficher ses résultats avec
 * plus de confiance visuelle que le modèle simplifié quand ses entrées sont moins bien sourcées :
 * voir badgeProvenancePire() dans public/index.php, qui fait porter à energieTotaleWh et
 * emissionsGco2eq du modèle complet la pire (au sens le moins fiable) des deux provenances
 * (actifs, totaux) dont ils dépendent, et à cartesGpu la provenance des paramètres totaux dont il
 * dépend exclusivement.
 */
final class FootprintCalculatorComplet
{
    /**
     * Facteur de surcharge mémoire appliqué à la taille brute des poids du modèle pour estimer la
     * mémoire GPU réellement nécessaire à l'inférence (buffers d'activation, KV-cache, etc.).
     * Sans unité (facteur multiplicatif).
     * Source : llm_inference.md, formule M_model(P_total,Q) = 1.2 * P_total * Q / 8, qui cite elle
     * même Transformers Math 101 (https://blog.eleuther.ai/transformer-math/#total-inference-memory).
     */
    private const FACTEUR_SURCHARGE_MEMOIRE = 1.2;

    /**
     * Nombre de bits utilisés par défaut pour représenter chaque paramètre du modèle (quantification),
     * en l'absence d'information publiée par le fournisseur sur la quantification réellement utilisée
     * en production.
     * Contrairement aux autres constantes de cette classe, cette valeur n'apparaît nulle part dans la
     * prose de llm_inference.md (qui ne fait que nommer la variable Q, sans lui donner de valeur par
     * défaut) : le code est ici la seule source.
     * Source : https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py#L8,
     * constante MODEL_QUANTIZATION_BITS = 4 (valeur par défaut du paramètre optionnel
     * model_quantization_bits de compute_llm_impacts_dag).
     */
    private const QUANTIFICATION_BITS_PAR_DEFAUT = 4;

    /**
     * Mémoire disponible sur une carte GPU, en Go, pour le modèle d'inférence de référence
     * (NVIDIA A100 80GB) utilisé par EcoLogits pour ajuster ses régressions.
     * Source : llm.py, constante GPU_MEMORY = 80 ; llm_inference.md, "we use M_GPU = 80 GB for an
     * NVIDIA A100 80GB GPU".
     */
    private const MEMOIRE_GPU_GO = 80;

    /**
     * Nombre de cartes GPU installées sur le serveur de référence utilisé par EcoLogits (une seule
     * requête n'utilise pas forcément toutes les cartes du serveur qui l'héberge).
     * Source : llm.py, constante SERVER_GPUS = 8 ; llm_inference.md, "#GPU_installed = 8".
     */
    private const CARTES_GPU_INSTALLEES_PAR_SERVEUR = 8;

    /**
     * Puissance du serveur hors GPU, en watts, pour le serveur de référence utilisé par EcoLogits.
     * Source : llm.py, constante SERVER_POWER = 1 (en kW) ; llm_inference.md,
     * "we use W_server\GPU = 1 kW". Exprimée ici en watts (1 kW = 1000 W) pour rester cohérent avec
     * les autres constantes de cette classe, toutes en Wh/W.
     */
    private const PUISSANCE_SERVEUR_HORS_GPU_W = 1000;

    /**
     * Coefficient multiplicatif (pente) reliant les paramètres actifs (en milliards) à la durée de
     * génération par token, en secondes.
     * Source : llm.py, constante GPU_LATENCY_ALPHA = 8.02e-4 ; llm_inference.md, "A = 8.02e-4".
     */
    private const LATENCE_ALPHA_S_PAR_MILLIARD = 8.02e-4;

    /**
     * Terme constant (ordonnée à l'origine) de la durée de génération par token, en secondes.
     * Source : llm.py, constante GPU_LATENCY_BETA = 2.23e-2 ; llm_inference.md, "B = 2.23e-2".
     */
    private const LATENCE_BETA_S = 2.23e-2;

    /**
     * Coefficient multiplicatif (pente) reliant les paramètres actifs (en milliards) à l'énergie
     * consommée par une seule carte GPU par token généré, en Wh. Valeur identique à celle du modèle
     * simplifié (FootprintCalculatorSimplifie::ECOLOGITS_ENERGIE_ALPHA_WH_PAR_MILLIARD) : c'est la
     * même régression, ici multipliée par le nombre de cartes GPU requises plutôt qu'utilisée seule.
     * Source : llm_inference.md, formule E_GPU/#T_out = alpha * P_active + beta.
     */
    private const ENERGIE_GPU_ALPHA_WH_PAR_MILLIARD = 8.91e-5;

    /**
     * Terme constant (ordonnée à l'origine) de l'énergie consommée par une seule carte GPU par
     * token généré, en Wh.
     * Source : llm_inference.md, formule E_GPU/#T_out = alpha * P_active + beta.
     */
    private const ENERGIE_GPU_BETA_WH = 1.43e-3;

    /**
     * PUE (Power Usage Effectiveness) retenu par EcoLogits v0.4.0 pour un datacenter hyperscale ou
     * un supercalculateur. Sans unité (ratio énergie totale du datacenter / énergie des seuls
     * équipements IT).
     * CE N'EST PAS UNE MOYENNE SECTORIELLE : dans v0.4.0 (la version citée ici), EcoLogits ne
     * ventile pas cette valeur par fournisseur — elle est présentée telle quelle. Les versions plus
     * récentes d'EcoLogits (non retenues dans ce projet) la ventilent et montrent que 1,2
     * correspond spécifiquement à OpenAI/Azure (≈1,09 pour Anthropic/Cohere/Google, 1,09-1,14 pour
     * HuggingFace, 1,16 pour Mistral) : ce projet applique donc cette valeur de façon uniforme, y
     * compris à des modèles non hébergés par OpenAI (ex. Llama 3.1 70B).
     * Source : llm.py, constante DATACENTER_PUE = 1.2 ; llm_inference.md, "PUE = 1.2".
     */
    private const PUE_DATACENTER = 1.2;

    public function calculate(
        LanguageModel $languageModel,
        EmissionFactor $emissionFactor,
        int $tokensGeneres
    ): FootprintComplet {
        if ($tokensGeneres <= 0) {
            throw new InvalidArgumentException('Le nombre de tokens générés doit être strictement positif.');
        }

        // Mémoire nécessaire pour charger les poids du modèle (paramètres TOTAUX) en GPU, avec
        // surcharge. parametresTotauxMilliards * 1e9 = nombre de paramètres ; * bits / 8 = octets ;
        // / 1e9 = Go. Les deux facteurs 1e9 s'annulent : le résultat s'exprime directement en
        // milliards de paramètres.
        $memoireRequiseGo = self::FACTEUR_SURCHARGE_MEMOIRE
            * $languageModel->parametresTotauxMilliards
            * self::QUANTIFICATION_BITS_PAR_DEFAUT
            / 8;

        $cartesGpu = (int) ceil($memoireRequiseGo / self::MEMOIRE_GPU_GO);

        $dureeSecondes = (self::LATENCE_ALPHA_S_PAR_MILLIARD * $languageModel->parametresActifsMilliards
            + self::LATENCE_BETA_S) * $tokensGeneres;

        // La puissance du serveur est en W et la durée en secondes : on convertit la durée en
        // heures (/ 3600) pour obtenir un résultat en Wh, cohérent avec le reste du calcul.
        $energieServeurWh = ($dureeSecondes / 3600)
            * self::PUISSANCE_SERVEUR_HORS_GPU_W
            * ($cartesGpu / self::CARTES_GPU_INSTALLEES_PAR_SERVEUR);

        $energieGpuParCarteWh = (self::ENERGIE_GPU_ALPHA_WH_PAR_MILLIARD * $languageModel->parametresActifsMilliards
            + self::ENERGIE_GPU_BETA_WH) * $tokensGeneres;

        $energieTotaleWh = self::PUE_DATACENTER
            * ($energieServeurWh + $cartesGpu * $energieGpuParCarteWh);

        $emissionsGco2eq = ($energieTotaleWh / 1000) * $emissionFactor->gCo2eqParKwh;

        return new FootprintComplet(
            $memoireRequiseGo,
            $cartesGpu,
            $dureeSecondes,
            $energieServeurWh,
            $energieGpuParCarteWh,
            $energieTotaleWh,
            $emissionsGco2eq,
        );
    }
}
