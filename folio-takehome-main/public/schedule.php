<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$docId = (int) ($_GET['doc'] ?? 0);
$stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found', $staff);
    ?>
    <div class="banner banner-error">Document not found.</div>
    <p><a href="/admin.php" class="back-link">← back to admin</a></p>
    <?php
    render_footer();
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $published_at = trim($_POST['published_at'] ?? '');
    
    if ($published_at === '') {
        $published_at = null;
    } else {
        $published_at = str_replace('T', ' ', $published_at);
        if (strlen($published_at) === 16) {
             $published_at .= ':00';
        }
    }

    try {
        $stmt = db()->prepare('UPDATE documents SET published_at = ? WHERE id = ?');
        $stmt->execute([$published_at, $doc['id']]);
        
        audit_log('update_schedule', 'document', $doc['id'], [
            'old_published_at' => $doc['published_at'],
            'new_published_at' => $published_at,
        ]);

        header('Location: /admin.php?scheduled=' . $doc['id']);
        exit;
    } catch (Throwable $e) {
        $error = 'Failed to update schedule. Please try again.';
    }
}

$current_schedule = '';
if ($doc['published_at']) {
    $current_schedule = date('Y-m-d\TH:i', strtotime($doc['published_at']));
}

render_header('Schedule · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">← back to admin</a>

<h1 class="page-title">Schedule "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Update when this document becomes available to recipients.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">Edit schedule</h2>
    <form method="post">
        <div class="form-field">
            <label for="published_at">Publish At (optional, local time)</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?= h($current_schedule) ?>">
        </div>
        <button type="submit" class="btn">Update schedule</button>
    </form>
</section>

<?php render_footer(); ?>
