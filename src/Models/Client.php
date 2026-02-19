<?php
declare(strict_types=1);

namespace VariuxLink\Models;

use VariuxLink\Database;

class Client
{
    public static function upsertFromNotion(array $data): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM notion_clients WHERE notion_id = ?');
        $stmt->execute([$data['notion_id']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $db->prepare('
                UPDATE notion_clients SET 
                    name = :name, code = :code, status_id = :status_id, 
                    responsible = :responsible, url = :url, nps = :nps,
                    updated_at = NOW()
                WHERE notion_id = :notion_id
            ');
            $stmt->execute($data);
        } else {
            $stmt = $db->prepare('
                INSERT INTO notion_clients 
                (notion_id, name, code, status_id, responsible, url, nps, created_at)
                VALUES (:notion_id, :name, :code, :status_id, :responsible, :url, :nps, NOW())
            ');
            $stmt->execute($data);
        }
    }
}