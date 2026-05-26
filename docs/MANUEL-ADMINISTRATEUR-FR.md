# Manuel de l'administrateur — Stamp Passport

Ce manuel s'adresse aux personnes responsables de la configuration initiale et de la maintenance du plugin Stamp Passport dans le panneau d'administration de Craft CMS.

---

## Table des matières

1. [Présentation du plugin](#1-présentation-du-plugin)
2. [Navigation dans le panneau d'administration](#2-navigation-dans-le-panneau-dadministration)
3. [Tableau de bord — Statistiques](#3-tableau-de-bord--statistiques)
4. [Gestion des emplacements](#4-gestion-des-emplacements)
5. [Textes d'interface](#5-textes-dinterface)
6. [Règles du concours](#6-règles-du-concours)
7. [Codes QR](#7-codes-qr)
8. [Paramètres — Général](#8-paramètres--général)
9. [Paramètres — Apparence](#9-paramètres--apparence)
10. [Paramètres — Intégrations](#10-paramètres--intégrations)
11. [Paramètres — Avancé](#11-paramètres--avancé)
12. [Gestion multisite](#12-gestion-multisite)
13. [Questions fréquentes](#13-questions-fréquentes)

---

## 1. Présentation du plugin

Stamp Passport est un outil de parcours de visite basé sur des codes QR. Les visiteurs scannent des codes aux différents emplacements participants et accumulent des tampons virtuels dans leur passeport. Selon le nombre de tampons récoltés, ils peuvent se qualifier pour un tirage au sort ou réclamer un cadeau.

En tant qu'administrateur, vous avez accès à toutes les fonctions de configuration : apparence, règles de campagne, intégrations et statistiques.

> 📸 **Image à insérer :** Vue générale de la page publique du passeport affichant une grille de tampons et une barre de progression.

![Vue de la page publique du passeport avec tampons et barre de progression](images/passport-public-overview.png)

---

## 2. Navigation dans le panneau d'administration

Une fois le plugin installé, vous trouverez **Stamp Passport** dans le menu latéral gauche de Craft CMS. Le menu contient six sections :

| Section | À quoi ça sert |
|---|---|
| **Tableau de bord** | Voir les statistiques de participation |
| **Emplacements** | Créer et gérer les lieux de scan |
| **Codes QR** | Générer les codes QR à imprimer |
| **Textes d'interface** | Personnaliser les messages visibles par le public |
| **Règles du concours** | Gérer le contenu légal et les règles par langue |
| **Paramètres** | Configurer tous les aspects techniques du plugin |

> 📸 **Image à insérer :** Capture du menu latéral de Craft CMS avec les six sections du plugin Stamp Passport visibles.

![Menu de navigation Stamp Passport dans le panneau d'administration Craft CMS](images/cp-navigation-menu.png)

---

## 3. Tableau de bord — Statistiques

Le tableau de bord vous donne un aperçu rapide de la santé de votre campagne.

### Ce que vous y trouvez

- **Total de scans** — Nombre total de fois qu'un code QR a été scanné.
- **Visiteurs uniques** — Nombre de passeports différents créés.
- **Moyenne de scans par visiteur** — Indique si les participants visitent plusieurs lieux ou un seul.
- **Lieu le plus visité** — L'emplacement qui a reçu le plus de scans.
- **Qualifiés pour le tirage** — Participants ayant atteint le nombre de tampons requis.
- **Qualifiés pour le cadeau** — Participants ayant visité tous les emplacements.

### Filtrer par période

En haut du tableau de bord, deux champs de date vous permettent de limiter les résultats à une période précise. Cliquez sur le champ **Du** et **Au**, choisissez vos dates, puis cliquez **Appliquer**.

> 📸 **Image à insérer :** Tableau de bord avec les cartes de statistiques (scans, visiteurs, qualifiés) et les filtres de date en haut de page.

![Tableau de bord Stamp Passport affichant les statistiques de campagne et les filtres de date](images/dashboard-stats.png)

### Graphiques disponibles

- **Par jour de la semaine** — Identifie les jours les plus achalandés.
- **7 derniers jours** — Tendance récente de participation.
- **30 derniers jours** — Vue d'ensemble du mois en cours.

> 📸 **Image à insérer :** Section des graphiques du tableau de bord montrant les barres de tendance sur 7 et 30 jours.

![Graphiques de tendance du tableau de bord sur 7 et 30 jours](images/dashboard-charts.png)

---

## 4. Gestion des emplacements

Les emplacements sont les lieux physiques associés aux codes QR. Chaque emplacement correspond à un tampon dans le passeport.

### Liste des emplacements

La liste affiche tous les emplacements configurés avec leur titre, leur code court, leurs coordonnées et leur état d'activation.

> 📸 **Image à insérer :** Liste des emplacements avec les colonnes titre, code court, coordonnées et statut actif/inactif.

![Liste des emplacements dans le panneau d'administration](images/items-list.png)

**Réordonner les emplacements :** Cliquez-déposez la poignée (≡) à gauche de chaque ligne pour changer l'ordre d'affichage dans le passeport.

**Supprimer un emplacement :** Cliquez sur la croix à droite de la ligne. Une confirmation vous sera demandée.

### Créer un nouvel emplacement

Cliquez sur le bouton **Nouvel emplacement** en haut à droite. Le formulaire d'édition s'ouvre.

> 📸 **Image à insérer :** Formulaire de création d'un emplacement avec les champs titre, description et image remplis.

![Formulaire d'édition d'un emplacement avec les champs principaux](images/item-edit-form.png)

### Champs disponibles

**Informations globales** (communes à tous les sites) :

| Champ | Description |
|---|---|
| **Activé** | Active ou désactive l'emplacement dans le passeport public. |
| **Latitude / Longitude** | Coordonnées géographiques du lieu (optionnel). Requises pour la validation par géofence. |
| **Image de l'emplacement** | Photo ou illustration représentant le lieu. Cliquez **Choisir une image** pour sélectionner un fichier depuis votre médiathèque Craft. |
| **Image centrale du code QR** | Petite image placée au centre du code QR imprimé (optionnel, remplace l'image globale). |

**Contenu par site/langue** (peut varier d'un site à l'autre) :

| Champ | Description |
|---|---|
| **Titre** | Nom affiché de l'emplacement dans le passeport. Obligatoire. |
| **Description** | Texte de présentation du lieu. Peut inclure du gras, des listes, des liens. |
| **Entrée liée** | Page Craft associée (optionnel). Sélectionnez une entrée pour créer un lien « En savoir plus ». |
| **Texte du lien** | Libellé du bouton de lien. Par défaut : « En savoir plus ». |

### Enregistrer et changer de langue

Cliquez **Enregistrer** pour sauvegarder vos modifications. Pour modifier le contenu dans une autre langue, cliquez sur le bouton du site actuel (en haut à droite, icône de globe) et choisissez un autre site. Le plugin sauvegardera automatiquement le site en cours avant de changer de vue.

> 📸 **Image à insérer :** Bouton de changement de site dans l'en-tête du formulaire d'édition, avec la liste déroulante des sites disponibles.

![Sélecteur de site dans le formulaire d'édition d'emplacement](images/item-site-switcher.png)

---

## 5. Textes d'interface

Cette section vous permet de personnaliser tous les messages et libellés visibles par le public sur la page du passeport.

### Pourquoi cette section existe

Le plugin inclut des textes par défaut en français et en anglais. Si vous souhaitez adapter le vocabulaire à votre organisation (par exemple changer « Tampons » par « Découvertes »), faites-le ici sans modifier le code.

### Modifier les textes

1. Sélectionnez le site/langue à configurer en haut de la page.
2. Remplissez les champs que vous souhaitez personnaliser.
3. Laissez un champ vide pour conserver le texte par défaut.
4. Cliquez **Enregistrer**.

> 📸 **Image à insérer :** Page Textes d'interface avec plusieurs champs remplis et le sélecteur de site en haut.

![Page de gestion des textes d'interface avec les champs de personnalisation](images/display-text-form.png)

### Groupes de textes disponibles

**En-tête et identité :**
- Nom de l'organisation
- Nom de la campagne (sous-titre léger)
- Titre de la campagne (titre principal, en gras)

**Instructions :**
- Instructions de scan (ex. : « Scannez tous les codes QR pour compléter votre passeport »)

**Fenêtres contextuelles (modales) :**
- Titre et contenu de la modale du tirage au sort
- Titre et contenu de la modale du cadeau

**Messages système :**
- Message si déjà enregistré (scan en double)
- Message pendant la vérification de localisation
- Message d'erreur de géolocalisation
- Message d'erreur d'enregistrement
- Message de succès d'enregistrement
- Message si le code QR n'est pas reconnu
- Message d'erreur de chargement

**Avertissement légal :**
- Titre, contenu et texte du bouton de l'avertissement initial

**Partage sur les réseaux sociaux :**
- Titre et description pour les aperçus de partage (Open Graph)

---

## 6. Règles du concours

Cette section permet de configurer le contenu des règles et modalités du concours, séparément pour chaque site/langue.

### Activer les règles pour un site

Les règles sont masquées si le champ **Texte du lien** est vide. Pour les activer :

1. Sélectionnez le site/langue souhaité.
2. Remplissez le champ **Texte du lien** (ex. : « Voir les règles »). Ce texte apparaîtra comme bouton sur la page publique.
3. Ajoutez le contenu des règles dans l'éditeur **Contenu de la modale**.
4. Optionnellement, liez une entrée Craft contenant les règles complètes via **Entrée des règles complètes**.
5. Cliquez **Enregistrer**.

> 📸 **Image à insérer :** Formulaire des règles du concours avec le champ texte du lien rempli et l'éditeur de contenu visible.

![Formulaire de gestion des règles du concours](images/contest-rules-form.png)

### Désactiver les règles

Effacez le champ **Texte du lien** et enregistrez. Le bouton et la modale des règles disparaîtront du site public.

---

## 7. Codes QR

La section Codes QR vous permet de générer les codes à imprimer pour chaque emplacement.

> 📸 **Image à insérer :** Page de génération des codes QR avec un exemple de code généré et les options de couleur.

![Page de génération des codes QR avec prévisualisation](images/qr-codes-page.png)

Les couleurs des codes QR sont configurables dans **Paramètres → Apparence** (voir section 9).

---

## 8. Paramètres — Général

Accédez aux paramètres via **Stamp Passport → Paramètres**, onglet **Général**.

### Identification du plugin

| Champ | Description |
|---|---|
| **Nom du plugin** | Nom affiché dans le menu du panneau d'administration. Par défaut : « Stamp Passport ». |
| **Préfixe de route** | Chemin URL de la page publique. Par défaut : `passport` (ex. : `monsite.ca/passport`). |
| **Préfixes de route par site** | Permet un chemin différent pour chaque site/langue (ex. : `/passport` en anglais, `/passeport` en français). |

### Géofence (validation de présence)

La géofence vérifie que le visiteur se trouve physiquement à l'emplacement avant d'accepter le scan.

| Champ | Description |
|---|---|
| **Activer la géofence** | Si activée, la position GPS du visiteur est vérifiée. |
| **Rayon de géofence** | Distance maximale en mètres entre le visiteur et l'emplacement (entre 50 et 10 000 m, par défaut 550 m). |

> **Conseil :** Désactivez la géofence lors des tests ou si votre campagne se déroule dans un espace large où la précision GPS est variable.

### Seuils de récompense

| Champ | Description |
|---|---|
| **Seuil du tirage** | Nombre de tampons nécessaires pour se qualifier au tirage. Par défaut : 5. |
| **Maximum de cadeaux** | Nombre total de cadeaux (adhésifs) disponibles. Par défaut : 100. |

### Suivi analytique

| Champ | Description |
|---|---|
| **Identifiant Google Analytics 4** | Votre identifiant GA4 au format `G-XXXXXXXXXX`. Laissez vide pour désactiver. |

### Version du concours

Le champ **Version du concours** identifie la version des règles en vigueur. Modifiez-le quand vous mettez à jour les règles officielles afin que les progrès des visiteurs soient correctement associés aux bonnes règles.

> 📸 **Image à insérer :** Onglet Général des paramètres avec les sections géofence et seuils visibles.

![Onglet Général des paramètres du plugin](images/settings-general.png)

---

## 9. Paramètres — Apparence

Onglet **Apparence** dans les paramètres.

### Images

| Champ | Description |
|---|---|
| **Logo** | Logo principal affiché en haut de la page passeport (forme circulaire recommandée). |
| **Logo alternatif** | Logo secondaire pour les sites non-anglais (optionnel, remplace le logo principal). |
| **Panneau en bois** | Image d'arrière-plan pour l'en-tête. Si absent, un fond brun est utilisé. |
| **Marqueur de complétion** | Image ou icône affichée sur les tampons déjà collectés. |
| **Arrière-plan du corps** | Image de fond de la page principale. |
| **Arrière-plan du pied de page** | Image décorative en bas de page. |
| **Image centrale des QR** | Image placée au centre de tous les codes QR générés. |
| **Image OG** | Image utilisée lors du partage sur les réseaux sociaux. |
| **Favicon** | Icône affichée dans l'onglet du navigateur. |

### Mode d'affichage de l'arrière-plan

| Option | Effet |
|---|---|
| **Couverture** | L'image couvre toute la page (recommandé pour les photos). |
| **Mosaïque** | L'image se répète en carreau (recommandé pour les textures). |
| **Répétition verticale** | L'image se répète uniquement en hauteur. |
| **Personnalisé** | Utilise la valeur du champ Taille de l'arrière-plan. |

### Couleurs de marque

Vous pouvez remplacer les couleurs par défaut du passeport en saisissant des codes hexadécimaux (ex. : `#2b6b8a`).

| Champ | Rôle visuel |
|---|---|
| **Couleur principale** | Boutons, liens actifs. |
| **Couleur principale foncée** | États de survol et de focus. |
| **Couleur d'accentuation** | Éléments secondaires mis en valeur. |

### Couleurs des codes QR

| Champ | Description |
|---|---|
| **Couleur des points QR** | Couleur des modules du code QR. Par défaut : noir (`#000000`). |
| **Couleur de fond QR** | Couleur de l'arrière-plan du code QR. Par défaut : blanc (`#ffffff`). |

> **Important :** Assurez-vous que le contraste entre la couleur des points et le fond est suffisant pour que les codes QR restent lisibles par les appareils mobiles.

> 📸 **Image à insérer :** Onglet Apparence des paramètres avec les sélecteurs d'images et les champs de couleur.

![Onglet Apparence des paramètres avec les options d'images et de couleurs](images/settings-appearance.png)

---

## 10. Paramètres — Intégrations

Onglet **Intégrations** dans les paramètres.

Cette section permet de connecter des formulaires Freeform au passeport.

| Champ | Description |
|---|---|
| **Formulaire du tirage** | Formulaire Freeform affiché quand un visiteur atteint le seuil de tampons pour le tirage. |
| **Formulaire des cadeaux** | Formulaire Freeform affiché quand un visiteur complète tous les emplacements. |

> **Note :** Si le plugin Freeform n'est pas installé, ces champs acceptent quand même un identifiant de formulaire, mais les formulaires ne s'afficheront pas avant l'installation de Freeform.

> 📸 **Image à insérer :** Onglet Intégrations avec les listes déroulantes de sélection des formulaires Freeform.

![Onglet Intégrations des paramètres avec les sélecteurs de formulaires](images/settings-integrations.png)

---

## 11. Paramètres — Avancé

Onglet **Avancé** dans les paramètres.

### CSS personnalisé

Permet d'injecter des règles CSS supplémentaires dans la page publique pour ajuster l'apparence sans toucher aux fichiers du plugin.

1. Activez l'interrupteur **CSS personnalisé activé**.
2. Saisissez vos règles CSS dans la zone de texte (maximum 10 000 caractères).
3. Enregistrez.

> **Conseil :** Utilisez cette fonctionnalité pour des ajustements mineurs. Pour des changements majeurs, adressez-vous à votre développeur.

### Options d'affichage

| Option | Description | Défaut |
|---|---|---|
| **Afficher le sélecteur de langue** | Montre ou masque le bouton de changement de langue sur le passeport. | Activé |
| **Exiger l'acceptation de l'avertissement** | Affiche une modale d'avertissement légal à la première visite. | Activé |
| **Afficher le nom de l'organisation** | Affiche le nom de l'organisation dans l'en-tête. | Activé |
| **Afficher le nom de la campagne** | Affiche le sous-titre de la campagne. | Activé |
| **Afficher le titre de la campagne** | Affiche le titre principal de la campagne. | Activé |

> 📸 **Image à insérer :** Onglet Avancé des paramètres avec la zone de CSS et les interrupteurs d'affichage.

![Onglet Avancé des paramètres avec les options CSS et les interrupteurs](images/settings-advanced.png)

---

## 12. Gestion multisite

Si votre installation Craft CMS gère plusieurs sites (par exemple, un site français et un site anglais), Stamp Passport s'adapte à chacun.

### Ce qui est partagé entre les sites

- L'identité de chaque emplacement (code court, coordonnées, images, activation).
- La configuration globale (géofence, seuils, apparence de base).

### Ce qui est différent par site

- Le titre, la description et les liens de chaque emplacement.
- Les textes d'interface (messages, libellés).
- Les règles du concours.
- Le préfixe de route (optionnel).

### Passer d'un site à l'autre

Dans la plupart des écrans, un sélecteur de site apparaît en haut de la page. Cliquez dessus pour basculer vers un autre site. Vos modifications en cours seront automatiquement sauvegardées avant le changement.

> 📸 **Image à insérer :** Sélecteur de site affiché en haut d'une page d'administration avec la liste des sites disponibles.

![Sélecteur de site dans le panneau d'administration Stamp Passport](images/multisite-switcher.png)

---

## 13. Questions fréquentes

**Un visiteur signale que son scan n'a pas été accepté. Que vérifier ?**

1. Vérifiez que l'emplacement est bien **activé** dans la liste des emplacements.
2. Si la géofence est activée, vérifiez que les coordonnées de l'emplacement sont correctes et que le rayon est suffisant.
3. Testez vous-même le scan depuis un appareil mobile sur place.

**Comment changer le lien de la page publique ?**

Allez dans **Paramètres → Général** et modifiez le champ **Préfixe de route**. Informez votre équipe technique pour mettre à jour les redirections si nécessaire.

**Comment ajouter un nouveau site/langue ?**

La gestion des sites est faite dans les paramètres généraux de Craft CMS (pas dans Stamp Passport). Une fois le nouveau site créé dans Craft, il apparaîtra automatiquement dans les sélecteurs de site du plugin.

**Le formulaire de tirage ne s'affiche pas. Pourquoi ?**

Vérifiez que :
- Le plugin Freeform est installé.
- Le formulaire sélectionné dans **Paramètres → Intégrations** existe bien dans Freeform.
- Le visiteur a bien atteint le nombre de tampons requis (voir **Paramètres → Général → Seuil du tirage**).

**Comment savoir combien de cadeaux ont déjà été réclamés ?**

Consultez le **Tableau de bord** et regardez la carte **Qualifiés pour le cadeau**. Pour un décompte exact des réclamations traitées, consultez les soumissions de formulaire dans Freeform.

---

*Pour toute question technique, contactez votre développeur ou l'équipe responsable de l'installation du plugin.*
