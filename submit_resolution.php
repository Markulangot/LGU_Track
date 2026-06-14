<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Submit Resolution";
$current_page = "submit";

$message = '';
$error = '';
$is_edit = isset($_GET['id']);
$res_id = $is_edit ? (int)$_GET['id'] : null;
$res_data = null;

// Ensure resolutions table and resolution_tags exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS resolutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resolution_number VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        date_enacted DATE NOT NULL,
        status ENUM('active','approved','under_review','for_reading','draft','1st_reading','2nd_reading','3rd_reading','rejected','amended','repealed') DEFAULT 'draft',
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
} catch (Exception $e) {
    // ignore - table creation not critical here, will surface on insert
}

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM resolutions WHERE id = ?");
    $stmt->execute([$res_id]);
    $res_data = $stmt->fetch();
    if (!$res_data) {
        header('Location: database.php?type=resolution');
        exit;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_rate_limit($pdo, 'submit', 15, 15);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!$error) {
        $title = trim($_POST['title']);
        $res_number = trim($_POST['ord_number']);
        $dept = trim($_POST['department']);
        $sponsor = trim($_POST['sponsor']);
        $co_authors = trim($_POST['co_authors'] ?? '');
        $date_enacted = $_POST['date_enacted'];
        $status = $_POST['status'] ?? '';
        if (empty($status)) $status = 'for_reading';
        $soft_copy = $_POST['soft_copy'] ?? '';
        $notes = trim($_POST['notes']);
        $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];

        if (empty($title) || empty($dept) || empty($date_enacted)) {
            $error = "Title, Department, and Date Enacted are required.";
        } else {
            try {
                $pdo->beginTransaction();

                // Handle File Upload
                $hard_copy_path = $is_edit ? $res_data['hard_copy_path'] : null;
                if (isset($_FILES['hard_copy']) && $_FILES['hard_copy']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['hard_copy']['tmp_name'];
                    $file_name = $_FILES['hard_copy']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $mime_type = mime_content_type($file_tmp);

                    if ($file_ext !== 'pdf' || $mime_type !== 'application/pdf') {
                        throw new Exception("Only PDF files are allowed.");
                    }

                    $new_file_name = time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
                    $upload_dir = 'uploads/pdfs/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $hard_copy_path = $upload_dir . $new_file_name;
                    move_uploaded_file($file_tmp, $hard_copy_path);
                }

                if ($is_edit) {
                    $stmt = $pdo->prepare("UPDATE resolutions SET resolution_number = ?, title = ?, description = ?, date_enacted = ?, status = ?, department = ?, main_author = ?, co_authors = ?, soft_copy = ?, hard_copy_path = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$res_number, $title, $title, $date_enacted, $status, $dept, $sponsor, $co_authors, $soft_copy, $hard_copy_path, $notes, $res_id]);
                    $pdo->prepare("DELETE FROM resolution_tags WHERE resolution_id = ?")->execute([$res_id]);
                } else {
                    if (empty($res_number)) {
                        $res_number = 'RES-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                    }
                    $stmt = $pdo->prepare("INSERT INTO resolutions (resolution_number, title, description, date_enacted, status, department, main_author, co_authors, soft_copy, hard_copy_path, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$res_number, $title, $title, $date_enacted, $status, $dept, $sponsor, $co_authors, $soft_copy, $hard_copy_path, $notes]);
                    $res_id = $pdo->lastInsertId();
                }

                if (!empty($selected_tags)) {
                    $tag_stmt = $pdo->prepare("INSERT INTO resolution_tags (resolution_id, tag_id) VALUES (?, ?)");
                    foreach ($selected_tags as $tag_id) {
                        $tag_stmt->execute([$res_id, $tag_id]);
                    }
                }

                $action = $is_edit ? 'update' : 'create';
                $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, ?, 'resolution', ?, ?, ?)");
                $log_stmt->execute([$_SESSION['user_id'], $action, $res_id, $res_number, "Resolution $res_number " . ($is_edit ? "updated" : "created")]);

                $pdo->commit();
                $_SESSION['submit_success'] = true;
                $_SESSION['submit_action'] = $is_edit ? 'updated' : 'created';
                $_SESSION['submit_res_id']  = $res_id;
                $_SESSION['submit_res_num'] = $res_number;
                header("Location: submit_resolution.php?done=1" . ($is_edit ? "&id=$res_id" : ""));
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$all_tags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$current_tag_ids = [];
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT tag_id FROM resolution_tags WHERE resolution_id = ?");
    $stmt->execute([$res_id]);
    $current_tag_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include 'includes/header.php';
?>

<div class="space-y-6 max-w-[1440px] mx-auto pb-20">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-[13px] font-bold text-on-surface-variant">
        <a href="database.php?type=resolution" class="hover:text-navy transition-colors">Submissions</a>
        <span class="text-outline-variant">+</span>
        <span class="text-navy">New Resolution</span>
    </div>
    
    <!-- Title -->
    <div>
        <h1 class="text-[32px] font-bold text-navy leading-tight">Submit New Resolution</h1>
        <p class="text-sm text-on-surface-variant mt-1">Complete the required metadata and document content for the resolution review.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 text-xs p-4 rounded-lg border border-red-100 font-medium">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="submit_resolution.php<?php echo $is_edit ? '?id='.$res_id : ''; ?>" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        
        <!-- Left Column: Forms -->
        <div class="space-y-8">
            <!-- Basic Information Card -->
            <div class="bg-white rounded-lg border border-outline-variant shadow-sm p-8 space-y-6">
                <h3 class="text-lg font-bold text-navy">Basic Information</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Resolution Title <span class="text-error">*</span></label>
                        <input type="text" name="title" required value="<?php echo $is_edit ? htmlspecialchars($res_data['title']) : ''; ?>"
                               class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline"
                               placeholder="e.g., Approval of Municipal Budget Allocation ">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Resolution Number (Optional)</label>
                            <input type="text" name="ord_number" value="<?php echo $is_edit ? htmlspecialchars($res_data['resolution_number']) : ''; ?>"
                                   class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline"
                                   placeholder="Leave blank for auto-assignment">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Committee <span class="text-error">*</span></label>
                            <div class="relative">
                                <select name="department" required class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm appearance-none">
                                    <option value="" disabled <?php echo !$is_edit ? 'selected' : ''; ?>>Select Committee...</option>
                                    <?php 
                                    $depts = [
                                        'Good Government, Public Ethics and Accountability',
                                        'Appropriation and Finance',
                                        'Ways and Means',
                                        'Laws and Human Rights',
                                        'House Rules',
                                        'Peace and Order',
                                        'Human Resource, Labor and Employment',
                                        'Agriculture, Fishery, and Food Security',
                                        'Trade Industry and Investment',
                                        'Economic Enterprise Development',
                                        'Social Welfare Services',
                                        'Health and Sanitation',
                                        'Women and Family',
                                        'Education, Science, & Technology',
                                        'Infrastructure & Engineering',
                                        'Landed Estate, Planning and Development',
                                        'Public Utilities, Transportation & Communication',
                                        'Tourism, Culture, & Arts',
                                        'Youth, Sports & Development',
                                        'Games and Amusement',
                                        'Environmental Protection',
                                        'Disaster and Calamities',
                                        'Cooperatives and People’s Organization',
                                        'Barangay Affairs',
                                        'Oversight'
                                    ];
                                    foreach ($depts as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($is_edit && $res_data['department'] == $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant">+</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Main Author / Sponsor</label>
                            <input type="text" name="sponsor" value="<?php echo $is_edit ? htmlspecialchars($res_data['main_author']) : ''; ?>"
                                   class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline"
                                   placeholder="e.g. Hon. Juan Dela Cruz">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Co-Author(S)</label>
                            <input type="text" name="co_authors" value="<?php echo $is_edit ? htmlspecialchars($res_data['co_authors'] ?? '') : ''; ?>"
                                   class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline"
                                   placeholder="Comma separated names">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking & Metadata Card -->
            <div class="bg-white rounded-lg border border-outline-variant shadow-sm p-8 space-y-6">
                <h3 class="text-lg font-bold text-navy">Tracking & Metadata</h3>
                
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Target Effective Date</label>
                            <div class="relative">
                                <input type="date" name="date_enacted" required value="<?php echo $is_edit ? $res_data['date_enacted'] : date('Y-m-d'); ?>"
                                       class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Status</label>
                            <div class="relative">
                                <select name="status" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm appearance-none">
                                    <option value="for_reading" <?php echo (!$is_edit || in_array($res_data['status'], ['under_review','for_reading'])) ? 'selected' : ''; ?>>For Reading</option>
                                    <option value="under_review" <?php echo ($is_edit && $res_data['status'] == 'under_review') ? 'selected' : ''; ?> style="display:none">(legacy) Pending Review</option>
                                    <option value="draft" <?php echo ($is_edit && $res_data['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="active" <?php echo ($is_edit && $res_data['status'] == 'active') ? 'selected' : ''; ?>>Active / Enacted</option>
                                    <option value="amended" <?php echo ($is_edit && $res_data['status'] == 'amended') ? 'selected' : ''; ?>>Amended</option>
                                    <option value="repealed" <?php echo ($is_edit && $res_data['status'] == 'repealed') ? 'selected' : ''; ?>>Repealed</option>
                                </select>
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant">+</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Tags & Categorization</label>
                        
                        <div class="space-y-3">
                            <span class="text-[10px] font-bold text-outline uppercase tracking-widest">Premade Tags</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($all_tags as $tag): 
                                    $is_checked = in_array($tag['id'], $current_tag_ids);
                                ?>
                                    <label class="cursor-pointer group">
                                        <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" class="hidden peer" <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <span class="px-3 py-1 rounded text-[10px] font-bold border transition-all 
                                                   peer-checked:bg-navy peer-checked:text-white peer-checked:border-navy
                                                   bg-white text-on-surface-variant border-outline-variant group-hover:border-navy uppercase">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <input type="text" placeholder="Type to create new tag..." class="flex-1 px-4 py-2 bg-surface-container-lowest border border-outline-variant rounded text-sm">
                            <button type="button" class="px-4 py-2 border border-navy text-navy font-bold text-[13px] rounded hover:bg-surface-container transition-all">+ Create Tag</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-navy mb-2 uppercase tracking-tight">Notes / Remarks</label>
                        <textarea name="notes" rows="4" 
                                  class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded focus:outline-none focus:border-navy text-sm placeholder:text-outline"
                                  placeholder="Add any additional notes or remarks here..."><?php echo $is_edit ? htmlspecialchars($res_data['notes']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Document Content -->
        <div class="bg-white rounded-lg border border-outline-variant shadow-sm p-8 space-y-8">
            <h3 class="text-lg font-bold text-navy">Document Content</h3>
            
            <!-- Hard Copy Upload -->
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="block text-sm font-bold text-navy">Hard Copy Upload</label>
                    <p class="text-xs text-on-surface-variant">Upload the scanned or finalized PDF version of the resolution.</p>
                </div>
                
                <div id="pdf-drop-zone" class="relative border-2 border-dashed border-outline-variant rounded-lg p-12 bg-surface-container-lowest flex flex-col items-center justify-center text-center group hover:border-navy transition-all">
                    <div class="w-12 h-12 bg-surface-container rounded-lg flex items-center justify-center mb-4" id="pdf-icon-wrap">
                        <img src="assets/icons/pdf.png" class="w-6 h-6 opacity-40" alt="PDF" id="pdf-icon">
                    </div>
                    <div class="space-y-1" id="pdf-prompt">
                        <p class="text-sm font-medium text-on-surface">
                            Drag and drop hard copy files here or <span class="text-error font-bold hover:underline cursor-pointer">browse</span>
                        </p>
                        <p class="text-[10px] text-outline font-bold uppercase tracking-widest">PDF ONLY, UP TO 50MB</p>
                    </div>
                    <!-- Selected file preview -->
                    <div id="pdf-selected" class="hidden mt-2 flex flex-col items-center gap-2">
                        <img src="assets/icons/pdf.png" class="w-8 h-8" alt="PDF">
                        <p id="pdf-filename" class="text-sm font-bold text-navy break-all text-center"></p>
                        <p class="text-[10px] text-green-600 font-bold uppercase tracking-widest">✓ Ready to upload</p>
                    </div>
                    <input type="file" name="hard_copy" id="hardCopyInput" accept="application/pdf" class="absolute inset-0 opacity-0 cursor-pointer">
                    
                    <?php if ($is_edit && $res_data['hard_copy_path']): ?>
                        <div id="pdf-current" class="mt-4 p-2 bg-green-50 text-green-700 text-[10px] font-bold rounded border border-green-100 uppercase tracking-widest">
                            Current: <?php echo basename($res_data['hard_copy_path']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Soft Copy Editor -->
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="block text-sm font-bold text-navy">Soft Copy Editor</label>
                    <p class="text-xs text-on-surface-variant">Edit the text version for search and processing.</p>
                </div>

                <div class="border border-outline-variant rounded overflow-hidden">
                    <!-- Fake Toolbar -->
                    <div class="bg-surface-container-lowest border-b border-outline-variant p-2 flex items-center gap-4">
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded"><img src="assets/icons/bold.png" class="w-4 h-4 opacity-70"></button>
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded italic font-serif text-lg opacity-70">I</button>
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded underline opacity-70">U</button>
                        <div class="w-[1px] h-4 bg-outline-variant mx-1"></div>
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded opacity-70"><img src="assets/icons/edit.png" class="w-4 h-4"></button>
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded opacity-70"><img src="assets/icons/clock.png" class="w-4 h-4"></button>
                        <button type="button" class="p-1.5 hover:bg-surface-container rounded opacity-70"><img src="assets/icons/printer.png" class="w-4 h-4"></button>
                    </div>
                    <textarea name="soft_copy" rows="18" 
                              class="w-full px-6 py-6 focus:outline-none text-[15px] font-doc leading-relaxed placeholder:text-outline"
                              placeholder="Enter the official text of the resolution here..."><?php echo $is_edit ? htmlspecialchars($res_data['soft_copy']) : ''; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Actions (Mockup - simple footer for now) -->
        <div class="lg:col-span-2 flex items-center justify-between pt-6 border-t border-outline-variant">
            <div class="flex items-center gap-2">
                <a href="database.php?type=resolution" class="flex items-center gap-2 text-[13px] font-bold text-on-surface-variant hover:text-navy transition-all">
                    <span class="text-lg">←</span>
                    Back to Resolution Database
                </a>
            </div>
            <button type="submit" class="px-12 py-3 bg-navy text-white rounded font-bold text-sm hover:opacity-95 transition-all shadow-xl">
                Submit Resolution
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php if (isset($_GET['done']) && !empty($_SESSION['submit_success'])): ?>
<?php
    $submit_action  = $_SESSION['submit_action']  ?? 'saved';
    $submit_res_id  = $_SESSION['submit_res_id']  ?? null;
    $submit_res_num = $_SESSION['submit_res_num'] ?? 'Resolution';
    $is_edit_done   = ($submit_action === 'updated');
    unset($_SESSION['submit_success'], $_SESSION['submit_action'], $_SESSION['submit_res_id'], $_SESSION['submit_res_num']);
?>
<style>
    @keyframes fadeIn   { from{opacity:0} to{opacity:1} }
    @keyframes popUp    { from{opacity:0;transform:scale(.92)} to{opacity:1;transform:scale(1)} }
    @keyframes drawCheck{ from{stroke-dashoffset:60} to{stroke-dashoffset:0} }
    #sm-backdrop { animation:fadeIn .2s ease both; }
    #sm-card     { animation:popUp .25s cubic-bezier(.34,1.56,.64,1) both; }
    #sm-check    { stroke-dasharray:60;stroke-dashoffset:60;animation:drawCheck .45s ease .2s both; }
</style>

<div id="sm-backdrop" role="dialog" aria-modal="true" aria-labelledby="sm-title"
     class="fixed inset-0 z-[300] flex items-center justify-center px-4"
     style="background:rgba(0,0,0,.45);backdrop-filter:blur(3px)"
     onclick="smClose()">

    <div id="sm-card"
         onclick="event.stopPropagation()"
         class="bg-white rounded-xl shadow-xl w-full max-w-[340px] px-8 py-9 flex flex-col items-center text-center gap-4">

        <!-- Check icon -->
        <div class="w-[72px] h-[72px] rounded-full flex items-center justify-center"
             style="background:#fff1f2;border:2.5px solid #b91c1c;">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none"
                 stroke="#b91c1c" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                <path id="sm-check" d="M4.5 12.5l5 5L19.5 7"/>
            </svg>
        </div>

        <!-- Text -->
        <div class="space-y-1">
            <h2 id="sm-title" class="text-[19px] font-bold" style="color:#7f0000;">
                Changes Saved Successfully!
            </h2>
            <p class="text-[13px]" style="color:#6b7280;">
                Updating resolution records&hellip;
            </p>
        </div>

        <!-- Buttons -->
        <div class="w-full flex flex-col gap-2 pt-1">
            <?php if ($is_edit_done && $submit_res_id): ?>
            <a href="submit_resolution.php?id=<?php echo (int)$submit_res_id; ?>"
               class="w-full py-2.5 rounded-lg text-[13px] font-bold text-center"
               style="background:#fef2f2;color:#7f0000;border:1.5px solid #fca5a5;">
                Edit Again
            </a>
            <?php endif; ?>
            <a href="database.php?type=resolution"
               class="w-full py-2.5 rounded-lg text-[13px] font-bold text-white text-center"
               style="background:#8B0000;">
                Back to Resolutions
            </a>
        </div>
    </div>
</div>

<script>
    function smClose(){ window.location.href='database.php?type=resolution'; }
    document.addEventListener('keydown',function(e){ if(e.key==='Escape') smClose(); });
    setTimeout(smClose, 4000);
</script>
<?php endif; ?>
