<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Deterministic draft builder, not a legal opinion or a consent manager. */
final class SiteLegal
{
    public const FIELDS = [
        'legal' => [
            'publisher'=>['Éditeur du site : nom ou dénomination','Précisez la personne ou l’organisme réellement responsable de l’édition.', false],
            'publisher_details'=>['Coordonnées et identification de l’éditeur','Adresse, contact et informations d’identification applicables à votre situation. Vérifiez les règles de publication de ces coordonnées.', true],
            'director'=>['Responsable de la publication','Nom de la personne responsable de la publication.', false],
            'host'=>['Hébergeur : nom et coordonnées','Nom, adresse et téléphone de l’hébergeur, à retrouver sur votre contrat d’hébergement.', true],
        ],
        'privacy' => [
            'controller'=>['Responsable du traitement et coordonnées','Qui décide de l’utilisation des données ? Indiquez également comment le joindre.', true],
            'activities'=>['Données, objectifs et fondements juridiques','Pour chaque traitement (formulaires, journaux techniques, etc.), indiquez les données, la finalité, la base légale et, si nécessaire, la condition applicable aux données sensibles (article 9 du RGPD). Précisez les champs obligatoires et les conséquences d’une absence de réponse.', true],
            'recipients'=>['Destinataires des données','Indiquez les catégories de destinataires, prestataires et accès autorisés réellement concernés, dont Convaincre si utilisé.', true],
            'retention'=>['Durées de conservation','Indiquez les durées ou critères par finalité. Ne choisissez pas une durée unique par défaut.', true],
            'rights_contact'=>['Contact pour exercer ses droits','Adresse électronique ou postale dédiée ; coordonnées du délégué à la protection des données si applicable.', true],
            'additional'=>['Informations complémentaires applicables','Transferts hors EEE et garanties, profilage/décision automatisée, traceurs, sources indirectes et autres informations nécessaires. Indiquez « Non applicable » uniquement après vérification.', true],
        ],
    ];
    public static function clean(array $input): array
    {
        $out = [];
        foreach (self::FIELDS as $group=>$fields) {
            foreach ($fields as $key=>$spec) {
                $value = $input[$group][$key] ?? '';
                $out[$group][$key] = is_string($value) ? sanitize_textarea_field($value) : '';
            }
        }
        return $out;
    }
    public static function missing(string $kind, array $fields): array
    {
        if (!isset(self::FIELDS[$kind])) { return ['document']; }
        return array_keys(array_filter(self::FIELDS[$kind], static fn($spec, $key) => !is_string($fields[$key] ?? null) || trim($fields[$key]) === '', ARRAY_FILTER_USE_BOTH));
    }
    public static function draft(string $kind, array $fields): ?string
    {
        if (self::missing($kind, $fields)) { return null; }
        $v = static fn(string $k): string => trim($fields[$k]);
        if ($kind === 'legal') {
            return "Édition du site\n" . $v('publisher') . "\n" . $v('publisher_details')
                . "\n\nResponsable de la publication\n" . $v('director')
                . "\n\nHébergement\n" . $v('host');
        }
        return "Responsable du traitement\n" . $v('controller')
            . "\n\nTraitements de données personnelles\n" . $v('activities')
            . "\n\nDestinataires\n" . $v('recipients')
            . "\n\nConservation des données\n" . $v('retention')
            . "\n\nVos droits\nSelon le traitement et les conditions prévues par le RGPD, vous pouvez demander l’accès, la rectification, l’effacement ou la limitation du traitement de vos données, exercer votre droit d’opposition ou de portabilité, et retirer votre consentement lorsque le traitement repose sur celui-ci."
            . "\nPour exercer vos droits : " . $v('rights_contact')
            . "\nVous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr)."
            . "\n\nInformations complémentaires\n" . $v('additional');
    }
}
