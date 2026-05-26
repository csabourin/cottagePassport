# Manuel de l'intégrateur de contenu — Stamp Passport

Ce manuel s'adresse aux personnes qui saisissent et mettent à jour le contenu du passeport dans Craft CMS : titres, descriptions, images, textes d'interface et règles du concours. Il suppose que la configuration initiale du plugin a déjà été effectuée par un administrateur.

Vous n'avez pas besoin de connaissances techniques pour utiliser ce guide.

---

## Table des matières

1. [Votre rôle dans le plugin](#1-votre-rôle-dans-le-plugin)
2. [Accéder au plugin](#2-accéder-au-plugin)
3. [Gérer les emplacements](#3-gérer-les-emplacements)
4. [Travailler en plusieurs langues](#4-travailler-en-plusieurs-langues)
5. [Personnaliser les textes affichés au public](#5-personnaliser-les-textes-affichés-au-public)
6. [Gérer les règles du concours](#6-gérer-les-règles-du-concours)
7. [Vérifier le résultat sur la page publique](#7-vérifier-le-résultat-sur-la-page-publique)
8. [Checklist avant publication](#8-checklist-avant-publication)
9. [Questions fréquentes](#9-questions-fréquentes)

---

## 1. Votre rôle dans le plugin

Le passeport numérique est composé de plusieurs couches :

| Ce qui est fait par… | Rôle |
|---|---|
| **L'administrateur** | Configuration initiale : routes, géofence, seuils de récompense, apparence générale. |
| **Vous — l'intégrateur de contenu** | Contenu des emplacements, textes visibles par le public, règles du concours. |
| **Le visiteur** | Scanne les codes QR et accumule des tampons dans son passeport. |

En pratique, vous travaillez principalement dans trois sections du plugin :

- **Emplacements** — Le cœur du passeport : chaque emplacement correspond à un lieu physique avec un code QR.
- **Textes d'interface** — Les messages et libellés affichés sur la page publique.
- **Règles du concours** — Le contenu légal et les modalités de participation.

---

## 2. Accéder au plugin

1. Connectez-vous à l'interface d'administration de votre site Craft CMS.
2. Dans le menu latéral gauche, repérez **Stamp Passport**.
3. Cliquez pour déployer le sous-menu.

> 📸 **Image à insérer :** Menu latéral gauche de Craft CMS avec le sous-menu Stamp Passport déployé, montrant les options Emplacements, Textes d'interface et Règles du concours en surbrillance.

![Menu de navigation Stamp Passport dans Craft CMS avec les sections de contenu en évidence](images/cp-menu-contenu.png)

---

## 3. Gérer les emplacements

Les emplacements sont les lieux physiques associés aux codes QR. Chacun devient un tampon dans le passeport du visiteur.

### Voir la liste des emplacements

Cliquez sur **Stamp Passport → Emplacements**. Vous voyez la liste de tous les emplacements existants.

> 📸 **Image à insérer :** Liste des emplacements avec les colonnes titre, code court, coordonnées et état actif/inactif.

![Liste des emplacements dans le panneau d'administration](images/items-list.png)

Chaque ligne affiche :
- Le **titre** de l'emplacement (cliquez dessus pour l'ouvrir).
- Le **code court** — une série de lettres et de chiffres générée automatiquement. Ne le modifiez pas.
- Les **coordonnées** GPS, si elles ont été saisies.
- Un **indicateur vert** si l'emplacement est actif.

### Ouvrir un emplacement existant

Cliquez sur le titre d'un emplacement pour ouvrir son formulaire d'édition.

> 📸 **Image à insérer :** Formulaire d'édition d'un emplacement ouvert, montrant les champs titre, description et image remplis.

![Formulaire d'édition d'un emplacement avec les champs de contenu visibles](images/item-edit-form.png)

### Créer un nouvel emplacement

Cliquez sur le bouton **Nouvel emplacement** en haut à droite de la liste.

Un formulaire vierge s'ouvre. Remplissez les champs décrits ci-dessous, puis enregistrez.

---

### Les champs du formulaire d'emplacement

Le formulaire est divisé en deux types de champs : ceux qui sont **communs à tous les sites** et ceux qui **varient par langue**.

#### Champs communs (partagés par tous les sites)

Ces champs s'appliquent à tous les sites/langues. Vous ne les remplissez qu'une seule fois.

**Activé**

Un interrupteur à bascule. Laissez-le activé (bleu) pour que l'emplacement apparaisse sur la page publique. Désactivez-le temporairement si l'emplacement n'est pas encore prêt ou s'il est fermé pour la saison.

> 📸 **Image à insérer :** Gros plan sur l'interrupteur Activé en position active (bleu).

![Interrupteur d'activation d'un emplacement en position active](images/item-toggle-enabled.png)

**Latitude et Longitude**

Coordonnées géographiques du lieu, utilisées pour la validation de présence (géofence). Ces valeurs sont habituellement fournies par votre équipe technique ou votre administrateur. Si vous n'en avez pas, laissez ces champs vides.

> **Conseil :** Pour trouver les coordonnées d'un lieu, ouvrez Google Maps, faites un clic droit sur l'emplacement exact et copiez les coordonnées affichées (ex. : `45.4281237, -75.6942809`).

**Image de l'emplacement**

La photo ou l'illustration représentant ce lieu dans la grille du passeport.

Pour ajouter une image :
1. Cliquez sur **Choisir une image**.
2. Une fenêtre de sélection de fichiers Craft s'ouvre.
3. Naviguez jusqu'au dossier contenant vos images.
4. Cliquez sur l'image souhaitée, puis sur **Sélectionner**.

> 📸 **Image à insérer :** Fenêtre de sélection d'image de Craft CMS avec une image sélectionnée et le bouton Sélectionner visible.

![Fenêtre de sélection d'image Craft CMS pour un emplacement](images/asset-picker.png)

Pour remplacer une image existante, cliquez sur la croix à côté de l'image actuelle pour la retirer, puis choisissez-en une nouvelle.

---

#### Champs par site/langue

Ces champs peuvent être différents d'un site à l'autre. Par exemple, un titre en français sur le site français et un titre en anglais sur le site anglais.

**Titre** *(obligatoire)*

Le nom de l'emplacement tel qu'il apparaît dans le passeport. Soyez concis et descriptif (ex. : « Château Frontenac », « Pavillon principal »).

**Description**

Un texte de présentation du lieu, affiché quand un visiteur clique sur l'emplacement. Vous pouvez utiliser l'éditeur de texte enrichi pour ajouter :
- Du texte en **gras** ou en *italique*
- Des listes à puces ou numérotées
- Des liens hypertextes

> 📸 **Image à insérer :** Éditeur de texte enrichi avec une description saisie et la barre d'outils visible (gras, italique, lien, listes).

![Éditeur de description avec barre d'outils de mise en forme](images/item-description-editor.png)

**Entrée liée**

Permet d'associer une page existante de votre site à cet emplacement. Un bouton « En savoir plus » (ou le texte que vous définissez) apparaîtra dans le passeport et dirigera le visiteur vers cette page.

Pour choisir une entrée :
1. Cliquez sur **Choisir une entrée**.
2. Une fenêtre de sélection s'ouvre avec les pages de votre site.
3. Cochez l'entrée souhaitée, puis cliquez **Choisir**.

Ce champ est **optionnel**. Si vous ne liez pas d'entrée, aucun bouton ne s'affichera.

**Texte du lien**

Le libellé du bouton qui mène vers l'entrée liée. Par défaut : « En savoir plus ». Modifiez-le selon le contexte (ex. : « Voir le programme », « Visiter le site »).

Ce champ n'a d'effet que si une entrée liée est sélectionnée.

---

### Enregistrer un emplacement

Cliquez sur le bouton **Enregistrer** en haut à droite du formulaire. Un message de confirmation apparaît en haut de la page si l'enregistrement a réussi.

> 📸 **Image à insérer :** Bouton Enregistrer en haut à droite du formulaire avec le message de confirmation vert affiché.

![Bouton Enregistrer et message de confirmation dans le formulaire d'emplacement](images/item-save-confirmation.png)

---

## 4. Travailler en plusieurs langues

Si votre site existe en plusieurs langues (par exemple, français et anglais), vous devez saisir le contenu pour chaque langue séparément.

### Identifier le site actif

En haut du formulaire d'édition, un bouton indique le site sur lequel vous travaillez en ce moment (ex. : « Français » ou « English »). Vérifiez toujours que vous êtes sur le bon site avant de saisir du contenu.

> 📸 **Image à insérer :** En-tête du formulaire d'édition avec le bouton de sélection de site indiquant « Français », et la liste déroulante des sites disponibles ouverte.

![Sélecteur de site dans l'en-tête du formulaire d'emplacement](images/item-site-switcher.png)

### Passer d'une langue à l'autre

1. Cliquez sur le bouton du site actuel (en haut à droite, avec une icône de globe).
2. Une liste déroulante affiche les sites disponibles.
3. Cliquez sur le site souhaité.

Le plugin **enregistre automatiquement vos modifications en cours** avant de basculer vers l'autre langue. Vous ne perdrez pas votre travail.

### Flux de travail recommandé pour les traductions

1. Ouvrez un emplacement.
2. Saisissez le contenu dans la langue principale (ex. : français).
3. Cliquez **Enregistrer**.
4. Changez de site vers la deuxième langue (ex. : anglais).
5. Remplissez les champs traduits (titre, description, texte du lien).
6. Cliquez **Enregistrer**.
7. Répétez pour chaque langue.

> **Conseil :** Gardez un onglet de navigateur ouvert sur la page publique dans chaque langue pour vérifier le résultat en temps réel après chaque enregistrement.

---

## 5. Personnaliser les textes affichés au public

La section **Textes d'interface** regroupe tous les messages et libellés visibles par les visiteurs sur la page du passeport. Ces textes ont des valeurs par défaut (en français et en anglais), mais vous pouvez les adapter à la voix de votre organisation.

### Accéder aux textes d'interface

Cliquez sur **Stamp Passport → Textes d'interface**.

> 📸 **Image à insérer :** Page des textes d'interface avec les groupes de champs dépliés et le sélecteur de site visible en haut.

![Page de gestion des textes d'interface avec les champs de personnalisation](images/display-text-page.png)

### Règle de base

- **Champ rempli** = le texte que vous avez saisi s'affiche sur le site.
- **Champ vide** = le texte par défaut du plugin s'affiche (en français ou en anglais selon la langue du site).

Vous n'avez pas à remplir tous les champs. Modifiez uniquement ce qui doit être différent des valeurs par défaut.

### Changer de langue

Comme pour les emplacements, utilisez le sélecteur de site en haut de la page pour passer d'une langue à l'autre. Chaque langue a ses propres textes d'interface.

### Textes que vous pouvez modifier

**Identité de la campagne :**

| Champ | Exemple de valeur |
|---|---|
| **Nom de l'organisation** | Musée de la nature |
| **Nom de la campagne** | Édition printemps 2026 |
| **Titre de la campagne** | Explorez nos collections |

**Instructions :**

| Champ | Exemple de valeur |
|---|---|
| **Instructions de scan** | Scannez les codes QR à chaque station pour compléter votre passeport ! |

**Messages des fenêtres contextuelles (modales) :**

| Champ | Quand il s'affiche |
|---|---|
| **Titre de la modale du tirage** | Quand le visiteur atteint le nombre de tampons requis pour le tirage. |
| **Contenu de la modale du tirage** | Corps du message pour s'inscrire au tirage. |
| **Titre de la modale du cadeau** | Quand le visiteur a complété tous les emplacements. |
| **Contenu de la modale du cadeau** | Corps du message pour réclamer un cadeau. |

**Avertissement légal (première visite) :**

| Champ | Description |
|---|---|
| **Titre de l'avertissement** | Titre de la fenêtre affichée à la première visite. |
| **Contenu de l'avertissement** | Texte des conditions à accepter. |
| **Texte du bouton** | Libellé du bouton d'acceptation (ex. : « J'accepte »). |

**Messages système :**

Ces messages s'affichent automatiquement selon ce qui se passe lors du scan. Modifiez-les si le ton par défaut ne correspond pas à celui de votre organisation.

| Champ | Quand il apparaît |
|---|---|
| **Déjà enregistré** | Si le visiteur scanne un endroit déjà collecté. |
| **Vérification en cours** | Pendant la validation de la position GPS. |
| **Erreur de localisation** | Si le GPS du visiteur ne fonctionne pas. |
| **Erreur d'enregistrement** | Si le scan n'a pas pu être sauvegardé. |
| **Enregistrement réussi** | Confirmation après un scan accepté. |
| **Code non reconnu** | Si le code QR scanné ne correspond à aucun emplacement. |
| **Erreur de chargement** | Si la page ne peut pas se charger correctement. |

**Partage sur les réseaux sociaux :**

| Champ | Description |
|---|---|
| **Titre OG** | Titre affiché quand quelqu'un partage le lien du passeport sur les réseaux sociaux. |
| **Description OG** | Courte description pour le partage social. |

### Enregistrer les textes

Cliquez sur **Enregistrer** après avoir rempli ou modifié les champs voulus.

---

## 6. Gérer les règles du concours

La section **Règles du concours** vous permet de publier les règles et modalités de participation, séparément pour chaque langue. Ces règles s'affichent dans une fenêtre contextuelle accessible depuis un bouton sur la page publique.

### Accéder aux règles du concours

Cliquez sur **Stamp Passport → Règles du concours**.

> 📸 **Image à insérer :** Page des règles du concours avec le champ Texte du lien rempli et l'éditeur de contenu visible.

![Page de gestion des règles du concours avec les champs de contenu](images/contest-rules-page.png)

### Activer les règles pour une langue

Les règles ne s'affichent sur le site public que si le champ **Texte du lien** est rempli.

1. Sélectionnez la langue souhaitée avec le sélecteur de site.
2. Remplissez le champ **Texte du lien** — c'est le libellé du bouton sur la page publique (ex. : « Règles du concours »).
3. Rédigez le contenu des règles dans l'éditeur **Contenu de la modale**.
4. Cliquez **Enregistrer**.

Un bouton « Règles du concours » apparaîtra maintenant sur la page publique pour cette langue.

### Lier une page de règles complètes

Si vos règles légales complètes se trouvent sur une page de votre site Craft, vous pouvez y ajouter un lien depuis la modale des règles.

1. Dans le champ **Texte du bouton des règles complètes**, saisissez le libellé du lien (ex. : « Lire les règles complètes »). Si vous laissez ce champ vide, le texte par défaut sera utilisé.
2. Cliquez sur **Choisir une entrée** à côté de **Entrée des règles complètes**.
3. Sélectionnez la page correspondante dans votre site.
4. Enregistrez.

### Désactiver les règles pour une langue

Effacez le champ **Texte du lien** et enregistrez. Le bouton et la fenêtre des règles disparaîtront du site public pour cette langue.

---

## 7. Vérifier le résultat sur la page publique

Après chaque modification importante, prenez l'habitude de vérifier le résultat directement sur la page publique du passeport.

### Accéder à la page publique depuis le panneau d'administration

Depuis la liste des emplacements, cliquez sur le bouton **Prévisualiser** (icône d'œil ou lien externe) en haut de la page. Cela ouvre la page publique dans un nouvel onglet.

> 📸 **Image à insérer :** Barre d'outils en haut de la liste des emplacements avec le bouton de prévisualisation en surbrillance.

![Bouton de prévisualisation de la page publique dans la liste des emplacements](images/preview-button.png)

### Ce qu'il faut vérifier

- Le titre et la description de chaque emplacement s'affichent correctement.
- Les images se chargent et sont bien cadrées.
- Les liens « En savoir plus » fonctionnent et mènent aux bonnes pages.
- Les textes d'interface (instructions, messages) correspondent à ce que vous avez saisi.
- Sur mobile, la mise en page reste lisible et les images ne sont pas coupées.

### Vérifier les langues

Si votre site est multilingue, vérifiez la page dans chaque langue. Les URLs des versions linguistiques sont habituellement différentes (ex. : `/passeport` pour le français et `/passport` pour l'anglais). Votre administrateur peut vous communiquer les adresses exactes.

---

## 8. Checklist avant publication

Utilisez cette liste avant de rendre le passeport public ou de lancer une nouvelle campagne.

### Emplacements

- [ ] Tous les emplacements prévus sont créés.
- [ ] Chaque emplacement est **activé**.
- [ ] Chaque emplacement a une **image** (pas d'emplacement sans visuel).
- [ ] Chaque emplacement a un **titre** dans toutes les langues actives.
- [ ] Les descriptions sont rédigées et relues pour chaque langue.
- [ ] Les entrées liées (« En savoir plus ») pointent vers les bonnes pages.
- [ ] Les textes de lien sont adaptés au contexte (pas tous « En savoir plus »).

### Textes d'interface

- [ ] Le **nom de l'organisation** est saisi pour chaque langue.
- [ ] Le **titre de la campagne** est saisi pour chaque langue.
- [ ] Les **instructions de scan** sont claires et adaptées au public.
- [ ] Les messages des modales (tirage, cadeau) sont rédigés si ces fonctions sont activées.
- [ ] L'**avertissement légal** est à jour si la fonctionnalité est activée.

### Règles du concours

- [ ] Les règles sont saisies pour chaque langue où un concours est en cours.
- [ ] Le **texte du bouton** est clair (ex. : « Règles et modalités »).
- [ ] Si une page de règles complètes existe, elle est liée correctement.

### Vérification finale

- [ ] La page publique a été testée dans chaque langue sur un téléphone mobile.
- [ ] Les images se chargent correctement.
- [ ] Les liens de chaque emplacement fonctionnent.

---

## 9. Questions fréquentes

**Un emplacement que j'ai créé n'apparaît pas sur la page publique. Pourquoi ?**

Vérifiez deux choses : l'interrupteur **Activé** est bien en position active (bleu), et le champ **Titre** est rempli pour la langue concernée. Un emplacement sans titre ou désactivé ne s'affiche pas.

**J'ai oublié de sauvegarder avant de changer de langue. Est-ce que j'ai perdu mes modifications ?**

Non. Quand vous changez de site via le sélecteur, le plugin enregistre automatiquement le formulaire en cours avant de basculer.

**Les textes d'interface que j'ai saisis ne s'affichent pas sur le site. Que faire ?**

Assurez-vous d'avoir enregistré après vos modifications. Vérifiez aussi que vous avez bien sélectionné le bon site/langue dans le sélecteur avant de saisir.

**Comment savoir si un texte est en version par défaut ou personnalisé ?**

Si le champ est vide dans l'interface, c'est la valeur par défaut qui s'affiche sur le site. Si vous avez saisi quelque chose, c'est votre texte personnalisé qui est utilisé.

**Je veux supprimer un texte personnalisé et revenir au texte par défaut. Comment faire ?**

Effacez simplement le contenu du champ et enregistrez. Le plugin reprendra automatiquement le texte par défaut.

**Un visiteur signale que le texte de confirmation après un scan est en anglais sur le site français. Que faire ?**

Allez dans **Textes d'interface**, sélectionnez le site français, et remplissez le champ **Enregistrement réussi** avec votre texte en français. Si ce champ est vide, le plugin utilise son texte par défaut qui devrait être en français — si ce n'est pas le cas, contactez votre administrateur.

**Je veux modifier l'ordre des emplacements dans le passeport. Est-ce que je peux le faire ?**

Oui. Depuis la liste des emplacements, cliquez-déposez la poignée (≡) à gauche de chaque ligne pour changer l'ordre. L'ordre de la liste correspond à l'ordre d'affichage sur la page publique.

> 📸 **Image à insérer :** Liste des emplacements avec une flèche indiquant la poignée de réorganisation à gauche d'une ligne.

![Poignée de réorganisation des emplacements dans la liste](images/items-reorder-handle.png)

**Je ne trouve pas le champ pour modifier le logo ou les couleurs du passeport. Où est-il ?**

Ces éléments sont gérés dans **Paramètres → Apparence** et sont réservés aux administrateurs. Si vous avez besoin de modifier l'identité visuelle, contactez l'administrateur de votre site.

---

*Pour toute question sur la configuration technique ou l'apparence du passeport, adressez-vous à votre administrateur Craft CMS.*
