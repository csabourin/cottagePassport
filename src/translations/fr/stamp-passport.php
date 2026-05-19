<?php

/**
 * French translations for the Stamp Passport plugin.
 *
 * Covers all strings using |t('stamp-passport') across CP and frontend templates.
 */

return [
    // ── Frontend: index.twig ──
    'Language' => 'Langue',
    'Details' => 'Détails',
    'Learn more' => 'En savoir plus',
    'Progress' => 'Progression',
    'Close' => 'Fermer',
    'Reopen draw entry form' => 'Ré-ouvrir le formulaire du tirage',
    'Reopen sticker request form' => 'Ré-ouvrir le formulaire d\'autocollant',

    // ── CP: items/index.twig ──
    'Items' => 'Éléments',
    'New Item' => 'Nouvel élément',
    'Title' => 'Titre',
    'Short Code' => 'Code court',
    'Coordinates' => 'Coordonnées',
    'Enabled' => 'Activé',
    'Not set' => 'Non défini',
    'No items yet.' => 'Aucun élément pour l\'instant.',
    'Create one' => 'Créer un élément',
    'Display Text' => 'Textes d\'affichage',
    'Customize all display text per site. Leave fields blank to use built-in defaults for that site\'s language.' => 'Personnalisez tous les textes d\'affichage par site. Laissez les champs vides pour utiliser les valeurs par défaut de la langue du site.',
    'Header' => 'En-tête',
    'Organization name displayed in the page header. Use new lines for multiple lines.' => 'Nom de l\'organisation affiché dans l\'en-tête de la page. Utilisez des sauts de ligne pour plusieurs lignes.',
    'Bold title shown after the header logo.' => 'Titre en gras affiché après le logo de l\'en-tête.',
    'Instruction text shown below the header.' => 'Texte d\'instructions affiché sous l\'en-tête.',
    'Draw Modal' => 'Fenêtre du tirage',
    'Sticker Modal' => 'Fenêtre de l\'autocollant',
    'Disclaimer Modal' => 'Fenêtre d\'avertissement',
    'Status Messages' => 'Messages d\'état',
    'Save Display Text' => 'Enregistrer les textes d\'affichage',
    'Are you sure you want to delete this item?' => 'Êtes-vous sûr de vouloir supprimer cet élément?',

    // ── CP: items/_edit.twig ──
    'Auto-generated. Used in QR code URLs.' => 'Généré automatiquement. Utilisé dans les URL des codes QR.',
    'Latitude' => 'Latitude',
    'Longitude' => 'Longitude',
    'Image' => 'Image',
    'Location Image' => 'Image de l\'emplacement',
    'Content' => 'Contenu',
    'Description' => 'Description',
    'Link URL' => 'URL du lien',
    'Link Text' => 'Texte du lien',

    // ── CP: _settings-fields.twig ──
    'General' => 'Général',
    'Route Prefix' => 'Préfixe de route',
    'URL prefix for the frontend page (e.g. "passport" -> /passport).' => 'Préfixe d\'URL pour la page publique (ex. « passport » -> /passport).',
    'Geofence' => 'Géorepérage',
    'Enable Geofence' => 'Activer le géorepérage',
    'When enabled, visitors must be within the configured radius of a location to check in.' => 'Lorsqu\'activé, les visiteurs doivent se trouver dans le rayon configuré d\'un emplacement pour s\'enregistrer.',
    'Geofence Radius (metres)' => 'Rayon de géorepérage (mètres)',
    'Prizes' => 'Prix',
    'Draw Threshold' => 'Seuil du tirage',
    'Number of stamps required before the draw entry form appears.' => 'Nombre d\'étampes requis avant l\'affichage du formulaire du tirage.',
    'Max Stickers' => 'Autocollants maximum',
    'Maximum number of sticker prizes available (0 = unlimited).' => 'Nombre maximum d\'autocollants disponibles (0 = illimité).',
    'Freeform Integration' => 'Intégration Freeform',
    'Draw Form' => 'Formulaire du tirage',
    'Freeform form shown when the visitor qualifies for the draw.' => 'Formulaire Freeform affiché lorsque le visiteur est admissible au tirage.',
    '— None —' => '— Aucun —',
    'Sticker Form' => 'Formulaire de l\'autocollant',
    'Freeform form shown when the visitor completes every location.' => 'Formulaire Freeform affiché lorsque le visiteur a complété tous les emplacements.',
    'Draw Form Handle' => 'Handle du formulaire de tirage',
    'Freeform form handle for the end-of-season draw entry.' => 'Handle du formulaire Freeform pour le tirage de fin de saison.',
    'Sticker Form Handle' => 'Handle du formulaire d\'autocollant',
    'Freeform form handle for the sticker request.' => 'Handle du formulaire Freeform pour la demande d\'autocollant.',
    'Appearance' => 'Apparence',
    'Header Logo' => 'Logo de l\'en-tête',
    'Circular image displayed at the top of the page header. Recommended: square PNG with transparent background.' => 'Image circulaire affichée en haut de l\'en-tête. Recommandé : PNG carré avec fond transparent.',
    'Header Wood Panel' => 'Panneau de bois de l\'en-tête',
    'Background image for the page header. If not set, a CSS wood-grain texture is used as fallback.' => 'Image d\'arrière-plan pour l\'en-tête de la page. Si non défini, une texture boisée CSS est utilisée.',
    'Checked Marker Image' => 'Image du marqueur validé',
    'Optional image shown when a location is checked. If not set, a default checkmark badge is used.' => 'Image optionnelle affichée lorsqu\'un emplacement est validé. Si non défini, un badge coche par défaut est utilisé.',
    'Body Background Image' => 'Image d\'arrière-plan du corps',
    'Optional image used as the page body background. If not set, the default pattern is used.' => 'Image optionnelle utilisée comme arrière-plan du corps de la page. Si non défini, le motif par défaut est utilisé.',
    'Body Background Mode' => 'Mode d\'arrière-plan du corps',
    'How the body background image should render.' => 'Mode d\'affichage de l\'image d\'arrière-plan du corps.',
    'Cover' => 'Couverture',
    'Tiled' => 'Mosaïque',
    'Custom size' => 'Taille personnalisée',
    'Custom Background Size' => 'Taille d\'arrière-plan personnalisée',
    'CSS background-size value (e.g. 800px, 50%, contain).' => 'Valeur CSS background-size (ex. 800px, 50%, contain).',
    'Background Color' => 'Couleur d\'arrière-plan',
    'Fill color shown behind the background image (not applicable for tiled mode).' => 'Couleur de remplissage affichée derrière l\'image d\'arrière-plan (non applicable en mode mosaïque).',
    'QR Center Image URL' => 'URL de l\'image centrale du QR',
    'Optional URL for the image centered in generated QR codes. A square image or circle with transparency is preferable.' => 'URL optionnelle pour l\'image centrée dans les codes QR générés. Une image carrée ou un cercle avec transparence est préférable.',
    'Analytics' => 'Analytique',
    'GA4 Measurement ID' => 'ID de mesure GA4',
    'Google Analytics 4 measurement ID (e.g. G-XXXXXXXXXX). Leave blank to disable.' => 'ID de mesure Google Analytics 4 (ex. G-XXXXXXXXXX). Laisser vide pour désactiver.',

    // ── CP: settings.twig ──
    'Settings' => 'Paramètres',

    // ── CP: qr-generator.twig ──
    'QR Codes' => 'Codes QR',
    'Base URL' => 'URL de base',
    'The site URL where the passport frontend lives. QR codes will encode {baseUrl}?q={shortCode}.' => 'L\'URL du site où se trouve la page du passeport. Les codes QR encoderont {baseUrl}?q={shortCode}.',
    'Print All QR Codes' => 'Imprimer tous les codes QR',

    // ── JS error prefix (passed via config) ──
    'Error: ' => 'Erreur : ',
];
