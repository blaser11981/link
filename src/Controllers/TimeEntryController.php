<?php
// src/Controllers/TimeEntryController.php (example for creating time entries)
declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Database;

class TimeEntryController
{
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'status_id' => (int) ($_POST['status'] ?? 0),
                'staff' => $_POST['staff'] ?? '',
                // etc.
            ];
            $db = Database::getInstance();
            $db->prepare('INSERT INTO time_entries (name, status_id, staff /* ... */) VALUES (?, ?, ? /* ... */)')->execute(array_values($data));
            $_SESSION['message'] = 'Time entry created';
            header('Location: /dashboard');
            exit;
        }

        require __DIR__ . '/../../views/time_entry_form.php';
    }
}