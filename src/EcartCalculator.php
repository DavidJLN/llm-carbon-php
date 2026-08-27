<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Calcule l'écart entre le modèle COMPLET (FootprintCalculatorComplet) et le modèle SIMPLIFIÉ
 * (FootprintCalculatorSimplifie) pour un même scénario, et le décompose en une part « serveur » et
 * une part « cartes ». Ne porte aucun coefficient de la méthodologie EcoLogits : ne lit que les
 * résultats déjà exposés par Footprint et FootprintComplet, jamais les constantes privées des deux
 * calculateurs — la décomposition reste donc valable même si le PUE de l'un des deux calculateurs
 * venait à diverger de l'autre.
 */
final class EcartCalculator
{
    public function calculate(Footprint $simplifie, FootprintComplet $complet): EcartModeles
    {
        // energieServeurWh et (cartesGpu * energieGpuParCarteWh) sont les deux termes additionnés
        // avant application du PUE dans le modèle complet (voir FootprintCalculatorComplet::calculate) ;
        // leur somme, une fois le PUE appliqué, donne energieTotaleWh. Le PUE multipliant les deux
        // termes uniformément, la part de energieTotaleWh imputable au terme serveur est donc cette
        // même fraction — pas besoin de connaître le PUE lui-même pour l'isoler.
        $sommeAvantPueWh = $complet->energieServeurWh + $complet->cartesGpu * $complet->energieGpuParCarteWh;
        $ecartServeurWh = $complet->energieTotaleWh * ($complet->energieServeurWh / $sommeAvantPueWh);

        $ecartTotalWh = $complet->energieTotaleWh - $simplifie->energieTotaleWh;
        $ecartCartesWh = $ecartTotalWh - $ecartServeurWh;

        // Le multiplicateur (complet / simplifié) et le pourcentage d'écart ((complet - simplifié)
        // / simplifié) ne sont PAS le même nombre : un écart de +449,5 % correspond à un
        // multiplicateur de 5,495 (= 1 + 449,5/100), pas 4,495.
        return new EcartModeles(
            $ecartTotalWh,
            ($ecartTotalWh / $simplifie->energieTotaleWh) * 100,
            $complet->energieTotaleWh / $simplifie->energieTotaleWh,
            $ecartServeurWh,
            ($ecartServeurWh / $simplifie->energieTotaleWh) * 100,
            $ecartCartesWh,
            ($ecartCartesWh / $simplifie->energieTotaleWh) * 100,
        );
    }
}
