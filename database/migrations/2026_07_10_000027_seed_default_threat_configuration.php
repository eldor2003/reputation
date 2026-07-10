<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $weights = [
            [
                'factor_key' => 'sentiment',
                'weight' => 0.2500,
                'scoring_config' => json_encode([
                    'type' => 'map',
                    'values' => [
                        'negative' => 100,
                        'neutral' => 40,
                        'positive' => 10,
                    ],
                    'default' => 30,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'severity',
                'weight' => 0.2500,
                'scoring_config' => json_encode([
                    'type' => 'linear',
                    'min' => 1,
                    'max' => 5,
                    'multiplier' => 20,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'source_credibility',
                'weight' => 0.1500,
                'scoring_config' => json_encode([
                    'type' => 'map',
                    'values' => [
                        'youscan' => 85,
                        'brand24' => 80,
                        'mentionlytics' => 75,
                    ],
                    'default' => 50,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'serp_visibility',
                'weight' => 0.1000,
                'scoring_config' => json_encode([
                    'type' => 'position',
                    'thresholds' => [
                        ['max_position' => 3, 'score' => 100],
                        ['max_position' => 10, 'score' => 70],
                        ['max_position' => 20, 'score' => 40],
                    ],
                    'default' => 15,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'cluster_size',
                'weight' => 0.1000,
                'scoring_config' => json_encode([
                    'type' => 'thresholds',
                    'thresholds' => [
                        ['min' => 10, 'score' => 100],
                        ['min' => 5, 'score' => 75],
                        ['min' => 2, 'score' => 50],
                    ],
                    'default' => 20,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'mention_recency',
                'weight' => 0.1000,
                'scoring_config' => json_encode([
                    'type' => 'recency_hours',
                    'thresholds' => [
                        ['max_hours' => 1, 'score' => 100],
                        ['max_hours' => 24, 'score' => 70],
                        ['max_hours' => 168, 'score' => 40],
                    ],
                    'default' => 15,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'factor_key' => 'person_importance',
                'weight' => 0.0500,
                'scoring_config' => json_encode([
                    'type' => 'metadata',
                    'field' => 'importance_score',
                    'default' => 0,
                ], JSON_THROW_ON_ERROR),
            ],
        ];

        foreach ($weights as $weight) {
            DB::table('threat_factor_weights')->insert([
                'project_id' => null,
                'factor_key' => $weight['factor_key'],
                'weight' => $weight['weight'],
                'scoring_config' => $weight['scoring_config'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rules = [
            ['level' => 'P1', 'min_score' => 80, 'priority' => 1],
            ['level' => 'P2', 'min_score' => 65, 'priority' => 2],
            ['level' => 'P3', 'min_score' => 45, 'priority' => 3],
            ['level' => 'P4', 'min_score' => 0, 'priority' => 4],
        ];

        foreach ($rules as $rule) {
            DB::table('threat_rules')->insert([
                'project_id' => null,
                'level' => $rule['level'],
                'min_score' => $rule['min_score'],
                'priority' => $rule['priority'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('threat_rules')->whereNull('project_id')->delete();
        DB::table('threat_factor_weights')->whereNull('project_id')->delete();
    }
};
