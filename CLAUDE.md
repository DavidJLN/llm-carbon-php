# CLAUDE.md

Ce fichier fournit des indications à Claude Code (claude.ai/code) pour travailler dans ce dépôt.

## Ce que fait ce dépôt

`llm-carbon-php` estime l'énergie consommée et les émissions de CO2eq d'une requête à un modèle de langage (LLM). Le calcul suit la méthodologie [EcoLogits](https://ecologits.ai/latest/methodology/energy/), à partir d'un scénario codé en dur (modèle, paramètres actifs, tokens générés). Le résultat est affiché sous forme d'une page HTML unique, avec le détail chiffré du calcul et un tableau comparant les émissions selon la zone d'hébergement du datacenter.

## Environnement technique

- **PHP** : `>= 8.4` (déclaré dans `composer.json`), requis notamment pour les propriétés
  `readonly` utilisées dans les classes de `src/`.
- **Composer** : présent, mais uniquement pour l'autoloading. `composer.json` ne déclare aucune dépendance d'exécution — juste la contrainte de version PHP et le mapping PSR-4. Lancer `composer install` génère `vendor/autoload.php` sans rien télécharger.
- **Espace de noms** : `LlmCarbon\`, mappé vers `src/` via PSR-4.
- **Dépendances** : aucune, ni en production ni en développement (pas de PHPUnit, pas de
  bibliothèque JS/CSS externe — le CSS reste inline dans `public/index.php`).

## Commandes disponibles

- `composer dump-autoload` — à effectuer après toute création de classe.
- `vendor/bin/phpunit tests` - à effectuer avant de déclarer qu'une modification est finie
- `php -S localhost:8000 -t public` — lance le serveur de développement intégré de PHP et sert le dossier `public/` ; ouvrir ensuite http://localhost:8000 dans un navigateur.

Aucune autre commande n'est configurée dans ce dépôt (pas de build, pas de linter, pas de suite de tests).

## Architecture générale

- `composer.json` — déclare le nom du paquet, la licence MIT, la contrainte `php >= 8.4` et
  l'autoload PSR-4 (`LlmCarbon\` → `src/`).
- `public/index.php` — point d'entrée HTTP : instancie le scénario (`LanguageModel`,
  `EmissionFactor`), appelle `FootprintCalculator`, puis affiche les résultats, le tableau
  comparatif par zone et par modèle, et le détail de chaque provenance en pied de page. Ne
  contient plus aucun calcul. Affiche un badge visuel (`provenanceBadge()`) distinguant une
  provenance `Hypothesis` d'une provenance `MeasuredAndPublished` partout où une valeur est montrée.
- `src/ProvenanceType.php` — enum : nature d'une provenance, `MeasuredAndPublished` (la source publie la
  valeur telle quelle) ou `Hypothesis` (valeur reconstituée faute de donnée publiée).
- `src/Provenance.php` — objet-valeur `readonly` portant la provenance d'une valeur numérique :
  son `type` (`ProvenanceType`), l'`url` de la source, le `yearOrConsultationDate` et une
  `note` disant ce que la source affirme exactement. Les quatre champs sont obligatoires et non
  nullables (le constructeur lève si l'un d'eux est vide) : une valeur ne peut pas être construite
  sans provenance complète.
- `src/LanguageModel.php` — objet-valeur `readonly` : nom du modèle, paramètres actifs (en
  milliards), `Provenance` ; expose les fabriques statiques `llama31_70b()`, `gpt4()` (modèle
  propriétaire, provenance de type `Hypothesis`), et `all()`.
- `src/EmissionFactor.php` — objet-valeur `readonly` : zone géographique, facteur d'émission
  (gCO2eq/kWh), `Provenance` ; expose les fabriques statiques `france()`, `europe()`,
  `unitedStates()`, `world()`, et `all()`.
- `src/Footprint.php` — objet-valeur `readonly` : résultat du calcul (énergie par token, énergie totale, émissions).
- `src/FootprintCalculator.php` — seule classe portant les coefficients de la méthodologie EcoLogits (alpha, beta, PUE) ; sa méthode `calculate()` combine un `LanguageModel` et un `EmissionFactor` en un `Footprint`.
- `README.md` — documente l'usage, la méthodologie EcoLogits pas à pas, les sources et les limites connues du calcul.
- `.gitignore` — exclut les artefacts non versionnés (`vendor/`, fichiers Composer, `.env`, logs, caches d'outils, fichiers d'IDE).

## Règle primordiale : aucun chiffre sans source vérifiable

Toute constante numérique utilisée dans le calcul (paramètres de la régression EcoLogits, PUE, facteurs d'émission par zone, paramètres actifs d'un modèle, etc.) **doit** citer sa source précise (URL, document, méthodologie), son millésime ou sa date de consultation, et une note disant ce que la source affirme exactement.

Pour `EmissionFactor` et `LanguageModel`, cette règle n'est plus seulement une convention de
commentaire : elle est portée par le type. Les deux classes exigent un objet `Provenance`
(`src/Provenance.php`) en paramètre obligatoire, non nullable, de leur constructeur — il est donc
impossible de compiler une instance sans provenance. `Provenance` porte quatre champs
obligatoires : `type` (`ProvenanceType::MeasuredAndPublished` ou `ProvenanceType::Hypothesis`), `url`,
`yearOrConsultationDate`, `note`. **Ne jamais introduire un second champ de source** (par
exemple un `urlSource` séparé) à côté de `Provenance` sur ces deux classes : deux sources de
vérité pour la même chose finissent toujours par diverger — une seule provenance par valeur.

Pour les coefficients privés de `FootprintCalculator` (alpha, beta, PUE), qui ne sont pas des
objets-valeurs construits depuis l'extérieur, le commentaire/docblock citant la source reste la
convention à suivre.

**Mesure ou hypothèse — jamais l'inverse :**
- `ProvenanceType::MeasuredAndPublished` : la source publie la valeur telle quelle ; la note rappelle
  ce qu'elle affirme.
- `ProvenanceType::Hypothesis` : la valeur n'est pas publiée par son propriétaire (typiquement un
  modèle propriétaire dont les paramètres actifs sont secrets) et a été reconstituée à partir
  d'indices indirects. Dans ce cas, la note **doit** expliquer pourquoi la donnée n'est pas
  publiée et ce que vaudrait la borne haute de l'estimation (voir `LanguageModel::gpt4()` pour un
  exemple).
- L'affichage (`public/index.php`, fonction `provenanceBadge()`) **doit** distinguer visuellement
  une hypothèse d'une mesure partout où la valeur apparaît (badge « ⚠ Hypothèse » vs « ✓ Mesuré et
  publié »). Une hypothèse qui se présente visuellement comme une mesure est le défaut à corriger
  en priorité si on le rencontre.

**Conséquences si cette règle n'est pas respectée :**
- Un chiffre affiché sans source vérifiable rend l'estimation invérifiable et donc non crédible : l'utilisateur ne peut plus distinguer une donnée issue d'une méthodologie reconnue d'un chiffre inventé ou approximatif.
- Cela expose le projet à diffuser une désinformation chiffrée sur l'impact environnemental des LLM, un sujet sensible où l'exactitude et la traçabilité des sources sont la seule légitimité du projet.
- Une constante non sourcée ne peut pas être mise à jour correctement si la méthodologie évolue, car son origine est perdue.
- Une hypothèse affichée comme une mesure trompe l'utilisateur sur la fiabilité du chiffre qu'il lit.

En conséquence : ne jamais ajouter, modifier ou approximer une constante numérique liée au calcul sans citer sa source exacte, et ne jamais remplacer une source par une valeur « raisonnable » ou estimée à la main — sauf hypothèse explicitement typée `ProvenanceType::Hypothesis`, justifiée et bornée comme décrit ci-dessus.

## Interdits absolus

- **Aucune dépendance externe** : Composer sert uniquement à l'autoloading PSR-4. Ne jamais ajouter de paquet en `require` (production) ou `require-dev` (y compris PHPUnit), ni de bibliothèque JS/CSS externe (CDN compris). Le projet doit rester exécutable sans rien télécharger d'autre que sa propre autoload.
