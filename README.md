# llm-carbon-php

Petit script PHP qui estime l'énergie consommée et les émissions de CO2eq
d'une requête à un modèle de langage (LLM), à partir de valeurs d'entrée
codées en dur (modèle, nombre de paramètres actifs, nombre de tokens
générés).

## Utilisation

```bash
composer install
php -S localhost:8000 -t public
```

Puis ouvrir http://localhost:8000 dans un navigateur. `composer install` ne télécharge aucune dépendance : il génère uniquement l'autoloader PSR-4 de l'espace de noms `LlmCarbon\`.

## Méthodologie

Le calcul suit la méthodologie [EcoLogits
v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md),
qui propose deux niveaux de modélisation. Les deux coexistent dans ce
dépôt (`FootprintCalculatorSimplifie` et `FootprintCalculatorComplet`) pour
pouvoir comparer leurs résultats.

**Modèle simplifié** (`src/FootprintCalculatorSimplifie.php`) :

1. **Énergie GPU par token** : régression linéaire entre le nombre de
   paramètres actifs du modèle (en milliards) et l'énergie consommée par
   token généré, `énergie = α × paramètres + β`.
2. **Énergie totale** : l'énergie par token est multipliée par le nombre de
   tokens générés, puis par le PUE (Power Usage Effectiveness) d'un
   datacenter hyperscale ou d'un supercalculateur retenu par EcoLogits v0.4.0
   (1,2), pour tenir compte de l'énergie annexe (refroidissement,
   infrastructure) — voir « Limites » ci-dessous : ce n'est pas une moyenne
   sectorielle.
3. **Émissions de CO2eq** : l'énergie totale (convertie en kWh) est
   multipliée par le facteur d'émission du mix électrique de la zone
   d'hébergement du datacenter.

**Modèle complet** (`src/FootprintCalculatorComplet.php`) : reprend la même
régression pour l'énergie GPU, mais l'intègre dans un calcul plus détaillé qui
tient aussi compte du matériel réellement nécessaire pour héberger le modèle :

1. **Mémoire requise** à partir des paramètres TOTAUX du modèle (et non des
   seuls paramètres actifs) et d'une hypothèse de quantification (4 bits par
   défaut, faute de publication du fournisseur), avec une surcharge de ×1,2.
2. **Nombre de cartes GPU** nécessaires pour charger le modèle (mémoire
   requise divisée par la mémoire d'une carte de référence — 80 Go — arrondie
   au plafond).
3. **Durée de génération**, régression sur les paramètres actifs.
4. **Énergie du serveur hors GPU**, proportionnelle à la durée et au nombre de
   cartes utilisées (sur les 8 cartes que compte le serveur de référence).
5. **Énergie GPU**, comme dans le modèle simplifié mais multipliée par le
   nombre de cartes requises.
6. **Énergie totale et émissions**, comme dans le modèle simplifié, à partir
   de la somme des deux énergies précédentes.

Le modèle complet donne un résultat plus élevé que le modèle simplifié dès
qu'un modèle nécessite plusieurs cartes GPU (l'énergie GPU est alors comptée
plusieurs fois) ; pour un modèle dense tenant sur une seule carte, l'écart
vient uniquement de l'énergie du serveur hors GPU, absente du modèle
simplifié. `src/EcartCalculator.php` décompose cet écart en deux parts qui
s'additionnent exactement (aucun résidu) : la part « serveur » (le terme
absent du modèle simplifié) et la part « cartes » (le supplément dû à compter
l'énergie GPU plusieurs fois). La page affiche les deux résultats côte à côte
ainsi que l'écart total et sa décomposition.

Le détail chiffré de chaque étape des deux modèles est affiché sur la page,
sous les résultats, ainsi que deux tableaux comparatifs à égalité des autres
paramètres : par zone d'hébergement (France, Europe, États-Unis, Monde) et par
modèle du catalogue (Llama 3.1 70B, GPT-4, GPT-4o, Qwen3-235B-A22B) — ce
second tableau affiche aussi le nombre de cartes GPU requises et l'écart
(total, dont serveur, dont cartes) par modèle. Le facteur d'émission français a
une double attribution : [Base Empreinte de
l'ADEME](https://base-empreinte.ademe.fr/) et [electricity_mixes.csv
d'EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
qui publient la même valeur. Les trois autres zones viennent du [jeu de
données électriques de
Boavizta](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
sourcé ADEME Base IMPACTS® (données 2011).

## Provenance des valeurs

Chaque `EmissionFactor` porte une `Provenance` (`src/Provenance.php`), et
chaque `LanguageModel` en porte deux — une pour ses paramètres actifs, une
pour ses paramètres totaux, qui peuvent avoir des origines différentes (voir
GPT-4 ci-dessous). Une `Provenance` est obligatoire à la construction : type
(`MesureeEtPubliee` ou `Hypothese`), URL de la source, millésime ou date de
consultation, et une note qui dit ce que la source affirme exactement.
Impossible de créer l'une de ces deux classes sans provenance complète — le
constructeur l'exige. Pour un modèle dense (paramètres actifs = paramètres
totaux, comme Llama 3.1 70B), la fabrique `LanguageModel::dense()` pose cette
égalité une seule fois plutôt que de répéter la valeur et sa provenance.

La plupart des valeurs du catalogue sont mesurées et publiées par leur source
(badge vert « ✓ Mesuré et publié »), y compris **Qwen3-235B-A22B** (Alibaba),
un modèle ouvert dont l'équipe Qwen publie officiellement les 235 milliards de
paramètres totaux et les 22 milliards de paramètres activés par token. Les
deux exceptions concernent les modèles OpenAI (badge orange « ⚠ Hypothèse »),
dont l'architecture n'est jamais publiée :

- **GPT-4** — paramètres actifs (176 milliards retenus) : suit la méthode
  d'EcoLogits pour les modèles propriétaires — à partir d'une architecture
  Mixture-of-Experts ayant fuité (~1,8 billion de paramètres au total) et d'un
  ratio d'activation MoE typique de 10 % à 30 %, EcoLogits estime une
  fourchette de 176 à 528 milliards de paramètres actifs (x3 exactement) ; ce
  projet retient la borne basse, la plus conservatrice. La borne haute
  multiplierait l'énergie par token par environ 2,8 dans la seule régression
  EcoLogits — et l'énergie **totale** du modèle complet, elle, par environ
  2,81 seulement (voir l'avertissement ci-dessous). Paramètres totaux
  (1 800 milliards retenus) : la fuite évoquée ci-dessus porte directement sur
  ce chiffre ; il n'existe pas de borne haute distincte publiée.
- **GPT-4o** — paramètres actifs (44 milliards retenus) et totaux
  (440 milliards) : EcoLogits estime ce modèle à très exactement un quart des
  paramètres qu'il retient pour GPT-4 (1760/4, 176/4, 528/4), sans publier de
  raisonnement indépendant pour ce ratio précis au-delà de son propre jeu de
  données. Ce projet retient là aussi la borne basse (44 milliards) des
  paramètres actifs ; la borne haute (132 milliards, x3 exactement) multiplie
  l'énergie par token de la régression par environ 2,47, et l'énergie
  **totale** du modèle complet par environ 2,40 seulement.

**Une fourchette d'entrée n'est pas une fourchette de sortie.** Les deux
exemples ci-dessus (x3 en paramètres actifs → x2,8/x2,5 sur la régression
seule → x2,81/x2,40 sur l'énergie totale du modèle complet) illustrent que ces
trois ratios ne coïncident jamais : la régression EcoLogits est affine (elle a
un terme constant β qui ne varie pas avec les paramètres, donc rien n'y est
strictement proportionnel), et le modèle complet en combine deux (durée,
énergie GPU) sous un même PUE, ce qui produit encore un troisième ratio. Voir
le docblock de `src/FootprintCalculatorComplet.php` et les tests dédiés de
`tests/FootprintCalculatorCompletTest.php`.

Voir [la méthodologie EcoLogits pour les modèles
propriétaires](https://ecologits.ai/latest/methodology/proprietary_models/)
et le [jeu de données des modèles
d'EcoLogits 0.11.1](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

## Limites

- **Valeurs d'entrée codées en dur** : le modèle, le nombre de paramètres et
  le nombre de tokens ne sont pas configurables dynamiquement.
- **Estimation, pas une mesure** : la régression EcoLogits est une
  approximation empirique, pas une mesure directe de la consommation réelle
  du GPU pour une requête donnée.
- **Énergie GPU uniquement (modèle simplifié) ou GPU + serveur hors GPU
  (modèle complet)** : dans les deux cas, le calcul ne couvre pas l'énergie
  liée au stockage, ni au réseau ou à l'entraînement du modèle — seule
  l'inférence est estimée.
- **Quantification supposée, pas publiée** : le modèle complet suppose une
  quantification par défaut de 4 bits par paramètre (faute d'information
  publiée par les fournisseurs sur la quantification réellement utilisée en
  production), qui détermine la mémoire GPU requise et donc le nombre de
  cartes.
- **PUE unique appliqué à tous les modèles, pas une moyenne sectorielle** :
  1,2 est la valeur qu'EcoLogits v0.4.0 retient sans la ventiler par
  fournisseur ; les versions plus récentes d'EcoLogits (non retenues dans ce
  projet) montrent que 1,2 correspond spécifiquement à OpenAI/Azure
  (≈1,09 pour Anthropic/Cohere/Google, 1,09-1,14 pour HuggingFace, 1,16 pour
  Mistral). Ce projet applique donc 1,2 uniformément, y compris à Llama 3.1
  70B, qui n'est pas hébergé par OpenAI.
- **Zone d'hébergement non détectée automatiquement** : le tableau compare
  plusieurs zones (France, Europe, États-Unis, Monde), mais ne sait pas
  quelle est la localisation réelle du datacenter qui a traité la requête.
- **Pas de prise en compte du contexte** (tokens en entrée, taille du
  prompt), seul le nombre de tokens générés est utilisé.
- **Paramètres de GPT-4 et GPT-4o non publiés** : ces valeurs sont des
  hypothèses (voir « Provenance des valeurs » ci-dessus), pas des mesures —
  les résultats affichés pour ces deux modèles héritent de cette incertitude.
- **Le modèle complet ne doit pas paraître plus fiable que ses entrées** :
  le nombre de cartes GPU (`cartesGpu`) est déterminé **uniquement** par les
  paramètres TOTAUX et par la quantification supposée — aucune autre donnée
  ne le corrobore. Pour GPT-4o, ce sont donc les 440 milliards de paramètres
  totaux *supposés* (une hypothèse, pas une mesure) qui fixent à eux seuls ce
  nombre de cartes, et par ricochet une bonne part de l'énergie totale du
  modèle complet. La page reflète ça visuellement : `cartesGpu`, l'énergie et
  les émissions du modèle complet portent le badge « ⚠ Hypothèse » dès que
  l'une de leurs deux entrées (actifs ou totaux) en est une — ils ne portent
  jamais le badge « ✓ Mesuré et publié » si le modèle simplifié, sur la même
  ligne, ne le porte pas déjà lui-même.
