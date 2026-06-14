# Changes Since Project Added

This concise changelog summarizes updates made to the codebase after the project directory was added. It focuses on the new resolutions workflow, migration, UI adjustments, and supporting scripts.

- **New tables**: Added `resolutions` and `resolution_tags` to store resolutions separately (code falls back to legacy RES-* rows if the table is missing).
- **Submit page**: Added [submit_resolution.php](submit_resolution.php) — resolution create/update and activity logging.
- **Review workflow**: Updated [pending.php](pending.php) — supports resolution cards (`data-type="resolution"`), sends `type` in AJAX, groups Proceed/Approve/Reject controls, and improves event delegation.
- **Status endpoint**: Updated [actions/update_status.php](actions/update_status.php) — accepts `type`, updates the correct table, and logs `target_type`.
- **Migration**: Added [actions/migrate_resolutions.php](actions/migrate_resolutions.php) — migrates legacy RES-* ordinances to the `resolutions` table, including tags and activity_log records.
- **Bin / delete / recover**: Updated [actions/move_to_bin.php](actions/move_to_bin.php), [actions/delete.php](actions/delete.php), and [actions/recover.php](actions/recover.php) — handle resolutions and record `original_table` for restores.
- **Public DB view**: Updated [database.php](database.php) — supports `?type=resolution`, detects the `resolutions` table, and falls back to legacy RES-* rows when needed.
- **Sidebar counts & dashboard**: Updated [actions/get_sidebar_counts.php](actions/get_sidebar_counts.php) and [index.php](index.php) — counts only published records (status IN 'approved','active'); resolutions counted separately.
- **View page**: Updated [view.php](view.php) — supports `?type=resolution&id=` and aliases fields for shared templates.
- **Client-side UX**: Replaced user-facing "Network error" toasts with console logging, fixed confirm modal handling, and ensured consistent action rendering in `pending.php`.
- **Tests**: Added [actions/test_e2e_resolution.php](actions/test_e2e_resolution.php) — DB-level E2E script (created and executed during verification).
- **Notes / Next steps**: Manual browser E2E is recommended: submit via submit_resolution.php, advance readings in pending.php, and verify results in database.php?type=resolution and activity_log.
