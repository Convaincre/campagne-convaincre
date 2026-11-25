# Campagne Convaincre

Extension WordPress libre de création et de gestion de sites de campagne politique. Elle fonctionne de manière autonome, sans compte Convaincre. La connexion à la plateforme est facultative et permet de synchroniser les formulaires et l'agenda.

**Version du code : 1.0.5.** Candidature à la publication sur WordPress.org ; l'acceptation officielle n'est pas encore confirmée.

## Fonctionnalités

- Huit modèles graphiques responsives, couleurs et typographies personnalisables.
- Identité, équipe, projet, territoire, actualités, dates électorales, réseaux sociaux et logos de soutien.
- Assistant de création, aperçu privé, brouillons et publication par rubrique.
- Statistiques locales facultatives sans identifiants de visiteurs.
- Formulaires et agenda connectés à Convaincre en option.

## Installation

WordPress 6.4 ou ultérieur, PHP 8.1 ou ultérieur. OpenSSL est nécessaire pour les fonctions connectées. Utilisez un WordPress de test et sauvegardez le site avant installation ou migration.

1. Clonez le dépôt, puis exécutez `bash build.sh` pour obtenir une archive installable dans `dist/`.
2. Dans WordPress, ouvrez Extensions > Ajouter > Téléverser une extension. Installez le ZIP puis activez-le.
3. Ouvrez Campagne Convaincre > Mon site internet. Configurez le site, prévisualisez-le et publiez-le.
4. Facultatif : configurez la connexion à une instance Convaincre autorisée.

Ne faites pas cohabiter une installation historique `convaincre-connect` et `campagne-convaincre` : elles partagent les mêmes données. Pour une migration, sauvegardez le site et désactivez l'ancienne extension sans la désinstaller.

## Contribuer

Les rapports de bugs, propositions, améliorations graphiques, corrections d'accessibilité, de sécurité et de traduction sont les bienvenus. Voir [CONTRIBUTING.md](CONTRIBUTING.md). Utilisez les Issues pour les sujets non sensibles et les pull requests pour proposer des modifications.

Le dépôt contient uniquement le plugin WordPress, pas le code serveur de Convaincre. Les interfaces de connexion doivent rester compatibles avec le service.

## Confidentialité et licence

Consultez [readme.txt](readme.txt) pour les services externes, les données traitées et les conditions d'utilisation. La connexion est volontaire. Ne publiez jamais de données de visiteurs, de secrets ou de formulaires réels dans une issue.

Code original : GPL-2.0-or-later ([LICENSE.txt](LICENSE.txt)). Les pictogrammes sociaux Font Awesome Free 6.7.2 sont sous CC BY 4.0 ; voir [NOTICE](assets/social/NOTICE.txt). Les marques restent la propriété de leurs titulaires.

Site officiel : [convainc.re](https://convainc.re/).
