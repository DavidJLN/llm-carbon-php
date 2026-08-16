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
résultats, ainsi qu'un tableau comparant les émissions obtenues, à énergie
identique, selon la zone d'hébergement (France, Europe, États-Unis, Monde).
Le facteur d'émission français vient de la
[Base Empreinte de l'ADEME](https://base-empreinte.ademe.fr/) ; les trois
autres viennent du [jeu de données électriques de
Boavizta](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
sourcé ADEME Base IMPACTS® (données 2011).

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
