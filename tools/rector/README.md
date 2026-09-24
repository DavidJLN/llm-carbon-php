# Règles Rector de llm-carbon-php

Projet Composer **séparé** : Rector et PHPUnit sont déclarés ici, jamais dans le `composer.json`
racine, qui reste sans dépendance d'exécution.

```bash
cd tools/rector
composer install
vendor/bin/phpunit tests                            # tests des règles
vendor/bin/rector process ../../public --dry-run    # aperçu, sans rien écrire
vendor/bin/rector process ../../public              # applique
```

## `SimplifiedToFullCalculatorRector`

Remplace le calculateur simplifié par le calculateur complet :

```php
// avant
$calculator = new FootprintCalculatorSimplified();
$wh = $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;

// après
$calculator = new \LlmCarbon\FootprintCalculatorFull();
$wh = $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;
```

**La forme du code est préservée, pas les chiffres.** Le modèle complet ajoute l'énergie du
serveur hors GPU et multiplie par le nombre de cartes GPU nécessaires : `totalEnergyWh` et
`emissionsGco2eq` changent (exemple : Llama 3.1 70B, France, 500 tokens : 4,60 Wh → 6,23 Wh).

- **Type résolu** : la classe est reconnue par le type PHPStan des expressions et le nom résolu
  des `Name`, jamais par le texte écrit. Un alias (`use … as Legacy`) est migré ; une autre classe
  qui porte le même nom court dans un autre espace de noms est ignorée.
- **`null` si rien à changer**, et **idempotente** : une fois le fichier migré, il ne contient plus
  de calculateur simplifié.
- **Refus signalé** : face à un cas non pris en charge, la règle lève
  `RefusedTransformationException`. Rector affiche une erreur qui nomme le fichier, chaque ligne
  concernée et la raison. Le fichier reste intact, les autres fichiers sont quand même traités, et
  le code de sortie est `1`. Le refus porte sur tout le fichier, pour ne pas mélanger les deux
  modèles sans le dire.

Formes prises en charge, tout le reste est refusé :
- `new FootprintCalculatorSimplified()` affecté à une variable simple, ou appelé directement
  (`(new …)->calculate(…)`) ;
- l'instance ne sert qu'à appeler `calculate()` ;
- chaque résultat est lu immédiatement via `->totalEnergyWh` ou `->emissionsGco2eq`, les deux
  seules propriétés communes à `Footprint` et `FootprintFull`. `FootprintFull` n'est pas un
  `Footprint` : un résultat conservé dans une variable, passé à `DifferenceCalculator` ou lu via
  `->energyPerTokenWh` est refusé ;
- aucune autre référence au type (déclaration de type, `instanceof`, `::class`…).

Les docblocks ne sont pas analysés. Sur `public/index.php`, la règle refuse aujourd'hui (lignes
274, 286, 295) : la page compare volontairement les deux modèles.
