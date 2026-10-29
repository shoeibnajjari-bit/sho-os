<?php
declare(strict_types=1);

// Seed defaults and recover legacy transaction/budget categories once per authenticated session.
// The old implementation repeated these INSERT/SELECT statements on every page request.
if ((int)($_SESSION['category_sync_user'] ?? 0) !== $userId) {
    $defaultCategories = ['خوراک','حمل‌ونقل','خرید','قبض و اینترنت','سلامت','تفریح','دود و دخانیات','درآمد کاری','حقوق و مستمری','سرمایه‌گذاری','متفرقه'];
    $seedCategory = $pdo->prepare('INSERT IGNORE INTO categories(user_id,name) VALUES(?,?)');
    foreach ($defaultCategories as $defaultCategory) {
        $seedCategory->execute([$userId, $defaultCategory]);
    }
    foreach (['transactions','budgets'] as $sourceTable) {
        // Table names are selected only from this fixed allowlist.
        $query = $pdo->prepare("SELECT DISTINCT category FROM {$sourceTable} WHERE user_id=? AND category<>''");
        $query->execute([$userId]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $existingCategory) {
            $seedCategory->execute([$userId, mb_substr((string)$existingCategory, 0, 80)]);
        }
    }
    $_SESSION['category_sync_user'] = $userId;
}
