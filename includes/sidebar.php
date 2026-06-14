<!-- SideNavBar -->
<aside
    class="bg-white text-on-surface h-screen w-64 border-r border-outline-variant shadow-sm flex flex-col fixed left-0 top-0 z-40">
    <!-- Header/Logo Section -->
    <div class="pt-8 pb-4 flex flex-col items-center text-center">
        <div class="mb-4">
            <img src="assets/icons/UpdateLogo-Clean.png" class="w-32 h-32 object-contain" alt="LGU Mambajao Seal">
        </div>
        <div class="space-y-0.5">
            <h1 class="text-xl font-bold tracking-tight text-navy">SB e-Legis</h1>
            <h2 class="text-lg font-bold tracking-tight text-navy">LGU MAMBAJAO</h2>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-2 py-4 space-y-1">
        <?php
        $nav_items = [
            ['id' => 'dashboard', 'label' => 'Overview', 'icon' => 'calendar.png', 'url' => 'index.php'],
            ['id' => 'database', 'label' => 'All Ordinances', 'icon' => 'mace.png', 'url' => 'database.php', 'count_id' => 'count-total'],
            ['id' => 'resolutions', 'label' => 'All Resolutions', 'icon' => 'mace.png', 'url' => 'resolutions.php', 'count_id' => 'count-resolutions'],
            ['id' => 'activity', 'label' => 'Recent Activity', 'icon' => 'clock.png', 'url' => 'activity.php'],
            ['id' => 'pending', 'label' => 'For Reading', 'icon' => 'clipboard.png', 'url' => 'pending.php', 'count_id' => 'count-pending'],
            ['id' => 'tags', 'label' => 'Manage Tags', 'icon' => 'price-tag.png', 'url' => 'tags.php'],
            ['id' => 'archive', 'label' => 'Archive', 'icon' => 'archive.png', 'url' => 'archive.php', 'count_id' => 'count-archives'],
            ['id' => 'bin', 'label' => 'Bin', 'icon' => 'delete.png', 'url' => 'bin.php', 'count_id' => 'count-bin'],
        ];

        foreach ($nav_items as $item):
            $is_active = ($current_page == $item['id']);
            $active_classes = $is_active ? 'bg-red-50 text-red-900 border-l-4 border-red-800 translate-x-1 shadow-sm' : 'text-on-surface-variant hover:bg-red-50/40';
            ?>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-sm transition-all duration-200 <?php echo $active_classes; ?>"
                href="<?php echo $item['url']; ?>">
                <img src="assets/icons/<?php echo $item['icon']; ?>"
                    class="w-5 h-5 <?php echo $is_active ? 'icon-red' : 'opacity-70'; ?>"
                    alt="<?php echo $item['label']; ?>">
                <span class="text-sm font-medium flex-1"><?php echo $item['label']; ?></span>
                <?php if (isset($item['count_id'])): ?>
                    <span id="<?php echo $item['count_id']; ?>"
                        class="text-[10px] <?php echo $item['id'] == 'pending' ? 'bg-error-container text-error' : 'bg-surface-container-highest text-on-surface-variant'; ?> px-1.5 py-0.5 rounded-full opacity-0 transition-all duration-300">0</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Administrative Action -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="px-4 mb-6 space-y-2">
            <a href="submit.php"
                class="w-full bg-primary text-white py-2.5 px-4 rounded font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-all shadow-md">
                <span class="text-lg">+</span>
                New Ordinance
            </a>

            <a href="submit_resolution.php"
                class="w-full bg-primary text-white py-2.5 px-4 rounded font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-all shadow-md">
                <span class="text-lg">+</span>
                New Resolution
            </a>
        </div>
    <?php endif; ?>
</aside>

<!-- Spacer for fixed sidebar -->
<div class="w-64 flex-shrink-0"></div>

<style>
    .icon-red {
        filter: invert(21%) sepia(100%) saturate(2335%) hue-rotate(346deg) brightness(92%) contrast(97%);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        fetchSidebarCounts();
        // Refresh counts every 30 seconds for real-time feel
        setInterval(fetchSidebarCounts, 30000);
    });

    function fetchSidebarCounts() {
        fetch('actions/get_sidebar_counts.php')
            .then(response => response.json())
            .then(data => {
                    if (data.status === 'success') {
                    updateCount('count-total', data.counts.total);
                    updateCount('count-resolutions', data.counts.resolutions);
                    updateCount('count-pending', data.counts.pending);
                    updateCount('count-archives', data.counts.archives);
                    updateCount('count-bin', data.counts.bin || 0);
                }
            })
            .catch(err => console.error('Error fetching counts:', err));
    }

    function updateCount(id, count) {
        const el = document.getElementById(id);
        if (!el) return;

        if (count > 0) {
            el.innerText = count;
            el.classList.remove('opacity-0');
        } else {
            el.classList.add('opacity-0');
        }
    }
</script>