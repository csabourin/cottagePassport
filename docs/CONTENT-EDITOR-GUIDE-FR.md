# Stamp Passport — Guide éditeur (version française)

Ce document s’adresse aux équipes éditoriales et aux responsables de campagne. Son objectif est de vous aider à comprendre, sans jargon technique inutile, les choix de conception de l’interface d’administration et la logique des principales fonctionnalités.

## Pourquoi ce plugin existe

Stamp Passport permet de piloter des parcours de visite fondés sur des codes de type **Quick Response (QR)** : les visiteurs ouvrent une page “passport”, scannent des emplacements, cumulent des tampons et débloquent ensuite des récompenses ou des formulaires selon les règles de campagne.

Le principe clé est simple : l’équipe de contenu se concentre sur les textes, les visuels et la cohérence multilingue, tandis que la mécanique de collecte, de validation et de synchronisation est gérée par le plugin.

## La logique du Panneau de contrôle (CP)

L’interface a été pensée pour réduire les tâches répétitives et limiter les erreurs de publication.

D’abord, un lieu n’est défini qu’une seule fois côté “identité” (code court, image, coordonnées, activation), puis enrichi avec un contenu par site/langue. Cela évite les doublons et facilite la maintenance quand une campagne vit sur plusieurs sites.

Ensuite, le plugin privilégie les sélecteurs d’éléments Craft (entrées et médias) plutôt que des URL saisies à la main. En pratique, cette approche renforce la robustesse éditoriale : on référence des éléments internes, plus stables dans le temps, au lieu de multiplier des liens fragiles.

La section **Display Text** a aussi été isolée pour clarifier les responsabilités. On y gère les libellés d’interface et les messages affichés aux visiteurs, site par site, avec des valeurs de secours lorsque certains champs restent vides. Résultat : la localisation est plus rapide et le risque d’oubli diminue.

Enfin, la navigation par onglets dans les réglages n’est pas cosmétique : elle répond à une augmentation du volume de paramètres. En séparant les sujets (général, apparence, intégrations, avancé), la lecture est plus fluide et la configuration plus sûre.

## Les écrans importants pour l’éditorial

### Items

L’écran des items sert à construire la base du parcours. Vous y gérez l’activation des lieux, leurs coordonnées, leur image principale et, pour chaque site, le titre, la description, le texte d’appel à l’action et l’éventuelle entrée liée.

Cet écran est au cœur de la qualité de campagne : c’est là que se joue l’équilibre entre précision pratique (coordonnées, activation) et qualité narrative (promesse, ton, clarté des appels à l’action).

### Display Text

Cette section centralise les textes d’interface visibles par le public : intitulés, messages de modales et textes d’accompagnement. Dans un contexte multilingue, elle est particulièrement utile pour harmoniser le ton entre sites tout en gardant la possibilité d’adaptations locales.

### Contest Rules

Les règles de concours sont volontairement séparées des autres textes. Cette distinction facilite la collaboration entre éditorial, marketing et validation légale, avec des mises à jour plus ciblées.

### Settings

Les réglages couvrent la route d’accès au passport, les contraintes de géolocalisation, les seuils de récompense, l’identité visuelle, l’intégration analytique et les options de comportement d’interface (par exemple l’affichage du sélecteur de langue).

L’idée générale : permettre des ajustements de campagne sans intervention de développement dans la plupart des cas.

### Dashboard (tableau de bord)

Le tableau de bord met en avant des indicateurs actionnables : volumes de scans, visiteurs, qualification aux paliers de récompense, lieu le plus visité, tendances récentes et filtres temporels.

Il ne s’agit pas d’un simple écran statistique : c’est un outil de pilotage éditorial et opérationnel, utile pour identifier les lieux qui performent, ceux qui stagnent, et les moments où ajuster contenus ou mise en avant.

## Gestion multilingue : le changement de site/langue

Le plugin prend en charge des contextes multi-sites, avec possibilité de routes par site. Le changement de site dans l’administration a été amélioré pour accélérer les allers-retours de traduction et de vérification.

Concrètement, vous pouvez passer d’une langue à l’autre pour contrôler la cohérence globale sans repartir de zéro à chaque écran.

## Ce que l’historique récent laisse comprendre

Les évolutions récentes montrent une direction claire : renforcer l’efficacité éditoriale en campagne active.

Le tableau de bord a été introduit puis affiné à plusieurs reprises (lisibilité, filtres de dates, organisation des cartes, libellés). Le support multi-site a progressé avec des routes dédiées par site. L’expérience de changement de site a été fluidifiée. Les réglages ont été structurés en onglets pour améliorer la navigation. Enfin, la modélisation de contenu a été consolidée en séparant certains types d’information.

Autrement dit, la trajectoire produit vise à rendre la gestion multilingue plus rapide, plus fiable et plus mesurable directement dans Craft.

## Routine recommandée avant et pendant campagne

Avant lancement, vérifiez les paramètres globaux, les items, les textes par langue et la qualité des liens vers les entrées/médias. Faites ensuite un test réel de scan sur mobile pour chaque langue prioritaire.

Pendant campagne, consultez régulièrement le tableau de bord pour détecter les écarts de performance entre lieux et ajuster le contenu en conséquence.

## Questions de cadrage à trancher

Souhaitez-vous ajouter à ce guide une mini-section d’interprétation des indicateurs (exemples concrets de lecture du tableau de bord) ?

Faut-il formaliser une règle éditoriale simple pour l’usage des entrées liées (quand privilégier une entrée Craft versus un texte d’appel plus générique) ?

Voulez-vous intégrer une checklist qualité multilingue, à utiliser systématiquement avant publication ?

Souhaitez-vous documenter explicitement la frontière “éditeur vs administrateur” pour éviter les changements sensibles au mauvais niveau ?
