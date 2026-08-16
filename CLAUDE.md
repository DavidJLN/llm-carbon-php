# CLAUDE.md

Ce fichier fournit des indications à Claude Code (claude.ai/code) pour travailler dans ce dépôt.

## Ce que fait ce dépôt

`llm-carbon-php` estime l'énergie consommée et les émissions de CO2eq d'une requête à un modèle de langage (LLM). Le calcul suit la méthodologie [EcoLogits](https://ecologits.ai/latest/methodology/energy/), à partir d'un scénario codé en dur (modèle, paramètres actifs, tokens générés). Le résultat est affiché sous forme d'une page HTML unique, avec le détail chiffré du calcul et un tableau comparant les émissions selon la zone d'hébergement du datacenter.

## Environnement technique

- **PHP** : `>= 8.1` (déclaré dans `composer.json`), requis notamment pour les propriétés
  `readonly` utilisées dans les classes de `src/`.
- **Composer** : présent, mais uniquement pour l'autoloading. `composer.json` ne déclare aucune dépendance d'exécution — juste la contrainte de version PHP et le mapping PSR-4. Lancer `composer install` génère `vendor/autoload.php` sans rien télécharger.
- **Espace de noms** : `LlmCarbon\`, mappé vers `src/` via PSR-4.
- **Dépendances** : aucune, ni en production ni en développement (pas de PHPUnit, pas de
  bibliothèque JS/CSS externe — le CSS reste inline dans `public/index.php`).

## Commandes disponibles

- `composer dump-autoload` — à effectuer après toute création de classe.
- `php -S localhost:8000 -t public` — lance le serveur de développement intégré de PHP et sert le dossier `public/` ; ouvrir ensuite http://localhost:8000 dans un navigateur.

Aucune autre commande n'est configurée dans ce dépôt (pas de build, pas de linter, pas de suite de tests).

## Architecture générale

- `composer.json` — déclare le nom du paquet, la licence MIT, la contrainte `php >= 8.1` et
  l'autoload PSR-4 (`LlmCarbon\` → `src/`).
- `public/index.php` — point d'entrée HTTP : instancie le scénario (`LanguageModel`,
  `EmissionFactor`), appelle `FootprintCalculator`, puis affiche les résultats et le tableau comparatif. Ne contient plus aucun calcul.
- `src/LanguageModel.php` — objet-valeur `readonly` : nom du modèle, paramètres actifs (en milliards), URL de la source des paramètres.
- `src/EmissionFactor.php` — objet-valeur `readonly` : zone géographique, facteur d'émission (gCO2eq/kWh), URL de la source ; expose les fabriques statiques `france()`, `europe()`, `etatsUnis()`, `monde()`.
- `src/Footprint.php` — objet-valeur `readonly` : résultat du calcul (énergie par token, énergie totale, émissions).
- `src/FootprintCalculator.php` — seule classe portant les coefficients de la méthodologie EcoLogits (alpha, beta, PUE) ; sa méthode `calculate()` combine un `LanguageModel` et un `EmissionFactor` en un `Footprint`.
- `README.md` — documente l'usage, la méthodologie EcoLogits pas à pas, les sources et les limites connues du calcul.
- `.gitignore` — exclut les artefacts non versionnés (`vendor/`, fichiers Composer, `.env`, logs, caches d'outils, fichiers d'IDE).

## Règle primordiale : aucun chiffre sans source vérifiable

Toute constante numérique utilisée dans le calcul (paramètres de la régression EcoLogits, PUE, facteurs d'émission par zone, paramètres actifs d'un modèle, etc.) **doit** être accompagnée, dans le code, d'un commentaire ou docblock citant sa source précise (URL, document, méthodologie). C'est déjà le cas pour toutes les constantes actuelles (`FootprintCalculator`, fabriques d'`EmissionFactor`, instanciation de `LanguageModel` dans `public/index.php`) — ce standard doit être maintenu pour toute
constante ajoutée ou modifiée, y compris pour tout nouveau modèle ou toute nouvelle zone géographique.

**Conséquences si cette règle n'est pas respectée :**
- Un chiffre affiché sans source vérifiable rend l'estimation invérifiable et donc non crédible : l'utilisateur ne peut plus distinguer une donnée issue d'une méthodologie reconnue d'un chiffre inventé ou approximatif.
- Cela expose le projet à diffuser une désinformation chiffrée sur l'impact environnemental des LLM, un sujet sensible où l'exactitude et la traçabilité des sources sont la seule légitimité du projet.
- Une constante non sourcée ne peut pas être mise à jour correctement si la méthodologie évolue, car son origine est perdue.

En conséquence : ne jamais ajouter, modifier ou approximer une constante numérique liée au calcul sans citer sa source exacte, et ne jamais remplacer une source par une valeur « raisonnable » ou estimée à la main.

## Interdits absolus

- **Aucune dépendance externe** : Composer sert uniquement à l'autoloading PSR-4. Ne jamais ajouter de paquet en `require` (production) ou `require-dev` (y compris PHPUnit), ni de bibliothèque JS/CSS externe (CDN compris). Le projet doit rester exécutable sans rien télécharger d'autre que sa propre autoload.
