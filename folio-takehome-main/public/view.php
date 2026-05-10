<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$token = $_GET['token'] ?? '';
$readable_id = $_GET['doc'] ?? '';
$doc = null;
$recipient_email = null;

if ($token !== '') {
    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE s.token = ?
    ');
    $stmt->execute([$token]);
    $doc = $stmt->fetch();
    if ($doc) {
        $recipient_email = $doc['recipient_email'];
    }
} elseif ($readable_id !== '') {
    $stmt = db()->prepare('
        SELECT *
        FROM documents
        WHERE readable_id = ?
    ');
    $stmt->execute([$readable_id]);
    $doc = $stmt->fetch();
    if ($doc) {
        $recipient_email = 'Public Link';
    }
}

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Document not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

$is_published = true;
if ($doc['published_at'] !== null) {
    $published_time = strtotime($doc['published_at']);
    if ($published_time > time()) {
        $is_published = false;
    }
}

if (!$is_published) {
    render_header('Not available yet');
    ?>
    <div class="centered-message">
        <h1>Not yet available</h1>
        <p>This document is scheduled to be published at <?= h($doc['published_at']) ?>.</p>
    </div>
    <?php
    render_footer();
    exit;
}

render_header($doc['title']);
?>

<h1 class="page-title"><?= h($doc['title']) ?></h1>
<p class="meta">Shared with <?= h($recipient_email) ?></p>

<pre class="doc-body"><?= h($doc['body']) ?></pre>

<?php render_footer(); ?>
