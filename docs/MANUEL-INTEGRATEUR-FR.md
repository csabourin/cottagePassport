# Manuel de l'intégrateur — Stamp Passport

Ce manuel s'adresse aux personnes chargées d'intégrer le passeport dans les gabarits Craft CMS : développeurs, intégrateurs web ou toute personne qui travaille avec les fichiers Twig du site.

---

## Table des matières

1. [Ce que fait ce plugin côté gabarit](#1-ce-que-fait-ce-plugin-côté-gabarit)
2. [Utiliser le plugin sans modifier les gabarits](#2-utiliser-le-plugin-sans-modifier-les-gabarits)
3. [Accéder aux données depuis vos gabarits](#3-accéder-aux-données-depuis-vos-gabarits)
4. [Les emplacements](#4-les-emplacements)
5. [Les paramètres du plugin](#5-les-paramètres-du-plugin)
6. [Les textes d'interface](#6-les-textes-dinterface)
7. [Les images](#7-les-images)
8. [Les URL des APIs](#8-les-url-des-apis)
9. [Nettoyer le HTML des éditeurs](#9-nettoyer-le-html-des-éditeurs)
10. [Remplacer le gabarit par défaut](#10-remplacer-le-gabarit-par-défaut)
11. [Structure du gabarit par défaut](#11-structure-du-gabarit-par-défaut)
12. [Référence complète des variables Twig](#12-référence-complète-des-variables-twig)

---

## 1. Ce que fait ce plugin côté gabarit

Stamp Passport fournit :

- Une **page publique** accessible à l'URL configurée (ex. : `/passport`), gérée automatiquement par le plugin.
- Un **objet Twig** (`craft.stampPassport`) pour accéder aux données depuis n'importe quel gabarit.
- Des **points d'API** (URLs) pour le JavaScript de scan QR et de synchronisation de progression.

> 📸 **Image à insérer :** Schéma simplifié montrant le lien entre le panneau d'administration (contenu), les gabarits Twig (affichage) et le navigateur du visiteur (page publique).

![Schéma du flux de données entre l'administration, les gabarits et la page publique](images/integration-schema.png)

---

## 2. Utiliser le plugin sans modifier les gabarits

Dans la majorité des cas, **vous n'avez rien à faire** côté gabarits. Le plugin génère automatiquement la page publique à l'URL configurée dans les paramètres. Il suffit de :

1. Configurer le plugin dans le panneau d'administration (emplacements, apparence, textes).
2. Tester l'URL publique (ex. : `monsite.ca/passport`).

Si la page par défaut correspond à vos besoins visuels, passez directement à la section suivante. Si vous devez personnaliser la mise en page, consultez la section [Remplacer le gabarit par défaut](#10-remplacer-le-gabarit-par-défaut).

---

## 3. Accéder aux données depuis vos gabarits

Toutes les données du plugin sont accessibles via l'objet `craft.stampPassport` dans n'importe quel gabarit Twig de votre site.

```twig
{# Exemple de base : lister tous les emplacements actifs #}
{% for emplacement in craft.stampPassport.items %}
    <p>{{ emplacement.title }}</p>
{% endfor %}
```

---

## 4. Les emplacements

### Récupérer tous les emplacements actifs

```twig
{% set emplacements = craft.stampPassport.items %}
```

Retourne un tableau de tous les emplacements activés pour le site courant.

Chaque emplacement contient :

| Propriété | Type | Description |
|---|---|---|
| `id` | Nombre entier | Identifiant unique de l'emplacement. |
| `shortCode` | Texte | Code court utilisé dans les URLs des codes QR. |
| `latitude` | Nombre décimal | Latitude géographique (peut être vide). |
| `longitude` | Nombre décimal | Longitude géographique (peut être vide). |
| `imageId` | Nombre entier | Identifiant de l'image de l'emplacement (peut être vide). |
| `enabled` | Vrai/Faux | Indique si l'emplacement est actif. |
| `contents` | Tableau | Contenu par site (voir ci-dessous). |

Pour chaque entrée dans `contents` :

| Propriété | Type | Description |
|---|---|---|
| `title` | Texte | Titre de l'emplacement pour ce site. |
| `description` | HTML | Description en HTML. |
| `linkUrl` | Texte | URL de l'entrée liée (peut être vide). |
| `linkText` | Texte | Texte du bouton de lien. |

### Exemple d'affichage d'une liste d'emplacements

```twig
{% set emplacements = craft.stampPassport.items %}

<ul class="passeport-emplacements">
    {% for emplacement in emplacements %}
    <li>
        {% if emplacement.imageId %}
            <img src="{{ craft.stampPassport.imageUrl(emplacement.imageId) }}"
                 alt="{{ emplacement.title }}">
        {% endif %}
        <h3>{{ emplacement.title }}</h3>
        {{ emplacement.description | raw }}
    </li>
    {% endfor %}
</ul>
```

> **Attention :** Utilisez `| raw` uniquement avec `craft.stampPassport.sanitizeHtml()` pour éviter les risques de sécurité (voir section 9).

### Récupérer un emplacement par son code court

```twig
{% set emplacement = craft.stampPassport.itemByCode('abc123') %}

{% if emplacement %}
    <h1>{{ emplacement.title }}</h1>
{% endif %}
```

---

## 5. Les paramètres du plugin

Tous les paramètres configurés dans le panneau d'administration sont accessibles :

```twig
{% set config = craft.stampPassport.settings %}

{# Exemples #}
{{ config.pluginName }}       {# Nom du plugin #}
{{ config.routePrefix }}      {# Préfixe de route (ex. : "passport") #}
{{ config.drawThreshold }}    {# Seuil de tampons pour le tirage #}
{{ config.maxStickers }}      {# Maximum de cadeaux disponibles #}
{{ config.enableGeofence }}   {# Géofence activée ? (vrai/faux) #}
{{ config.geofenceRadius }}   {# Rayon de géofence en mètres #}
```

### Exemple : afficher le seuil de qualification

```twig
<p>
    Collectez {{ craft.stampPassport.settings.drawThreshold }} tampons
    ou plus pour vous qualifier au tirage !
</p>
```

---

## 6. Les textes d'interface

Utilisez `craft.stampPassport.text()` pour afficher les textes personnalisés par les administrateurs, avec retour automatique aux valeurs par défaut.

```twig
{{ craft.stampPassport.text('challengeTitle') }}
```

### Textes disponibles

| Clé | Description |
|---|---|
| `orgName` | Nom de l'organisation |
| `challengeName` | Nom de la campagne (sous-titre) |
| `challengeTitle` | Titre de la campagne |
| `scanInstructions` | Instructions de scan |
| `drawModalTitle` | Titre de la modale du tirage |
| `drawModalBody` | Contenu de la modale du tirage |
| `stickerModalTitle` | Titre de la modale des cadeaux |
| `stickerModalBody` | Contenu de la modale des cadeaux |
| `disclaimerTitle` | Titre de l'avertissement légal |
| `disclaimerBody` | Contenu de l'avertissement légal |
| `disclaimerButton` | Texte du bouton de l'avertissement |
| `alreadyCheckedIn` | Message si déjà enregistré |
| `checkingLocation` | Message pendant la vérification GPS |
| `locationError` | Message d'erreur de géolocalisation |
| `checkinFailed` | Message d'erreur d'enregistrement |
| `checkedIn` | Message de succès d'enregistrement |
| `qrNotRecognized` | Message si code QR non reconnu |
| `loadError` | Message d'erreur de chargement |
| `ogTitle` | Titre pour le partage social |
| `ogDescription` | Description pour le partage social |

### Spécifier un site particulier

```twig
{# Forcer le texte du site "en" même si on est sur "fr" #}
{{ craft.stampPassport.text('challengeTitle', 'en') }}
```

### Exemple : en-tête personnalisé

```twig
<header>
    <h1>{{ craft.stampPassport.text('challengeTitle') }}</h1>
    <p>{{ craft.stampPassport.text('scanInstructions') }}</p>
</header>
```

---

## 7. Les images

Pour obtenir l'URL d'une image à partir de son identifiant :

```twig
{% set urlImage = craft.stampPassport.imageUrl(emplacement.imageId) %}

{% if urlImage %}
    <img src="{{ urlImage }}" alt="{{ emplacement.title }}">
{% endif %}
```

Cette fonction retourne l'URL si l'image existe, ou `null` sinon.

### Accéder aux images de configuration

```twig
{% set config = craft.stampPassport.settings %}

{# Logo principal #}
{% set urlLogo = craft.stampPassport.imageUrl(config.logoAssetId) %}

{# Fond d'en-tête (panneau en bois) #}
{% set urlEntete = craft.stampPassport.imageUrl(config.woodPanelAssetId) %}
```

---

## 8. Les URL des APIs

Le JavaScript du passeport communique avec le serveur via quatre points d'accès. Utilisez ces fonctions pour obtenir les URL correctes, quel que soit l'environnement :

```twig
{# URL pour récupérer la liste des emplacements #}
{{ craft.stampPassport.locationsActionUrl() }}

{# URL pour enregistrer un scan #}
{{ craft.stampPassport.collectActionUrl() }}

{# URL pour résoudre un code QR #}
{{ craft.stampPassport.resolveActionUrl() }}

{# URL pour synchroniser la progression du concours #}
{{ craft.stampPassport.contestProgressUrl() }}
```

### Injecter la configuration dans le JavaScript

Le gabarit par défaut injecte ces URLs dans une variable JavaScript globale. Si vous créez votre propre gabarit, reproduisez ce patron :

```twig
<script>
window.__PASSPORT_CONFIG__ = {
    locationsUrl: "{{ craft.stampPassport.locationsActionUrl() }}",
    collectUrl: "{{ craft.stampPassport.collectActionUrl() }}",
    resolveUrl: "{{ craft.stampPassport.resolveActionUrl() }}",
    contestProgressUrl: "{{ craft.stampPassport.contestProgressUrl() }}",
    drawThreshold: {{ craft.stampPassport.settings.drawThreshold }},
    maxStickers: {{ craft.stampPassport.settings.maxStickers }}
};
</script>
```

> 📸 **Image à insérer :** Diagramme montrant les quatre points d'API et leur rôle dans le cycle de scan QR (récupération des lieux → scan → enregistrement → synchronisation).

![Diagramme des points d'API du plugin et leur rôle dans le cycle de scan](images/api-flow-diagram.png)

---

## 9. Nettoyer le HTML des éditeurs

Quand vous affichez du contenu HTML saisi par les administrateurs (descriptions d'emplacements, règles du concours, etc.), utilisez toujours `sanitizeHtml` pour éliminer les balises dangereuses :

```twig
{{ craft.stampPassport.sanitizeHtml(emplacement.description) | raw }}
```

### Balises autorisées après nettoyage

Le filtre autorise uniquement les balises sûres pour la mise en forme de contenu :

- **Mise en forme de base :** `<p>`, `<br>`, `<strong>`, `<em>`, `<b>`, `<i>`, `<u>`
- **Listes :** `<ul>`, `<ol>`, `<li>`
- **Liens :** `<a>` avec `href`, `target`, `rel` (URLs `http`, `https`, `mailto`, `tel` seulement)
- **Structure :** `<blockquote>`, `<hr>`, `<h1>` à `<h6>`, `<span>`

Toutes les autres balises et attributs sont supprimés automatiquement.

> **Règle à retenir :** N'utilisez jamais `| raw` seul sur du contenu saisi par un éditeur. Combinez toujours avec `sanitizeHtml()` d'abord.

---

## 10. Remplacer le gabarit par défaut

Pour personnaliser entièrement la mise en page de la page passeport, créez un fichier dans votre répertoire de gabarits Craft :

```
templates/_stamp-passport/index.twig
```

Si ce fichier existe, il remplace le gabarit par défaut du plugin. Votre gabarit a accès à toutes les variables Twig documentées dans ce manuel.

> 📸 **Image à insérer :** Arborescence de fichiers montrant l'emplacement du gabarit de remplacement `templates/_stamp-passport/index.twig` dans la structure d'un projet Craft CMS.

![Arborescence de fichiers montrant l'emplacement du gabarit personnalisé](images/template-override-tree.png)

### Point de départ recommandé

Copiez le gabarit par défaut du plugin comme point de départ :

```
vendor/csabourin/stamp-passport/src/templates/_frontend/index.twig
```

Puis modifiez-le selon vos besoins dans `templates/_stamp-passport/index.twig`.

---

## 11. Structure du gabarit par défaut

La page publique par défaut est organisée ainsi :

```
┌─────────────────────────────┐
│         EN-TÊTE             │  Logo, nom de l'organisation,
│  Logo  |  Titre  |  Langue  │  titre de la campagne,
│                             │  sélecteur de langue
├─────────────────────────────┤
│    BANDEAU D'INSTRUCTIONS   │  Texte de scan configurable
├─────────────────────────────┤
│                             │
│    GRILLE DES EMPLACEMENTS  │  Image + titre par emplacement
│    □  □  □  □  □  □         │  Coche ou marqueur si collecté
│    □  □  □  □  □  □         │
│                             │
├─────────────────────────────┤
│    BARRE DE PROGRESSION     │  X sur Y tampons collectés
├─────────────────────────────┤
│         PIED DE PAGE        │  Image décorative
└─────────────────────────────┘
```

### Modales (fenêtres contextuelles)

Plusieurs modales s'affichent selon l'état du visiteur :

| Modale | Déclencheur |
|---|---|
| **Avertissement légal** | Première visite (si activé dans les paramètres). |
| **Détail d'emplacement** | Clic sur un emplacement dans la grille. |
| **Tirage au sort** | Seuil de tampons atteint (si formulaire Freeform configuré). |
| **Cadeau** | Tous les emplacements complétés (si formulaire Freeform configuré). |
| **Règles du concours** | Clic sur le bouton des règles (si configurées). |

> 📸 **Image à insérer :** Capture de la page publique du passeport avec une modale de détail d'emplacement ouverte, montrant le titre, la description et le bouton d'action.

![Page publique du passeport avec une modale de détail d'emplacement ouverte](images/passport-modal-detail.png)

### Stockage côté navigateur

La progression du visiteur est enregistrée localement dans son navigateur (IndexedDB, avec repli sur localStorage). **Aucune donnée personnelle n'est stockée sur le serveur sans action explicite du visiteur** (soumission de formulaire).

---

## 12. Référence complète des variables Twig

### Tableau récapitulatif

| Variable / Fonction | Retourne | Description |
|---|---|---|
| `craft.stampPassport.items` | Tableau | Tous les emplacements actifs du site courant. |
| `craft.stampPassport.itemByCode('code')` | Objet ou `null` | Un emplacement par son code court. |
| `craft.stampPassport.settings` | Objet | Tous les paramètres du plugin. |
| `craft.stampPassport.text('clé')` | Texte | Texte d'interface pour la clé donnée (avec repli). |
| `craft.stampPassport.text('clé', 'siteHandle')` | Texte | Texte d'interface pour un site spécifique. |
| `craft.stampPassport.imageUrl(id)` | URL ou `null` | URL d'une image par son identifiant. |
| `craft.stampPassport.sanitizeHtml(html)` | HTML sécurisé | Nettoie le HTML des éditeurs. |
| `craft.stampPassport.locationsActionUrl()` | URL | Point d'API : liste des emplacements. |
| `craft.stampPassport.collectActionUrl()` | URL | Point d'API : enregistrement d'un scan. |
| `craft.stampPassport.resolveActionUrl()` | URL | Point d'API : résolution d'un code QR. |
| `craft.stampPassport.contestProgressUrl()` | URL | Point d'API : synchronisation du concours. |

### Exemple complet : page personnalisée

```twig
{# templates/_stamp-passport/index.twig #}
{% extends '_layouts/site.twig' %}

{% set emplacements = craft.stampPassport.items %}
{% set config = craft.stampPassport.settings %}

{% block content %}

<div class="passeport">

    <header class="passeport__entete">
        {% set urlLogo = craft.stampPassport.imageUrl(config.logoAssetId) %}
        {% if urlLogo %}
            <img src="{{ urlLogo }}" alt="{{ craft.stampPassport.text('orgName') }}">
        {% endif %}
        <h1>{{ craft.stampPassport.text('challengeTitle') }}</h1>
    </header>

    <p class="passeport__instructions">
        {{ craft.stampPassport.text('scanInstructions') }}
    </p>

    <ul class="passeport__grille" id="stampGrid">
        {% for emplacement in emplacements %}
        <li class="stamp-slot" data-code="{{ emplacement.shortCode }}">
            {% set urlImage = craft.stampPassport.imageUrl(emplacement.imageId) %}
            {% if urlImage %}
                <img src="{{ urlImage }}" alt="{{ emplacement.title }}" loading="lazy">
            {% endif %}
            <span class="stamp-slot__titre">{{ emplacement.title }}</span>
            <span class="stamp-check" aria-hidden="true"></span>
        </li>
        {% endfor %}
    </ul>

    <div class="passeport__progression">
        <div id="progressFill"></div>
        <span id="progressText"></span>
    </div>

</div>

{# Configuration pour le JavaScript #}
<script>
window.__PASSPORT_CONFIG__ = {
    locationsUrl: "{{ craft.stampPassport.locationsActionUrl() }}",
    collectUrl: "{{ craft.stampPassport.collectActionUrl() }}",
    resolveUrl: "{{ craft.stampPassport.resolveActionUrl() }}",
    contestProgressUrl: "{{ craft.stampPassport.contestProgressUrl() }}",
    drawThreshold: {{ config.drawThreshold }},
    maxStickers: {{ config.maxStickers }},
    enableGeofence: {{ config.enableGeofence ? 'true' : 'false' }},
    geofenceRadius: {{ config.geofenceRadius }}
};
</script>

{% endblock %}
```

> **Important :** Les identifiants `id="stampGrid"`, `data-code`, `class="stamp-check"`, `id="progressFill"` et `id="progressText"` sont requis par le JavaScript du plugin. Ne les modifiez pas si vous réutilisez le fichier `passport.js` fourni.

---

*Pour toute question sur l'architecture du plugin ou les API disponibles, consultez le fichier [CLAUDE.md](../CLAUDE.md) à la racine du projet ou contactez l'équipe de développement.*
