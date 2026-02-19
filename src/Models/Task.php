<?php
// src/Models/Task.php (similar structure)
declare(strict_types=1);

namespace VariuxLink\Models;

use VariuxLink\Database;
use VariuxLink\Services\NotionService;

class Task
{
    public static function upsertFromNotion(array $page, NotionService $notion): int
    {
        $props = $page['properties'];
        $notionId = str_replace('-', '', $page['id']);

        $data = [
            'notion_id' => $notionId,
            'name' => $notion->extractValue($props['Name']) ?? '',
            'status_id' => Project::getOptionId('status', $notion->extractValue($props['Status'])), // Reuse helper
            'assignee' => $notion->extractValue($props['Assignee']) ?? '',
            'project_id' => Project::getIdByNotion('projects', $notion->extractValue($props['Project'])[0] ?? ''),
            'workstream_id' => Project::getIdByNotion('workstreams', $notion->extractValue($props['Workstream'])[0] ?? ''),
            'billing_item_id' => Project::getOptionId('billing_item', $notion->extractValue($props['Billing Item'])),
            'phase_id' => Project::getOptionId('phase', $notion->extractValue($props['Phase'])),
            'tag' => json_encode($notion->extractValue($props['Tag']) ?? []),
            'support_vendor' => json_encode($notion->extractValue($props['Support Vendor']) ?? []),
            'task_code' => $notion->extractValue($props['Task Code']) ?? '',
            'next_step' => $notion->extractValue($props['Next Step']) ?? '',
            'due_date' => $notion->extractValue($props['Due Date']) ?? null,
            'est_hours' => $notion->extractValue($props['Est Hours']) ?? null,
            'non_billable' => (int) $notion->extractValue($props['Non-Billable']),
            'scope_creep' => (int) $notion->extractValue($props['Scope Creep']),
            'email_thread' => $notion->extractValue($props['Email Thread']) ?? null,
            'trello_card_link' => $notion->extractValue($props['Trello Card Link']) ?? null,
            'create_date' => $notion->extractValue($props['Create Date']) ?? null,
            'created_by' => $notion->extractValue($props['Created by']) ?? '',
            'last_edited' => $notion->extractValue($props['Last edited']) ?? null,
            'type_id' => Project::getOptionId('type', $notion->extractValue($props['Type'])),
            'files' => json_encode($notion->extractValue($props['Files & media']) ?? []),
        ];

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM tasks WHERE notion_id = ?');
        $stmt->execute([$notionId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            // Update (construct dynamic query or use named params)
            // For brevity, assume insert only for example; expand as needed
        } else {
            $keys = implode(', ', array_keys($data));
            $values = implode(', ', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO tasks ($keys) VALUES ($values)")->execute(array_values($data));
            $id = $db->lastInsertId();
        }

        // Sync relations: sprints, docs, meetings, time_entries, parties, blocked_by, etc.
        // Similar to Project::syncRelations

        return (int) $id;
    }
}