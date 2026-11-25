<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Fixed presentation choices; no user-defined CSS, external fonts or scripts. */
final class SiteAppearance
{
    public const MODELS = [
        'editorial'=>['Éditorial','Grand titre, composition éditoriale, photographie et rubriques à filets.','split'],
        'classic'=>['Classique','Masthead centré, papier crème, titres à empattements.','split'],
        'minimal'=>['Minimaliste','Blancs généreux, typographie légère et lignes fines.','stacked'],
        'modern'=>['Contemporain','Grand conteneur arrondi, boutons et cartes structurées.','split'],
        'photo'=>['Photographique','Photographie immersive, texte superposé et contraste maîtrisé.','stacked'],
        'poster'=>['Affiche','Titres monumentaux, formes franches, style affiche.','split'],
        'local'=>['Territorial','Tons naturels, photo encadrée, composition chaleureuse.','reverse'],
        'magazine'=>['Magazine','Masthead de revue, titres serif et grille éditoriale.','split'],
    ];
    public const FONTS = ['model'=>'Selon le modèle','sans'=>'Sans empattements','serif'=>'Avec empattements','rounded'=>'Arrondie'];
    public const LAYOUTS = ['model'=>'Selon le modèle','split'=>'Texte à gauche, photo à droite','reverse'=>'Photo à gauche, texte à droite','stacked'=>'Texte puis grande photo'];
    public const NAV = ['team'=>'Notre équipe','project'=>'Notre projet','territory'=>'Notre territoire','news'=>'Actualités','agenda'=>'Agenda','forms'=>'Nous suivre'];
    public static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? strtolower($value) : $fallback;
    }
    public static function order(mixed $value): array
    {
        $ordered = [];
        if (is_array($value)) {
            foreach ($value as $key) {
                if (is_string($key) && isset(self::NAV[$key]) && $key !== 'forms' && !in_array($key, $ordered, true)) { $ordered[] = $key; }
            }
        }
        return array_merge($ordered, array_values(array_diff(array_keys(self::NAV), $ordered)));
    }
    public static function normalize(array $input): array
    {
        $model = $input['model'] ?? '';
        $font = $input['font'] ?? '';
        $layout = $input['layout'] ?? '';
        return [
            'model'=>is_string($model) && isset(self::MODELS[$model]) ? $model : 'editorial',
            'color'=>self::color($input['color'] ?? null, '#354d63'),
            'secondary'=>self::color($input['secondary'] ?? null, '#e9eef2'),
            'font'=>is_string($font) && isset(self::FONTS[$font]) ? $font : 'model',
            'layout'=>is_string($layout) && isset(self::LAYOUTS[$layout]) ? $layout : 'model',
            'nav_order'=>self::order($input['nav_order'] ?? []),
        ];
    }
    public static function layout(array $appearance): string
    {
        return $appearance['layout'] === 'model' ? self::MODELS[$appearance['model']][2] : $appearance['layout'];
    }
}
