<?php

namespace App\Services;

use App\Contracts\PersonRepositoryInterface;
use App\Contracts\PersonResolverInterface;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchCandidateDTO;
use App\DTO\PersonMatchResultDTO;
use App\DTO\ResolvedPersonDTO;
use App\Models\Person;
use App\Models\PersonAlias;

class PersonResolver implements PersonResolverInterface
{
    public function __construct(
        private readonly PersonRepositoryInterface $personRepository,
        private readonly PersonNameNormalizer $normalizer,
    ) {}

    public function resolve(NormalizedMentionDTO $mention): PersonMatchResultDTO
    {
        $searchFields = $this->extractSearchFields($mention);

        if ($searchFields === []) {
            return new PersonMatchResultDTO(resolvedPerson: null, isAmbiguous: false, candidates: []);
        }

        $persons = $this->personRepository->listByProject($mention->projectId);
        $candidates = [];

        foreach ($persons as $person) {
            $bestMatch = $this->findBestMatchForPerson($person, $searchFields);

            if ($bestMatch !== null) {
                $candidates[] = $bestMatch;
            }
        }

        usort(
            $candidates,
            fn (PersonMatchCandidateDTO $left, PersonMatchCandidateDTO $right): int => $right->confidence <=> $left->confidence,
        );

        return $this->buildResult($candidates);
    }

    /**
     * @param  array<string, string>  $searchFields
     */
    private function findBestMatchForPerson(Person $person, array $searchFields): ?PersonMatchCandidateDTO
    {
        $bestMatch = null;

        foreach ($person->aliases as $alias) {
            foreach ($searchFields as $fieldName => $fieldText) {
                if (! $this->containsAlias($fieldText, $alias->normalized_alias)) {
                    continue;
                }

                $candidate = new PersonMatchCandidateDTO(
                    personId: $person->id,
                    personUuid: $person->uuid,
                    fullName: $person->full_name,
                    matchedAlias: $alias->alias,
                    matchType: $alias->type,
                    confidence: $alias->type->matchConfidence(),
                    matchedIn: $fieldName,
                );

                if ($bestMatch === null || $candidate->confidence > $bestMatch->confidence) {
                    $bestMatch = $candidate;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * @return array<string, string>
     */
    private function extractSearchFields(NormalizedMentionDTO $mention): array
    {
        $fields = [];

        if (is_string($mention->author) && trim($mention->author) !== '') {
            $fields['author'] = $this->normalizer->normalize($mention->author);
        }

        if (is_string($mention->title) && trim($mention->title) !== '') {
            $fields['title'] = $this->normalizer->normalize($mention->title);
        }

        if (trim($mention->text) !== '') {
            $fields['content'] = $this->normalizer->normalize($mention->text);
        }

        return $fields;
    }

    private function containsAlias(string $fieldText, string $normalizedAlias): bool
    {
        if ($normalizedAlias === '') {
            return false;
        }

        if ($fieldText === $normalizedAlias) {
            return true;
        }

        $pattern = '/(?<!\p{L})'.preg_quote($normalizedAlias, '/').'(?!\p{L})/u';

        return (bool) preg_match($pattern, $fieldText);
    }

    /**
     * @param  list<PersonMatchCandidateDTO>  $candidates
     */
    private function buildResult(array $candidates): PersonMatchResultDTO
    {
        if ($candidates === []) {
            return new PersonMatchResultDTO(resolvedPerson: null, isAmbiguous: false, candidates: []);
        }

        $topCandidate = $candidates[0];
        $secondCandidate = $candidates[1] ?? null;
        $threshold = (float) config('person.resolver.ambiguity_threshold', 0.05);

        if ($secondCandidate !== null
            && ($topCandidate->confidence - $secondCandidate->confidence) <= $threshold) {
            return new PersonMatchResultDTO(
                resolvedPerson: null,
                isAmbiguous: true,
                candidates: $candidates,
            );
        }

        return new PersonMatchResultDTO(
            resolvedPerson: $this->toResolvedPerson($topCandidate),
            isAmbiguous: false,
            candidates: $candidates,
        );
    }

    private function toResolvedPerson(PersonMatchCandidateDTO $candidate): ResolvedPersonDTO
    {
        return new ResolvedPersonDTO(
            personId: $candidate->personId,
            personUuid: $candidate->personUuid,
            fullName: $candidate->fullName,
            matchedAlias: $candidate->matchedAlias,
            matchType: $candidate->matchType,
            confidence: $candidate->confidence,
            matchedIn: $candidate->matchedIn,
        );
    }
}
