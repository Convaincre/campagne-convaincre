# Contribuer à Campagne Convaincre

Les contributions en français et en anglais sont les bienvenues.

## Proposer une amélioration

1. Ouvrez une issue pour expliquer le problème ou la fonctionnalité souhaitée. Pour un bug, indiquez les versions de WordPress, PHP et du plugin, les étapes de reproduction, le résultat attendu et le résultat constaté.
2. Créez un fork et une branche dédiée. Limitez chaque pull request à un sujet.
3. Testez sur une installation WordPress de développement, avec des données fictives.
4. Décrivez les changements, tests et limites dans la pull request. Ajoutez des captures pour les modifications visuelles.

## Repères techniques

- `campagne-convaincre.php` : initialisation du plugin.
- `includes/` : logique PHP, publication, configuration et connexion facultative.
- `templates/site.php` : rendu public.
- `assets/` : CSS, JavaScript et pictogrammes locaux.
- `readme.txt` : fiche de distribution WordPress.org, en anglais.

## Vérifications avant une PR

- Syntaxe PHP : `find includes templates -name '*.php' -exec php -l {} \;`, puis `php -l campagne-convaincre.php` et `php -l uninstall.php`.
- Syntaxe JavaScript : `find assets -name '*.js' -exec node --check {} \;`.
- Construisez l'archive avec `bash build.sh`, installez-la dans WordPress et lancez Plugin Check.
- Vérifiez le mode autonome, la création et la publication du site, les brouillons, les modifications par rubrique, les thèmes et le rendu mobile.
- Pour les fonctions connectées, utilisez une instance et des identifiants de test autorisés ; ne modifiez pas le contrat API sans coordination.
- Respectez les nonces, capacités WordPress, validations et échappements ; utilisez les API WordPress et préservez les données existantes.
- Le domaine de traduction public doit rester `campagne-convaincre`. Ne renommez pas les options, tables, shortcodes ou routes historiques sans plan de migration.

Les contributions au code original sont proposées sous GPL-2.0-or-later. Ne soumettez que des ressources dont vous disposez des droits nécessaires et indiquez leur licence.

Pour une vulnérabilité, suivez [SECURITY.md](SECURITY.md), pas une issue publique.
