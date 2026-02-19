<!-- views/time_entry_form.php -->
<?php require 'layout.php'; ?>
<div class="container mt-4">
    <h1>Create Time Entry</h1>
    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <!-- Add more fields -->
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>