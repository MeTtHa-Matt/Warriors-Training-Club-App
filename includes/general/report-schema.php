<?php

function ensureReportSchema(PDO $pdo): void
{
    $columns = [
        'owner_user_id' => 'INT DEFAULT NULL',
        'status' => "VARCHAR(16) NOT NULL DEFAULT 'open'",
        'user_last_viewed_message_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
    ];

    $existingColumns = $pdo->query('SHOW COLUMNS FROM dashboard_reports')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existingColumns, true)) {
            $pdo->exec("ALTER TABLE dashboard_reports ADD COLUMN {$name} {$definition}");
        }
    }

    $indexes = $pdo->query('SHOW INDEX FROM dashboard_reports')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('idx_dashboard_reports_owner_status', $indexes, true)) {
        $pdo->exec('ALTER TABLE dashboard_reports ADD INDEX idx_dashboard_reports_owner_status (owner_user_id, status)');
    }

    $ownerConstraint = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = \'dashboard_reports\'
           AND CONSTRAINT_NAME = \'fk_dashboard_reports_owner\''
    );
    $ownerConstraint->execute();
    if ((int) $ownerConstraint->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE dashboard_reports ADD CONSTRAINT fk_dashboard_reports_owner
             FOREIGN KEY (owner_user_id) REFERENCES account_wtc(id) ON DELETE SET NULL'
        );
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_report_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sender_type ENUM(\'user\', \'admin\') NOT NULL,
            sender_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report_messages_report_id (report_id, id),
            CONSTRAINT fk_report_messages_report FOREIGN KEY (report_id)
                REFERENCES dashboard_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_report_messages_sender FOREIGN KEY (sender_id)
                REFERENCES account_wtc(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}
