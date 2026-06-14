<!-- SideNavBar -->
<aside
    class="bg-white text-on-surface h-screen w-64 border-r border-outline-variant shadow-sm flex flex-col fixed left-0 top-0 z-40">
    <!-- Header/Logo Section -->
    <div class="pt-8 pb-4 flex flex-col items-center text-center">
        <div class="mb-4">
            <img src="../assets/icons/UpdateLogo-Clean.png" class="w-32 h-32 object-contain" alt="LGU Mambajao Seal">
        </div>
        <div class="space-y-0.5">
            <h1 class="text-xl font-bold tracking-tight text-navy">LexTrack</h1>
            <h2 class="text-lg font-bold tracking-tight text-navy">LGU Mambajao</h2>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-2 py-4 space-y-1">
        <?php
        $nav_items = [
            ['id' => 'database', 'label' => 'All Ordinances', 'icon' => 'mace.png', 'url' => 'index.php', 'count_id' => 'count-total'],
            ['id' => 'archive', 'label' => 'Archive', 'icon' => 'archive.png', 'url' => 'archive.php', 'count_id' => 'count-archives'],
        ];

        foreach ($nav_items as $item):
            $is_active = ($current_page == $item['id']);
            $active_classes = $is_active ? 'bg-red-50 text-red-900 border-l-4 border-red-800 translate-x-1 shadow-sm' : 'text-on-surface-variant hover:bg-red-50/40';
            ?>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-sm transition-all duration-200 <?php echo $active_classes; ?>"
                href="<?php echo $item['url']; ?>">
                <img src="../assets/icons/<?php echo $item['icon']; ?>"
                    class="w-5 h-5 <?php echo $is_active ? 'icon-red' : 'opacity-70'; ?>"
                    alt="<?php echo $item['label']; ?>">
                <span class="text-sm font-medium flex-1"><?php echo $item['label']; ?></span>
                <?php if (isset($item['count_id'])): ?>
                    <span id="<?php echo $item['count_id']; ?>"
                        class="text-[10px] bg-surface-container-highest text-on-surface-variant px-1.5 py-0.5 rounded-full opacity-0 transition-all duration-300">0</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom Links (Small) -->
    <div class="px-2 py-4 border-t border-outline-variant space-y-1">
        <a class="flex items-center gap-3 px-4 py-2 rounded text-on-surface-variant hover:bg-slate-50 transition-all"
            href="#">
            <img src="../assets/icons/checked.png" class="w-4 h-4 opacity-70" alt="Help">
            <span class="text-xs font-medium">Help Center</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-2 rounded text-on-surface-variant hover:bg-slate-50 transition-all"
            href="#">
            <img src="../assets/icons/user.png" class="w-4 h-4 opacity-70" alt="Admin">
            <span class="text-xs font-medium">Contact Admin</span>
        </a>
    </div>
</aside>

<div class="w-64 flex-shrink-0"></div>

<style>
    .icon-red {
        filter: invert(21%) sepia(100%) saturate(2335%) hue-rotate(346deg) brightness(92%) contrast(97%);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        fetchSidebarCounts();
        setInterval(fetchSidebarCounts, 30000);
    });

    function fetchSidebarCounts() {
        fetch('../actions/get_sidebar_counts.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateCount('count-total', data.counts.total);
                    updateCount('count-archives', data.counts.archives);
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