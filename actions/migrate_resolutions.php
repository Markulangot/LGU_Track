<?php
/**
 * One-time migration to move RES-* records from `ordinances` into `resolutions` table.
 * Run: from project root: php actions/migrate_resolutions.php
 */
require_once __DIR__ . '/../includes/db.php';

echo "Starting migration of RES-* ordinances to resolutions table...\n";

try {
    $pdo->beginTransaction();

    // Create resolutions table if missing (match submit_resolution.php schema)
    $pdo->exec("CREATE TABLE IF NOT EXISTS resolutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resolution_number VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        date_enacted DATE,
        status VARCHAR(30) DEFAULT 'draft',
        department VARCHAR(100),
        main_author VARCHAR(100),
        co_authors TEXT,
        soft_copy LONGTEXT,
        hard_copy_path VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resolution_tags (
        resolution_id INT,
        tag_id INT,
        PRIMARY KEY (resolution_id, tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Find RES-* ordinances
    $stmt = $pdo->query("SELECT * FROM ordinances WHERE ordinance_number LIKE 'RES-%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "No RES-* records found. Nothing to migrate.\n";
        if ($pdo->inTransaction()) $pdo->commit();
        exit(0);
    }

    $migrated = 0;
    foreach ($rows as $row) {
        $old_id = (int)$row['id'];
        $res_number = $row['ordinance_number'] ?? null;

        // Skip if already exists in resolutions
        $check = $pdo->prepare("SELECT id FROM resolutions WHERE resolution_number = ?");
        $check->execute([$res_number]);
        if ($check->fetchColumn()) {
            echo "Skipping existing resolution $res_number\n";
            continue;
        }

        $cols = [
            'resolution_number' => $res_number,
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'date_enacted' => $row['date_enacted'] ?? null,
            'status' => $row['status'] ?? null,
            'department' => $row['department'] ?? null,
            'main_author' => $row['main_author'] ?? null,
            'co_authors' => $row['co_authors'] ?? ($row['co_authors'] ?? null),
            'soft_copy' => $row['soft_copy'] ?? null,
            'hard_copy_path' => $row['hard_copy_path'] ?? null,
            'notes' => $row['notes'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
        $ins = $pdo->prepare("INSERT INTO resolutions (" . implode(',', array_keys($cols)) . ") VALUES ($placeholders)");
        $ins->execute(array_values($cols));
        $new_id = $pdo->lastInsertId();

        // Migrate tags
        $t = $pdo->prepare("SELECT tag_id FROM ordinance_tags WHERE ordinance_id = ?");
        $t->execute([$old_id]);
        $tag_ids = $t->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tag_ids)) {
            $ins_tag = $pdo->prepare("INSERT INTO resolution_tags (resolution_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $ins_tag->execute([$new_id, $tag_id]);
            }
            // remove old tags
            $pdo->prepare("DELETE FROM ordinance_tags WHERE ordinance_id = ?")->execute([$old_id]);
        }

        // Update activity log entries that referenced the old ordinance id
        $upd = $pdo->prepare("UPDATE activity_log SET target_type = 'resolution', target_id = ? WHERE target_type = 'ordinance' AND target_id = ?");
        $upd->execute([$new_id, $old_id]);

        // Finally remove the ordinance row
        $pdo->prepare("DELETE FROM ordinances WHERE id = ?")->execute([$old_id]);

        echo "Migrated $res_number (old id: $old_id -> new id: $new_id)\n";
        $migrated++;
    }

    if ($pdo->inTransaction()) $pdo->commit();
    echo "Migration complete. Migrated $migrated records.\n";
    exit(0);

} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $_) { /* ignore rollback errors */ }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
