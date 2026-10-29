<?php
declare(strict_types=1);

/**
 * Create a per-user rolling backup snapshot at most once every 24 hours.
 * Called by an authenticated background endpoint so page rendering never waits for it.
 */
function createDailyBackupSnapshot(PDO $pdo, int $userId): bool
{
    $lockName = 'madar_backup_' . $userId;
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        return false; // Another request is creating the snapshot.
    }

    try {
        $q = $pdo->prepare('SELECT created_at FROM backup_snapshots WHERE user_id=? ORDER BY created_at DESC LIMIT 1');
        $q->execute([$userId]);
        $lastSnapshot = $q->fetchColumn();
        if ($lastSnapshot && strtotime((string)$lastSnapshot) >= time() - 86400) {
            return false;
        }

        $snapshot = [];
        foreach (['transactions','tasks','budgets','contacts','bank_cards','savings_goals','checks','categories'] as $table) {
            $q = $pdo->prepare("SELECT * FROM {$table} WHERE user_id=?");
            $q->execute([$userId]);
            $snapshot[$table] = $q->fetchAll();
        }
        $json = json_encode(
            ['format'=>'madar-backup-v1','created_at'=>date(DATE_ATOM),'tables'=>$snapshot],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new RuntimeException('Snapshot JSON encoding failed.');
        }
        $payload = gzencode($json, 6);
        if ($payload === false) {
            throw new RuntimeException('Snapshot compression failed.');
        }

        $q = $pdo->prepare('INSERT INTO backup_snapshots(user_id,created_at,payload) VALUES(?,?,?)');
        $q->execute([$userId, date('Y-m-d H:i:s'), $payload]);
        $pdo->prepare('DELETE FROM backup_snapshots WHERE user_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM backup_snapshots WHERE user_id=? ORDER BY created_at DESC LIMIT 7) keep_ids)')->execute([$userId, $userId]);
        return true;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
