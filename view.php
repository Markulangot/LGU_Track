<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$type = isset($_GET['type']) && $_GET['type'] === 'resolution' ? 'resolution' : 'ordinance';

if (!$id) {
    header("Location: database.php" . ($type === 'resolution' ? '?type=resolution' : ''));
    exit;
}

if ($type === 'resolution') {
    $stmt = $pdo->prepare("SELECT r.*, r.resolution_number AS ordinance_number, 
           GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '|') as tag_names,
           GROUP_CONCAT(DISTINCT t.color_theme ORDER BY t.name SEPARATOR '|') as tag_colors
    FROM resolutions r
    LEFT JOIN resolution_tags rt ON r.id = rt.resolution_id
    LEFT JOIN tags t ON rt.tag_id = t.id
    WHERE r.id = ?
    GROUP BY r.id");
    $stmt->execute([$id]);
    $ord = $stmt->fetch();
    if (!$ord) die('Resolution not found.');
    $page_title = 'Resolution ' . $ord['ordinance_number'];
} else {
    // Fetch Ordinance with Tags
    $stmt = $pdo->prepare("SELECT o.*, 
           GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '|') as tag_names,
           GROUP_CONCAT(DISTINCT t.color_theme ORDER BY t.name SEPARATOR '|') as tag_colors
    FROM ordinances o
    LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
    LEFT JOIN tags t ON ot.tag_id = t.id
    WHERE o.id = ?
    GROUP BY o.id");
    $stmt->execute([$id]);
    $ord = $stmt->fetch();
    if (!$ord) die('Ordinance not found.');
    $page_title = 'Ordinance ' . $ord['ordinance_number'];
}

$current_page = "database";
include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2">
        <a href="database.php" class="flex items-center gap-2 text-[13px] font-bold text-on-surface-variant hover:text-navy transition-all">
            <span class="text-lg">←</span>
            Back to Database
        </a>
    </div>

    <!-- Header Row -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <h1 class="text-[32px] font-bold text-navy leading-none">Ordinance No. <?php echo htmlspecialchars($ord['ordinance_number']); ?></h1>
            <div class="relative group">
                <button class="status-badge px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo 'status-' . str_replace('_', '-', $ord['status']); ?> flex items-center gap-2">
                    <?php echo str_replace('_', ' ', $ord['status']); ?>
                    <span class="text-[8px] opacity-40">▼</span>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-4 py-2 bg-white border border-outline-variant rounded font-bold text-xs text-navy flex items-center gap-2 hover:bg-surface-container transition-all">
                <img src="assets/icons/printer.png" class="w-4 h-4 opacity-60" alt="Print">
                Print
            </button>
            <?php if ($type === 'resolution'): ?>
            <a href="submit_resolution.php?id=<?php echo $ord['id']; ?>" class="bg-navy text-white px-4 py-2 rounded font-bold text-xs flex items-center gap-2 hover:opacity-90 transition-all shadow-md">
                <img src="assets/icons/edit.png" class="w-4 h-4 icon-invert" alt="Edit">
                Edit Draft
            </a>
            <?php else: ?>
            <a href="submit.php?id=<?php echo $ord['id']; ?>" class="bg-navy text-white px-4 py-2 rounded font-bold text-xs flex items-center gap-2 hover:opacity-90 transition-all shadow-md">
                <img src="assets/icons/edit.png" class="w-4 h-4 icon-invert" alt="Edit">
                Edit Draft
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs Bar -->
    <div class="border-b border-outline-variant flex gap-8">
        <button onclick="switchViewTab('document')" class="view-tab py-3 text-[13px] font-bold border-b-2 border-navy text-navy" data-tab="document">Soft Copy (Editable Text)</button>
        <button onclick="switchViewTab('pdf')" class="view-tab py-3 text-[13px] font-bold border-b-2 border-transparent text-on-surface-variant hover:text-navy" data-tab="pdf">Hard Copy (PDF)</button>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-8">
        
        <!-- Left Column: The Document -->
        <div class="space-y-8">
            <div id="view-tab-document" class="view-tab-content block">
                <div class="bg-white shadow-xl border border-outline-variant rounded-sm min-h-[1000px] flex flex-col">
                    <!-- Paper Content Container -->
                    <div class="p-[64px_80px] flex-1">
                        <div class="space-y-10">
                            <!-- Title & Preamble -->
                            <div class="space-y-4">
                                <h2 class="text-2xl font-bold text-navy leading-tight"><?php echo htmlspecialchars($ord['title']); ?></h2>
                                <p class="text-[15px] font-doc italic text-on-surface-variant leading-relaxed">
                                    <?php echo htmlspecialchars($ord['description']); ?>
                                </p>
                            </div>

                            <!-- Body Text -->
                            <div class="font-doc text-[17px] leading-[1.8] text-on-surface space-y-8">
                                <?php 
                                if ($ord['soft_copy']) {
                                    echo nl2br(htmlspecialchars($ord['soft_copy']));
                                } else {
                                    echo '<div class="text-center py-40 opacity-20 italic">No document content provided.</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PDF View Tab -->
            <div id="view-tab-pdf" class="view-tab-content hidden">
                <div class="bg-white border border-outline-variant rounded shadow-xl h-[1000px] overflow-hidden">
                    <?php if ($ord['hard_copy_path']): ?>
                        <iframe src="<?php echo htmlspecialchars($ord['hard_copy_path']); ?>" class="w-full h-full border-none"></iframe>
                    <?php else: ?>
                        <div class="h-full flex flex-col items-center justify-center opacity-30 space-y-4">
                            <img src="assets/icons/pdf.png" class="w-16 h-16 grayscale">
                            <p class="text-sm font-bold uppercase tracking-widest">No PDF hard copy attached</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attachments Section (Always Visible) -->
            <div class="bg-white rounded-lg border border-outline-variant p-10 shadow-sm space-y-6">
                <div class="flex items-center gap-2">
                    <img src="assets/icons/price-tag.png" class="w-5 h-5 opacity-40 rotate-90" alt="Attachments">
                    <h3 class="text-sm font-bold text-navy uppercase tracking-widest">Attachments</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php if ($ord['hard_copy_path']): ?>
                        <?php $pdf_path = htmlspecialchars($ord['hard_copy_path']); $pdf_name = basename($ord['hard_copy_path']); ?>
                        <div class="flex flex-col gap-3 p-4 bg-white border border-outline-variant rounded shadow-sm hover:border-red-300 transition-all group">
                            <!-- File info row -->
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-red-50 rounded flex items-center justify-center flex-shrink-0">
                                    <img src="assets/icons/pdf.png" class="w-6 h-6" alt="PDF">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-bold text-navy truncate"><?php echo $pdf_name; ?></div>
                                    <div class="text-[10px] text-on-surface-variant font-bold tracking-tight mt-0.5 uppercase">Official Signed Hard Copy</div>
                                </div>
                            </div>
                            <!-- Action buttons -->
                            <div class="flex gap-2">
                                <button onclick="openPdfPreview('<?php echo $pdf_path; ?>')"
                                        class="flex-1 py-1.5 text-xs font-bold rounded transition-all"
                                        style="background:#fef2f2;color:#7f0000;border:1.5px solid #fca5a5;">
                                    Preview
                                </button>
                                <a href="<?php echo $pdf_path; ?>" download
                                   class="flex-1 py-1.5 text-xs font-bold rounded text-white text-center transition-all"
                                   style="background:#8B0000;">
                                    Download
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    
                    <div class="flex items-center gap-4 p-4 bg-white border border-outline-variant rounded shadow-sm opacity-80 cursor-not-allowed">
                        <div class="w-10 h-10 bg-blue-50 rounded flex items-center justify-center flex-shrink-0">
                            <img src="assets/icons/doc.png" class="w-6 h-6" alt="DOC">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-bold text-navy truncate">Planning Commission Staff...</div>
                            <div class="text-[10px] text-on-surface-variant font-bold tracking-tight mt-0.5">1.1 MB &middot; Uploaded Apr 10, 2024</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar Cards -->
        <div class="space-y-6">
            <!-- Metadata Card -->
            <div class="bg-white rounded border border-outline-variant p-6 space-y-6 shadow-sm">
                <h3 class="text-[17px] font-bold text-navy">Metadata</h3>
                
                <div class="space-y-6">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Sponsor</span>
                        <div class="flex items-center gap-2 text-[13px] font-bold text-navy">
                            <img src="assets/icons/user.png" class="w-4 h-4 opacity-50">
                            <?php echo htmlspecialchars($ord['main_author']); ?>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Co-Author(s)</span>
                        <div class="text-[13px] font-medium text-on-surface-variant">
                            <?php echo !empty($ord['co_authors']) ? htmlspecialchars($ord['co_authors']) : '<span class="italic text-outline">None specified</span>'; ?>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Introduction Date</span>
                        <div class="text-[13px] font-medium text-navy">
                            <?php echo date('F d, Y', strtotime($ord['date_enacted'])); ?>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Committee Assignment</span>
                        <div class="text-[13px] font-medium text-on-surface-variant">
                            <?php echo htmlspecialchars($ord['department'] ?? ''); ?>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Fiscal Impact</span>
                        <div class="text-[13px] font-medium text-on-surface-variant">
                            Revenue Neutral
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-outline block mb-2">Tags</span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php 
                            if ($ord['tag_names']) {
                                $tags = explode('|', $ord['tag_names']);
                                $colors = explode('|', $ord['tag_colors']);
                                foreach ($tags as $i => $tag) {
                                    $color = $colors[$i] ?? 'tc-blue';
                                    echo "<span class='px-2.5 py-1 rounded text-[10px] font-bold border {$color} uppercase'>{$tag}</span>";
                                }
                            }
                            ?>
                            <button class="w-6 h-6 border border-outline-variant border-dashed rounded flex items-center justify-center text-outline hover:border-navy hover:text-navy transition-all">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version History Card -->
            <div class="bg-white rounded border border-outline-variant p-6 space-y-6 shadow-sm">
                <h3 class="text-[17px] font-bold text-navy">Version History</h3>
                
                <div class="relative pl-6 space-y-8 before:content-[''] before:absolute before:left-[3px] before:top-2 before:bottom-2 before:w-[1px] before:bg-outline-variant">
                    <!-- First Reading -->
                    <div class="relative">
                        <div class="absolute -left-[27px] top-1 w-2.5 h-2.5 rounded-full border-2 border-on-surface bg-white"></div>
                        <div class="text-[13px] font-bold text-navy">First Reading</div>
                        <div class="text-[10px] text-outline font-medium uppercase tracking-widest mt-0.5">Scheduled for <?php echo date('M d, Y', strtotime($ord['date_enacted'] . ' + 14 days')); ?></div>
                    </div>

                    <!-- Committee Recommendation -->
                    <div class="relative">
                        <div class="absolute -left-[27px] top-1 w-2.5 h-2.5 rounded-full bg-on-surface"></div>
                        <div class="text-[13px] font-bold text-navy">Committee Recommendation: Approve</div>
                        <div class="text-[10px] text-outline font-medium uppercase tracking-widest mt-0.5"><?php echo date('M d, Y', strtotime($ord['date_enacted'] . ' - 2 days')); ?> - <?php echo htmlspecialchars($ord['department'] ?? ''); ?></div>
                        <button class="text-[10px] text-navy font-bold flex items-center gap-1 mt-2 hover:underline">
                            <img src="assets/icons/glass.png" class="w-2.5 h-2.5 opacity-40">
                            View Report
                        </button>
                    </div>

                    <!-- Introduced to Council -->
                    <div class="relative">
                        <div class="absolute -left-[27px] top-1 w-2.5 h-2.5 rounded-full bg-on-surface"></div>
                        <div class="text-[13px] font-bold text-navy">Introduced to Council</div>
                        <div class="text-[10px] text-outline font-medium uppercase tracking-widest mt-0.5"><?php echo date('M d, Y', strtotime($ord['date_enacted'])); ?> - Regular Session</div>
                    </div>
                    
                    <!-- Initial Draft Created -->
                    <div class="relative">
                        <div class="absolute -left-[27px] top-1 w-2.5 h-2.5 rounded-full bg-on-surface"></div>
                        <div class="text-[13px] font-bold text-navy">Initial Draft Created</div>
                        <div class="text-[10px] text-outline font-medium uppercase tracking-widest mt-0.5">
                            <?php echo date('M d, Y', strtotime($ord['created_at'])); ?> &middot; Legis. Drafting Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function switchViewTab(tabId) {
        document.querySelectorAll('.view-tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById(`view-tab-${tabId}`).classList.remove('hidden');
        document.getElementById(`view-tab-${tabId}`).classList.add('block');
        
        document.querySelectorAll('.view-tab').forEach(el => {
            el.classList.remove('border-navy', 'text-navy');
            el.classList.add('border-transparent', 'text-on-surface-variant');
            if (el.getAttribute('data-tab') === tabId) {
                el.classList.add('border-navy', 'text-navy');
                el.classList.remove('border-transparent', 'text-on-surface-variant');
            }
        });
    }
</script>

<!-- PDF Preview Modal -->
<div id="pdfPreviewModal"
     class="fixed inset-0 z-[400] hidden flex-col"
     style="background:rgba(10,0,0,.82);backdrop-filter:blur(4px)">
    <div class="flex items-center justify-between px-5 py-3 flex-shrink-0"
         style="background:rgba(127,0,0,.95);border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="flex items-center gap-3">
            <img src="assets/icons/pdf.png" class="w-5 h-5" alt="PDF">
            <span id="pdfPreviewName" class="text-white text-sm font-bold truncate max-w-[400px]"></span>
        </div>
        <div class="flex items-center gap-3">
            <a id="pdfPreviewDownload" href="#" download
               class="px-4 py-1.5 text-xs font-bold text-white rounded"
               style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25)">⬇ Download</a>
            <button onclick="closePdfPreview()"
                    class="w-8 h-8 rounded flex items-center justify-center text-white text-lg"
                    style="background:rgba(255,255,255,.12)" aria-label="Close">✕</button>
        </div>
    </div>
    <div class="flex-1 overflow-hidden">
        <iframe id="pdfPreviewFrame" src="" class="w-full h-full border-none" title="PDF Preview"></iframe>
    </div>
</div>
<script>
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
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closePdfPreview(); });
</script>

<?php require_once 'includes/footer.php'; ?>
