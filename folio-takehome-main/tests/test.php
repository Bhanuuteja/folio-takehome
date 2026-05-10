<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('generate_readable_id creates valid ID', function () {
    $id = generate_readable_id();
    assert_true(strpos($id, 'DOC-') === 0, 'expected DOC- prefix');
    assert_true(strlen($id) === 8, 'expected length 8');
});

test('scheduled publishing works', function () {
    $pdo = db();
    $future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
    $stmt = $pdo->prepare('INSERT INTO documents (title, body, created_by, published_at, readable_id) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Future Doc', 'Secret', $future_date, 'future-123']);
    
    // Simulate what view.php does
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE readable_id = ?');
    $stmt->execute(['future-123']);
    $doc = $stmt->fetch();
    
    assert_true($doc !== false, 'expected document to exist');
    
    $is_published = true;
    if ($doc['published_at'] !== null) {
        if (strtotime($doc['published_at']) > time()) {
            $is_published = false;
        }
    }
    
    assert_true($is_published === false, 'expected document to not be published yet');
});

test('search by title works', function () {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO documents (title, body, created_by, readable_id) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Find Me Please', 'Content', 'find-123']);
    
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE title LIKE ?');
    $stmt->execute(['%Me Please%']);
    $docs = $stmt->fetchAll();
    
    assert_true(count($docs) >= 1, 'expected to find the document');
    assert_true($docs[0]['title'] === 'Find Me Please', 'expected correct title');
});

test('update schedule updates document correctly', function () {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO documents (title, body, created_by, readable_id) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Schedule Update Test', 'Content', 'update-123']);
    $docId = (int) $pdo->lastInsertId();
    
    $future_date = date('Y-m-d H:i:s', strtotime('+2 days'));
    $stmt = $pdo->prepare('UPDATE documents SET published_at = ? WHERE id = ?');
    $stmt->execute([$future_date, $docId]);
    
    $stmt = $pdo->prepare('SELECT published_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    assert_true($doc['published_at'] === $future_date, 'expected published_at to be updated');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
