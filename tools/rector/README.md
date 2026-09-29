# Règles Rector de llm-carbon-php

Projet Composer **séparé** : Rector et PHPUnit sont déclarés ici, jamais dans le `composer.json`
racine, qui reste sans dépendance d'exécution.

## Installation dans un projet : activation automatique

Le paquet est une extension Rector (`"type": "rector-extension"`). Il déclare en
`extra.rector.includes` le fichier `config/config.php`, qui enregistre la règle, et il tire
`rector/rector` et `rector/extension-installer`. Un `composer require` suffit, **sans
`rector.php`** :

```bash
composer require --dev davidjln/llm-carbon-php-rector
vendor/bin/rector process src --dry-run
```

**Une confirmation reste obligatoire, et c'est Composer qui l'impose.** Seul le projet racine
peut autoriser un plugin Composer ; une dépendance ne le peut pas (voir
https://getcomposer.org/allow-plugins). Lors d'un `composer require` interactif, Composer demande
`Do you trust "rector/extension-installer" to execute code…?` : répondre `y` écrit l'autorisation
dans `composer.json`. En CI ou avec `--no-interaction`, le plugin est bloqué et la règle ne
s'active pas. Il faut alors autoriser le plugin une fois, puis relancer l'installation :

```bash
composer config --no-plugins allow-plugins.rector/extension-installer true
composer install
```

Vérifié le 2026-09-29 (Composer 2.2.6, Rector 2.6.7) dans un projet vierge qui ne demande que ce
paquet : la règle s'applique sans `rector.php`.

**Attention :** une fois le paquet installé, *chaque* exécution de Rector dans le projet applique
la règle et fait passer le code du calculateur simplifié au calculateur complet. Les chiffres
calculés changent (voir ci-dessous). Si le projet a son propre `rector.php`, la règle s'ajoute à
ses règles.

## Développement de l'extension

```bash
cd tools/rector
composer install
vendor/bin/phpunit tests                            # tests des règles, via config/config.php
vendor/bin/rector process ../../public --dry-run    # aperçu, sans rien écrire
vendor/bin/rector process ../../public              # applique
```

Dans ce dépôt, les classes `LlmCarbon\` sont chargées depuis `../../src/` par `autoload-dev`
seulement. Installé ailleurs, le paquet n'embarque pas ce chemin : les classes viennent de
`davidjln/llm-carbon-php`, déjà présent dans tout projet qui a du code à migrer.

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
- **Idempotente** : relancée sur son propre résultat, elle ne modifie plus aucun fichier. Une fois
  migré, le fichier ne contient plus de calculateur simplifié. Le test
  `testRerunningOnItsOwnOutputChangesNothing` relance la règle sur la sortie attendue de chaque
  fixture de `Fixture/`. Il rougit si la règle réagit à sa propre sortie ; un sabotage de ce type
  (raccourcir le `\LlmCarbon\FootprintCalculatorFull` qu'elle vient d'écrire) n'est vu par aucun
  autre test.
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

### Taux de faux positifs mesuré

Mesure du 2026-09-29 : Rector 2.6.7, PHP 8.4.24. La règle a tourné en `--dry-run` sur une copie
des **60 paquets tiers** de `vendor/` à la racine (3 320 fichiers PHP : phpunit, infection,
symfony/*, sebastian/*, nikic/php-parser, thecodingmachine/safe…). Aucun de ces fichiers ne
mentionne `LlmCarbon` : **tout signalement y est un faux positif.**

| | Avant correction | Après correction |
|---|---|---|
| Paquets analysés | 58 (2 plantages du pool parallèle de Rector) | 60 |
| Fichiers refusés | 395 | 0 |
| Signalements (lignes) | 3 577 | 0 |
| Faux positifs | 3 577 (**100 %**) | 0 |
| Dus à un cas non prévu | 3 577 (un seul cas) | — |

**Taux de faux positifs : 0 signalement sur 3 320 fichiers tiers (avant correction : 100 %,
3 577 signalements).**

Le cas non prévu : **toute expression de type `false`**. Le littéral `false` en premier (valeurs
par défaut `bool $x = false`, `return false;`, `$found = false;`), mais aussi `instanceof`, `!$x`
ou comparaisons dont PHPStan sait qu'elles valent `false`, et les variables `false|null`.
`isObjectType()` de Rector retire `false` du type avant de comparer (pour les « falsy
nullables ») ; il reste `never`, sous-type de toutes les classes, donc accepté comme
calculateur simplifié. La règle exige maintenant un type au moins possiblement objet
(`Simplified|false` reste reconnu). Le fixture `skip_false_typed_expressions.php.inc` verrouille
ce cas.

Limite de la mesure : ce corpus ne contient aucun usage du calculateur. Il ne mesure que les faux
positifs, pas les cas légitimes manqués ; ceux-là sont couverts par les fixtures.
