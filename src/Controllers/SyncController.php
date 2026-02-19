<?php
// src/Controllers/SyncController.php
declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Services\NotionService;
use VariuxLink\Models\Client; // Import others as needed
use VariuxLink\Database;
use Exception;

class SyncController
{
    private NotionService $notion;

    public function __construct()
    {
        $this->notion = new NotionService();
    }

    public function dashboard(): void
    {
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
            // Sync others similarly

            $_SESSION['message'] = 'Sync completed successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: /dashboard');
        exit;
    }

    private function syncClients(): void
    {
        $pages = $this->notion->queryDatabase(NOTION_CLIENTS_DB);
        $db = Database::getInstance();

        foreach ($pages as $page) {
            $props = $page['properties'];

            $data = [
                'notion_id' => str_replace('-', '', $page['id']),
                'name' => $this->notion->extractPropertyValue($props['Name']),
                'code' => $this->notion->extractPropertyValue($props['Code']),
                'status_id' => $this->getOrCreateOption('status', $this->notion->extractPropertyValue($props['Status'])),
                'responsible' => $this->notion->extractPropertyValue($props['Responsible']),
                'url' => $this->notion->extractPropertyValue($props['URL']),
                'nps' => $this->notion->extractPropertyValue($props['NPS']),
            ];

            Client::upsert($data);

            // Handle relations: primary_contact, contacts, projects, engagements, invoices
            // e.g., for primary_contact (assuming single)
            $primary = $this->notion->extractPropertyValue($props['Primary contact'])[0] ?? null;
            if ($primary) {
                $crmId = $this->getCrmIdByNotion($primary); // Assume sync CRM first
                $db->prepare('UPDATE clients SET primary_contact_id = :id WHERE notion_id = :notion_id')
                    ->execute(['id' => $crmId, 'notion_id' => $data['notion_id']]);
            }

            // For multi: contacts
            $contacts = $this->notion->extractPropertyValue($props['Contacts']);
            $clientId = $this->getClientIdByNotion($data['notion_id']);
            $db->prepare('DELETE FROM client_contacts WHERE client_id = :id')->execute(['id' => $clientId]);
            foreach ($contacts as $contactNotion) {
                $crmId = $this->getCrmIdByNotion($contactNotion);
                $db->prepare('INSERT INTO client_contacts (client_id, crm_id) VALUES (:client, :crm)')
                    ->execute(['client' => $clientId, 'crm' => $crmId]);
            }

            // Similar for other relations
        }
    }

    // Similar private methods for syncProjects, syncTasks, etc.
    // Implement extraction and upsert for each

    private function getOrCreateOption(string $category, string $value): int
    {
        if (empty($value)) return 0;
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM options WHERE category = :cat AND value = :val');
        $stmt->execute(['cat' => $category, 'val' => $value]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            $insert = $db->prepare('INSERT INTO options (category, value) VALUES (:cat, :val)');
            $insert->execute(['cat' => $category, 'val' => $value]);
            $id = $db->lastInsertId();
        }
        return (int) $id;
    }

    private function getClientIdByNotion(string $notionId): int
    {
        $stmt = Database::getInstance()->prepare('SELECT id FROM clients WHERE notion_id = :notion_id');
        $stmt->execute(['notion_id' => $notionId]);
        return (int) $stmt->fetchColumn() ?: 0;
    }

    // Similar getters for other tables

    // Note: For full implementation, sync order matters (e.g., sync CRM before clients)
    // Handle inferred tables similarly, assuming minimal fields
    // For Zoho/Missive integrations, add separate services/controllers
    // Time tracking: Add routes/models for creating time_entries
    // Task creation: Forms to create tasks, sync back to Notion if needed
    // Missive: Assume API integration for linking conversations
    // Bootstrap UI in views
}