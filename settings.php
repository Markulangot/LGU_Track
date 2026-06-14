<?php
$page_title = "Settings";
$current_page = "settings";
require_once 'includes/header.php';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This is a placeholder for actual settings logic
    $msg = "Settings updated successfully";
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-[28px] font-bold text-navy leading-tight">Settings</h1>
            <p class="text-sm text-on-surface-variant mt-1">Manage your account preferences and system configuration.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Sidebar Navigation -->
        <div class="space-y-1">
            <button class="w-full text-left px-4 py-2 rounded bg-surface-container text-navy font-bold text-sm flex items-center gap-3">
                <img src="assets/icons/settings.png" class="w-4 h-4 opacity-50"> General
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/doc.png" class="w-4 h-4 opacity-50"> Legislative
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/user.png" class="w-4 h-4 opacity-50"> Users & Roles
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/clipboard.png" class="w-4 h-4 opacity-50"> Security
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/download (2).png" class="w-4 h-4 opacity-50"> Backup
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/archive.png" class="w-4 h-4 opacity-50"> Archive
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/notification.png" class="w-4 h-4 opacity-50"> Notifications
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/settings.png" class="w-4 h-4 opacity-50"> Appearance
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/clock.png" class="w-4 h-4 opacity-50"> Activity Logs
            </button>
            <button class="w-full text-left px-4 py-2 rounded text-on-surface-variant hover:bg-surface-container text-sm flex items-center gap-3">
                <img src="assets/icons/settings.png" class="w-4 h-4 opacity-50"> System Info
            </button>
        </div>

        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-lg border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low">
                    <h3 class="text-sm font-bold text-navy uppercase tracking-widest">Profile Information</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-6 pb-6 border-b border-outline-variant border-dashed">
                        <div
                            class="w-20 h-20 rounded-full bg-navy text-white flex items-center justify-center text-2xl font-bold">
                            <?php
                            $initials = "";
                            $names = explode(" ", $_SESSION['full_name']);
                            foreach ($names as $n)
                                $initials .= $n[0];
                            echo htmlspecialchars(substr($initials, 0, 2));
                            ?>
                        </div>
                        <div class="space-y-1">
                            <button
                                class="px-4 py-1.5 border border-outline-variant rounded text-xs font-bold text-navy hover:bg-surface-container">Change
                                Avatar</button>
                            <p class="text-[10px] text-outline">JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                    </div>

                    <form class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Full
                                    Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>"
                                    class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['username']); ?>"
                                    class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm bg-surface-container-low"
                                    readonly>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Email
                                Address</label>
                            <input type="email" value="admin@mambajao.gov.ph"
                                class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold uppercase tracking-wider text-outline mb-1.5">Department</label>
                            <input type="text" value="Legislative Division"
                                class="w-full px-3 py-2 border border-outline-variant rounded focus:outline-none focus:border-navy text-sm">
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button type="button"
                                class="bg-navy text-white px-6 py-2 rounded text-sm font-bold hover:opacity-90 transition-all shadow-md">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications Section -->
            <div class="bg-white rounded-lg border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low">
                    <h3 class="text-sm font-bold text-navy uppercase tracking-widest">Notification Preferences</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-on-surface">Email Notifications</h4>
                            <p class="text-xs text-on-surface-variant">Receive email alerts for new ordinance
                                submissions.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div
                                class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-navy">
                            </div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-outline-variant border-dashed">
                        <div>
                            <h4 class="text-sm font-bold text-on-surface">System Alerts</h4>
                            <p class="text-xs text-on-surface-variant">Show desktop notifications for urgent tasks.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer">
                            <div
                                class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-navy">
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>