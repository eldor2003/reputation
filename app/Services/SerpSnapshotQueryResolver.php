<?php

namespace App\Services;

use App\Models\Person;

class SerpSnapshotQueryResolver
{
    /**
     * @return list<array{query: string, person_id: int|null}>
     */
    public function resolve(): array
    {
        $targets = [];

        foreach ($this->configuredQueries() as $query) {
            $targets[] = [
                'query' => $query,
                'person_id' => null,
            ];
        }

        if (! (bool) config('serpapi.snapshots.include_persons', true)) {
            return $this->uniqueTargets($targets);
        }

        $persons = Person::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'full_name']);

        foreach ($persons as $person) {
            $query = trim($person->full_name);

            if ($query === '') {
                continue;
            }

            $targets[] = [
                'query' => $query,
                'person_id' => $person->id,
            ];
        }

        return $this->uniqueTargets($targets);
    }

    /**
     * @return list<string>
     */
    private function configuredQueries(): array
    {
        /** @var list<string>|string|null $configured */
        $configured = config('serpapi.snapshots.queries');

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode('|', $configured)));
        }

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            $configured,
            fn (mixed $query): bool => is_string($query) && trim($query) !== '',
        ));
    }

    /**
     * @param  list<array{query: string, person_id: int|null}>  $targets
     * @return list<array{query: string, person_id: int|null}>
     */
    private function uniqueTargets(array $targets): array
    {
        $seen = [];
        $unique = [];

        foreach ($targets as $target) {
            $key = mb_strtolower($target['query']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $target;
        }

        return $unique;
    }
}
