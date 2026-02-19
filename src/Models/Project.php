<?php
// src/Models/Project.php
declare(strict_types=1);

namespace VariuxLink\Models;

use VariuxLink\Database;

class Project
{
    public static function upsertFromNotion(array $page, NotionService $notion): int
    {
        $props = $page['properties'];
        $notionId = str_replace('-', '', $page['id']);

        $data = [
            'notion_id' => $notionId,
            'name' => $notion->extractValue($props['Name']) ?? '',
            'status_id' => self::getOptionId('status', $notion->extractValue($props['Status'])),
            'responsible' => $notion->extractValue($props['Responsible']) ?? '',
            'pm' => $notion->extractValue($props['PM']) ?? '',
            'type' => json_encode($notion->extractValue($props['Type']) ?? []),
            'website' => $notion->extractValue($props['Website']) ?? null,
            'description' => $notion->extractValue($props['Project Description']) ?? '',
            'rate' => $notion->extractValue($props['Project Rate']) ?? null,
            'completion_date' => $notion->extractValue($props['Completion Date']) ?? null,
            'duration_start' => $props['Duration']['date']['start'] ?? null,
            'duration_end' => $props['Duration']['date']['end'] ?? null,
            'last_edited_time' => $notion->extractValue($props['Last edited time']) ?? null,
        ];

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM notion_projects WHERE notion_id = ?');
        $stmt->execute([$notionId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            $updateQuery = 'UPDATE notion_projects SET name = ?, status_id = ?, responsible = ?, pm = ?, type = ?, website = ?, description = ?, rate = ?, completion_date = ?, duration_start = ?, duration_end = ?, last_edited_time = ? WHERE notion_id = ?';
            $updateData = array_values($data);
            $updateData[] = $notionId; // append for WHERE
            $db->prepare($updateQuery)->execute($updateData);
        } else {
            $insertQuery = 'INSERT INTO notion_projects (notion_id, name, status_id, responsible, pm, type, website, description, rate, completion_date, duration_start, duration_end, last_edited_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $db->prepare($insertQuery)->execute(array_values($data));
            $id = $db->lastInsertId();
        }

        // Handle relations (e.g., clients, teams) - call separate methods
        self::syncRelations($id, $notionId, $props, $notion);

        return (int) $id;
    }

    private static function syncRelations(int $id, string $notionId, array $props, NotionService $notion): void
    {
        $db = Database::getInstance();

        // Example: Multi clients
        $clientNotionIds = $notion->extractValue($props['Clients']) ?? [];
        $db->prepare('DELETE FROM notion_project_clients WHERE project_id = ?')->execute([$id]);
        foreach ($clientNotionIds as $clientNotion) {
            $clientId = self::getIdByNotion('notion_clients', $clientNotion);
            if ($clientId) {
                $db->prepare('INSERT IGNORE INTO notion_project_clients (project_id, client_id) VALUES (?, ?)')->execute([$id, $clientId]);
            }
        }

        // Similar for other relations: teams, engagements, etc.
    }

    private static function getOptionId(string $category, ?string $value): ?int
    {
        if (empty($value)) return null;
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM notion_options WHERE category = ? AND value = ?');
        $stmt->execute([$category, $value]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            $db->prepare('INSERT INTO notion_options (category, value) VALUES (?, ?)')->execute([$category, $value]);
            $id = $db->lastInsertId();
        }
        return (int) $id;
    }

    private static function getIdByNotion(string $table, string $notionId): ?int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM $table WHERE notion_id = ?");
        $stmt->execute([str_replace('-', '', $notionId)]);
        return (int) $stmt->fetchColumn() ?: null;
    }
}