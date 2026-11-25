<?php

declare(strict_types=1);

namespace Convaincre\Connect;

use DomainException;

final class Payload
{
    private const CONTACT_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'postal_code',
        'municipality_code',
    ];

    public static function build(
        array $binding,
        array $raw,
        string $externalSubmissionId,
        string $submittedAt
    ): array {
        self::validateBinding($binding);
        $schema = $binding['field_schema'];
        $contactInput = is_array($raw['contact'] ?? null) ? $raw['contact'] : [];
        $answerInput = is_array($raw['answers'] ?? null) ? $raw['answers'] : [];
        $consentInput = is_array($raw['consents'] ?? null) ? $raw['consents'] : [];

        $allowedContact = array_values($schema['contact'] ?? []);
        $unknownContact = array_diff(array_keys($contactInput), $allowedContact);
        if ($unknownContact !== []) {
            throw new DomainException('unexpected_contact_field');
        }
        $allowedAnswers = array_values($schema['answers'] ?? []);
        $unknownAnswers = array_diff(array_keys($answerInput), $allowedAnswers);
        if ($unknownAnswers !== []) {
            throw new DomainException('unexpected_answer_field');
        }

        $contact = [];
        foreach ($allowedContact as $field) {
            if (! in_array($field, self::CONTACT_FIELDS, true)) {
                throw new DomainException('binding_contact_field_invalid');
            }
            $value = $contactInput[$field] ?? '';
            if (! is_scalar($value) && $value !== null) {
                throw new DomainException('contact_value_invalid');
            }
            $value = trim((string) $value);
            if (strlen($value) > 1000) {
                throw new DomainException('contact_value_too_long');
            }
            if ($value !== '') {
                $contact[$field] = $value;
            }
        }

        foreach ($schema['required_contact'] ?? [] as $required) {
            if (($contact[$required] ?? '') === '') {
                throw new DomainException('required_contact_missing');
            }
        }

        $answers = [];
        foreach ($allowedAnswers as $field) {
            $value = $answerInput[$field] ?? null;
            if (is_array($value)) {
                if (count($value) > 100) {
                    throw new DomainException('answer_too_many_values');
                }
                $clean = [];
                foreach ($value as $item) {
                    if (! is_scalar($item) && $item !== null) {
                        throw new DomainException('answer_value_invalid');
                    }
                    $clean[] = trim((string) $item);
                }
                $answers[$field] = $clean;
                continue;
            }
            if ($value !== null && ! is_scalar($value)) {
                throw new DomainException('answer_value_invalid');
            }
            $clean = trim((string) ($value ?? ''));
            if (strlen($clean) > 5000) {
                throw new DomainException('answer_value_too_long');
            }
            if ($clean !== '') {
                $answers[$field] = $clean;
            }
        }

        $visibleQuestions = [];
        foreach ($schema['questions'] ?? [] as $question) {
            $key = $question['key'];
            $condition = $question['show_if'] ?? null;
            if (is_array($condition) && (! isset($visibleQuestions[$condition['question']]) || ($answers[$condition['question']] ?? null) !== $condition['value'])) {
                if (isset($answers[$key]) && $answers[$key] !== '' && $answers[$key] !== []) {
                    throw new DomainException('hidden_answer');
                }
                unset($answers[$key]);
                continue;
            }
            $visibleQuestions[$key] = true;
            if (is_array($condition) && ! empty($question['required']) && (! isset($answers[$key]) || $answers[$key] === [] || $answers[$key] === '')) {
                throw new DomainException('required_answer_missing');
            }
        }

        foreach ($schema['required_answers'] ?? [] as $required) {
            if (! array_key_exists($required, $answers) || $answers[$required] === '' || $answers[$required] === []) {
                throw new DomainException('required_answer_missing');
            }
        }

        $consents = [];
        foreach ($binding['consent_schema'] ?? [] as $index => $requirement) {
            if (! is_array($requirement)
                || ! isset($requirement['method'], $requirement['purpose'], $requirement['wording_version'])
            ) {
                throw new DomainException('binding_consent_invalid');
            }
            $granted = isset($consentInput[(string) $index]) || isset($consentInput[$index]);
            if (! empty($requirement['required']) && ! $granted) {
                throw new DomainException('required_consent_missing');
            }
            $consents[] = [
                'method' => (string) $requirement['method'],
                'purpose' => (string) $requirement['purpose'],
                'granted' => $granted,
                'wording_version' => (int) $requirement['wording_version'],
            ];
        }

        return [
            'external_submission_id' => $externalSubmissionId,
            'binding_key' => (string) $binding['binding_key'],
            'binding_version' => (int) $binding['version'],
            'submitted_at' => $submittedAt,
            'contact' => $contact,
            'answers' => $answers,
            'consents' => $consents,
            'explicit_opposition' => false,
        ];
    }

    private static function validateBinding(array $binding): void
    {
        if (! isset($binding['binding_key'], $binding['version'], $binding['field_schema'])) {
            throw new DomainException('binding_invalid');
        }
        if (isset($binding['organization_id']) || isset($binding['territory_id'])) {
            throw new DomainException('routing_boundary_violation');
        }
    }
}
