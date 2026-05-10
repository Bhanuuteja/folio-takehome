<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $published_at = trim($_POST['published_at'] ?? '');
    $readable_id = trim($_POST['readable_id'] ?? '');

    if ($published_at === '') {
        $published_at = null;
    } else {
        $published_at = str_replace('T', ' ', $published_at);
        if (strlen($published_at) === 16) {
             $published_at .= ':00';
        }
    }

    if ($readable_id === '') {
        $readable_id = generate_readable_id();
    }

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        try {
            $stmt = db()->prepare('
                INSERT INTO documents (title, body, created_by, published_at, readable_id)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$title, $body, $staff['id'], $published_at, $readable_id]);
            $docId = (int) db()->lastInsertId();

            audit_log('create', 'document', $docId, [
                'title' => $title,
                'published_at' => $published_at,
                'readable_id' => $readable_id,
            ]);

            header('Location: /admin.php?created=' . $docId);
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed: documents.readable_id') !== false) {
                $error = 'That Readable ID is already in use. Please choose another or leave it blank.';
            } else {
                throw $e;
            }
        }
    }
}

$searchQuery = trim($_GET['q'] ?? '');

if ($searchQuery !== '') {
    $stmt = db()->prepare('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        WHERE d.title LIKE ?
        ORDER BY d.created_at DESC
    ');
    $stmt->execute(['%' . $searchQuery . '%']);
    $docs = $stmt->fetchAll();
} else {
    $docs = db()->query('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        ORDER BY d.created_at DESC
    ')->fetchAll();
}

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['created'] ?> created.</div>
<?php endif ?>

<?php if (!empty($_GET['scheduled'])): ?>
    <div class="banner banner-success">Schedule updated for Document #<?= (int) $_GET['scheduled'] ?>.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required></textarea>
        </div>
        <div class="form-field">
            <label for="readable_id">Readable ID (optional)</label>
            <input type="text" id="readable_id" name="readable_id" placeholder="e.g. welcome-2026">
        </div>
        <div class="form-field">
            <label for="published_at">Publish At (optional, local time)</label>
            <input type="datetime-local" id="published_at" name="published_at">
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Documents</h2>
    <form method="get" style="margin-bottom: 1rem;">
        <input type="text" name="q" placeholder="Search by title..." value="<?= h($searchQuery) ?>" style="padding: 0.5rem; width: 250px;">
        <button type="submit" class="btn">Search</button>
        <?php if ($searchQuery !== ''): ?>
            <a href="/admin.php" class="btn-link" style="margin-left: 0.5rem;">Clear</a>
        <?php endif ?>
    </form>
    <?php if (empty($docs)): ?>
        <p class="empty">No documents yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Readable ID</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th>Published At</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td class="id">#<?= (int) $d['id'] ?></td>
                        <td>
                            <?php if ($d['readable_id']): ?>
                                <a href="/view.php?doc=<?= h($d['readable_id']) ?>"><?= h($d['readable_id']) ?></a>
                            <?php else: ?>
                                <span class="empty">-</span>
                            <?php endif ?>
                        </td>
                        <td><?= h($d['title']) ?></td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td><?= h($d['created_at']) ?></td>
                        <td><?= $d['published_at'] ? h($d['published_at']) : '<span class="empty">Immediate</span>' ?></td>
                        <td>
                            <a href="/schedule.php?doc=<?= (int) $d['id'] ?>" class="btn-link" style="margin-right: 0.5rem;">Edit schedule</a>
                            <a href="/share.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Create share →</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>
