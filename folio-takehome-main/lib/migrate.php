<?php

require_once __DIR__ . '/bootstrap.php';

function run_migrations() {
    $pdo = db();
    
    // Create migrations table if it doesn't exist
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )
    ');

    $migrationsDir = __DIR__ . '/../migrations';
    if (!is_dir($migrationsDir)) {
        return;
    }

    $files = glob($migrationsDir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        $filename = basename($file);
        
        $stmt = $pdo->prepare('SELECT id FROM migrations WHERE filename = ?');
        $stmt->execute([$filename]);
        
        if (!$stmt->fetch()) {
            echo "Applying migration: $filename\n";
            $sql = file_get_contents($file);
            
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Ignore "duplicate column name" or "index already exists" errors
                $msg = $e->getMessage();
                if (strpos($msg, 'duplicate column name') === false && strpos($msg, 'already exists') === false) {
                    throw $e;
                }
            }
            
            $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
            $stmt->execute([$filename]);
        }
    }
}
