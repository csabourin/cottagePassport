# Passeport-étampes — Prochaines étapes (après la première campagne)

> Document de planification destiné à la gestion de projet. Il regroupe les
> améliorations recommandées pour la prochaine campagne, classées par priorité,
> ainsi que les indicateurs de succès à surveiller. Aucune de ces fonctionnalités
> ne modifie les contrats d'API ni le protocole de synchronisation existants.

---

## 1. Vision

La première campagne valide la mécanique de base : scanner un code QR, valider
l'emplacement et suivre sa progression. L'objectif de la prochaine itération est
de transformer une **liste à cocher** en une **expérience de découverte** : aider
le visiteur à planifier ses sorties, récompenser sa progression, et donner aux
gestionnaires des données réellement exploitables.

---

## 2. Améliorations pour le visiteur (expérience mobile)

### 2.1 — Vue carte des emplacements `[Priorité : ÉLEVÉE]`
- **Quoi** : afficher une carte avec les emplacements, la position du visiteur
  (« vous êtes ici »), la distance jusqu'à chaque lieu et un lien « itinéraire ».
- **Pourquoi** : les coordonnées (latitude/longitude) sont déjà saisies pour chaque
  emplacement, mais ne servent aujourd'hui qu'à la validation invisible du
  géorepérage. Les exposer répond à la vraie question du visiteur — « qu'est-ce qui
  est près de moi maintenant? » — et encourage les déplacements réels.
- **Effort estimé** : moyen. **Impact attendu** : élevé.

### 2.2 — Badges et jalons de progression `[Priorité : ÉLEVÉE]`
- **Quoi** : récompenses symboliques (« Première étampe », « À mi-chemin »,
  « Trois en une journée », « Passeport complété »).
- **Pourquoi** : le champ `badges` existe déjà dans la structure de données mais
  n'est pas utilisé. Donne aux visiteurs récurrents une raison de revenir au-delà
  du simple décompte.
- **Effort estimé** : faible à moyen. **Impact attendu** : élevé.

### 2.3 — Moment de validation valorisant `[Priorité : MOYENNE]`
- **Quoi** : animation d'« étampage », vibration (retour haptique) et/ou son lors
  d'une validation réussie.
- **Pourquoi** : transforme l'action en moment gratifiant plutôt qu'en simple coche.
- **Effort estimé** : faible. **Impact attendu** : moyen.

### 2.4 — Rareté et compte à rebours `[Priorité : MOYENNE]`
- **Quoi** : afficher « Il reste X autocollants » et « La campagne se termine dans
  X jours ».
- **Pourquoi** : `maxStickers` et la date de fin sont déjà connus; la rareté et
  l'urgence sont des leviers de motivation éprouvés.
- **Effort estimé** : faible. **Impact attendu** : moyen.

### 2.5 — Carte de réussite partageable `[Priorité : MOYENNE]`
- **Quoi** : à la complétion, générer une image partageable (« J'ai complété le
  défi! ») pour les réseaux sociaux.
- **Pourquoi** : marketing gratuit; transforme les participants en ambassadeurs.
  S'appuie sur les métadonnées Open Graph déjà en place.
- **Effort estimé** : moyen. **Impact attendu** : moyen.

### 2.6 — Application installable (PWA) et rappels `[Priorité : MOYENNE]`
- **Quoi** : ajout à l'écran d'accueil, fonctionnement hors-ligne, et notifications
  de réengagement (« 3 étampes restantes, une semaine avant la fin »).
- **Pourquoi** : pour une activité extérieure réalisée sur plusieurs visites, c'est
  le mécanisme qui ramène les gens pour une 2e sortie.
- **Effort estimé** : moyen à élevé. **Impact attendu** : moyen.

### 2.7 — Mode chasse au trésor (indices) `[Priorité : FAIBLE / optionnel]`
- **Quoi** : présenter le prochain emplacement sous forme d'énigme plutôt qu'une
  liste plate.
- **Pourquoi** : engagement beaucoup plus élevé, mais demande un effort de contenu
  important. À considérer comme une option saisonnière.
- **Effort estimé** : élevé (contenu). **Impact attendu** : élevé mais ciblé.

---

## 3. Améliorations pour le gestionnaire (tableau de bord Craft)

### 3.1 — Outil de tirage d'un gagnant `[Priorité : ÉLEVÉE]`
- **Quoi** : bouton pour sélectionner aléatoirement un participant admissible, lié
  aux soumissions Freeform pour récupérer les coordonnées en un clic.
- **Pourquoi** : le nombre de participants admissibles (`qualifyDraw`) est déjà
  calculé; le tirage est probablement fait manuellement aujourd'hui.
- **Effort estimé** : faible à moyen. **Impact attendu** : élevé.

### 3.2 — Entonnoir de conversion `[Priorité : ÉLEVÉE]`
- **Quoi** : visualiser le parcours — vues → première étampe → seuil atteint →
  formulaire soumis → passeport complété.
- **Pourquoi** : répond à « où perd-on les visiteurs? » et « le géorepérage est-il
  trop strict? ». Plus utile que les totaux bruts actuels.
- **Effort estimé** : moyen. **Impact attendu** : élevé.

### 3.3 — Performance par emplacement `[Priorité : MOYENNE]`
- **Quoi** : classer les emplacements et signaler les moins performants
  (faible nombre de validations).
- **Pourquoi** : `locationCounts` est déjà compilé. Donne des actions concrètes
  (signalisation, code QR défraîchi, lieu difficile à trouver).
- **Effort estimé** : faible. **Impact attendu** : moyen.

### 3.4 — Carte de chaleur des validations `[Priorité : MOYENNE]`
- **Quoi** : combiner coordonnées et données temporelles (`last7Days`,
  `weekdayCounts`) pour montrer où et quand l'activité se produit.
- **Pourquoi** : utile pour la dotation en personnel et la planification des
  emplacements de la prochaine saison.
- **Effort estimé** : moyen. **Impact attendu** : moyen.

### 3.5 — Programmation des emplacements et mode test `[Priorité : MOYENNE]`
- **Quoi** : activer/désactiver des emplacements par date (échanges saisonniers) et
  un mode « test » qui contourne le géorepérage pour le personnel.
- **Pourquoi** : réduit le travail manuel et facilite la validation des codes QR
  depuis le bureau.
- **Effort estimé** : faible à moyen. **Impact attendu** : moyen.

### 3.6 — Tableau de bord d'intégrité `[Priorité : FAIBLE]`
- **Quoi** : signaler les complétions suspectes (toutes les étampes en moins d'une
  minute, déplacements impossibles).
- **Pourquoi** : protège la crédibilité du concours si des prix réels sont en jeu.
- **Effort estimé** : moyen. **Impact attendu** : faible à moyen (selon les enjeux).

---

## 4. Recommandation de priorisation (3 premières)

1. **Vue carte** (visiteur) — les données existent, sert directement l'objectif de
   visite réelle, plus grand gain d'expérience.
2. **Badges + moment de validation** (visiteur) — peu coûteux, le champ existe déjà,
   rend la boucle gratifiante.
3. **Tirage d'un gagnant + entonnoir** (gestionnaire) — fait passer le tableau de
   bord de simples statistiques à un véritable outil de gestion de campagne.

---

## 5. Indicateurs de succès à surveiller (prochaine campagne)

### Acquisition et activation
- **Taux d'activation** : % des visiteurs qui valident au moins une étampe
  (vues de page → première validation). *Indicateur santé du parcours d'entrée.*
- **Nombre de participants uniques** (`totalVisitors`).

### Engagement et rétention
- **Étampes moyennes par participant** (`totalScans` / `totalVisitors`).
- **Taux de visiteurs récurrents** : % revenant sur plus d'un jour
  (mesure l'effet des rappels/PWA et des badges).
- **Délai médian de complétion** : temps entre la 1re et la dernière étampe.

### Conversion (entonnoir)
- **Taux d'atteinte du seuil** : % atteignant `drawThreshold`.
- **Taux de soumission au tirage** : % d'admissibles qui soumettent le formulaire.
- **Taux de complétion** : % validant tous les emplacements.

### Performance des emplacements
- **Écart de validations entre emplacements** (le plus fort vs le plus faible)
  pour cibler la signalisation et le placement.
- **Répartition jour/heure des validations** pour la planification opérationnelle.

### Effet viral et notoriété (si 2.5 / 2.6 livrés)
- **Nombre de partages / cartes de réussite générées.**
- **Installations PWA** et **taux d'ouverture des rappels.**

### Cibles suggérées (à valider avec les données de la 1re campagne)
> Établir la *référence (baseline)* à partir des résultats actuels, puis viser une
> amélioration relative plutôt qu'un chiffre absolu :
- Taux d'activation : **+10 points** vs référence.
- Taux de complétion : **+15 %** relatif vs référence.
- Visiteurs récurrents : **+20 %** relatif vs référence.
