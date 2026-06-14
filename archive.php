<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Archives";
$current_page = "archive";

// Summary queries
$total_archives = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'repealed'")->fetchColumn();
$this_year = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'repealed' AND YEAR(date_enacted) = YEAR(CURDATE())")->fetchColumn();

// Fetch archived ordinances grouped by year
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tag_names,
           GROUP_CONCAT(DISTINCT t.color_theme SEPARATOR ', ') as tag_colors,
           YEAR(o.date_enacted) as archive_year
    FROM ordinances o
    LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
    LEFT JOIN tags t ON ot.tag_id = t.id
    WHERE o.status = 'repealed'
    GROUP BY o.id
    ORDER BY archive_year DESC, o.date_enacted DESC
");
$stmt->execute();
$archived_ordinances = $stmt->fetchAll();

$archives_by_year = [];
foreach ($archived_ordinances as $ord) {
    $year = $ord['archive_year'] ?? 'Unknown Year';
    if (!isset($archives_by_year[$year])) {
        $archives_by_year[$year] = [];
    }
    $archives_by_year[$year][] = $ord;
}

require_once 'includes/header.php';
?>

<div class="space-y-6 max-w-[1440px] mx-auto pb-20">
    <!-- Page Header & Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[28px] font-bold text-navy leading-tight">Archives & Records</h1>
            <p class="text-sm text-on-surface-variant mt-1">Cabinet view of repealed, superseded, and historical
                legislative records.</p>
        </div>

        <div class="flex items-center gap-4">
            <div class="bg-white px-4 py-2 rounded-lg border border-outline-variant shadow-sm flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center">
                    <img src="assets/icons/archive.png" class="w-4 h-4 opacity-70">
                </div>
                <div>
                    <div class="text-[10px] text-outline font-bold uppercase tracking-widest">Total Archived</div>
                    <div class="text-lg font-bold text-navy leading-none"><?php echo $total_archives; ?></div>
                </div>
            </div>

            <div class="bg-white px-4 py-2 rounded-lg border border-outline-variant shadow-sm flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center">
                    <img src="assets/icons/calendar.png" class="w-4 h-4 opacity-70">
                </div>
                <div>
                    <div class="text-[10px] text-outline font-bold uppercase tracking-widest">Archived This Year</div>
                    <div class="text-lg font-bold text-navy leading-none"><?php echo $this_year; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div
        class="bg-white p-4 rounded-lg border border-outline-variant shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div class="relative flex-1">
            <img src="assets/icons/search.png" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 opacity-40">
            <input type="text" id="archiveSearchInput"
                placeholder="Instant Search (Ordinance No, Title, Author, Year)..."
                class="w-full pl-10 pr-4 py-2.5 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline transition-all">
        </div>
        <div class="flex items-center gap-2">
            <button
                class="px-4 py-2.5 bg-surface-container-lowest border border-outline-variant rounded text-xs font-bold text-navy hover:bg-surface-container transition-all">
                Export Backup (ZIP)
            </button>
        </div>
    </div>

    <div class="space-y-8" id="archiveContainer">
        <?php if (empty($archives_by_year)): ?>
            <div
                class="flex flex-col items-center justify-center py-20 bg-white rounded-lg border border-outline-variant shadow-sm">
                <img src="assets/icons/archive.png" class="w-16 h-16 opacity-20 mb-4" alt="No archives">
                <p class="text-on-surface-variant font-medium">No archived records found in the database.</p>
            </div>
        <?php else: ?>
            <?php foreach ($archives_by_year as $year => $ordinances): ?>
                <div class="cabinet-year-group" data-year="<?php echo htmlspecialchars($year); ?>">
                    <div class="flex items-center gap-4 mb-4 sticky top-0 bg-surface z-10 py-2">
                        <div class="w-10 h-10 text-black rounded-lg flex items-center justify-center font-bold text-lg">
                            <?php echo htmlspecialchars($year); ?>
                        </div>
                        <div class="h-px flex-1 bg-outline-variant"></div>
                        <div class="text-xs font-bold text-outline-variant uppercase tracking-widest">
                            <?php echo count($ordinances); ?> Records
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($ordinances as $ord): ?>
                            <div class="archive-card bg-white p-5 rounded-lg border border-outline-variant hover:border-navy hover:shadow-md transition-all group flex flex-col relative"
                                data-search="<?php echo htmlspecialchars(strtolower($ord['ordinance_number'] . ' ' . $ord['title'] . ' ' . $ord['main_author'] . ' ' . $ord['description'] . ' ' . $year)); ?>">

                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded bg-red-50 flex items-center justify-center">
                                            <img src="assets/icons/pdf.png" class="w-4 h-4 opacity-70">
                                        </div>
                                        <div>
                                            <h3 class="text-[13px] font-bold text-navy uppercase tracking-tight leading-none">
                                                <?php echo htmlspecialchars($ord['ordinance_number']); ?>
                                            </h3>
                                            <span
                                                class="text-[10px] text-outline font-bold uppercase tracking-widest"><?php echo date('M d', strtotime($ord['date_enacted'])); ?></span>
                                        </div>
                                    </div>
                                    <span
                                        class="status-badge px-2.5 py-0.5 rounded-sm border border-red-100 bg-red-50 text-red-700 text-[9px] font-bold uppercase tracking-widest">
                                        REPEALED
                                    </span>
                                </div>

                                <h4
                                    class="text-[14px] font-medium text-on-surface mb-2 font-doc italic leading-snug line-clamp-2 flex-1 archive-title">
                                    <?php echo htmlspecialchars($ord['title']); ?>
                                </h4>

                                <div class="mt-auto pt-4 border-t border-outline-variant flex items-center justify-between">
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] text-outline font-medium truncate max-w-[150px]">
                                        <img src="assets/icons/user.png" class="w-3.5 h-3.5 opacity-50" alt="Sponsor">
                                        <span class="truncate"><?php echo htmlspecialchars($ord['main_author']); ?></span>
                                    </div>

                                    <div class="flex gap-2">
                                        <a href="view.php?id=<?php echo $ord['id']; ?>"
                                            class="w-7 h-7 rounded-full bg-surface-container hover:bg-navy flex items-center justify-center transition-all group-hover:bg-navy/10">
                                            <img src="assets/icons/search.png" class="w-3 h-3 opacity-60 group-hover:hidden">
                                            <img src="assets/icons/search.png" class="w-3 h-3 icon-invert hidden group-hover:block">
                                        </a>
                                        <?php if ($ord['hard_copy_path']): ?>
                                            <button onclick="openPdfPreview('<?php echo htmlspecialchars($ord['hard_copy_path']); ?>')"
                                                class="w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-all">
                                                <img src="assets/icons/pdf.png" class="w-3 h-3">
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- PDF Preview Modal -->
<div id="pdfPreviewModal" class="fixed inset-0 z-[400] hidden flex-col"
    style="background:rgba(10,0,0,.82);backdrop-filter:blur(4px)">
    <div class="flex items-center justify-between px-5 py-3 flex-shrink-0"
        style="background:rgba(127,0,0,.95);border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="flex items-center gap-3">
            <img src="assets/icons/pdf.png" class="w-5 h-5" alt="PDF">
            <span id="pdfPreviewName" class="text-white text-sm font-bold truncate max-w-[400px]">Offline Preview</span>
        </div>
        <div class="flex items-center gap-3">
            <a id="pdfPreviewDownload" href="#" download
                class="px-4 py-1.5 text-xs font-bold text-white rounded hover:bg-white/20 transition-all"
                style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25)">⬇ Download</a>
            <button onclick="closePdfPreview()"
                class="w-8 h-8 rounded flex items-center justify-center text-white text-lg hover:bg-white/20 transition-all"
                style="background:rgba(255,255,255,.12)" aria-label="Close">✕</button>
        </div>
    </div>
    <div class="flex-1 overflow-hidden flex items-center justify-center bg-zinc-900">
        <iframe id="pdfPreviewFrame" src="" class="w-full h-full border-none" title="PDF Preview"></iframe>
    </div>
</div>

<script>
    // PDF Preview Logic
    function openPdfPreview(path) {
        if (!path) return;
        var m = document.getElementById('pdfPreviewModal');
        document.getElementById('pdfPreviewFrame').src = path;
        document.getElementById('pdfPreviewName').textContent = path.split('/').pop();
        document.getElementById('pdfPreviewDownload').href = path;
        m.classList.remove('hidden'); m.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function closePdfPreview() {
        var m = document.getElementById('pdfPreviewModal');
        m.classList.add('hidden'); m.classList.remove('flex');
        document.getElementById('pdfPreviewFrame').src = '';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePdfPreview(); });

    // Instant Search Logic (Offline Keyword Extraction style)
    const searchInput = document.getElementById('archiveSearchInput');
    const cards = document.querySelectorAll('.archive-card');
    const yearGroups = document.querySelectorAll('.cabinet-year-group');

    searchInput.addEventListener('input', function (e) {
        const term = e.target.value.toLowerCase().trim();

        cards.forEach(card => {
            const dataSearch = card.getAttribute('data-search');
            if (term === '' || dataSearch.includes(term)) {
                card.style.display = 'flex';

                // Highlight logic (simple)
                const titleEl = card.querySelector('.archive-title');
                if (term !== '') {
                    // Reset first
                    titleEl.innerHTML = titleEl.textContent;
                    // Apply highlight
                    const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    titleEl.innerHTML = titleEl.textContent.replace(regex, '<mark class="bg-yellow-200 text-navy">$1</mark>');
                } else {
                    titleEl.innerHTML = titleEl.textContent;
                }
            } else {
                card.style.display = 'none';
                card.querySelector('.archive-title').innerHTML = card.querySelector('.archive-title').textContent;
            }
        });

        // Hide empty year groups
        yearGroups.forEach(group => {
            const visibleCards = group.querySelectorAll('.archive-card[style="display: flex;"], .archive-card:not([style*="display: none"])');
            if (visibleCards.length === 0 && term !== '') {
                group.style.display = 'none';
            } else {
                group.style.display = 'block';
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>