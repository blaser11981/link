<?php
// src/Controllers/SyncController.php (more complete with multiple syncs)
declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Database;
use VariuxLink\Services\NotionService;
use VariuxLink\Models\Client;
use VariuxLink\Models\Project;
use VariuxLink\Models\Task;
// Add imports for other models

class SyncController
{
    private NotionService $notion;

    public function __construct()
    {
        $this->notion = new NotionService();
    }

    public function dashboard(): void
    {
        $db = Database::getInstance();
        $clients = $db->query('SELECT * FROM clients LIMIT 10')->fetchAll();
        $projects = $db->query('SELECT * FROM projects LIMIT 10')->fetchAll();
        // Fetch more for display
        require __DIR__ . '/../../views/dashboard.php';
    }

    public function syncNotion(): void
    {
        try {
            $this->syncDatabase(NOTION_CLIENTS_DB, Client::class);
            $this->syncDatabase(NOTION_PROJECTS_DB, Project::class);
            $this->syncDatabase(NOTION_TASKS_DB, Task::class);
            // Add syncTimeEntries, syncParties, etc. similarly

            $_SESSION['message'] = 'Sync completed';
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: /dashboard');
        exit;
    }

    private function syncDatabase(string $dbId, string $modelClass): void
    {
        $pages = $this->notion->queryDatabase($dbId);
        foreach ($pages as $page) {
            $modelClass::upsertFromNotion($page, $this->notion);
        }
    }
}