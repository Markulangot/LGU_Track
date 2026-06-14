<?php
$page_title = "For Reading";
$current_page = "pending";
require_once 'includes/header.php';

// Load pending records from both ordinances and resolutions (if present)
$pending_ordinances = [];
$reading_statuses = "('for_reading','under_review', 'draft', '1st_reading', '2nd_reading', '3rd_reading')";

// Ordinances
$stmt = $pdo->prepare("SELECT o.*, 
        GROUP_CONCAT(t.name SEPARATOR '||') as tag_names,
        GROUP_CONCAT(t.color_theme SEPARATOR '||') as tag_colors,
        'ordinance' AS record_type
    FROM ordinances o
    LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
    LEFT JOIN tags t ON ot.tag_id = t.id
    WHERE o.status IN $reading_statuses
    GROUP BY o.id");
$stmt->execute();
$ords = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ords as $r) $pending_ordinances[] = $r;

// Resolutions (if table exists)
try {
    $tbl = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'resolutions'");
    $tbl->execute();
    $has_res = (bool)$tbl->fetchColumn();
} catch (Exception $e) {
    $has_res = false;
}
if ($has_res) {
    $stmt = $pdo->prepare("SELECT r.*, r.resolution_number AS ordinance_number, 
            GROUP_CONCAT(t.name SEPARATOR '||') as tag_names,
            GROUP_CONCAT(t.color_theme SEPARATOR '||') as tag_colors,
            'resolution' AS record_type
        FROM resolutions r
        LEFT JOIN resolution_tags rt ON r.id = rt.resolution_id
        LEFT JOIN tags t ON rt.tag_id = t.id
        WHERE r.status IN $reading_statuses
        GROUP BY r.id");
    $stmt->execute();
    $res_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res_rows as $r) $pending_ordinances[] = $r;
}

// Sort merged list by date_enacted desc
usort($pending_ordinances, function($a, $b){
    $ta = strtotime($a['date_enacted'] ?? '1970-01-01');
    $tb = strtotime($b['date_enacted'] ?? '1970-01-01');
    return $tb <=> $ta;
});

// Recent review activity (last 15 log entries for review actions)
$logs = $pdo->query("
    SELECT al.*, u.full_name as reviewer_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action IN ('approved','revision_requested','marked_under_review','marked_amended')
    ORDER BY al.timestamp DESC
    LIMIT 15
")->fetchAll();

$total = count($pending_ordinances);
// Compute normalized reading-stage counts
// Track counts including the initial For Reading stage
$counts = ['for_reading'=>0,'1st_reading'=>0,'2nd_reading'=>0,'3rd_reading'=>0,'approved'=>0,'rejected'=>0,'draft'=>0];
foreach ($pending_ordinances as $o) {
    $k = normalize_reading_status($o['status']);
    if (isset($counts[$k])) $counts[$k]++;
    if ($o['status'] === 'draft') $counts['draft']++;
}
?>

<div class="space-y-6">

    <!-- ── Page Header ─────────────────────────────────────── -->
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-[26px] font-bold leading-tight" style="color:#7f0000;">For Reading</h1>
            <p class="text-sm text-on-surface-variant mt-0.5">Ordinances at various reading stages awaiting committee action.</p>
        </div>
        <!-- Quick stats -->
        <div class="flex gap-3 flex-wrap">
            <div class="px-4 py-2 rounded-lg text-center" style="background:#fef2f2;border:1px solid #fca5a5;min-width:80px">
                <div class="text-[22px] font-bold" style="color:#7f0000;"><?php echo $total; ?></div>
                <div class="text-[10px] font-bold text-outline uppercase tracking-wider">Total</div>
            </div>
            <div class="px-4 py-2 rounded-lg text-center" style="background:#fff7ed;border:1px solid #fed7aa;min-width:80px">
                <div class="text-[22px] font-bold text-amber-700"><?php echo ($counts['for_reading'] + $counts['1st_reading'] + $counts['2nd_reading'] + $counts['3rd_reading']); ?></div>
                <div class="text-[10px] font-bold text-outline uppercase tracking-wider">In Reading</div>
            </div>
            <div class="px-4 py-2 rounded-lg text-center" style="background:#f8fafc;border:1px solid #cbd5e1;min-width:80px">
                <div class="text-[22px] font-bold text-slate-600"><?php echo $counts['draft']; ?></div>
                <div class="text-[10px] font-bold text-outline uppercase tracking-wider">Drafts</div>
            </div>
        </div>
    </div>

    <!-- ── Filter Bar ──────────────────────────────────────── -->
    <div class="sticky top-[80px] z-30 flex flex-wrap gap-3 items-center bg-white border border-outline-variant rounded-lg px-4 py-3 shadow-sm">
        <span class="text-xs font-bold text-outline uppercase tracking-wider">Filter:</span>
        <button onclick="filterReview('all')" data-filter="all"
            class="filter-btn active-filter px-3 py-1 rounded text-xs font-bold transition-all">All</button>
        <button onclick="filterReview('for_reading')" data-filter="for_reading"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">For Reading</button>
        <button onclick="filterReview('1st_reading')" data-filter="1st_reading"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">1st Reading</button>
        <button onclick="filterReview('2nd_reading')" data-filter="2nd_reading"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">2nd Reading</button>
        <button onclick="filterReview('3rd_reading')" data-filter="3rd_reading"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">3rd Reading</button>
        <button onclick="filterReview('approved')" data-filter="approved"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">Approved</button>
        <button onclick="filterReview('rejected')" data-filter="rejected"
            class="filter-btn px-3 py-1 rounded text-xs font-bold transition-all">Rejected</button>
        <div class="w-[1px] h-6 bg-outline-variant mx-2 hidden sm:block"></div>
        <div class="relative flex-1 min-w-[200px]">
            <img src="assets/icons/glass.png" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 opacity-40" alt="Search">
                 <input type="text" id="searchInput" onkeyup="applyFilters()"
                   class="w-full pl-9 pr-4 py-1.5 border border-outline-variant rounded text-xs focus:outline-none focus:border-navy transition-all"
                     placeholder="Search for reading...">
        </div>
        <!-- Keyboard shortcut hint -->
        <span class="text-[10px] text-outline hidden md:block">
            Shortcuts: <kbd class="bg-gray-100 border border-gray-300 rounded px-1">A</kbd> Approve &nbsp;
            <kbd class="bg-gray-100 border border-gray-300 rounded px-1">R</kbd> Revision &nbsp;
            <kbd class="bg-gray-100 border border-gray-300 rounded px-1">V</kbd> View
        </span>
    </div>

    <!-- ── Main 2-Column Layout ────────────────────────────── -->
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">

        <!-- LEFT COLUMN WRAPPER -->
        <div class="flex flex-col gap-4 min-w-0">
            <!-- LEFT: Ordinance List -->
        <div class="space-y-3" id="ordinanceList">
            <?php if (empty($pending_ordinances)): ?>
                <div class="flex flex-col items-center justify-center py-24 bg-white rounded-lg border border-outline-variant">
                    <img src="assets/icons/clipboard.png" class="w-12 h-12 opacity-20 mb-4" alt="No pending">
                    <p class="text-on-surface-variant font-medium">No pending ordinances.</p>
                    <p class="text-xs text-outline mt-1">All ordinances are up to date.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_ordinances as $idx => $ord): ?>
                    <?php
                    $is_review = in_array(normalize_reading_status($ord['status']), ['1st_reading','2nd_reading','3rd_reading']);
                    $tags  = $ord['tag_names'] ? explode('||', $ord['tag_names']) : [];
                    $colors = $ord['tag_colors'] ? explode('||', $ord['tag_colors']) : [];

                    // Completeness checklist
                    $checks = [
                        'Ordinance Number' => !empty($ord['ordinance_number']),
                        'Title'            => !empty($ord['title']),
                        'Department'       => !empty($ord['department']),
                        'Author'           => !empty($ord['main_author']),
                        'Date Enacted'     => !empty($ord['date_enacted']),
                        'Description'      => !empty($ord['description']),
                        'Hard Copy PDF'    => !empty($ord['hard_copy_path']),
                    ];
                    $done_count = count(array_filter($checks));
                    $pct = round(($done_count / count($checks)) * 100);
                    $pct_color = $pct >= 85 ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626');
                ?>
                 <div class="review-card bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm transition-all hover:shadow-md"
                     data-status="<?php echo $ord['status']; ?>"
                     data-reading="<?php echo normalize_reading_status($ord['status']); ?>"
                     data-id="<?php echo $ord['id']; ?>"
                     data-type="<?php echo $ord['record_type'] ?? 'ordinance'; ?>"
                     data-idx="<?php echo $idx; ?>">

                    <!-- Card Header -->
                    <div class="flex items-start gap-4 p-5">

                        <!-- Status icon -->
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background:<?php echo $is_review ? '#fff7ed' : '#f8fafc'; ?>;">
                            <img src="assets/icons/<?php echo $is_review ? 'clock.png' : 'edit.png'; ?>"
                                 class="w-5 h-5 opacity-70" alt="Status">
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap justify-between items-start gap-2 mb-1">
                                <span class="text-[12px] font-bold uppercase tracking-tight" style="color:#7f0000;">
                                    <?php echo htmlspecialchars($ord['ordinance_number']); ?>
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider status-<?php echo str_replace('_','-',$ord['status']); ?>">
                                    <?php echo reading_label($ord['status']); ?>
                                </span>
                            </div>
                            <h3 class="text-sm font-semibold text-on-surface mb-1 font-doc italic line-clamp-1">
                                <?php echo htmlspecialchars($ord['title']); ?>
                            </h3>
                            <p class="text-xs text-on-surface-variant line-clamp-1 mb-3">
                                <?php echo htmlspecialchars($ord['description'] ?: '—'); ?>
                            </p>

                            <!-- Meta strip -->
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="flex items-center gap-1 text-[11px] text-outline">
                                    <img src="assets/icons/calendar.png" class="w-3 h-3 opacity-50">
                                    <?php echo date('M d, Y', strtotime($ord['date_enacted'])); ?>
                                </span>
                                <span class="flex items-center gap-1 text-[11px] text-outline">
                                    <img src="assets/icons/user.png" class="w-3 h-3 opacity-50">
                                    <?php echo htmlspecialchars($ord['main_author'] ?: '—'); ?>
                                </span>
                                <?php foreach ($tags as $i => $tag): ?>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold border <?php echo $colors[$i] ?? 'tc-slate'; ?>">
                                        <?php echo htmlspecialchars($tag); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Completeness Bar -->
                    <div class="px-5 pb-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-outline">Completeness</span>
                            <span class="text-[11px] font-bold" style="color:<?php echo $pct_color; ?>;"><?php echo $pct; ?>%</span>
                        </div>
                        <div class="w-full rounded-full overflow-hidden" style="background:#f1f5f9;height:4px;">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="width:<?php echo $pct; ?>%;background:<?php echo $pct_color; ?>;"></div>
                        </div>

                        <!-- Mini checklist (collapsed) -->
                        <div class="checklist-body hidden mt-3 grid grid-cols-2 gap-1">
                            <?php foreach ($checks as $label => $passed): ?>
                            <div class="flex items-center gap-1.5 text-[11px] <?php echo $passed ? 'text-green-700' : 'text-red-600'; ?>">
                                <span><?php echo $passed ? '✓' : '✗'; ?></span>
                                <span><?php echo $label; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button onclick="toggleChecklist(this)"
                                class="mt-2 text-[10px] font-bold uppercase tracking-wider text-outline hover:text-on-surface transition-all">
                            Show checklist ▾
                        </button>
                    </div>

                    <!-- ── Sticky Action Bar ─────────────────────── -->
                        <div class="flex items-center gap-2 px-5 py-3 border-t border-outline-variant"
                         style="background:#fdf8f8;">
                        <?php
                            $current_stage = normalize_reading_status($ord['status']);
                        ?>
                        <!-- Proceed / Approve / Reject controls (left group) -->
                        <div class="action-controls flex items-center gap-1.5" style="white-space:nowrap;margin-right:6px;">
                                <?php if ($current_stage === 'for_reading'): ?>
                                    <button data-review-action="1st_reading" title="Proceed to 1st Reading"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">
                                        ⇢ Proceed to 1st Reading
                                    </button>
                                <?php elseif ($current_stage === '1st_reading'): ?>
                                    <button data-review-action="2nd_reading" title="Proceed to 2nd Reading"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">
                                        ⇢ Proceed to 2nd Reading
                                    </button>
                                <?php elseif ($current_stage === '2nd_reading'): ?>
                                    <button data-review-action="3rd_reading" title="Proceed to 3rd Reading"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">
                                        ⇢ Proceed to 3rd Reading
                                    </button>
                                <?php elseif ($current_stage === '3rd_reading'): ?>
                                    <button data-review-action="approved" title="Approve (A)"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#166534;color:#fff;margin-right:6px;">
                                        ✓ Approve
                                    </button>
                                <?php else: ?>
                                    <button data-review-action="1st_reading" title="Proceed to 1st Reading"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">
                                        ⇢ Proceed to 1st Reading
                                    </button>
                                <?php endif; ?>

                                <?php if ($current_stage !== '3rd_reading'): ?>
                                    <button data-review-action="approved" title="Approve (A)"
                                            class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                            style="background:#166534;color:#fff;margin-right:6px;">
                                        ✓ Approve
                                    </button>
                                <?php endif; ?>
                                <button data-review-action="rejected" title="Reject"
                                        class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                                        style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">
                                    ✕ Reject
                                </button>
                        </div>
                        <div class="flex-1"></div>
                        <!-- Move to Bin (right group) -->
                        <form action="actions/move_to_bin.php" method="POST" style="display:inline;margin-right:6px;" onsubmit="return confirm('Move this item to Bin?');">
                            <input type="hidden" name="id" value="<?php echo $ord['id']; ?>">
                            <input type="hidden" name="type" value="<?php echo $ord['record_type'] ?? 'ordinance'; ?>">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button type="submit" class="px-3 py-1.5 rounded text-xs font-bold transition-all" style="background:#ef4444;color:#fff;">Delete</button>
                        </form>
                        <!-- Edit -->
                        <a href="<?php echo ($ord['record_type'] === 'resolution') ? 'submit_resolution.php?id=' . $ord['id'] : 'submit.php?id=' . $ord['id']; ?>"
                           class="px-3 py-1.5 rounded text-xs font-bold transition-all"
                           style="border:1px solid #e0c8c8;color:#7f0000;" title="Edit (V)">
                            ✎ Edit
                        </a>
                        <!-- View -->
                        <a href="<?php echo ($ord['record_type'] === 'resolution') ? 'view.php?type=resolution&id=' . $ord['id'] : 'view.php?id=' . $ord['id']; ?>"
                           class="px-3 py-1.5 rounded text-xs font-bold text-white transition-all"
                           style="background:#7f0000;" title="View full record">
                            View →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Controls -->
        <div id="paginationControls" class="flex items-center justify-between mt-4 bg-white px-4 py-3 border border-outline-variant rounded-lg">
            <span class="text-[11px] font-bold text-outline uppercase tracking-wider" id="recordCountDisplay">Showing records</span>
            <div id="paginationButtons" class="flex gap-1.5"></div>
        </div>
        </div>

        <!-- RIGHT: Sidebar Panels -->
        <div class="space-y-5">

            <!-- ── Review Checklist Guide ───────────────── -->
            <div class="bg-white border border-outline-variant rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-base">📋</span>
                    <h3 class="text-sm font-bold" style="color:#7f0000;">Review Criteria</h3>
                </div>
                <div class="space-y-2 text-[12px]">
                    <?php
                    $criteria = [
                        ['Ordinance number follows format', true],
                        ['Title is descriptive and complete', true],
                        ['Author and co-authors listed', true],
                        ['Date of enactment set', true],
                        ['Department assigned', true],
                        ['Soft copy content added', null],
                        ['Hard copy PDF attached', null],
                    ];
                    foreach ($criteria as [$label, $status]):
                    ?>
                    <div class="flex items-center gap-2 <?php echo $status === true ? 'text-green-700' : 'text-on-surface-variant'; ?>">
                        <span class="w-4 h-4 rounded flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                              style="background:<?php echo $status === true ? '#dcfce7' : '#f1f5f9'; ?>">
                            <?php echo $status === true ? '✓' : '·'; ?>
                        </span>
                        <?php echo $label; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Keyboard shortcuts -->
                <div class="mt-5 pt-4 border-t border-outline-variant">
                    <p class="text-[10px] font-bold text-outline uppercase tracking-wider mb-2">Keyboard Shortcuts</p>
                    <div class="grid grid-cols-2 gap-1 text-[11px]">
                        <div><kbd class="bg-gray-100 border border-gray-300 rounded px-1 text-[10px]">A</kbd> Approve</div>
                        <div><kbd class="bg-gray-100 border border-gray-300 rounded px-1 text-[10px]">R</kbd> Revision</div>
                        <div><kbd class="bg-gray-100 border border-gray-300 rounded px-1 text-[10px]">V</kbd> View</div>
                        <div><kbd class="bg-gray-100 border border-gray-300 rounded px-1 text-[10px]">↑↓</kbd> Navigate</div>
                    </div>
                </div>
            </div>

            <!-- ── Reviewer Activity Timeline ───────────── -->
            <div class="bg-white border border-outline-variant rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-base">⏱</span>
                    <h3 class="text-sm font-bold" style="color:#7f0000;">Review Activity</h3>
                </div>
                <?php if (empty($logs)): ?>
                    <p class="text-xs text-outline italic text-center py-4">No review activity yet.</p>
                <?php else: ?>
                <div class="space-y-3 max-h-[360px] overflow-y-auto pr-1">
                    <?php foreach ($logs as $log):
                        $icon_map = [
                            'approved'              => ['✓', '#16a34a', '#dcfce7'],
                            'revision_requested'    => ['↩', '#b91c1c', '#fef2f2'],
                            'marked_under_review'   => ['⇢', '#d97706', '#fff7ed'],
                            'marked_amended'        => ['~', '#6d28d9', '#f5f3ff'],
                        ];
                        [$ico, $color, $bg] = $icon_map[$log['action']] ?? ['·', '#6b7280', '#f1f5f9'];
                    ?>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-[11px] font-bold"
                             style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>;">
                            <?php echo $ico; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[12px] font-semibold text-on-surface">
                                <?php echo htmlspecialchars($log['target_name']); ?>
                            </p>
                            <p class="text-[11px] text-on-surface-variant line-clamp-1">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </p>
                            <p class="text-[10px] text-outline mt-0.5">
                                <?php echo htmlspecialchars($log['reviewer_name'] ?? 'System'); ?>
                                · <?php echo date('M d, g:ia', strtotime($log['timestamp'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Confirmation Modal ──────────────────────────────── -->
<div id="confirmModal" class="fixed inset-0 z-[600] hidden items-center justify-center px-4" style="background:rgba(0,0,0,0.45); backdrop-filter:blur(3px);">
    <div class="bg-white shadow-2xl w-full max-w-[380px] p-8 relative">
        <h3 id="confirmTitle" class="text-lg font-bold text-navy mb-2">Confirm Action</h3>
        <p id="confirmDesc" class="text-[13px] text-on-surface-variant mb-8 leading-relaxed">Are you sure you want to proceed?</p>
        <div class="flex items-center gap-3 justify-end">
            <button onclick="closeConfirm()" class="px-4 py-2 text-sm font-bold text-on-surface-variant hover:text-navy transition-all border border-transparent hover:border-outline-variant">No, Cancel</button>
            <button id="confirmBtn" class="px-6 py-2 text-sm font-bold text-white bg-navy transition-all shadow-md hover:opacity-90">Yes, Proceed</button>
        </div>
    </div>
</div>

<!-- ── Toast Notification ──────────────────────────────── -->
<div id="reviewToast" class="fixed bottom-6 right-6 z-[500] hidden"
     style="transition:all .3s ease;">
    <div class="px-5 py-3 shadow-xl text-sm font-bold text-white flex items-center gap-3"
         style="background:#7f0000;min-width:260px;">
        <span id="reviewToastIcon">✓</span>
        <span id="reviewToastMsg">Done</span>
    </div>
</div>

<style>
    .filter-btn { background:#f8fafc; color:#6b7280; border:1px solid #e2e8f0; }
    .active-filter { background:#fef2f2 !important; color:#7f0000 !important; border-color:#fca5a5 !important; }
    .review-card.selected { border-color:#b91c1c !important; box-shadow:0 0 0 2px rgba(185,28,28,.15); }
    .review-card { cursor:pointer; }
    .checklist-body { display:none; }
    .checklist-body.open { display:grid; }
</style>

<script>
// ── State ─────────────────────────────────────────────────
var cards = Array.from(document.querySelectorAll('.review-card'));
// Ensure each card's action controls are rendered consistently
cards.forEach(c => renderActionControls(c));
var visibleCards = [...cards];

// Delegate clicks on dynamically-rendered review buttons to ensure they work
cards.forEach(c => {
    c.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-review-action]');
        if (btn && c.contains(btn)) {
            e.preventDefault();
            e.stopPropagation();
            var action = btn.getAttribute('data-review-action');
            reviewAction(c.dataset.id, action, btn);
        }
    });
});
if (visibleCards.length) selectCard(0);

// ── Keyboard Shortcuts ─────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    var card = visibleCards[activeIdx];
    if (!card) return;
    var id = card.dataset.id;

    if (e.key === 'ArrowDown') { e.preventDefault(); selectCard(activeIdx + 1); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); selectCard(activeIdx - 1); }
    if (e.key.toLowerCase() === 'a') reviewAction(id, 'approved',   card.querySelector('[title*="Approve"]'));
    if (e.key.toLowerCase() === 'r') reviewAction(id, 'draft',    card.querySelector('[title*="Revision"]'));
    if (e.key.toLowerCase() === 'v') { var link = card.querySelector('a[href*="view.php"]'); if(link) link.click(); }
});

// ── Filter & Search & Pagination ────────────────────────────────────────────────
function filterReview(status) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active-filter'));
    document.querySelector(`[data-filter="${status}"]`).classList.add('active-filter');
    currentStatusFilter = status;
    applyFilters(true);
}

function applyFilters(resetPage = true) {
    if (resetPage) currentPage = 1;
    
    const searchVal = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase() : '';
    
    removeHighlights();
    
    let matchingCards = [];
    
    cards.forEach(c => {
        let match = true;
        
        if (currentStatusFilter !== 'all') {
                var cardReading = c.dataset.reading || c.dataset.status;
                if (cardReading !== currentStatusFilter) {
                    match = false;
                }
            }
        
        if (searchVal) {
            const textContent = c.innerText.toLowerCase();
            if (!textContent.includes(searchVal)) {
                match = false;
            }
        }
        
        if (match) {
            matchingCards.push(c);
        } else {
            c.style.display = 'none';
        }
    });
    
    const totalMatching = matchingCards.length;
    const totalPages = Math.ceil(totalMatching / itemsPerPage);
    
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    
    visibleCards = [];
    matchingCards.forEach((c, index) => {
        if (index >= startIndex && index < endIndex) {
            c.style.display = '';
            visibleCards.push(c);
        } else {
            c.style.display = 'none';
        }
    });
    
    const countDisplay = document.getElementById('recordCountDisplay');
    if (countDisplay) {
        if (totalMatching === 0) {
            countDisplay.innerHTML = `Showing <strong>0</strong> records`;
        } else {
            const displayEnd = Math.min(endIndex, totalMatching);
            countDisplay.innerHTML = `Showing <strong>${startIndex + 1}-${displayEnd}</strong> of <strong>${totalMatching}</strong> records`;
        }
    }
    
    renderPagination(totalPages);
    
    if (searchVal && searchVal.trim().length > 1) {
        highlightKeywords(searchVal.trim());
    }
    
    if (visibleCards.length > 0) {
        if (!visibleCards[activeIdx]) activeIdx = 0;
        selectCard(activeIdx);
    }
}

function renderPagination(totalPages) {
    const container = document.getElementById('paginationButtons');
    if (!container) return;
    container.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    const prevBtn = document.createElement('button');
    prevBtn.innerText = 'Prev';
    prevBtn.className = `px-2.5 py-1 border rounded text-[11px] font-bold transition-all ${currentPage === 1 ? 'opacity-50 cursor-not-allowed border-outline-variant text-outline' : 'border-outline-variant text-navy hover:bg-surface-container'}`;
    prevBtn.onclick = () => { if (currentPage > 1) changePage(currentPage - 1); };
    container.appendChild(prevBtn);

    let startP = Math.max(1, currentPage - 1);
    let endP = Math.min(totalPages, currentPage + 1);

    if (startP > 1) {
        container.appendChild(createPageBtn(1));
        if (startP > 2) {
            const dots = document.createElement('span');
            dots.innerText = '...';
            dots.className = 'px-1 text-outline text-[11px]';
            container.appendChild(dots);
        }
    }

    for (let i = startP; i <= endP; i++) {
        container.appendChild(createPageBtn(i));
    }

    if (endP < totalPages) {
        if (endP < totalPages - 1) {
            const dots = document.createElement('span');
            dots.innerText = '...';
            dots.className = 'px-1 text-outline text-[11px]';
            container.appendChild(dots);
        }
        container.appendChild(createPageBtn(totalPages));
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerText = 'Next';
    nextBtn.className = `px-2.5 py-1 border rounded text-[11px] font-bold transition-all ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed border-outline-variant text-outline' : 'border-outline-variant text-navy hover:bg-surface-container'}`;
    nextBtn.onclick = () => { if (currentPage < totalPages) changePage(currentPage + 1); };
    container.appendChild(nextBtn);
}

function createPageBtn(num) {
    const btn = document.createElement('button');
    btn.innerText = num;
    if (num === currentPage) {
        btn.className = 'px-2.5 py-1 border rounded text-[11px] font-bold border-navy bg-navy text-white shadow-sm';
    } else {
        btn.className = 'px-2.5 py-1 border rounded text-[11px] font-bold border-outline-variant text-navy hover:bg-surface-container transition-all';
        btn.onclick = () => changePage(num);
    }
    return btn;
}

function changePage(num) {
    currentPage = num;
    applyFilters(false);
}

function removeHighlights() {
    document.querySelectorAll('#ordinanceList .highlight').forEach(el => {
        const parent = el.parentNode;
        parent.replaceChild(document.createTextNode(el.textContent), el);
        parent.normalize();
    });
}

function highlightKeywords(keyword) {
    const regex = new RegExp(`(${keyword})`, 'gi');
    const container = document.getElementById('ordinanceList');
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
    
    const nodesToReplace = [];
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.parentElement && !['SCRIPT', 'STYLE'].includes(node.parentElement.tagName) && !node.parentElement.classList.contains('highlight')) {
            if (node.nodeValue.match(regex)) {
                nodesToReplace.push(node);
            }
        }
    }
    
    nodesToReplace.forEach(node => {
        const fragment = document.createDocumentFragment();
        const parts = node.nodeValue.split(regex);
        parts.forEach(part => {
            if (part.toLowerCase() === keyword.toLowerCase()) {
                const hl = document.createElement('span');
                hl.className = 'highlight';
                hl.textContent = part;
                fragment.appendChild(hl);
            } else if (part) {
                fragment.appendChild(document.createTextNode(part));
            }
        });
        node.parentNode.replaceChild(fragment, node);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyFilters(true);
});

// ── Checklist Toggle ──────────────────────────────────────
function toggleChecklist(btn) {
    var body = btn.previousElementSibling;
    var open = body.classList.toggle('open');
    body.style.display = open ? 'grid' : 'none';
    btn.textContent = open ? 'Hide checklist ▴' : 'Show checklist ▾';
}

// ── Review Action (AJAX & Confirm) ────────────────────────
var pendingAction = null;

function reviewAction(id, status, btn) {
    if (!id || !status) return;

    var actionNames = {
        'approved': 'Approve Ordinance',
        'active': 'Approve Ordinance',
        'draft': 'Request Revision',
        'under_review': 'Send for Review',
        'for_reading': 'Proceed to 1st Reading',
        '1st_reading': 'Send to 1st Reading',
        '2nd_reading': 'Send to 2nd Reading',
        '3rd_reading': 'Send to 3rd Reading',
        'rejected': 'Reject Ordinance'
    };
    
    var actionDescs = {
        'approved': 'Are you sure you want to approve this ordinance? It will be marked as Approved and published publicly.',
        'draft': 'Are you sure you want to send this back to the author for revisions?',
        'under_review': 'Move this draft into the reading queue.',
        'for_reading': 'Proceed this document to First Reading.',
        '1st_reading': 'Move this ordinance to First Reading stage.',
        '2nd_reading': 'Move this ordinance to Second Reading stage.',
        '3rd_reading': 'Move this ordinance to Third Reading stage.',
        'rejected': 'Are you sure you want to reject this ordinance?'
    };

    var modal = document.getElementById('confirmModal');
    document.getElementById('confirmTitle').textContent = actionNames[status] || 'Confirm Action';
    document.getElementById('confirmDesc').textContent = actionDescs[status] || 'Are you sure you want to proceed?';
    
    pendingAction = { id: id, status: status };
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    // Attach one-shot handler to confirm button to execute this specific action
    var confirmBtn = document.getElementById('confirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = function() {
            executeReviewAction(id, status);
            closeConfirm();
        };
    }
}

function closeConfirm() {
    var modal = document.getElementById('confirmModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingAction = null;
    var confirmBtn = document.getElementById('confirmBtn');
    if (confirmBtn) confirmBtn.onclick = null;
}



// Render the progression + approve/reject controls for a given card
function renderActionControls(card) {
    if (!card) return;
    var stage = card.dataset.reading || card.dataset.status || '';
    var id = card.dataset.id;
    var container = card.querySelector('.action-controls');
    if (!container) return;

    var btnBase = 'px-3 py-1.5 rounded text-xs font-bold transition-all';
    var html = '';
    html += '<div class="flex items-center gap-1.5" style="white-space:nowrap;">';

    if (stage === 'for_reading') {
        html += '<button data-review-action="1st_reading" title="Proceed to 1st Reading" class="'+btnBase+'" style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">⇢ Proceed to 1st Reading</button>';
        html += '<button data-review-action="approved" title="Approve (A)" class="'+btnBase+'" style="background:#166534;color:#fff;margin-right:6px;">✓ Approve</button>';
        html += '<button data-review-action="rejected" title="Reject" class="'+btnBase+'" style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">✕ Reject</button>';
    } else if (stage === '1st_reading') {
        html += '<button data-review-action="2nd_reading" title="Proceed to 2nd Reading" class="'+btnBase+'" style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">⇢ Proceed to 2nd Reading</button>';
        html += '<button data-review-action="approved" title="Approve (A)" class="'+btnBase+'" style="background:#166534;color:#fff;margin-right:6px;">✓ Approve</button>';
        html += '<button data-review-action="rejected" title="Reject" class="'+btnBase+'" style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">✕ Reject</button>';
    } else if (stage === '2nd_reading') {
        html += '<button data-review-action="3rd_reading" title="Proceed to 3rd Reading" class="'+btnBase+'" style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">⇢ Proceed to 3rd Reading</button>';
        html += '<button data-review-action="approved" title="Approve (A)" class="'+btnBase+'" style="background:#166534;color:#fff;margin-right:6px;">✓ Approve</button>';
        html += '<button data-review-action="rejected" title="Reject" class="'+btnBase+'" style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">✕ Reject</button>';
    } else if (stage === '3rd_reading') {
        html += '<button data-review-action="approved" title="Approve (A)" class="'+btnBase+'" style="background:#166534;color:#fff;margin-right:6px;">✓ Approve</button>';
        html += '<button data-review-action="rejected" title="Reject" class="'+btnBase+'" style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">✕ Reject</button>';
    } else {
        html += '<button data-review-action="1st_reading" title="Proceed to 1st Reading" class="'+btnBase+'" style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa;margin-right:6px;">⇢ Proceed to 1st Reading</button>';
        html += '<button data-review-action="approved" title="Approve (A)" class="'+btnBase+'" style="background:#166534;color:#fff;margin-right:6px;">✓ Approve</button>';
        html += '<button data-review-action="rejected" title="Reject" class="'+btnBase+'" style="background:#fef2f2;color:#7f0000;border:1px solid #fca5a5;">✕ Reject</button>';
    }

    html += '</div>';
    container.innerHTML = html;
}

function executeReviewAction(id, status) {
    var label_map = {
        'approved'     : { msg:'Approved successfully!',        icon:'✓', bg:'#166534' },
        'active'       : { msg:'Approved successfully!',        icon:'✓', bg:'#166534' },
        'draft'        : { msg:'Sent back for revision.',        icon:'↩', bg:'#7f0000' },
        'under_review' : { msg:'Sent for review.',              icon:'⇢', bg:'#d97706' },
        '1st_reading'  : { msg:'Moved to 1st Reading.',         icon:'⇢', bg:'#d97706' },
        '2nd_reading'  : { msg:'Moved to 2nd Reading.',         icon:'⇢', bg:'#d97706' },
        '3rd_reading'  : { msg:'Moved to 3rd Reading.',         icon:'⇢', bg:'#d97706' },
        'rejected'     : { msg:'Marked as Rejected.',           icon:'✕', bg:'#b91c1c' },
    };
    var info = label_map[status] || { msg:'Status updated.', icon:'·', bg:'#475569' };

    var cardEl = document.querySelector('.review-card[data-id="' + id + '"]');
    var recordType = (cardEl && cardEl.dataset.type) ? cardEl.dataset.type : 'ordinance';
    fetch('actions/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status) + '&type=' + encodeURIComponent(recordType)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(info.msg, info.icon, info.bg);
            var card = document.querySelector('.review-card[data-id="' + id + '"]');
            var readingStatuses = ['for_reading','1st_reading','2nd_reading','3rd_reading','under_review','draft'];
            var finalStatuses = ['approved','active','rejected'];
            // If new status is a reading-stage, update card in place
            if (card && readingStatuses.indexOf(status) !== -1) {
                // Normalize mapping for legacy statuses
                var normalizeMap = { 'under_review':'for_reading', 'draft':'for_reading' };
                var newReading = normalizeMap[status] || status;
                card.dataset.status = status;
                card.dataset.reading = newReading;
                // update visible status badge text
                var badge = card.querySelector('[class*="status-"]');
                var readingLabels = { 'for_reading':'For Reading', '1st_reading':'1st Reading', '2nd_reading':'2nd Reading', '3rd_reading':'3rd Reading', 'approved':'Approved', 'rejected':'Rejected', 'draft':'Draft' };
                if (badge) {
                    badge.textContent = readingLabels[newReading] || (status.replace(/_/g,' '));
                }
                // refresh action controls for the updated card, then refresh filters and keep it visible
                renderActionControls(card);
                applyFilters(false);
            } else if (card && finalStatuses.indexOf(status) === -1) {
                // For non-final statuses not in readingStatuses, just refresh list
                applyFilters(false);
            } else if (card) {
                // For final statuses, remove the card from list after a short delay
                card.style.transition = 'all .4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(40px)';
                setTimeout(() => {
                    card.remove();
                    cards = Array.from(document.querySelectorAll('.review-card'));
                    applyFilters(false);
                }, 400);
            }
        } else {
            showToast('Error: ' + data.message, '!', '#dc2626');
        }
    })
    .catch((err) => {
        console.error('Network error updating status:', err);
    });
}

// ── Toast ─────────────────────────────────────────────────
var toastTimer;
function showToast(msg, icon, bg) {
    var toast = document.getElementById('reviewToast');
    var inner = toast.querySelector('div');
    document.getElementById('reviewToastMsg').textContent  = msg;
    document.getElementById('reviewToastIcon').textContent = icon;
    inner.style.background = bg;
    toast.classList.remove('hidden');
    toast.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.classList.add('hidden'), 300); }, 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
