<?php
declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Database;

abstract class BaseController
{
    protected function getOrCreateOptionId(string $category, ?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT id FROM notion_options 
            WHERE category = ? AND value = ?
        ');
        $stmt->execute([$category, $value]);

        if ($id = $stmt->fetchColumn()) {
            return (int) $id;
        }

        $insert = $db->prepare('
            INSERT INTO notion_options (category, value, created_at) 
            VALUES (?, ?, NOW())
        ');
        $insert->execute([$category, $value]);

        return (int) $db->lastInsertId();
    }
}