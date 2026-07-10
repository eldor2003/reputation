<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rules = [
            [
                'name' => 'P1 Critical Immediate',
                'rule_priority' => 10,
                'routing_priority' => 'immediate',
                'delivery_mode' => 'immediate',
                'auto_skip' => false,
                'skip_moderation' => false,
                'reason_template' => 'Критический уровень угрозы {threat_level} (score {threat_score}) требует немедленного оповещения.',
                'conditions' => [
                    ['condition_type' => 'threat_level', 'operator' => 'in', 'value' => ['values' => ['P1']]],
                ],
                'targets' => [
                    ['target_type' => 'telegram_moderation', 'sort_order' => 1],
                    ['target_type' => 'telegram_delivery', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'P2 High Priority',
                'rule_priority' => 20,
                'routing_priority' => 'normal',
                'delivery_mode' => 'immediate',
                'auto_skip' => false,
                'skip_moderation' => false,
                'reason_template' => 'Высокий уровень угрозы {threat_level} (score {threat_score}) требует оповещения.',
                'conditions' => [
                    ['condition_type' => 'threat_level', 'operator' => 'in', 'value' => ['values' => ['P2']]],
                ],
                'targets' => [
                    ['target_type' => 'telegram_moderation', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'P3/P4 Night Digest',
                'rule_priority' => 30,
                'routing_priority' => 'deferred',
                'delivery_mode' => 'digest',
                'auto_skip' => false,
                'skip_moderation' => true,
                'reason_template' => 'Ночной режим: уровень {threat_level} отложен до утреннего дайджеста без модерации.',
                'conditions' => [
                    ['condition_type' => 'threat_level', 'operator' => 'in', 'value' => ['values' => ['P3', 'P4']]],
                    ['condition_type' => 'night_mode', 'operator' => 'between', 'value' => ['start' => '22:00', 'end' => '08:00']],
                ],
                'targets' => [
                    ['target_type' => 'telegram_delivery', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'P3 Standard',
                'rule_priority' => 40,
                'routing_priority' => 'normal',
                'delivery_mode' => 'immediate',
                'auto_skip' => false,
                'skip_moderation' => false,
                'reason_template' => 'Уровень угрозы {threat_level} направлен на модерацию.',
                'conditions' => [
                    ['condition_type' => 'threat_level', 'operator' => 'in', 'value' => ['values' => ['P3']]],
                ],
                'targets' => [
                    ['target_type' => 'telegram_moderation', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'P4 Low Priority',
                'rule_priority' => 50,
                'routing_priority' => 'low',
                'delivery_mode' => 'deferred',
                'auto_skip' => false,
                'skip_moderation' => false,
                'reason_template' => 'Низкий уровень угрозы {threat_level} отложен.',
                'conditions' => [
                    ['condition_type' => 'threat_level', 'operator' => 'in', 'value' => ['values' => ['P4']]],
                ],
                'targets' => [
                    ['target_type' => 'telegram_delivery', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Fallback Skip',
                'rule_priority' => 999,
                'routing_priority' => 'low',
                'delivery_mode' => 'skip',
                'auto_skip' => true,
                'skip_moderation' => false,
                'reason_template' => 'Не найдено подходящее правило маршрутизации.',
                'is_fallback' => true,
                'conditions' => [],
                'targets' => [],
            ],
        ];

        foreach ($rules as $ruleData) {
            $ruleId = DB::table('routing_rules')->insertGetId([
                'project_id' => null,
                'person_id' => null,
                'name' => $ruleData['name'],
                'rule_priority' => $ruleData['rule_priority'],
                'routing_priority' => $ruleData['routing_priority'],
                'delivery_mode' => $ruleData['delivery_mode'],
                'auto_skip' => $ruleData['auto_skip'],
                'skip_moderation' => $ruleData['skip_moderation'],
                'reason_template' => $ruleData['reason_template'],
                'is_active' => true,
                'is_fallback' => $ruleData['is_fallback'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($ruleData['conditions'] as $condition) {
                DB::table('routing_conditions')->insert([
                    'routing_rule_id' => $ruleId,
                    'condition_type' => $condition['condition_type'],
                    'operator' => $condition['operator'],
                    'value' => json_encode($condition['value'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($ruleData['targets'] as $target) {
                DB::table('routing_targets')->insert([
                    'routing_rule_id' => $ruleId,
                    'target_type' => $target['target_type'],
                    'target_config' => null,
                    'sort_order' => $target['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('routing_conditions')->delete();
        DB::table('routing_targets')->delete();
        DB::table('routing_rules')->delete();
    }
};
