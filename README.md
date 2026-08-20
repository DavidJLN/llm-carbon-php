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

Le calcul suit la méthodologie [EcoLogits](https://ecologits.ai/latest/methodology/energy/) :

1. **Énergie GPU par token** : régression linéaire entre le nombre de
   paramètres actifs du modèle (en milliards) et l'énergie consommée par
   token généré, `énergie = α × paramètres + β`.
2. **Énergie totale** : l'énergie par token est multipliée par le nombre de
   tokens générés, puis par le PUE (Power Usage Effectiveness) moyen d'un
   datacenter, pour tenir compte de l'énergie annexe (refroidissement,
   infrastructure).
3. **Émissions de CO2eq** : l'énergie totale (convertie en kWh) est
   multipliée par le facteur d'émission du mix électrique de la zone
   d'hébergement du datacenter.

Le détail chiffré de chaque étape est affiché sur la page, sous les
résultats, ainsi que deux tableaux comparatifs à égalité des autres
paramètres : par zone d'hébergement (France, Europe, États-Unis, Monde) et par
modèle du catalogue (Llama 3.1 70B, GPT-4). Le facteur d'émission français a
une double attribution : [Base Empreinte de
l'ADEME](https://base-empreinte.ademe.fr/) et [electricity_mixes.csv
d'EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
qui publient la même valeur. Les trois autres zones viennent du [jeu de
données électriques de
Boavizta](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
sourcé ADEME Base IMPACTS® (données 2011).

## Provenance des valeurs

Chaque `EmissionFactor` et chaque `LanguageModel` porte une `Provenance`
(`src/Provenance.php`), obligatoire à la construction : type (`MesureeEtPubliee`
ou `Hypothese`), URL de la source, millésime ou date de consultation, et une
note qui dit ce que la source affirme exactement. Impossible de créer l'une de
ces deux classes sans provenance complète — le constructeur l'exige.

La plupart des valeurs du catalogue sont mesurées et publiées par leur source
(badge vert « ✓ Mesuré et publié »). Une seule est une hypothèse (badge orange
« ⚠ Hypothèse ») : **GPT-4**, dont OpenAI n'a jamais publié le nombre de
paramètres. La valeur retenue (176 milliards de paramètres actifs) suit la
méthode d'EcoLogits pour les modèles propriétaires : à partir d'une
architecture Mixture-of-Experts ayant fuité (~1,8 billion de paramètres au
total) et d'un ratio d'activation MoE typique de 10 % à 30 %, EcoLogits estime
une fourchette de 176 à 528 milliards de paramètres actifs ; ce projet retient
la borne basse, la plus conservatrice. La borne haute (528 milliards)
multiplierait l'énergie par token par environ 2,8 dans la régression EcoLogits.
Voir [la méthodologie EcoLogits pour les modèles
propriétaires](https://ecologits.ai/latest/methodology/proprietary_models/).

## Limites

- **Valeurs d'entrée codées en dur** : le modèle, le nombre de paramètres et
  le nombre de tokens ne sont pas configurables dynamiquement.
- **Estimation, pas une mesure** : la régression EcoLogits est une
  approximation empirique, pas une mesure directe de la consommation réelle
  du GPU pour une requête donnée.
- **Énergie GPU uniquement** : le calcul ne couvre pas l'énergie liée au
  CPU, à la RAM, au stockage, ni au réseau ou à l'entraînement du modèle.
- **Zone d'hébergement non détectée automatiquement** : le tableau compare
  plusieurs zones (France, Europe, États-Unis, Monde), mais ne sait pas
  quelle est la localisation réelle du datacenter qui a traité la requête.
- **Pas de prise en compte du contexte** (tokens en entrée, taille du
  prompt), seul le nombre de tokens générés est utilisé.
- **Paramètres actifs de GPT-4 non publiés** : cette valeur est une hypothèse
  (voir « Provenance des valeurs » ci-dessus), pas une mesure — le résultat
  affiché pour ce modèle hérite de cette incertitude.
