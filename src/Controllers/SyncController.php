<?php
// src/Controllers/SyncController.php
// Full sync implementations for all Notion databases from the user's layout
// Assumes models exist for each (e.g. Client.php, Project.php, etc.) with upsertFromNotion() methods
// Uses modern PHP 8.3, strict types, named params where possible
// Handles options (status, select, multi_select) via getOrCreateOptionId()
// Add relations handling as needed (e.g. multi-relations via pivots)

declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Database;
use VariuxLink\Services\NotionService;
use VariuxLink\Models\Client;
use VariuxLink\Models\Project;
use VariuxLink\Models\Task;
use VariuxLink\Models\TimeEntry;
use VariuxLink\Models\Party;
use VariuxLink\Models\Sprint;
use VariuxLink\Models\Workstream;

use const NOTION_CLIENTS_DB_ID;
use const NOTION_PROJECTS_DB_ID;
use const NOTION_TASKS_DB_ID;
use const NOTION_TIME_ENTRIES_DB_ID;
use const NOTION_PARTIES_DB_ID;
use const NOTION_SPRINTS_DB_ID;
use const NOTION_WORKSTREAMS_DB_ID;

class SyncController extends BaseController
{
    private NotionService $notion;

    public function __construct()
    {
        $this->notion = new NotionService();
    }

    public function dashboard(): void
    {
        $db = Database::getInstance();
        $clientsCount = (int) $db->query('SELECT COUNT(*) FROM notion_clients')->fetchColumn();
        $projectsCount = (int) $db->query('SELECT COUNT(*) FROM notion_projects')->fetchColumn();
        // Add more counts as needed
        require __DIR__ . '/../../views/dashboard.php';
    }

    public function syncNotion(): void
    {
        try {
            $this->syncClients();
            $this->syncProjects();
            $this->syncTasks();
            $this->syncTimeEntries();
            $this->syncParties();
            $this->syncSprints();
            $this->syncWorkstreams();

            $_SESSION['message'] = 'All Notion data synced successfully.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Sync failed: ' . $e->getMessage();
            error_log('Notion sync error: ' . $e->getMessage());
        }

        header('Location: /dashboard');
        exit;
    }

    private function syncClients(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_CLIENTS_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'code' => $this->notion->extractValue($props['Code'] ?? []) ?? null,
                'status' => $this->notion->extractValue($props['Status'] ?? []) ?? null,
                'responsible' => $this->notion->extractValue($props['Responsible'] ?? []) ?? null,
                'url' => $this->notion->extractValue($props['URL'] ?? []) ?? null,
                'nps' => $this->notion->extractValue($props['NPS'] ?? null) ?? null,
            ];

            if ($data['status']) {
                $data['status_id'] = $this->getOrCreateOptionId('client_status', $data['status']);
            }

            Client::upsertFromNotion($data);

            // Handle relations (e.g. primary_contact, contacts, projects) - add pivot inserts here if needed

            $count++;
        }

        error_log("Synced $count clients.");
    }

    private function syncProjects(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_PROJECTS_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'status' => $this->notion->extractValue($props['Status'] ?? []) ?? null,
                'responsible' => $this->notion->extractValue($props['Responsible'] ?? []) ?? null,
                'pm' => $this->notion->extractValue($props['PM'] ?? []) ?? null,
                'type_json' => json_encode($this->notion->extractValue($props['Type'] ?? [])),
                'website' => $this->notion->extractValue($props['Website'] ?? []) ?? null,
                'description' => $this->notion->extractValue($props['Project Description'] ?? []) ?? null,
                'rate' => $this->notion->extractValue($props['Project Rate'] ?? null) ?? null,
                'completion_date' => $this->notion->extractValue($props['Completion Date'] ?? null) ?? null,
                'duration_start' => $props['Duration']['date']['start'] ?? null,
                'duration_end' => $props['Duration']['date']['end'] ?? null,
                'last_edited_time' => $this->notion->extractValue($props['Last edited time'] ?? null) ?? null,
            ];

            if ($data['status']) {
                $data['status_id'] = $this->getOrCreateOptionId('project_status', $data['status']);
            }

            Project::upsertFromNotion($data);

            // Handle relations (e.g. clients, teams, engagements) via pivots
            $clientIds = $this->notion->extractValue($props['Clients'] ?? []);
            $this->syncRelationPivots($data['notion_id'], 'notion_projects', $clientIds, 'notion_clients', 'notion_project_clients');

            $count++;
        }

        error_log("Synced $count projects.");
    }

    private function syncTasks(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_TASKS_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'status' => $this->notion->extractValue($props['Status'] ?? []) ?? null,
                'assignee' => $this->notion->extractValue($props['Assignee'] ?? []) ?? null,
                'project_id' => $this->getLocalIdFromNotion('notion_projects', $this->notion->extractValue($props['Project'] ?? [])[0] ?? null),
                'workstream_id' => $this->getLocalIdFromNotion('notion_workstreams', $this->notion->extractValue($props['Workstream'] ?? [])[0] ?? null),
                'billing_item' => $this->notion->extractValue($props['Billing Item'] ?? []) ?? null,
                'phase' => $this->notion->extractValue($props['Phase'] ?? []) ?? null,
                'tag_json' => json_encode($this->notion->extractValue($props['Tag'] ?? [])),
                'support_vendor_json' => json_encode($this->notion->extractValue($props['Support Vendor'] ?? [])),
                'task_code' => $this->notion->extractValue($props['Task Code'] ?? []) ?? null,
                'next_step' => $this->notion->extractValue($props['Next Step'] ?? []) ?? null,
                'due_date' => $this->notion->extractValue($props['Due Date'] ?? null) ?? null,
                'est_hours' => $this->notion->extractValue($props['Est Hours'] ?? null) ?? null,
                'non_billable' => (bool) $this->notion->extractValue($props['Non-Billable'] ?? false),
                'scope_creep' => (bool) $this->notion->extractValue($props['Scope Creep'] ?? false),
                'email_thread_url' => $this->notion->extractValue($props['Email Thread'] ?? []) ?? null,
                'trello_card_url' => $this->notion->extractValue($props['Trello Card Link'] ?? []) ?? null,
                'files_json' => json_encode($this->notion->extractValue($props['Files & media'] ?? [])),
                'type' => $this->notion->extractValue($props['Type'] ?? []) ?? null,
            ];

            if ($data['status']) {
                $data['status_id'] = $this->getOrCreateOptionId('task_status', $data['status']);
            }
            if ($data['billing_item']) {
                $data['billing_item_id'] = $this->getOrCreateOptionId('billing_item', $data['billing_item']);
            }
            if ($data['phase']) {
                $data['phase_id'] = $this->getOrCreateOptionId('phase', $data['phase']);
            }
            if ($data['type']) {
                $data['type_id'] = $this->getOrCreateOptionId('task_type', $data['type']);
            }

            Task::upsertFromNotion($data);

            // Handle relations (e.g. sprints, docs, meetings, time_entries)
            $sprintIds = $this->notion->extractValue($props['Sprint'] ?? []);
            $this->syncRelationPivots($data['notion_id'], 'notion_tasks', $sprintIds, 'notion_sprints', 'notion_task_sprints');

            $count++;
        }

        error_log("Synced $count tasks.");
    }

    private function syncTimeEntries(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_TIME_ENTRIES_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'status' => $this->notion->extractValue($props['Status'] ?? []) ?? null,
                'staff' => $this->notion->extractValue($props['Staff'] ?? []) ?? null,
                'approved_by' => $this->notion->extractValue($props['Approved by'] ?? []) ?? null,
                'start_time' => $this->notion->extractValue($props['Start Time'] ?? null) ?? null,
                'end_time' => $this->notion->extractValue($props['End Time'] ?? null) ?? null,
                'exported' => (bool) $this->notion->extractValue($props['Exported'] ?? false),
                'project_id' => $this->getLocalIdFromNotion('notion_projects', $this->notion->extractValue($props['Projects - make sure this is right'] ?? [])[0] ?? null),
                'workstream_text' => $this->notion->extractValue($props['Workstream'] ?? []) ?? null,
                'lookup_rate' => $this->notion->extractValue($props['Lookup Rate Here'] ?? []) ?? null,
            ];

            if ($data['status']) {
                $data['status_id'] = $this->getOrCreateOptionId('time_entry_status', $data['status']);
            }
            if ($data['lookup_rate']) {
                $data['lookup_rate_id'] = $this->getOrCreateOptionId('lookup_rate', $data['lookup_rate']);
            }

            TimeEntry::upsertFromNotion($data);

            // Handle relations (e.g. tasks)
            $taskIds = $this->notion->extractValue($props['Tasks'] ?? []);
            $this->syncRelationPivots($data['notion_id'], 'notion_time_entries', $taskIds, 'notion_tasks', 'notion_time_entry_tasks');

            $count++;
        }

        error_log("Synced $count time entries.");
    }

    private function syncParties(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_PARTIES_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'type' => $this->notion->extractValue($props['Type'] ?? []) ?? null,
                'company' => $this->notion->extractValue($props['Company'] ?? []) ?? null,
                'contact_email' => $this->notion->extractValue($props['Contact Email'] ?? []) ?? null,
                'internal_staff' => $this->notion->extractValue($props['Internal Staff'] ?? []) ?? null,
                'notes' => $this->notion->extractValue($props['Notes'] ?? []) ?? null,
                'active' => (bool) $this->notion->extractValue($props['Active'] ?? true),
            ];

            if ($data['type']) {
                $data['type_id'] = $this->getOrCreateOptionId('party_type', $data['type']);
            }

            Party::upsertFromNotion($data);

            // Handle relations (e.g. projects)
            $projectIds = $this->notion->extractValue($props['Projects'] ?? []);
            $this->syncRelationPivots($data['notion_id'], 'notion_parties', $projectIds, 'notion_projects', 'notion_party_projects');

            $count++;
        }

        error_log("Synced $count parties.");
    }

    private function syncSprints(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_SPRINTS_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'status' => $this->notion->extractValue($props['Status'] ?? []) ?? null,
                'sprint_start' => $props['Sprint Period']['date']['start'] ?? null,
                'sprint_end' => $props['Sprint Period']['date']['end'] ?? null,
                'week' => $this->notion->extractValue($props['Week'] ?? []) ?? null,
            ];

            if ($data['status']) {
                $data['status_id'] = $this->getOrCreateOptionId('sprint_status', $data['status']);
            }

            Sprint::upsertFromNotion($data);

            $count++;
        }

        error_log("Synced $count sprints.");
    }

    private function syncWorkstreams(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_WORKSTREAMS_DB_ID);
        $count = 0;

        foreach ($pages as $page) {
            $props = $page['properties'] ?? [];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractValue($props['Name'] ?? []) ?? '',
                'project_id' => $this->getLocalIdFromNotion('notion_projects', $this->notion->extractValue($props['Project'] ?? [])[0] ?? null),
                'project_name_rollup' => $this->notion->extractValue($props['Project name'] ?? []) ?? null,
            ];

            Workstream::upsertFromNotion($data);

            $count++;
        }

        error_log("Synced $count workstreams.");
    }

    // Helper to sync multi-relations (pivots)
    private function syncRelationPivots(string $localNotionId, string $localTable, array $relatedNotionIds, string $relatedTable, string $pivotTable): void
    {
        $db = Database::getInstance();

        $localId = $this->getLocalIdFromNotion($localTable, $localNotionId);

        if (!$localId) return;

        // Clear existing
        $db->prepare("DELETE FROM $pivotTable WHERE {$localTable}_id = ?")->execute([$localId]);

        // Insert new
        $insert = $db->prepare("INSERT IGNORE INTO $pivotTable ({$localTable}_id, {$relatedTable}_id) VALUES (?, ?)");

        foreach ($relatedNotionIds as $relatedNotion) {
            $relatedId = $this->getLocalIdFromNotion($relatedTable, $relatedNotion);
            if ($relatedId) {
                $insert->execute([$localId, $relatedId]);
            }
        }
    }

    // Helper to get local MySQL ID from Notion ID
    private function getLocalIdFromNotion(string $table, ?string $notionId): ?int
    {
        if (!$notionId) return null;

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT id FROM $table WHERE notion_id = ?");
        $stmt->execute([str_replace('-', '', $notionId)]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    // The option helper (already in your code or BaseController)
    protected function getOrCreateOptionId(string $category, ?string $value): ?int
    {
        if (!$value) return null;

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT id FROM notion_options WHERE category = ? AND value = ?");
        $stmt->execute([$category, $value]);

        if ($id = $stmt->fetchColumn()) return (int) $id;

        $insert = $db->prepare("INSERT INTO notion_options (category, value, created_at) VALUES (?, ?, NOW())");
        $insert->execute([$category, $value]);

        return (int) $db->lastInsertId();
    }
}