<!-- views/dashboard.php -->
<?php require 'layout.php'; ?>
<div class="container mt-4">
    <h1>Dashboard</h1>
    <h2>Clients</h2>
    <table class="table table-striped">
        <thead><tr><th>Name</th><th>Code</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($clients ?? [] as $client): ?>
                <tr><td><?= htmlspecialchars($client['name']) ?></td><td><?= htmlspecialchars($client['code']) ?></td><td><?= htmlspecialchars($client['status_id']) // Map to value ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Similar tables for projects, tasks -->
    <a href="/time-entry/create" class="btn btn-primary">Create Time Entry</a>
</div>