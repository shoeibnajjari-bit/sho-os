<?php
declare(strict_types=1);

$today = date('Y-m-d');
$todayJalali = jalaliDate($today);
$month = ['income' => 0, 'expense' => 0];
$balance = 0;
$todayExpense = 0;
$monthNet = 0;
$categoryTotals = [];
$budgets = [];
$transactions = [];
$tasks = [];
$pendingChecksForReminder = [];
$goals = [];
$editGoal = null;
$editTask = null;
$editTransaction = null;
$advice = 'ثبت منظم تراکنش‌ها را ادامه بده؛ با چند هفته داده، الگوی هزینه‌ها روشن‌تر می‌شود.';
$dailyBurn = 0.0;
$cashRunway = null;
$monthlyTrend = [];
$analyticsTxCount = 0;
$savingsRate = null;
$badgeTiers = [];
$badgeEligible = false;
$badgeIndex = 0;
$currentBadge = ['min'=>0,'name'=>'شروع‌کننده','icon'=>'✦','class'=>'starter','description'=>'شروع مسیر آگاهانهٔ مالی'];
$nextBadge = $currentBadge;
$badgeHasNext = false;
$badgeProgress = 0;

$txSearch = trim((string)($_GET['q'] ?? ''));
$txTypeFilter = in_array(($_GET['type'] ?? ''), ['income','expense'], true) ? (string)$_GET['type'] : '';
$txCategoryFilter = trim((string)($_GET['category'] ?? ''));
$txPage = max(1, min(100000, (int)($_GET['tx_page'] ?? 1)));
$txPerPage = 50;
$txTotal = 0;
$txPages = 1;
$txCategories = [];
$categories = [];

$needsFinanceOverview = in_array($page, ['home','finance','analytics'], true);
if ($needsFinanceOverview) {
    [$monthStart, $monthEnd] = jalaliMonthRange($today);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) expense FROM transactions WHERE user_id=? AND transaction_date BETWEEN ? AND ?");
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $month = $stmt->fetch() ?: $month;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0) balance FROM transactions WHERE user_id=?");
    $stmt->execute([$userId]);
    $balance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND transaction_date=?");
    $stmt->execute([$userId, $today]);
    $todayExpense = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT category, SUM(amount) total FROM transactions WHERE user_id=? AND type='expense' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC LIMIT 5");
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $categoryTotals = $stmt->fetchAll();

    $monthNet = (int)$month['income'] - (int)$month['expense'];
    if ($monthNet < 0) {
        $advice = 'هزینه‌های این ماه از درآمد ثبت‌شده بیشتر است؛ ابتدا هزینه‌های ضروری و غیرضروری را تفکیک کن.';
    } elseif ((int)$month['income'] > 0 && ((int)$month['expense'] / (int)$month['income']) > 0.8) {
        $advice = 'بیش از ۸۰٪ درآمد ثبت‌شده خرج شده؛ قبل از خرید بعدی بودجه باقی‌مانده را بررسی کن.';
    }

    if ($page === 'finance') {
        $stmt = $pdo->prepare("SELECT b.id,b.category,b.monthly_limit,COALESCE(SUM(t.amount),0) spent FROM budgets b LEFT JOIN transactions t ON t.user_id=b.user_id AND t.category=b.category AND t.type='expense' AND t.transaction_date BETWEEN ? AND ? WHERE b.user_id=? GROUP BY b.id,b.category,b.monthly_limit ORDER BY b.category");
        $stmt->execute([$monthStart, $monthEnd, $userId]);
        $budgets = $stmt->fetchAll();
    }

    if (in_array($page, ['home','analytics'], true)) {
        $rollingStart = date('Y-m-d', strtotime('-29 days'));
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) total_expense, COUNT(*) tx_count FROM transactions WHERE user_id=? AND transaction_date BETWEEN ? AND ?");
        $stmt->execute([$userId, $rollingStart, $today]);
        $rollingStats = $stmt->fetch() ?: ['total_expense'=>0,'tx_count'=>0];
        $last30Expense = (int)$rollingStats['total_expense'];
        $analyticsTxCount = (int)$rollingStats['tx_count'];
        $dailyBurn = $last30Expense / 30;
        $cashRunway = $dailyBurn > 0 ? max(0, (int)floor($balance / $dailyBurn)) : null;
        $savingsRate = (int)$month['income'] > 0 ? round($monthNet / (int)$month['income'] * 100) : null;

        if ($page === 'analytics') {
            $trendStart = date('Y-m-01', strtotime('-5 months', strtotime(date('Y-m-01'))));
            $trendStmt = $pdo->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym, COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) expense FROM transactions WHERE user_id=? AND transaction_date>=? AND transaction_date<=? GROUP BY ym ORDER BY ym");
            $trendStmt->execute([$userId, $trendStart, $today]);
            $trendRows = [];
            foreach ($trendStmt->fetchAll() as $trendRow) $trendRows[$trendRow['ym']] = $trendRow;
            for ($offset = 5; $offset >= 0; $offset--) {
                $monthIso = date('Y-m-01', strtotime('-'.$offset.' months', strtotime(date('Y-m-01'))));
                $monthKey = date('Y-m', strtotime($monthIso));
                $trend = $trendRows[$monthKey] ?? ['income'=>0,'expense'=>0];
                $monthlyTrend[] = [
                    'label' => jalaliDate($monthIso),
                    'income' => (int)$trend['income'],
                    'expense' => (int)$trend['expense']
                ];
            }
        }

        $badgeTiers = [
            ['min'=>0,'name'=>'شروع‌کننده','icon'=>'✦','class'=>'starter','description'=>'شروع مسیر آگاهانهٔ مالی'],
            ['min'=>1000000,'name'=>'برنزی','icon'=>'🥉','class'=>'bronze','description'=>'یک میلیون تومان پس‌انداز خالص در ماه'],
            ['min'=>3000000,'name'=>'نقره‌ای','icon'=>'🥈','class'=>'silver','description'=>'سه میلیون تومان پس‌انداز خالص در ماه'],
            ['min'=>5000000,'name'=>'طلایی','icon'=>'🥇','class'=>'gold','description'=>'پنج میلیون تومان پس‌انداز خالص در ماه'],
            ['min'=>10000000,'name'=>'پلاتینیوم','icon'=>'💠','class'=>'platinum','description'=>'ده میلیون تومان پس‌انداز خالص در ماه'],
            ['min'=>25000000,'name'=>'الماس','icon'=>'💎','class'=>'diamond','description'=>'بیست‌وپنج میلیون تومان پس‌انداز خالص در ماه']
        ];
        $badgeEligible = (int)$month['income'] > 0 && $monthNet >= 0;
        if ($badgeEligible) {
            foreach ($badgeTiers as $i => $tier) {
                if ($monthNet >= $tier['min']) $badgeIndex = $i;
            }
        }
        $currentBadge = $badgeTiers[$badgeIndex];
        $nextBadge = $badgeTiers[min($badgeIndex + 1, count($badgeTiers) - 1)];
        $badgeHasNext = $badgeIndex < count($badgeTiers) - 1;
        $badgeProgress = $badgeHasNext
            ? max(0, min(100, (int)round((max(0, $monthNet - (int)$currentBadge['min']) / max(1, (int)$nextBadge['min'] - (int)$currentBadge['min'])) * 100)))
            : 100;
    }
}

// The category list is needed only where a financial/category form or filter is rendered.
if (in_array($page, ['home','finance','transactions','categories'], true)) {
    $categoryListStmt = $pdo->prepare('SELECT id,name FROM categories WHERE user_id=? ORDER BY name');
    $categoryListStmt->execute([$userId]);
    $categories = $categoryListStmt->fetchAll();
    $txCategories = array_column($categories, 'name');
}

// Fetch transaction rows only on the pages that actually display them.
if ($page === 'transactions') {
    $txWhere = ['user_id=?'];
    $txParams = [$userId];
    if ($txTypeFilter !== '') {$txWhere[] = 'type=?'; $txParams[] = $txTypeFilter;}
    if ($txCategoryFilter !== '' && in_array($txCategoryFilter, $txCategories, true)) {$txWhere[] = 'category=?'; $txParams[] = $txCategoryFilter;}
    if ($txSearch !== '') {
        $txWhere[] = '(note LIKE ? OR category LIKE ? OR CAST(amount AS CHAR) LIKE ?)';
        $like = '%' . $txSearch . '%';
        array_push($txParams, $like, $like, $like);
    }
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE ' . implode(' AND ', $txWhere));
    $countStmt->execute($txParams);
    $txTotal = (int)$countStmt->fetchColumn();
    $txPages = max(1, (int)ceil($txTotal / $txPerPage));
    $txPage = min($txPage, $txPages);
    $txOffset = ($txPage - 1) * $txPerPage;
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE ' . implode(' AND ', $txWhere) . ' ORDER BY transaction_date DESC,id DESC LIMIT ' . $txPerPage . ' OFFSET ' . $txOffset);
    $stmt->execute($txParams);
    $transactions = $stmt->fetchAll();

    if (isset($_GET['edit_tx'])) {
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id=? AND user_id=?');
        $stmt->execute([(int)$_GET['edit_tx'], $userId]);
        $editTransaction = $stmt->fetch() ?: null;
    }
} elseif (in_array($page, ['home','finance'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY transaction_date DESC,id DESC LIMIT 12");
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll();
}

if (in_array($page, ['home','tasks'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id=? ORDER BY is_done ASC, (due_date IS NULL) ASC, due_date ASC, id DESC LIMIT 30");
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll();
}
if ($page === 'tasks' && isset($_GET['edit_task'])) {
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id=? AND user_id=?');
    $stmt->execute([(int)$_GET['edit_task'], $userId]);
    $editTask = $stmt->fetch() ?: null;
}

// Lightweight check query feeds the browser reminders. Keep its old behaviour on every authenticated page.
$stmt = $pdo->prepare("SELECT id,check_type,status,due_date,amount FROM checks WHERE user_id=? AND status='pending' AND due_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND DATE_ADD(CURDATE(),INTERVAL 3 DAY) ORDER BY due_date ASC LIMIT 100");
$stmt->execute([$userId]);
$pendingChecksForReminder = $stmt->fetchAll();

if ($page === 'goals') {
    $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id=? ORDER BY (current_amount>=target_amount) ASC,target_date IS NULL ASC,target_date ASC,id DESC');
    $stmt->execute([$userId]);
    $goals = $stmt->fetchAll();
    if (isset($_GET['edit_goal'])) {
        $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id=? AND user_id=?');
        $stmt->execute([(int)$_GET['edit_goal'], $userId]);
        $editGoal = $stmt->fetch() ?: null;
    }
}
