<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Bin';
$current_page = 'bin';

// Ensure recycle_bin table exists to avoid fatal errors when opening the Bin
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS recycle_bin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_table VARCHAR(100),
        original_id INT,
        data LONGTEXT,
        deleted_by INT,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT b.*, u.full_name as deleted_by_name FROM recycle_bin b LEFT JOIN users u ON b.deleted_by = u.id ORDER BY b.deleted_at DESC");
    $stmt->execute();
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    // If table creation or select fails, show empty bin instead of throwing
    $items = [];
}

include 'includes/header.php';
?>

<div class="space-y-6 max-w-[1200px] mx-auto pb-20">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[28px] font-bold text-navy">Bin</h1>
            <p class="text-sm text-on-surface-variant mt-1">Recover or permanently delete moved records.</p>
        </div>
        <div>
            <a href="database.php" class="px-4 py-2 bg-surface-container-lowest border border-outline-variant rounded">Back to Database</a>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-outline-variant shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container text-[12px] font-bold uppercase tracking-widest text-on-surface-variant">
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Number</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Deleted At</th>
                    <th class="px-4 py-3">Deleted By</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="p-8 text-center text-on-surface-variant">Bin is empty.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $i => $it):
                        $data = json_decode($it['data'], true);
                        $row = $data['row'] ?? [];
                    ?>
                        <tr class="border-t">
                            <td class="px-4 py-3"><?php echo $i+1; ?></td>
                            <td class="px-4 py-3 font-semibold text-xs text-on-surface-variant">
                                <?php echo ($it['original_table'] === 'resolutions') ? 'Resolution' : 'Ordinance'; ?>
                            </td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($row['ordinance_number'] ?? ($row['resolution_number'] ?? '')); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($it['deleted_at']); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($it['deleted_by_name'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                        <button onclick='previewBin(<?php echo json_encode($row); ?>, <?php echo $it['id']; ?>)'
                                            class="px-3 py-1 bg-gray-200 text-gray-800 rounded text-xs font-bold">Preview</button>
                                    <form action="actions/recover.php" method="POST" style="display:inline">
                                        <input type="hidden" name="bin_id" value="<?php echo $it['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded text-xs font-bold">Restore</button>
                                    </form>
                                    <form action="actions/delete_permanent.php" method="POST" style="display:inline" onsubmit="return confirm('Permanently delete this item? This cannot be undone.');">
                                        <input type="hidden" name="bin_id" value="<?php echo $it['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-xs font-bold">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bin Preview Modal -->
<div id="binPreviewModal" class="fixed inset-0 z-[500] hidden items-center justify-center px-4" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(3px);">
    <div class="bg-white shadow-2xl w-full max-w-[1000px] p-4 relative rounded">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <img src="assets/icons/doc.png" class="w-6 h-6" alt="Preview">
                <div>
                    <div id="binPreviewTitle" class="font-bold text-lg text-navy"></div>
                    <div id="binPreviewNumber" class="text-xs text-on-surface-variant"></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="binPreviewDownload" href="#" download class="px-3 py-1 bg-gray-100 rounded text-sm">Download</a>
                <button onclick="closeBinPreview()" class="px-3 py-1 bg-gray-200 rounded">Close</button>
            </div>
        </div>
        <div id="binPreviewContent" style="min-height:400px;">
            <!-- content injected here -->
        </div>
    </div>
</div>

<script>
function previewBin(row, binId) {
    if (!row) return;
    var modal = document.getElementById('binPreviewModal');
    document.getElementById('binPreviewTitle').textContent = row.title || 'Document Preview';
    document.getElementById('binPreviewNumber').textContent = row.ordinance_number || row.resolution_number || '';
    var content = document.getElementById('binPreviewContent');
    content.innerHTML = '';

    // Use hard_copy_path if available and looks like a file
    var path = row.hard_copy_path || '';
    var downloadBtn = document.getElementById('binPreviewDownload');

    if (path && /\.(pdf|docx?|xlsx?|pptx?|png|jpe?g|gif)$/i.test(path.split('?')[0])) {
        var iframe = document.createElement('iframe');
        iframe.src = path;
        iframe.style.width = '100%';
        iframe.style.height = '640px';
        iframe.className = 'border-none';
        content.appendChild(iframe);
        // Direct link to the file
        downloadBtn.href = path;
        downloadBtn.download = path.split('/').pop().split('?')[0];
    } else if (path) {
        // Path exists but isn't a direct file (maybe routed via PHP). Use download proxy to serve original attachment from bin entry.
        var iframe = document.createElement('iframe');
        iframe.src = path;
        iframe.style.width = '100%';
        iframe.style.height = '640px';
        iframe.className = 'border-none';
        content.appendChild(iframe);
        downloadBtn.href = 'actions/download_bin_file.php?bin_id=' + encodeURIComponent(binId);
        downloadBtn.removeAttribute('download');
    } else if (row.soft_copy) {
        var div = document.createElement('div');
        div.className = 'p-4 bg-white text-sm font-doc';
        div.innerHTML = row.soft_copy.replace(/\n/g, '<br>');
        content.appendChild(div);
        downloadBtn.href = '#';
        downloadBtn.removeAttribute('download');
    } else {
        content.innerHTML = '<div class="p-8 text-center text-on-surface-variant">No preview available for this item.</div>';
        downloadBtn.href = '#';
        downloadBtn.removeAttribute('download');
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeBinPreview() {
    var modal = document.getElementById('binPreviewModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('binPreviewContent').innerHTML = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeBinPreview(); });
</script>

<?php include 'includes/footer.php'; ?>
