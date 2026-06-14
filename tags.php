<?php
$page_title = "Manage Tags";
$current_page = "tags";
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Handle Tag Creation/Update — must run before header.php outputs HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save') {
            $name = trim($_POST['name']);
            $color = $_POST['color_theme'] ?? 'tc-blue';
            $desc = trim($_POST['description'] ?? '');
            $id = $_POST['id'] ?? null;

            if ($id) {
                $stmt = $pdo->prepare("UPDATE tags SET name = ?, color_theme = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $color, $desc, $id]);
                $msg = "Tag updated successfully";
            } else {
                $stmt = $pdo->prepare("INSERT INTO tags (name, color_theme, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $color, $desc]);
                $id = $pdo->lastInsertId();
                $msg = "Tag created successfully";
            }
            
            // Log activity
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, ?, 'tag', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $id ? 'update' : 'create', $id, $name, $msg]);
            
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            // Get tag name for logging
            $t_stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ?");
            $t_stmt->execute([$id]);
            $tag_name = $t_stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log activity
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'delete', 'tag', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $id, $tag_name, "Deleted tag: $tag_name"]);
        }
        header('Location: tags.php');
        exit;
    }
}

// Fetch all tags with usage count
$stmt = $pdo->query("
    SELECT t.*, COUNT(ot.ordinance_id) as usage_count 
    FROM tags t 
    LEFT JOIN ordinance_tags ot ON t.id = ot.tag_id 
    GROUP BY t.id 
    ORDER BY usage_count DESC, t.name ASC
");
$tags = $stmt->fetchAll();

// Get max usage for progress bars
$max_usage = 0;
foreach ($tags as $t) if ($t['usage_count'] > $max_usage) $max_usage = $t['usage_count'];

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-[28px] font-bold text-navy leading-tight">Tag Management</h1>
            <p class="text-sm text-on-surface-variant mt-1">Create, edit, and organize tags used to categorize ordinances.</p>
        </div>
        <button onclick="openTagModal()" class="bg-navy text-white px-4 py-2 rounded font-bold text-sm flex items-center gap-2 hover:opacity-90 transition-all">
            <img src="assets/icons/add.png" class="w-4 h-4 icon-invert" alt="Add">
            New Tag
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
        <!-- Left Column: Tag List -->
        <div class="bg-white rounded-lg border border-outline-variant overflow-hidden flex flex-col">
            <!-- Table Header -->
            <div class="bg-surface-container px-4 py-3 border-b border-outline-variant flex justify-between items-center">
                <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">All Tags</span>
                <div class="relative">
                    <img src="assets/icons/glass.png" class="absolute left-2.5 top-1/2 -translate-y-1/2 icon-sm opacity-40" alt="Search">
                    <input type="text" id="tagSearch" oninput="filterTags()" placeholder="Search tags..." 
                           class="pl-8 pr-3 py-1 border border-outline-variant rounded text-xs focus:outline-none focus:border-navy w-[180px]">
                </div>
            </div>

            <!-- Tag Rows -->
            <div class="p-3 space-y-1.5 overflow-y-auto max-h-[600px]" id="tagList">
                <?php foreach ($tags as $tag): ?>
                    <div class="tag-row flex items-center justify-between p-2 rounded border border-[#e6e8ea] bg-[#fafafa] hover:border-navy transition-all" data-name="<?php echo htmlspecialchars(strtolower($tag['name'])); ?>">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-bold border <?php echo htmlspecialchars($tag['color_theme']); ?>">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </span>
                            <span class="text-[11px] text-outline line-clamp-1 max-w-[300px]">
                                <?php echo htmlspecialchars($tag['description']); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xs font-bold text-on-surface-variant"><?php echo $tag['usage_count']; ?> ord.</span>
                            <div class="flex items-center gap-1">
                                <button onclick='openTagModal(<?php echo json_encode($tag); ?>)' class="p-1.5 hover:bg-surface-container rounded transition-all">
                                    <img src="assets/icons/edit.png" class="w-4 h-4 opacity-60" alt="Edit">
                                </button>
                                <button onclick="confirmDelete(<?php echo $tag['id']; ?>, '<?php echo addslashes($tag['name']); ?>', <?php echo $tag['usage_count']; ?>)" class="p-1.5 hover:bg-red-50 rounded transition-all">
                                    <img src="assets/icons/delete.png" class="w-4 h-4 opacity-60" style="filter: invert(13%) sepia(80%) saturate(5000%) hue-rotate(345deg) brightness(90%) contrast(100%);" alt="Delete">
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column: Statistics -->
        <div class="bg-white rounded-lg border border-outline-variant p-5 space-y-5 h-fit">
            <h3 class="text-sm font-bold text-navy">Tag Statistics</h3>
            <div class="space-y-4">
                <?php foreach ($tags as $tag): ?>
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo htmlspecialchars($tag['color_theme']); ?>">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </span>
                            <span class="text-[10px] font-bold text-navy"><?php echo $tag['usage_count']; ?></span>
                        </div>
                        <div class="w-full h-1 bg-surface-container rounded-full overflow-hidden">
                            <?php 
                            $percent = $max_usage > 0 ? ($tag['usage_count'] / $max_usage) * 100 : 0;
                            ?>
                            <div class="h-full bg-navy rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tag Modal -->
<div id="tagModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-6">
    <div class="absolute inset-0 bg-navy bg-opacity-30 backdrop-blur-[2px]" onclick="closeTagModal()"></div>
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-[420px] relative z-10 overflow-hidden">
        <form action="tags.php" method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="modalTagId">
            
            <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center">
                <h3 class="text-lg font-bold text-navy" id="modalTitle">New Tag</h3>
                <button type="button" onclick="closeTagModal()" class="text-outline hover:text-navy">
                    <img src="assets/icons/delete.png" class="w-5 h-5 opacity-40" alt="Close">
                </button>
            </div>
            
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Tag Name</label>
                    <input type="text" name="name" id="modalTagName" required
                           class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm"
                           placeholder="e.g., Agriculture">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-3">Color Theme</label>
                    <div class="grid grid-cols-4 gap-3">
                        <?php 
                        $themes = [
                            ['tc-slate', 'Gray'], ['tc-blue', 'Blue'], ['tc-green', 'Green'], ['tc-amber', 'Amber'],
                            ['tc-red', 'Red'], ['tc-purple', 'Purple'], ['tc-teal', 'Teal'], ['tc-orange', 'Orange']
                        ];
                        foreach ($themes as $theme): ?>
                            <label class="cursor-pointer group">
                                <input type="radio" name="color_theme" value="<?php echo $theme[0]; ?>" class="hidden peer">
                                <div class="w-full aspect-square rounded border-2 border-transparent peer-checked:border-navy p-0.5 transition-all">
                                    <div class="w-full h-full rounded flex items-center justify-center <?php echo $theme[0]; ?>">
                                        <img src="assets/icons/checked.png" class="w-3 h-3 hidden peer-checked:block icon-invert" alt="Selected">
                                    </div>
                                </div>
                                <span class="block text-center text-[9px] text-outline mt-1 group-hover:text-navy"><?php echo $theme[1]; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Description (Optional)</label>
                    <textarea name="description" id="modalTagDesc" rows="2"
                              class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm resize-none"
                              placeholder="Short description of scope"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface-container flex justify-end gap-3">
                <button type="button" onclick="closeTagModal()" class="px-4 py-2 text-sm font-bold text-on-surface-variant hover:text-navy">Cancel</button>
                <button type="submit" class="bg-navy text-white px-6 py-2 rounded text-sm font-bold hover:opacity-90 transition-all shadow-md">Save Tag</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation -->
<div id="deleteModal" class="fixed inset-0 z-[110] hidden items-center justify-center p-6">
    <div class="absolute inset-0 bg-navy bg-opacity-30 backdrop-blur-[2px]" onclick="closeDeleteModal()"></div>
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-[400px] relative z-10 overflow-hidden">
        <div class="p-6 text-center">
            <div class="w-12 h-12 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <img src="assets/icons/delete.png" class="w-6 h-6" style="filter: invert(13%) sepia(80%) saturate(5000%) hue-rotate(345deg) brightness(90%) contrast(100%);" alt="Delete">
            </div>
            <h3 class="text-lg font-bold text-navy mb-2" id="deleteTitle">Delete Tag?</h3>
            <p class="text-sm text-on-surface-variant" id="deleteBody">
                This tag is used in 0 ordinance(s). Deleting it will remove it from all ordinances.
            </p>
        </div>
        <div class="px-6 py-4 bg-surface-container flex justify-center gap-3">
            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-bold text-on-surface-variant hover:text-navy">Cancel</button>
            <form action="tags.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteTagId">
                <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded text-sm font-bold hover:bg-red-700 transition-all shadow-md">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
    function openTagModal(tag = null) {
        const modal = document.getElementById('tagModal');
        const title = document.getElementById('modalTitle');
        const idInput = document.getElementById('modalTagId');
        const nameInput = document.getElementById('modalTagName');
        const descInput = document.getElementById('modalTagDesc');
        
        if (tag) {
            title.innerText = 'Edit Tag';
            idInput.value = tag.id;
            nameInput.value = tag.name;
            descInput.value = tag.description || '';
            // Select radio button
            const radio = modal.querySelector(`input[value="${tag.color_theme}"]`);
            if (radio) radio.checked = true;
        } else {
            title.innerText = 'New Tag';
            idInput.value = '';
            nameInput.value = '';
            descInput.value = '';
            modal.querySelector('input[name="color_theme"][value="tc-blue"]').checked = true;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeTagModal() {
        const modal = document.getElementById('tagModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function confirmDelete(id, name, count) {
        const modal = document.getElementById('deleteModal');
        document.getElementById('deleteTitle').innerText = `Delete '${name}'?`;
        document.getElementById('deleteBody').innerText = `This tag is used in ${count} ordinance(s). Deleting it will remove it from all ordinances.`;
        document.getElementById('deleteTagId').value = id;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function filterTags() {
        const query = document.getElementById('tagSearch').value.toLowerCase();
        const rows = document.querySelectorAll('.tag-row');
        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            row.style.display = name.includes(query) ? 'flex' : 'none';
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
