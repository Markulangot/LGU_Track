<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Dashboard";
$current_page = "dashboard";

// Fetch metrics
$total_ordinances = $pdo->query("SELECT COUNT(*) FROM ordinances")->fetchColumn();
$pending_review = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status IN ('for_reading','under_review', 'draft', '1st_reading', '2nd_reading', '3rd_reading')")->fetchColumn();
$recently_enacted = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'active' AND date_enacted >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Fetch recent activity
$stmt = $pdo->query("SELECT o.*, 
           GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tag_names,
           GROUP_CONCAT(DISTINCT t.color_theme SEPARATOR ', ') as tag_colors
    FROM ordinances o
    LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
    LEFT JOIN tags t ON ot.tag_id = t.id
    WHERE o.ordinance_number NOT LIKE 'RES-%'
    GROUP BY o.id
    ORDER BY o.date_enacted DESC
    LIMIT 5");
$recent_ordinances = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8 pb-20">
    <!-- Page Header -->
    <div class="flex justify-between items-end">
        <div>
            <h1 class="text-[32px] font-bold text-navy leading-tight">Overview</h1>
            <p class="text-sm text-on-surface-variant mt-1">System metrics and recent legislative activity.</p>
        </div>
        <button
            class="px-4 py-2 border border-outline-variant rounded font-bold text-xs text-navy flex items-center gap-2 hover:bg-surface-container transition-all">
            <img src="assets/icons/download (2).png" class="w-4 h-4" alt="Export">
            Export Report
        </button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Metric 1 -->
        <div class="bg-white p-6 border border-outline-variant rounded-lg flex flex-col justify-between h-36 shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Total Ordinances
                    (YTD)</span>
                <img src="assets/icons/paper.png" class="w-5 h-5 opacity-40" alt="Total">
            </div>
            <div class="flex items-baseline gap-2">
                <span
                    class="text-[32px] font-bold text-navy leading-none"><?php echo number_format($total_ordinances); ?></span>
            </div>
        </div>

        <!-- Metric 2 (Highlight) -->
        <div class="bg-navy p-6 rounded-lg flex flex-col justify-between h-36 shadow-lg shadow-navy/10">
            <div class="flex justify-between items-start">
                <span class="text-[11px] font-bold uppercase tracking-widest text-white opacity-60">For Reading</span>
                <img src="assets/icons/clipboard.png" class="w-5 h-5 icon-invert opacity-60" alt="Pending">
            </div>
            <div class="space-y-1">
                <span class="text-[32px] font-bold text-white leading-none"><?php echo $pending_review; ?></span>
                <p class="text-[11px] text-white opacity-60 font-medium">Items awaiting reading stages</p>
            </div>
        </div>

        <!-- Metric 3 -->
        <div class="bg-white p-6 border border-outline-variant rounded-lg flex flex-col justify-between h-36 shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Recently
                    Enacted</span>
                <img src="assets/icons/checked.png" class="w-5 h-5 opacity-40" alt="Enacted">
            </div>
            <div class="space-y-1">
                <span class="text-[32px] font-bold text-navy leading-none"><?php echo $recently_enacted; ?></span>
                <p class="text-[11px] text-on-surface-variant font-medium">In the last 30 days</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Activity -->
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between border-b border-outline-variant pb-2">
                <h3 class="text-lg font-bold text-navy">Recent Legislative Activity</h3>
                <a href="database.php" class="text-xs font-bold text-navy hover:underline">View All</a>
            </div>

            <div class="space-y-2">
                <?php foreach ($recent_ordinances as $ord): ?>
                    <div onclick="window.location.href='view.php?id=<?php echo $ord['id']; ?>'"
                        class="bg-white p-4 border border-outline-variant rounded hover:border-navy transition-all cursor-pointer group flex gap-4">
                        <div class="w-12 h-12 bg-surface-container rounded flex items-center justify-center flex-shrink-0">
                            <img src="assets/icons/<?php echo $ord['status'] == 'active' ? 'checked.png' : 'mace.png'; ?>"
                                class="w-6 h-6 opacity-70 <?php echo $ord['status'] == 'active' ? '' : 'icon-navy'; ?>"
                                alt="Status">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-0.5">
                                <span
                                    class="text-[11px] font-bold text-navy uppercase tracking-widest"><?php echo htmlspecialchars($ord['ordinance_number']); ?></span>
                                <span
                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo 'status-' . str_replace('_', '-', $ord['status']); ?>">
                                    <?php echo str_replace('_', ' ', $ord['status']); ?>
                                </span>
                            </div>
                            <h4 class="text-sm font-medium text-on-surface mb-2 font-doc italic truncate">
                                <?php echo htmlspecialchars($ord['title']); ?>
                            </h4>
                            <div class="flex items-center gap-4 text-[11px] text-on-surface-variant font-medium">
                                <span class="flex items-center gap-1.5"><img src="assets/icons/calendar.png"
                                        class="w-3.5 h-3.5 opacity-50">
                                    <?php echo date('M d, Y', strtotime($ord['date_enacted'])); ?></span>
                                <span class="flex items-center gap-1.5"><img src="assets/icons/user.png"
                                        class="w-3.5 h-3.5 opacity-50">
                                    <?php echo htmlspecialchars($ord['main_author']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions & Alerts -->
        <div class="space-y-6">
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-navy border-b border-outline-variant pb-2">Quick Actions</h3>
                <div class="grid grid-cols-1 gap-2">
                    <a href="submit.php"
                        class="flex items-center gap-4 p-4 bg-white border border-outline-variant rounded hover:bg-[#191c1e] hover:bg-opacity-[0.03] transition-all group">
                        <div
                            class="w-10 h-10 bg-surface-container rounded flex items-center justify-center flex-shrink-0 transition-all">
                            <img src="assets/icons/add.png"
                                class="w-5 h-5 opacity-40 group-hover:opacity-100 transition-all" alt="Add">
                        </div>
                        <div>
                            <div class="text-sm font-bold text-navy">New Ordinance</div>
                            <div class="text-[11px] text-on-surface-variant">Start a blank legislative draft</div>
                        </div>
                    </a>

                    <a href="submit_resolution.php"
                        class="flex items-center gap-4 p-4 bg-white border border-outline-variant rounded hover:bg-[#191c1e] hover:bg-opacity-[0.03] transition-all group">
                        <div
                            class="w-10 h-10 bg-surface-container rounded flex items-center justify-center flex-shrink-0 transition-all">
                            <img src="assets/icons/add.png"
                                class="w-5 h-5 opacity-40 group-hover:opacity-100 transition-all" alt="Add">
                        </div>
                        <div>
                            <div class="text-sm font-bold text-navy">New Resolution</div>
                            <div class="text-[11px] text-on-surface-variant">Start a blank resolution draft</div>
                        </div>
                    </a>
                    <a href="database.php"
                        class="flex items-center gap-4 p-4 bg-white border border-outline-variant rounded hover:bg-[#191c1e] hover:bg-opacity-[0.03] transition-all group">
                        <div
                            class="w-10 h-10 bg-surface-container rounded flex items-center justify-center flex-shrink-0 transition-all">
                            <img src="assets/icons/glass.png"
                                class="w-5 h-5 opacity-40 group-hover:opacity-100 transition-all" alt="Search">
                        </div>
                        <div>
                            <div class="text-sm font-bold text-navy">Search Database</div>
                            <div class="text-[11px] text-on-surface-variant">Locate past municipal records</div>
                        </div>
                    </a>
                    <a href="pending.php"
                        class="flex items-center gap-4 p-4 bg-white border border-outline-variant rounded hover:bg-[#191c1e] hover:bg-opacity-[0.03] transition-all group">
                        <div
                            class="w-10 h-10 bg-surface-container rounded flex items-center justify-center flex-shrink-0 transition-all">
                            <img src="assets/icons/clipboard.png"
                                class="w-5 h-5 opacity-40 group-hover:opacity-100 transition-all" alt="Review">
                        </div>
                        <div>
                            <div class="text-sm font-bold text-navy">Committee Review</div>
                            <div class="text-[11px] text-on-surface-variant">Manage items awaiting action</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- System Notice -->
            <div class="bg-navy bg-opacity-5 p-5 border-l-4 border-navy rounded-r-lg">
                <div class="flex items-center gap-2 mb-2">
                    <img src="assets/icons/doc.png" class="w-4 h-4 icon-navy" alt="Notice">
                    <h4 class="text-xs font-bold text-navy uppercase tracking-widest">System Notice</h4>
                </div>
                <p class="text-xs text-on-surface-variant leading-relaxed font-medium">
                    TESTING
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>