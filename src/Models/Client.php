<?php
declare(strict_types=1);

namespace VariuxLink\Models;

use VariuxLink\Database;

class Client
{
    public static function upsertFromNotion(array $page): int
    {
        $props = $page['properties'];
        $notionId = str_replace('-', '', $page['id']);

        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM clients WHERE notion_id = ?');
        $stmt->execute([$notionId]);
        $id = $stmt->fetchColumn();

        $data = [
            str_replace('-', '', $page['id']),
            $props['Name']['title'][0]['plain_text'] ?? '',
            $props['Code']['rich_text'][0]['plain_text'] ?? null,
            // status → map to options table id (implement helper)
            // responsible → person name
            // etc.
        ];

        if ($id) {
            // UPDATE
        } else {
            $stmt = $db->prepare('INSERT INTO clients (notion_id, name, code /* ... */) VALUES (?, ?, ? /* ... */)');
            $stmt->execute($data);
            $id = $db->lastInsertId();
        }

        return (int) $id;
    }
}