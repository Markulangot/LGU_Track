<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Ordinance Database";
$current_page = "database";

// Filters from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : '';
$doc_type_filter = isset($_GET['doc_type']) ? trim($_GET['doc_type']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$selected_tags = isset($_GET['tags']) ? (is_array($_GET['tags']) ? $_GET['tags'] : explode(',', $_GET['tags'])) : [];
$selected_tags = array_filter($selected_tags);

$is_resolution = ($type_filter === 'resolution');
$use_legacy_res_fallback = false;

// If the user requested resolutions, check whether a dedicated `resolutions` table exists.
if ($is_resolution) {
    try {
        $tbl = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'resolutions'");
        $tbl->execute();
        $has_resolutions_table = (bool)$tbl->fetchColumn();
    } catch (Exception $e) {
        $has_resolutions_table = false;
    }

    if (!$has_resolutions_table) {
        // Fall back to legacy storage where resolutions are ORD records with 'RES-' prefix
        $use_legacy_res_fallback = true;
        $is_resolution = false; // force using ordinances query path below
    }
}

if ($is_resolution) {
    $query = "
        FROM resolutions o
        LEFT JOIN resolution_tags ot ON o.id = ot.resolution_id
        LEFT JOIN tags t ON ot.tag_id = t.id
        WHERE 1=1
    ";
} else {
    $query = "
        FROM ordinances o
        LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
        LEFT JOIN tags t ON ot.tag_id = t.id
        WHERE 1=1
    ";
}
$params = [];

// 1. Status Filter (Base Data Load)
if ($status_filter === 'all') {
    // Show all, no status filter applied
} elseif ($status_filter) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
} else {
    // By default show only approved/active records in the public database
    $query .= " AND o.status IN ('approved','active')";
}

// 2.a Type Filter (Ordinance vs Resolution)
if ($type_filter === 'resolution') {
    $current_page = 'resolutions';
    // If we couldn't find a resolutions table, keep using ordinances but filter by RES- prefix
    if ($use_legacy_res_fallback) {
        $query .= " AND o.ordinance_number LIKE 'RES-%'";
    }
}

// Fetch all data (Offline realtime filtering requires loading all base records)
if ($is_resolution) {
    // Alias resolution_number as ordinance_number so the existing template can render consistently
    $data_query = "
        SELECT o.*, o.resolution_number AS ordinance_number,
               GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '|') as tag_names,
               GROUP_CONCAT(DISTINCT t.color_theme ORDER BY t.name SEPARATOR '|') as tag_colors
        " . $query . " 
        GROUP BY o.id 
        ORDER BY o.date_enacted DESC 
    ";
} else {
    $data_query = "
        SELECT o.*, 
               GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '|') as tag_names,
               GROUP_CONCAT(DISTINCT t.color_theme ORDER BY t.name SEPARATOR '|') as tag_colors
        " . $query . " 
        GROUP BY o.id 
        ORDER BY o.date_enacted DESC 
    ";
}

$stmt = $pdo->prepare($data_query);
$stmt->execute($params);
$ordinances = $stmt->fetchAll();
$total_records = count($ordinances);

// Fetch all tags for chips
$all_tags_stmt = $pdo->query("SELECT name, color_theme FROM tags ORDER BY name");
$all_tags = $all_tags_stmt->fetchAll();

// Fetch available years
if ($is_resolution) {
    $all_years = $pdo->query("SELECT DISTINCT YEAR(date_enacted) as year FROM resolutions ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $all_years = $pdo->query("SELECT DISTINCT YEAR(date_enacted) as year FROM ordinances ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
}

include 'includes/header.php';
?>

<div class="space-y-6 pb-20">
    <!-- Page Header -->
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-[32px] font-bold text-navy leading-tight"><?php echo ($type_filter === 'resolution') ? 'Resolution Database' : 'Ordinance Database'; ?></h1>
            <p class="text-sm text-on-surface-variant mt-1">
                <?php echo ($type_filter === 'resolution') ? 'Repository of municipal resolutions. Click any row to view the resolution.' : 'Comprehensive repository of all municipal legislative records. Click any row to view the ordinance.'; ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?php echo ($type_filter === 'resolution') ? 'submit_resolution.php' : 'submit.php'; ?>"
                class="bg-navy text-white px-4 py-2 rounded font-bold text-xs flex items-center gap-2 hover:opacity-90 transition-all shadow-md">
                <img src="assets/icons/add.png" class="w-4 h-4 icon-invert" alt="Add">
                <?php echo ($type_filter === 'resolution') ? 'New Resolution' : 'New Ordinance'; ?>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-lg border border-outline-variant shadow-sm p-4 space-y-4 sticky top-0 z-40">
        <form id="filterForm" onsubmit="event.preventDefault(); applyFilters();" class="space-y-4">
            <!-- Row 1: Search and Dropdowns -->
            <div class="flex flex-col lg:flex-row gap-3">
                <div class="flex-1 relative">
                    <img src="assets/icons/glass.png"
                        class="absolute left-3 top-1/2 -translate-y-1/2 icon-sm opacity-40" alt="Search">
                    <input type="text" name="search" id="searchInput" onkeyup="applyFilters()"
                        class="w-full pl-10 pr-4 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm transition-all"
                        placeholder="Search by title, number, tags, sponsor...">
                </div>

                <div class="flex items-center gap-3">
                    <select name="status" id="statusFilter" onchange="applyFilters()"
                        class="px-3 py-2 border border-outline-variant rounded text-xs font-medium focus:outline-none focus:border-navy bg-white min-w-[140px] transition-all">
                        <option value="">Published Ordinances</option>
                        <option value="all">All Statuses</option>
                        <option value="active">Active/Enacted</option>
                        <option value="under_review">Under Review</option>
                        <option value="draft">Draft</option>
                        <option value="amended">Amended</option>
                        <option value="repealed">Repealed</option>
                    </select>

                    <select name="year" id="yearFilter" onchange="applyFilters()"
                        class="px-3 py-2 border border-outline-variant rounded text-xs font-medium focus:outline-none focus:border-navy bg-white min-w-[110px] transition-all">
                        <option value="">Any Year</option>
                        <?php foreach ($all_years as $year): ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="doc_type" id="docTypeFilter" onchange="applyFilters()"
                        class="px-3 py-2 border border-outline-variant rounded text-xs font-medium focus:outline-none focus:border-navy bg-white min-w-[140px] transition-all">
                        <option value="">Any Doc Type</option>
                        <option value="hard">Has Hard Copy</option>
                        <option value="soft">Has Soft Copy</option>
                    </select>

                    <button type="button" onclick="clearFilters()"
                        class="p-2 border border-outline-variant rounded hover:bg-surface-container transition-all"
                        title="Clear Filters">
                        <img src="assets/icons/letter-x.png" class="w-4 h-4 opacity-40" alt="Clear">
                    </button>
                </div>
            </div>

            <!-- Row 2: Tag Filter Chips -->
            <div class="flex items-center gap-4 border-t border-outline-variant pt-4">
                <span class="text-[11px] font-bold uppercase tracking-widest text-outline">Tags:</span>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($all_tags as $tag):
                        $is_selected = in_array($tag['name'], $selected_tags);
                        ?>
                        <label class="cursor-pointer">
                            <input type="checkbox" name="tags[]" value="<?php echo htmlspecialchars($tag['name']); ?>"
                                class="hidden peer" onchange="applyFilters()">
                            <span
                                class="px-3 py-1 rounded-none text-[11px] font-bold border transition-all bg-white text-on-surface-variant border-outline-variant hover:border-navy peer-checked:bg-navy peer-checked:text-white peer-checked:border-navy">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <style>
        /* Collapse long committee names, expand on hover as an overlay to avoid shifting layout */
        .committee-wrap {
            max-width: 12rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }

        td .committee-wrap:hover {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            max-width: 60vw;
            white-space: normal;
            background: #fff;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 60;
        }

        /* On small screens keep normal flow so the overlay doesn't break layout */
        @media (max-width: 768px) {
            td .committee-wrap:hover {
                position: static;
                transform: none;
                max-width: none;
                white-space: normal;
                box-shadow: none;
            }
        }
    </style>

    <div class="bg-white rounded-lg border border-outline-variant shadow-sm overflow-hidden">
        <div>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr
                        class="bg-surface-container text-[12px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-outline-variant">
                        <th class="px-6 py-4 w-[130px] sort-header active sort-desc" onclick="sortTable(0, 'text')"
                            data-column="0">
                            No. <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 min-w-[240px] sort-header" onclick="sortTable(1, 'text')" data-column="1">
                            Title & Description <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 w-[150px]">Tags</th>
                        <th class="px-6 py-4 w-[115px] sort-header" onclick="sortTable(3, 'date')" data-column="3">
                            Date <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 w-[160px] sort-header" onclick="sortTable(4, 'text')" data-column="4">
                            Author <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 w-[160px] sort-header" onclick="sortTable(5, 'text')" data-column="5">
                            Committee <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 w-[115px] sort-header" onclick="sortTable(6, 'text')" data-column="6">
                            Status <span class="sort-icon">▼</span>
                        </th>
                        <th class="px-6 py-4 w-[90px] text-center">Docs</th>
                        <th class="px-6 py-4 w-[56px] text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if (empty($ordinances)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <img src="assets/icons/glass.png" class="w-10 h-10 opacity-20 mb-3" alt="Empty">
                                    <p class="text-on-surface-variant font-medium">No ordinances match your current filters.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ordinances as $index => $ord): ?>
                            <tr class="border-b border-outline-variant hover:bg-[#f0f2ff] transition-all cursor-pointer group"
                                data-tags="<?php echo htmlspecialchars($ord['tag_names'] ?? ''); ?>"
                                onclick="openDrawer(<?php echo htmlspecialchars(json_encode($ord)); ?>)">
                                <td
                                    class="px-6 py-4 font-bold text-[#003ea8] group-hover:border-l-[3px] group-hover:border-navy transition-all">
                                    <?php echo htmlspecialchars($ord['ordinance_number']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-on-surface mb-0.5 line-clamp-1">
                                        <?php echo htmlspecialchars($ord['title']); ?>
                                    </div>
                                    <div class="text-[12px] text-on-surface-variant font-doc italic line-clamp-1">
                                        <?php echo htmlspecialchars($ord['description']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <?php
                                        if ($ord['tag_names']) {
                                            $tags = explode('|', $ord['tag_names']);
                                            $colors = explode('|', $ord['tag_colors']);
                                            $shown = array_slice($tags, 0, 2);
                                            foreach ($shown as $i => $tag) {
                                                $color = $colors[$i] ?? 'tc-blue';
                                                echo "<span class='px-2 py-0.5 rounded-full text-[10px] font-bold border {$color}'>{$tag}</span>";
                                            }
                                            if (count($tags) > 2) {
                                                echo "<span class='px-2 py-0.5 rounded-full text-[10px] font-bold border tc-slate'>+" . (count($tags) - 2) . "</span>";
                                            }
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-on-surface-variant font-medium">
                                    <?php echo date('M d, Y', strtotime($ord['date_enacted'])); ?>
                                </td>
                                <td class="px-6 py-4 text-on-surface-variant truncate"
                                    title="<?php echo htmlspecialchars($ord['main_author']); ?>">
                                    <?php echo htmlspecialchars($ord['main_author']); ?>
                                </td>
                                <td class="px-6 py-4 text-on-surface-variant relative">
                                    <div class="committee-wrap truncate" title="<?php echo htmlspecialchars($ord['department']); ?>" onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($ord['department']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo 'status-' . str_replace('_', '-', $ord['status']); ?>">
                                        <?php echo str_replace('_', ' ', $ord['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <img src="assets/icons/pdf.png"
                                            class="w-4 h-4 <?php echo $ord['hard_copy_path'] ? '' : 'grayscale opacity-30'; ?>"
                                            title="<?php echo $ord['hard_copy_path'] ? 'Hard Copy Available' : 'No Hard Copy'; ?>">
                                        <img src="assets/icons/doc.png"
                                            class="w-4 h-4 <?php echo $ord['soft_copy'] ? '' : 'grayscale opacity-30'; ?>"
                                            title="<?php echo $ord['soft_copy'] ? 'Soft Copy Available' : 'No Soft Copy'; ?>">
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="relative inline-block">
                                        <button
                                            class="p-1.5 hover:bg-surface-container rounded-full transition-all kebab-button group/kebab"
                                            onclick="toggleMenu(event, this)">
                                            <img src="assets/icons/menu-dots-vertical.png"
                                                class="w-4 h-4 opacity-40 group-hover/kebab:opacity-100">
                                        </button>
                                        <!-- Dropdown Menu -->
                                        <div
                                            class="dropdown-menu hidden fixed w-48 bg-white border border-outline-variant shadow-xl rounded-lg z-[200] py-1.5 text-left">
                                            <button
                                                class="w-full text-left px-4 py-2.5 hover:bg-surface-container text-[13px] text-navy font-bold flex items-center gap-3 transition-colors"
                                                onclick="openDrawer(<?php echo htmlspecialchars(json_encode($ord)); ?>)">
                                                <img src="assets/icons/view.png" class="w-4 h-4 opacity-70">
                                                <span>Quick View</span>
                                            </button>
                                            <a href="view.php?id=<?php echo $ord['id']; ?>&view=full"
                                                class="block px-4 py-2.5 hover:bg-surface-container text-[13px] text-navy font-bold flex items-center gap-3 transition-colors">
                                                <img src="assets/icons/view.png" class="w-4 h-4 opacity-70">
                                                <span>Full Page View</span>
                                            </a>
                                            <div class="h-[1px] bg-outline-variant my-1.5 mx-2"></div>
                                            <a href="<?php echo $is_resolution ? 'submit_resolution.php' : 'submit.php'; ?>?id=<?php echo $ord['id']; ?>"
                                                class="block px-4 py-2.5 hover:bg-surface-container text-[13px] text-navy font-bold flex items-center gap-3 transition-colors">
                                                <img src="assets/icons/edit.png" class="w-4 h-4 opacity-70">
                                                <span>Edit Record</span>
                                            </a>
                                            <button
                                                class="w-full text-left px-4 py-2.5 hover:bg-red-50 text-[13px] text-red-600 font-bold flex items-center gap-3 transition-colors"
                                                onclick="confirmDelete(<?php echo $ord['id']; ?>, '<?php echo addslashes($ord['ordinance_number']); ?>')">
                                                <img src="assets/icons/delete.png" class="w-4 h-4"
                                                    style="filter: invert(27%) sepia(91%) saturate(2352%) hue-rotate(346deg) brightness(80%) contrast(100%);">
                                                <span>Move to Bin</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-surface-container flex items-center justify-between border-t border-outline-variant">
            <span class="text-[12px] text-on-surface-variant" id="recordCountDisplay">
                Showing <strong><?php echo $total_records; ?></strong> records
            </span>
            <div id="paginationControls" class="flex items-center gap-1.5">
                <!-- Pagination buttons will be generated here by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Slide-in Drawer -->
<style>
    /* Nudge quick-view drawer up to touch the viewport top edge */
    /* Small negative top compensates for any layout offset so the panel visually touches the upper edge */
    #ordinanceDrawer { top: -8px !important; height: calc(100vh + 8px) !important; }
    /* Ensure the overlay covers the full viewport */
    #drawerOverlay { top: 0 !important; height: 100vh !important; }
</style>
<div id="drawerOverlay"
    class="fixed inset-0 bg-navy bg-opacity-30 backdrop-blur-[1px] z-[100] hidden transition-opacity duration-300 opacity-0"
    onclick="closeDrawer()"></div>
<div id="ordinanceDrawer"
    class="fixed right-0 top-0 h-screen w-[680px] bg-white shadow-2xl z-[101] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <!-- Drawer Header -->
    <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-start">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <span class="text-[11px] font-bold uppercase tracking-widest text-outline" id="drawerNumber"></span>
                <span id="drawerStatus"
                    class="status-badge px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"></span>
            </div>
            <h2 class="text-[17px] font-bold text-navy" id="drawerTitle"></h2>
            <div class="text-[12px] text-outline flex items-center gap-2">
                <span id="drawerDate"></span> &middot;
                <span id="drawerSponsor"></span> &middot;
                <span id="drawerDocIndicators" class="flex items-center gap-2"></span>
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            <button onclick="closeDrawer()" class="p-1 hover:bg-surface-container rounded transition-all">
                <img src="assets/icons/letter-x.png" class="w-5 h-5 opacity-40" alt="Close">
            </button>
            <a href="#" id="drawerFullViewBtn"
                class="px-3 py-1.5 border border-outline-variant rounded text-[11px] font-bold text-navy hover:bg-surface-container flex items-center gap-1.5">
                <img src="assets/icons/view.png" class="w-3.5 h-3.5" alt="Full View">
                Full View
            </a>
        </div>
    </div>

    <!-- Tabs Bar -->
    <div class="px-6 border-b-2 border-[#e0e3e5] flex gap-8">
        <button onclick="switchTab('document')"
            class="drawer-tab py-3 text-sm font-semibold border-b-3 border-transparent text-outline hover:text-navy transition-all"
            data-tab="document">Document</button>
        <button onclick="switchTab('pdf')"
            class="drawer-tab py-3 text-sm font-semibold border-b-3 border-transparent text-outline hover:text-navy transition-all"
            data-tab="pdf">PDF</button>
        <button onclick="switchTab('metadata')"
            class="drawer-tab py-3 text-sm font-semibold border-b-3 border-transparent text-outline hover:text-navy transition-all"
            data-tab="metadata">Metadata</button>
        <button onclick="switchTab('history')"
            class="drawer-tab py-3 text-sm font-semibold border-b-3 border-transparent text-outline hover:text-navy transition-all"
            data-tab="history">History</button>
    </div>

    <!-- Drawer Body -->
    <div class="flex-1 overflow-y-auto" id="drawerBody">
        <!-- Document Tab -->
        <div id="tab-document" class="tab-content hidden p-8 space-y-6">
            <div class="flex items-center justify-between border-b border-outline-variant pb-2">
                <span class="text-xs font-bold text-outline uppercase tracking-wider">Editable Soft Copy</span>
                <button class="p-1.5 hover:bg-surface-container rounded opacity-40"><img src="assets/icons/add.png"
                        class="w-4 h-4"></button>
            </div>
            <div class="space-y-4">
                <div class="text-[11px] font-bold text-outline uppercase tracking-widest" id="docMetaNum"></div>
                <h3 class="text-[19px] font-bold text-navy" id="docMetaTitle"></h3>
                <p class="text-sm font-doc italic text-on-surface-variant" id="docMetaDesc"></p>
                <hr class="border-outline-variant">
                <div class="font-doc text-[16px] leading-[1.75] text-on-surface" id="docBodyText"></div>
            </div>
        </div>

        <!-- PDF Tab -->
        <div id="tab-pdf" class="tab-content hidden p-10 flex flex-col items-center justify-center space-y-4">
            <div id="pdfExists" class="hidden flex flex-col items-center space-y-4">
                <div class="w-16 h-16 bg-red-50 rounded-lg flex items-center justify-center">
                    <img src="assets/icons/pdf.png" class="w-10 h-10" alt="PDF">
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-navy" id="pdfFileName"></p>
                    <p class="text-xs text-outline uppercase tracking-wider">Official signed hard copy</p>
                </div>
                <div class="flex gap-3">
                    <button id="pdfPreviewBtn" onclick="openPdfPreview(this.dataset.path)"
                        class="px-6 py-2 border border-outline-variant rounded text-xs font-bold text-navy hover:bg-red-50 transition-all">Preview</button>
                    <a href="#" id="pdfDownloadLink"
                        class="px-6 py-2 bg-red-800 text-white rounded text-xs font-bold hover:bg-red-900 transition-all shadow-md">Download</a>
                </div>
            </div>
            <div id="pdfEmpty" class="hidden flex flex-col items-center space-y-3 opacity-40">
                <img src="assets/icons/pdf.png" class="w-12 h-12 grayscale" alt="No PDF">
                <p class="text-sm font-medium">No PDF hard copy attached</p>
                <button class="text-xs font-bold text-navy hover:underline">Attach PDF</button>
            </div>
        </div>

        <!-- Metadata Tab -->
        <div id="tab-metadata" class="tab-content hidden p-8">
            <div class="divide-y divide-outline-variant border-t border-outline-variant">
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Number</span>
                    <span class="text-sm font-bold text-navy" id="metaNum"></span>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Status</span>
                    <div id="metaStatus"></div>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Date Enacted</span>
                    <span class="text-sm text-on-surface" id="metaDate"></span>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Sponsor</span>
                    <span class="text-sm text-on-surface" id="metaSponsor"></span>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Description</span>
                    <span class="text-sm font-doc italic text-on-surface-variant" id="metaDesc"></span>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Tags</span>
                    <div id="metaTags" class="flex flex-wrap gap-1"></div>
                </div>
                <div class="grid grid-cols-[140px_1fr] py-3">
                    <span class="text-xs font-bold text-outline uppercase tracking-wider">Notes</span>
                    <span class="text-sm text-on-surface-variant italic" id="metaNotes"></span>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="tab-history" class="tab-content hidden p-8">
            <div
                class="relative pl-8 space-y-8 before:content-[''] before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-[#e0e3e5]">
                <div class="relative">
                    <div
                        class="absolute -left-[27px] top-1 w-[12px] h-[12px] rounded-full bg-navy border-2 border-white">
                    </div>
                    <div class="text-[13px] font-bold text-navy">Ordinance Created</div>
                    <div class="text-[11px] text-outline mt-0.5" id="historyCreatedDate"></div>
                    <div class="text-[11px] text-on-surface-variant mt-1 italic">Initial record entry by Administrator
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer -->
    <div class="px-6 py-4 border-t border-outline-variant bg-[#f7f9fb] flex justify-between items-center">
        <div id="drawerFooterTags" class="flex gap-1"></div>
        <div class="flex gap-3">
            <a href="#" id="drawerEditBtn"
                class="px-4 py-2 text-xs font-bold text-on-surface-variant hover:text-navy transition-all">Edit</a>
            <a href="#" id="drawerFullViewBtn2"
                class="px-6 py-2 bg-navy text-white rounded text-xs font-bold hover:opacity-90 transition-all shadow-md">Full
                View</a>
        </div>
    </div>
</div>

<!-- Delete Modal (Existing) -->
<div id="deleteModal" class="fixed inset-0 z-[120] hidden items-center justify-center p-6">
    <div class="absolute inset-0 bg-[#0d1117] bg-opacity-80 backdrop-blur-md" onclick="closeDeleteModal()"></div>
    <div
        class="bg-[#161b22] border border-[#30363d] rounded-xl shadow-2xl w-full max-w-[420px] relative z-10 overflow-hidden">
        <div class="p-8 text-center">
            <div
                class="w-16 h-16 bg-red-900/20 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <img src="assets/icons/delete.png" class="w-8 h-8"
                    style="filter: invert(55%) sepia(50%) saturate(2000%) hue-rotate(320deg) brightness(95%) contrast(100%);"
                    alt="Delete">
            </div>
            <div class="flex items-center justify-center gap-2 mb-3">
                <span class="text-red-500 text-lg">⚠</span>
                <h3 class="text-xl font-bold text-[#adbac7]">Delete Ordinance?</h3>
            </div>
            <p class="text-[15px] text-[#768390] leading-relaxed mb-4">
                Are you sure you want to delete <span id="delOrdNo" class="font-bold text-[#adbac7]"></span>?<br>
            </p>
            <div
                class="inline-block px-3 py-1 bg-yellow-900/10 border border-yellow-900/30 rounded text-[11px] font-bold text-yellow-400 uppercase tracking-widest">
                This will move the ordinance to the Bin. You can restore it later.
            </div>
        </div>
        <div class="px-8 py-6 bg-[#0d1117] border-t border-[#30363d] flex justify-center gap-4">
            <button type="button" onclick="closeDeleteModal()"
                class="px-6 py-2.5 text-sm font-bold text-[#768390] hover:text-[#adbac7] transition-all">Cancel</button>
            <form action="actions/move_to_bin.php" method="POST">
                <input type="hidden" name="id" id="delOrdId">
                <input type="hidden" name="type" id="delOrdType">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                <button type="submit"
                    class="px-8 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-bold transition-all shadow-lg">Move
                    to Bin</button>
            </form>
        </div>
    </div>
</div>

<script>
    const isResolutionView = <?php echo $is_resolution ? 'true' : 'false'; ?>;
    let currentOrdinance = null;

    function openDrawer(ord) {
        currentOrdinance = ord;

        // Fill Header
        document.getElementById('drawerNumber').innerText = ord.ordinance_number;
        document.getElementById('drawerTitle').innerText = ord.title;
        document.getElementById('drawerDate').innerText = formatDate(ord.date_enacted);
        document.getElementById('drawerSponsor').innerText = `👤 ${ord.main_author}`;
        document.getElementById('drawerFullViewBtn').href = `view.php?id=${ord.id}&view=full` + (isResolutionView ? '&type=resolution' : '');
        document.getElementById('drawerFullViewBtn2').href = `view.php?id=${ord.id}&view=full` + (isResolutionView ? '&type=resolution' : '');
        document.getElementById('drawerEditBtn').href = isResolutionView ? `submit_resolution.php?id=${ord.id}` : `submit.php?id=${ord.id}`;

        // Status Badge
        const statusBadge = document.getElementById('drawerStatus');
        statusBadge.innerText = ord.status.replace('_', ' ');
        statusBadge.className = `status-badge px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider status-${ord.status.replace('_', '-')}`;

        // Doc Indicators
        const indicators = document.getElementById('drawerDocIndicators');
        indicators.innerHTML = `
            <img src="assets/icons/pdf.png" class="w-3.5 h-3.5 ${ord.hard_copy_path ? '' : 'grayscale opacity-30'}">
            <img src="assets/icons/doc.png" class="w-3.5 h-3.5 ${ord.soft_copy ? '' : 'grayscale opacity-30'}">
        `;

        // Tab: Document
        document.getElementById('docMetaNum').innerText = ord.ordinance_number;
        document.getElementById('docMetaTitle').innerText = ord.title;
        document.getElementById('docMetaDesc').innerText = ord.description;
        document.getElementById('docBodyText').innerHTML = ord.soft_copy || '<div class="text-center py-20 opacity-30 italic">No editable content yet</div>';

        // Tab: PDF
        if (ord.hard_copy_path) {
            document.getElementById('pdfExists').classList.remove('hidden');
            document.getElementById('pdfEmpty').classList.add('hidden');
            document.getElementById('pdfFileName').innerText = ord.hard_copy_path.split('/').pop();
            document.getElementById('pdfDownloadLink').href = ord.hard_copy_path;
            document.getElementById('pdfPreviewBtn').dataset.path = ord.hard_copy_path;
        } else {
            document.getElementById('pdfExists').classList.add('hidden');
            document.getElementById('pdfEmpty').classList.remove('hidden');
        }

        // Tab: Metadata
        document.getElementById('metaNum').innerText = ord.ordinance_number;
        document.getElementById('metaDate').innerText = formatDate(ord.date_enacted);
        document.getElementById('metaSponsor').innerText = ord.main_author;
        document.getElementById('metaDesc').innerText = ord.description;
        document.getElementById('metaNotes').innerText = ord.notes || 'No additional notes';

        const metaStatus = document.getElementById('metaStatus');
        metaStatus.innerHTML = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider status-${ord.status.replace('_', '-')}">${ord.status.replace('_', ' ')}</span>`;

        // Tags
        const tagContainer = document.getElementById('metaTags');
        const footerTagContainer = document.getElementById('drawerFooterTags');
        tagContainer.innerHTML = '';
        footerTagContainer.innerHTML = '';

        if (ord.tag_names) {
            const tags = ord.tag_names.split('|');
            const colors = ord.tag_colors.split('|');
            tags.forEach((tag, i) => {
                const color = colors[i] || 'tc-blue';
                const pill = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold border ${color}">${tag}</span>`;
                tagContainer.innerHTML += pill;
                if (i < 4) footerTagContainer.innerHTML += pill;
            });
            if (tags.length > 4) {
                footerTagContainer.innerHTML += `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold border tc-slate">+${tags.length - 4}</span>`;
            }
        }

        // Tab: History
        document.getElementById('historyCreatedDate').innerText = formatDate(ord.created_at);

        // Show Drawer
        document.getElementById('drawerOverlay').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('drawerOverlay').classList.add('opacity-100');
            document.getElementById('ordinanceDrawer').classList.remove('translate-x-full');
        }, 10);

        switchTab('document');
    }

    function closeDrawer() {
        document.getElementById('drawerOverlay').classList.remove('opacity-100');
        document.getElementById('ordinanceDrawer').classList.add('translate-x-full');
        setTimeout(() => {
            document.getElementById('drawerOverlay').classList.add('hidden');
        }, 300);
    }

    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById(`tab-${tabId}`).classList.remove('hidden');

        document.querySelectorAll('.drawer-tab').forEach(el => {
            el.classList.remove('border-navy', 'text-navy', 'font-bold');
            el.classList.add('border-transparent', 'text-outline');
            if (el.getAttribute('data-tab') === tabId) {
                el.classList.add('border-navy', 'text-navy', 'font-bold');
                el.classList.remove('border-transparent', 'text-outline');
            }
        });
    }

    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }

    function confirmDelete(id, num) {
        document.getElementById('delOrdId').value = id;
        document.getElementById('delOrdType').value = isResolutionView ? 'resolution' : 'ordinance';
        document.getElementById('delOrdNo').innerText = num;
        
        // Update text in delete modal based on document type
        const modalTitle = document.querySelector('#deleteModal h3');
        const modalSub = document.querySelector('#deleteModal .inline-block');
        if (modalTitle) modalTitle.innerText = isResolutionView ? 'Delete Resolution?' : 'Delete Ordinance?';
        if (modalSub) modalSub.innerText = isResolutionView ? 'This will move the resolution to the Bin. You can restore it later.' : 'This will move the ordinance to the Bin. You can restore it later.';

        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    // Kebab Menu Toggle Logic — uses fixed positioning to escape overflow clipping
    function toggleMenu(event, button) {
        event.stopPropagation();

        const menu = button.nextElementSibling;
        const isHidden = menu.classList.contains('hidden');

        // Close all other menus first
        document.querySelectorAll('.dropdown-menu').forEach(m => {
            m.classList.add('hidden');
        });

        if (!isHidden) return; // was open, now closed — leave all closed

        // Show menu off-screen to measure its height
        menu.style.visibility = 'hidden';
        menu.classList.remove('hidden');
        const menuW = menu.offsetWidth;
        const menuH = menu.offsetHeight;
        menu.style.visibility = '';

        const btnRect = button.getBoundingClientRect();
        const spaceBelow = window.innerHeight - btnRect.bottom;
        const spaceAbove = btnRect.top;

        // Horizontal: align right edge of menu to right edge of button
        let left = btnRect.right - menuW;
        if (left < 4) left = 4; // prevent going off left edge

        // Vertical: open below by default, flip up if not enough space
        let top;
        if (spaceBelow < menuH + 8 && spaceAbove > menuH + 8) {
            top = btnRect.top - menuH - 4; // open upward
        } else {
            top = btnRect.bottom + 4;       // open downward
        }

        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
    }

    // Close menus when clicking anywhere else
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.kebab-button') && !e.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });

    // Close menus on scroll (fixed menus don't follow the button)
    document.querySelector('.flex-1.overflow-y-auto')?.addEventListener('scroll', () => {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    }, { passive: true });

    // Keyboard support
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDrawer();
            closeDeleteModal();
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        }
    });

    // Keyword Highlighting Logic
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const searchTerm = urlParams.get('search');

        if (searchTerm && searchTerm.trim().length > 1) {
            highlightKeywords(searchTerm.trim());
        }
    });

    function removeHighlights() {
        const highlights = document.querySelectorAll('span.highlight');
        highlights.forEach(span => {
            const parent = span.parentNode;
            if (parent) {
                parent.replaceChild(document.createTextNode(span.textContent), span);
                parent.normalize();
            }
        });
    }

    function highlightKeywords(term) {
        const tableBody = document.querySelector('tbody');
        if (!tableBody) return;

        // Skip highlighting if it's the empty state row
        if (tableBody.querySelector('td[colspan]')) return;

        const regex = new RegExp(`(${escapeRegExp(term)})`, 'gi');

        // Target specific columns: No. (1st), Title/Desc (2nd), Sponsor (5th)
        const rows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const cells = row.querySelectorAll('td');
            if (cells.length >= 5) {
                // No.
                highlightNode(cells[0], regex);
                // Title & Description
                highlightNode(cells[1], regex);
                // Sponsor
                highlightNode(cells[4], regex);
            }
        });
    }

    function highlightNode(node, regex) {
        if (node.nodeType === 3) { // Text node
            const matches = node.data.match(regex);
            if (matches) {
                const span = document.createElement('span');
                span.innerHTML = node.data.replace(regex, '<span class="highlight">$1</span>');
                node.parentNode.replaceChild(span, node);
            }
        } else if (node.nodeType === 1 && node.childNodes && !/(script|style)/i.test(node.tagName)) {
            // Element node - recurse through children
            // Special handling to avoid breaking layout elements
            for (let i = 0; i < node.childNodes.length; i++) {
                highlightNode(node.childNodes[i], regex);
            }
        }
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Offline Real-time Filtering Logic
    document.addEventListener('DOMContentLoaded', () => {
        // Initial setup for empty state tracking
        const tableBody = document.querySelector('tbody');
        if (!document.getElementById('emptyStateRow')) {
            const emptyTr = document.createElement('tr');
            emptyTr.id = 'emptyStateRow';
            emptyTr.style.display = 'none';
            emptyTr.innerHTML = `
                <td colspan="8" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center">
                        <img src="assets/icons/glass.png" class="w-10 h-10 opacity-20 mb-3" alt="Empty">
                        <p class="text-on-surface-variant font-medium">No ordinances match your current filters.</p>
                    </div>
                </td>
            `;
            tableBody.appendChild(emptyTr);
        }

        // Trigger initial filter based on any URL params that populated the HTML
        applyFilters();
    });

    let currentPage = 1;
    const itemsPerPage = 10;

    function applyFilters(resetPage = true) {
        if (resetPage) currentPage = 1;

        const searchVal = document.getElementById('searchInput').value.toLowerCase();
        const statusVal = document.getElementById('statusFilter').value.toLowerCase();
        const yearVal = document.getElementById('yearFilter').value;
        const docVal = document.getElementById('docTypeFilter').value;
        const checkedTags = Array.from(document.querySelectorAll('input[name="tags[]"]:checked')).map(cb => cb.value.toLowerCase());

        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr:not(#emptyStateRow)'));
        let matchingRows = [];

        // Add a smooth transition class to tbody if not already present
        if (!tbody.style.transition) {
            tbody.style.transition = 'opacity 0.2s ease-in-out';
        }

        // Slightly dim during filtering for smoothness
        tbody.style.opacity = '0.6';

        setTimeout(() => {
            // Remove previous highlights before reading text content to ensure clean matching
            removeHighlights();

            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return; // skip other empty states if any

                const textContent = row.innerText.toLowerCase();
                const dateCell = row.cells[3].innerText;
                const statusCell = row.cells[5].innerText.toLowerCase();
                const hasPdf = !row.cells[6].querySelector('img[src*="pdf.png"]').classList.contains('grayscale');
                const hasDoc = !row.cells[6].querySelector('img[src*="doc.png"]').classList.contains('grayscale');

                // Extract tags from the data attribute instead of visual DOM to include hidden tags
                const rowTagsStr = row.dataset.tags || '';
                const rowTags = rowTagsStr.toLowerCase().split('|');

                let match = true;

                if (searchVal && !textContent.includes(searchVal)) match = false;
                if (statusVal && statusVal !== 'all' && !statusCell.includes(statusVal.replace('_', ' '))) match = false;
                if (yearVal && !dateCell.includes(yearVal)) match = false;

                if (docVal === 'hard' && !hasPdf) match = false;
                if (docVal === 'soft' && !hasDoc) match = false;

                if (checkedTags.length > 0) {
                    // ALL selected tags must be present in the row
                    const hasAllTags = checkedTags.every(tag => rowTags.includes(tag));
                    if (!hasAllTags) match = false;
                }

                if (match) {
                    matchingRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            // Pagination Logic
            const totalMatching = matchingRows.length;
            const totalPages = Math.ceil(totalMatching / itemsPerPage);
            
            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;

            matchingRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                    row.style.animation = 'pdfFadeIn 0.3s ease';
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle empty state
            const emptyRow = document.getElementById('emptyStateRow');
            if (emptyRow) {
                emptyRow.style.display = totalMatching === 0 ? '' : 'none';
            }

            // Update record count display
            const countDisplay = document.getElementById('recordCountDisplay');
            if (countDisplay) {
                if (totalMatching === 0) {
                    countDisplay.innerHTML = `Showing <strong>0</strong> matching records`;
                } else {
                    const displayEnd = Math.min(endIndex, totalMatching);
                    countDisplay.innerHTML = `Showing <strong>${startIndex + 1}-${displayEnd}</strong> of <strong>${totalMatching}</strong> records`;
                }
            }

            renderPagination(totalPages);

            // Apply new highlights dynamically
            if (searchVal && searchVal.trim().length > 1) {
                highlightKeywords(searchVal.trim());
            }

            // Restore opacity
            tbody.style.opacity = '1';
        }, 50); // slight delay allows UI to render the opacity change for smoothness
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('paginationControls');
        if (!container) return;
        
        container.innerHTML = '';
        if (totalPages <= 1) return;

        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.innerText = 'Prev';
        prevBtn.className = `px-3 py-1 border rounded text-xs font-bold transition-all ${currentPage === 1 ? 'opacity-50 cursor-not-allowed border-outline-variant text-outline' : 'border-outline-variant text-navy hover:bg-surface-container'}`;
        prevBtn.onclick = () => { if (currentPage > 1) changePage(currentPage - 1); };
        container.appendChild(prevBtn);

        // Page Numbers
        let startP = Math.max(1, currentPage - 2);
        let endP = Math.min(totalPages, currentPage + 2);

        if (startP > 1) {
            container.appendChild(createPageBtn(1));
            if (startP > 2) {
                const dots = document.createElement('span');
                dots.innerText = '...';
                dots.className = 'px-1 text-outline text-xs';
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
                dots.className = 'px-1 text-outline text-xs';
                container.appendChild(dots);
            }
            container.appendChild(createPageBtn(totalPages));
        }

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.innerText = 'Next';
        nextBtn.className = `px-3 py-1 border rounded text-xs font-bold transition-all ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed border-outline-variant text-outline' : 'border-outline-variant text-navy hover:bg-surface-container'}`;
        nextBtn.onclick = () => { if (currentPage < totalPages) changePage(currentPage + 1); };
        container.appendChild(nextBtn);
    }

    function createPageBtn(num) {
        const btn = document.createElement('button');
        btn.innerText = num;
        if (num === currentPage) {
            btn.className = 'px-3 py-1 border rounded text-xs font-bold border-navy bg-navy text-white shadow-sm';
        } else {
            btn.className = 'px-3 py-1 border rounded text-xs font-bold border-outline-variant text-navy hover:bg-surface-container transition-all';
            btn.onclick = () => changePage(num);
        }
        return btn;
    }

    function changePage(num) {
        currentPage = num;
        applyFilters(false);
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('yearFilter').value = '';
        document.getElementById('docTypeFilter').value = '';
        document.querySelectorAll('input[name="tags[]"]').forEach(cb => cb.checked = false);
        applyFilters(true);
    }

    // Real-time Sorting Logic
    let currentSortColumn = 0;
    let currentSortOrder = 'desc';

    function sortTable(columnIndex, type = 'text') {
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        // Don't sort if table is empty
        if (rows.length === 0 || rows[0].querySelector('td[colspan]')) return;

        // Determine order
        if (currentSortColumn === columnIndex) {
            currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortColumn = columnIndex;
            currentSortOrder = 'asc';
        }

        // Update UI Icons
        document.querySelectorAll('.sort-header').forEach(th => {
            th.classList.remove('active', 'sort-asc', 'sort-desc');
            if (parseInt(th.dataset.column) === columnIndex) {
                th.classList.add('active');
                th.classList.add(currentSortOrder === 'asc' ? 'sort-asc' : 'sort-desc');
            }
        });

        // Sort rows
        const sortedRows = rows.sort((a, b) => {
            let valA = a.cells[columnIndex].innerText.trim();
            let valB = b.cells[columnIndex].innerText.trim();

            if (type === 'date') {
                valA = new Date(valA);
                valB = new Date(valB);
            } else if (type === 'number') {
                valA = parseFloat(valA.replace(/[^0-9.-]+/g, ""));
                valB = parseFloat(valB.replace(/[^0-9.-]+/g, ""));
            } else {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }

            if (valA < valB) return currentSortOrder === 'asc' ? -1 : 1;
            if (valA > valB) return currentSortOrder === 'asc' ? 1 : -1;
            return 0;
        });

        // Re-append sorted rows with a smooth transition effect
        tbody.style.opacity = '0.5';
        setTimeout(() => {
            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            tbody.append(...sortedRows);
            applyFilters(true); // Re-apply pagination to show the sorted subset
        }, 50);
    }
</script>

<!-- ============================================================
     PDF PREVIEW MODAL
     ============================================================ -->
<style>
    .highlight { background: #fef08a; border-radius: 2px; padding: 0 2px; box-shadow: 0 0 0 1px #fde047; }
    @keyframes pdfFadeIn {
        from {
            opacity: 0
        }

        to {
            opacity: 1
        }
    }

    @keyframes pdfSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    #pdfPreviewModal {
        animation: pdfFadeIn .2s ease both;
    }

    #pdfPreviewModal .pdf-inner {
        animation: pdfSlideUp .25s ease both;
    }
</style>

<div id="pdfPreviewModal" class="fixed inset-0 z-[400] hidden flex-col"
    style="background:rgba(10,0,0,.82);backdrop-filter:blur(4px)">

    <!-- Top bar -->
    <div class="flex items-center justify-between px-5 py-3 flex-shrink-0"
        style="background:rgba(127,0,0,.95);border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="flex items-center gap-3">
            <img src="assets/icons/pdf.png" class="w-5 h-5" alt="PDF">
            <span id="pdfPreviewName" class="text-white text-sm font-bold truncate max-w-[400px]"></span>
        </div>
        <div class="flex items-center gap-3">
            <a id="pdfPreviewDownload" href="#" download class="px-4 py-1.5 text-xs font-bold text-white rounded"
                style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25)">
                ⬇ Download
            </a>
            <button onclick="closePdfPreview()"
                class="w-8 h-8 rounded flex items-center justify-center text-white text-lg leading-none"
                style="background:rgba(255,255,255,.12)" aria-label="Close preview">✕</button>
        </div>
    </div>

    <!-- iframe viewer -->
    <div class="pdf-inner flex-1 overflow-hidden">
        <iframe id="pdfPreviewFrame" src="" class="w-full h-full border-none" title="PDF Preview">
        </iframe>
    </div>
</div>

<script>
    function openPdfPreview(path) {
        if (!path) return;
        var modal = document.getElementById('pdfPreviewModal');
        document.getElementById('pdfPreviewFrame').src = path;
        document.getElementById('pdfPreviewName').textContent = path.split('/').pop();
        document.getElementById('pdfPreviewDownload').href = path;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closePdfPreview() {
        var modal = document.getElementById('pdfPreviewModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('pdfPreviewFrame').src = '';
        document.body.style.overflow = '';
    }

    // ESC key closes the modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePdfPreview();
    });
</script>

<?php require_once 'includes/footer.php'; ?>

<script>
    // Ensure the drawer and overlay are direct children of <body>
    // This prevents ancestor transforms or scroll containers from changing fixed positioning.
    (function moveDrawerToBody() {
        function move() {
            var overlay = document.getElementById('drawerOverlay');
            var drawer = document.getElementById('ordinanceDrawer');
            try {
                if (overlay && overlay.parentElement !== document.body) document.body.appendChild(overlay);
                if (drawer && drawer.parentElement !== document.body) document.body.appendChild(drawer);
            } catch (e) {
                console.error('Failed to move drawer to body:', e);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', move);
        } else {
            move();
        }
    })();
</script>