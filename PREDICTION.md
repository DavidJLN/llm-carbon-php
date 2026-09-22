# Prédiction

Nombre de mutants : 20
Score de mutants : 75 %
Trous :
    - DifferenceCalculatorTest -> non prise en compte de toutes les zones de test et de tous les modèles
    - FootprintCalculatorFullTest et FootprintCalculatorSimplifiedTest -> non prise en compte de tous les modèles (uniquement llama)

## Écart entre la prédiction et la première exécution réelle

Première exécution complète d'Infection (voir `mutation-testing/RAPPORT.md` pour le détail) :
**238 mutants générés, 110 tués, 128 échappés, MSI (code couvert) 46 %.**

L'écart est important et porte à la fois sur le volume et sur la localisation des trous :

- **Volume sous-estimé d'un ordre de grandeur** : 238 mutants générés contre 20 prédits (~12×
  plus), et un score effectif de 46 % contre 75 % prédit — soit un écart de 29 points, dans le
  sens le plus défavorable.
- **Les trous prédits ne se sont pas matérialisés.** `DifferenceCalculator.php`,
  `FootprintCalculatorFull.php`, `FootprintCalculatorSimplified.php`, `FootprintFull.php` et
  `Provenance.php` ont chacun un score de mutation de **100 %** dès la première exécution : la
  couverture par zone et par modèle anticipée comme manquante dans
  `DifferenceCalculatorTest`/`FootprintCalculatorFullTest`/`FootprintCalculatorSimplifiedTest`
  était en réalité déjà suffisante.
- **Le vrai trou n'avait pas été anticipé du tout** : 100 % des 128 mutants échappés se
  trouvaient dans `EmissionFactor.php` et `LanguageModel.php`, presque tous (126/128) sur les
  mutateurs `Concat`/`ConcatOperandRemoval` appliqués aux concaténations de chaînes qui
  construisent le champ `note` d'un `Provenance` — un champ que la règle primordiale du projet
  (voir `CLAUDE.md`) rend obligatoire mais que les tests ne vérifiaient, au mieux, que par
  sous-chaîne (`assertStringContainsString`), jamais par égalité exacte : un réordonnancement ou
  une suppression de fragment de texte source passait donc inaperçu. Les 2 mutants restants
  provenaient d'une garde redondante dans le constructeur de `LanguageModel` (code mort).
- **Conclusion pour la suite** : une prédiction de trous de test basée sur une lecture des noms
  de classes de test (« quels modèles/zones sont couverts ? ») a manqué la vraie surface de
  risque de ce projet — le contenu texte exact des `Provenance::$note`, qui est justement la
  donnée que la règle primordiale du dépôt (« aucun chiffre sans source vérifiable ») exige de
  protéger. Après correction (voir `mutation-testing/RAPPORT.md`), le MSI (code couvert) est
  passé à **97 %** ; les 5 mutants restants sont classés « détail d'implémentation » et justifiés
  par écrit dans `src/LanguageModel.php`.
