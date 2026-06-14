<?php
$page_title = "Activity Feed";
$current_page = "activity";
require_once 'includes/header.php';

// Fetch activities with user names
$stmt = $pdo->query("
    SELECT a.*, u.full_name as user_name, u.role as user_role
    FROM activity_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.timestamp DESC
    LIMIT 100
");
$activities = $stmt->fetchAll();

function getActionColor($action) {
    switch ($action) {
        case 'create': return 'bg-green-50 text-green-600 border-green-100';
        case 'update': return 'bg-blue-50 text-blue-600 border-blue-100';
        case 'delete': return 'bg-red-50 text-red-600 border-red-100';
        case 'login': return 'bg-navy bg-opacity-5 text-navy border-navy border-opacity-10';
        case 'logout': return 'bg-slate-50 text-slate-600 border-slate-100';
        default: return 'bg-slate-50 text-slate-600 border-slate-100';
    }
}

function getActionIcon($action) {
    switch ($action) {
        case 'create': return 'add.png';
        case 'update': return 'edit.png';
        case 'delete': return 'delete.png';
        case 'login': return 'user.png';
        case 'logout': return 'checked.png'; // Using checked as a generic "exit" icon
        default: return 'clock.png';
    }
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-[28px] font-bold text-navy leading-tight">Activity Feed</h1>
            <p class="text-sm text-on-surface-variant mt-1">System-wide audit log of all administrative actions.</p>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="bg-white rounded-lg border border-outline-variant overflow-hidden">
        <div class="divide-y divide-outline-variant">
            <?php if (empty($activities)): ?>
                <div class="p-10 text-center text-outline italic text-sm">
                    No activity recorded yet.
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="p-4 flex gap-4 hover:bg-surface-container-low transition-all">
                        <!-- Action Icon -->
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 border <?php echo getActionColor($activity['action']); ?>">
                            <img src="assets/icons/<?php echo getActionIcon($activity['action']); ?>" 
                                 class="w-4 h-4" 
                                 style="<?php 
                                    if ($activity['action'] === 'delete') echo 'filter: invert(13%) sepia(80%) saturate(5000%) hue-rotate(345deg) brightness(90%) contrast(100%);';
                                    if ($activity['action'] === 'create') echo 'filter: invert(39%) sepia(85%) saturate(464%) hue-rotate(93deg) brightness(94%) contrast(92%);';
                                    if ($activity['action'] === 'update') echo 'filter: invert(34%) sepia(93%) saturate(1450%) hue-rotate(196deg) brightness(94%) contrast(101%);';
                                    if ($activity['action'] === 'login') echo 'filter: invert(11%) sepia(21%) saturate(2878%) hue-rotate(220deg) brightness(90%) contrast(95%);';
                                 ?>"
                                 alt="<?php echo $activity['action']; ?>">
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div class="text-sm">
                                    <span class="font-bold text-navy"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></span>
                                    <span class="text-on-surface-variant"><?php echo htmlspecialchars($activity['description']); ?></span>
                                </div>
                                <span class="text-[11px] text-outline font-medium whitespace-nowrap ml-4">
                                    <?php echo date('M d, Y h:i A', strtotime($activity['timestamp'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($activity['target_type']): ?>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-widest px-1.5 py-0.5 bg-surface-container rounded text-outline border border-outline-variant">
                                        <?php echo $activity['target_type']; ?>
                                    </span>
                                    <?php if ($activity['target_name']): ?>
                                        <span class="text-[11px] font-medium text-navy truncate">
                                            <?php echo htmlspecialchars($activity['target_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
