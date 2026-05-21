<?php
declare(strict_types=1);

function gpBetaQaChecklistExtraItems(): array
{
    return [
        [
            'id' => 'qa_checklist_persistence',
            'title' => 'Beta QA Checklist Persistence',
            'description' => 'Validate that the checklist itself saves progress and notes reliably across sessions and devices.',
            'items' => [
                ['id' => 'checklist_page_loads', 'text' => 'Beta QA Checklist page loads from Menu → Support.', 'expected' => 'beta_qa_checklist.php opens without application error and shows progress controls.'],
                ['id' => 'checklist_database_save', 'text' => 'Check several checklist items and confirm account save status.', 'expected' => 'Save status changes to Saved to GuidePaw account.'],
                ['id' => 'checklist_notes_save', 'text' => 'Add testing notes and refresh the page.', 'expected' => 'Notes reload from the GuidePaw account save.'],
                ['id' => 'checklist_refresh_persists', 'text' => 'Refresh the checklist after checking items.', 'expected' => 'Checked items remain checked after refresh.'],
                ['id' => 'checklist_cross_device', 'text' => 'Open the checklist from another browser or device while logged into the same account.', 'expected' => 'Saved progress and notes follow the GuidePaw account.'],
                ['id' => 'checklist_role_filtering', 'text' => 'Open checklist as admin and as regular user.', 'expected' => 'Admin sees admin/beta/system sections; regular user sees regular feature checks only.'],
                ['id' => 'checklist_fallback_storage', 'text' => 'Simulate network/save endpoint failure if possible.', 'expected' => 'Checklist falls back to browser storage and shows a clear save warning instead of losing progress.'],
                ['id' => 'checklist_reset', 'text' => 'Use Reset checks only after confirming it is safe.', 'expected' => 'Saved visible checks clear for the account/browser and progress returns to zero.'],
                ['id' => 'checklist_print', 'text' => 'Use Print button for a paper/manual copy.', 'expected' => 'Printable checklist opens without bottom nav/menu clutter.'],
            ],
        ],
        [
            'id' => 'local_qa_crawler',
            'title' => 'Local QA Crawler',
            'description' => 'Use the laptop/local app to automate repeat smoke checks before manual beta testing.',
            'required_role' => 'admin',
            'items' => [
                ['id' => 'crawler_script_exists', 'text' => 'Confirm scripts/local_qa_crawler.php and scripts/run_local_qa_crawler.sh exist.', 'expected' => 'Both scripts are present after pulling latest GitHub changes.'],
                ['id' => 'crawler_admin_login', 'text' => 'Run crawler with admin credentials.', 'expected' => 'Crawler logs in as admin and reports PASS crawler_admin_login.'],
                ['id' => 'crawler_key_pages', 'text' => 'Crawler checks dashboard, notifications, QA checklist, admin users, dog access, audit, handler profile, and feedback.', 'expected' => 'No checked page reports fatal error, application error, or HTTP failure.'],
                ['id' => 'crawler_admin_ui_protection', 'text' => 'Crawler checks admin user management for built-in admin protection.', 'expected' => 'Protected badge/message is present for username admin.'],
                ['id' => 'crawler_regular_role', 'text' => 'Run crawler with a regular test account too.', 'expected' => 'Regular user is blocked from admin users page and does not see admin-only QA sections.'],
            ],
        ],
        [
            'id' => 'user_role_permissions',
            'title' => 'User Role Permissions',
            'description' => 'Validate Admin, Moderator, and User permission behavior.',
            'required_role' => 'admin',
            'items' => [
                ['id' => 'role_migration', 'text' => 'Confirm user_role migration applied.', 'expected' => 'users.user_role exists and supports master admin, basic admin, moderator, pro trainer, and user. Existing admin rows map correctly.'],
                ['id' => 'role_admin_management', 'text' => 'Open Admin → User Management and change a test account role.', 'expected' => 'Admin can change another account to user, pro trainer, moderator, basic admin, or master admin after confirmation.'],
                ['id' => 'role_builtin_admin_protected', 'text' => 'Attempt to downgrade or deactivate username admin.', 'expected' => 'The built-in admin account is blocked by the UI and protected by database trigger.'],
                ['id' => 'role_self_protection', 'text' => 'Attempt to change the currently logged-in admin account from User Management.', 'expected' => 'Current admin account cannot be changed from that page.'],
                ['id' => 'role_admin_access', 'text' => 'Log in as admin and open admin-only pages.', 'expected' => 'Master admin and basic admin can access admin dashboard, user management, beta/admin QA checks, and system tools.'],
                ['id' => 'role_user_blocked', 'text' => 'Log in as regular user and attempt admin-only pages.', 'expected' => 'Regular user is blocked from admin-only pages and does not see admin-only QA sections.'],
                ['id' => 'role_moderator_behavior', 'text' => 'Log in as moderator and review visible tools.', 'expected' => 'Moderator and pro trainer can access permitted support/review tools when enabled, but not full admin/system controls.'],
                ['id' => 'role_legacy_is_admin', 'text' => 'Confirm old is_admin compatibility still works.', 'expected' => 'Accounts with is_admin=1 are treated as basic admin or master admin based on the built-in protected account rules.'],
            ],
        ],
        [
            'id' => 'notification_center',
            'title' => 'In-App Notification Center',
            'description' => 'Validate user-facing notifications independent of Telegram, email, SMS, or browser push.',
            'items' => [
                ['id' => 'notification_page_loads', 'text' => 'Open Notification Center from bottom nav and Menu.', 'expected' => 'notifications.php opens without application error and stays inside the mobile viewport.'],
                ['id' => 'notification_empty_state', 'text' => 'Open Notification Center with no alerts.', 'expected' => 'Clear empty state appears.'],
                ['id' => 'notification_dashboard_summary', 'text' => 'Create an unread notification and return to Dashboard.', 'expected' => 'Dashboard shows unread notification summary and Needs Attention count includes it.'],
                ['id' => 'notification_preferences', 'text' => 'Hide and show notification categories in Notification Center.', 'expected' => 'Category preferences save and persist on reload.'],
                ['id' => 'notification_bulk_delete', 'text' => 'Select notifications and bulk delete them.', 'expected' => 'Selected notifications are removed from the inbox.'],
                ['id' => 'notification_nav_badge', 'text' => 'Check the Alerts badge in the bottom nav.', 'expected' => 'Unread count badge stays visible on the mobile navigation.'],
                ['id' => 'notification_dog_access', 'text' => 'Grant co-op/shared dog access to another account.', 'expected' => 'Receiving handler gets an in-app notification.'],
                ['id' => 'notification_transfer_request', 'text' => 'Send transfer request to another account.', 'expected' => 'Receiving handler gets high-priority transfer notification.'],
                ['id' => 'notification_transfer_result', 'text' => 'Accept or decline transfer.', 'expected' => 'Original owner gets transfer result notification.'],
                ['id' => 'notification_found_dog', 'text' => 'Submit public found-dog report.', 'expected' => 'Owner/handler gets high-priority found-dog notification.'],
                ['id' => 'notification_open_action', 'text' => 'Tap Open on a notification.', 'expected' => 'Notification is marked read and user is routed to the related page.'],
                ['id' => 'notification_mark_all_read', 'text' => 'Use Mark all read.', 'expected' => 'Unread count returns to zero and dashboard summary disappears.'],
            ],
        ],
        [
            'id' => 'admin_notifications',
            'title' => 'Admin Notifications',
            'description' => 'Validate admin-facing email and Telegram alerts for beta and operational events.',
            'required_role' => 'admin',
            'items' => [
                ['id' => 'admin_transfer_request_alert', 'text' => 'Send a dog ownership transfer request.', 'expected' => 'Admin notification email and/or Telegram alert includes the transfer request summary.'],
            ],
        ],
        [
            'id' => 'mobile_viewport_safety',
            'title' => 'Mobile Viewport Safety',
            'description' => 'Check that GuidePaw pages, cards, menus, text, tables, and buttons fit inside the viewable mobile screen.',
            'items' => [
                ['id' => 'viewport_dashboard', 'text' => 'Review Dashboard on a narrow phone screen.', 'expected' => 'No horizontal scrolling; active dog name, buttons, and cards wrap inside the viewport.'],
                ['id' => 'viewport_menu', 'text' => 'Open the bottom Menu on a narrow phone screen.', 'expected' => 'Menu panel fits screen width, scrolls vertically, and no menu items spill sideways.'],
                ['id' => 'viewport_forms', 'text' => 'Review Handler Profile, Dog Profile, Dog Access, and Feedback forms.', 'expected' => 'Inputs, labels, buttons, and notes wrap inside the viewable area.'],
                ['id' => 'viewport_tables', 'text' => 'Review pages with tables such as Dog Access handlers and admin reports.', 'expected' => 'Tables are horizontally scrollable inside their container, not the whole page.'],
                ['id' => 'viewport_long_text', 'text' => 'Check long dog names, email addresses, links, notes, and error reports.', 'expected' => 'Long text breaks/wraps instead of pushing content off-screen.'],
                ['id' => 'viewport_bottom_nav', 'text' => 'Review bottom navigation on small screens.', 'expected' => 'Icons/labels fit inside nav slots and do not overlap.'],
                ['id' => 'viewport_print', 'text' => 'Print QA Checklist.', 'expected' => 'Print layout excludes nav/menu clutter.'],
            ],
        ],
        [
            'id' => 'sms_notifications',
            'title' => 'SMS Notifications',
            'description' => 'Validate opt-in SMS text alerts for handlers while keeping Telegram admin-only.',
            'required_role' => 'admin',
            'items' => [
                ['id' => 'sms_render_env', 'text' => 'Confirm Render SMS/Twilio env vars are configured.', 'expected' => 'SMS_PROVIDER, SMS_NOTIFY_ENABLED, TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER, and ADMIN_NOTIFY_SMS_PHONE are present in Render; secrets are not committed.'],
                ['id' => 'sms_admin_test', 'text' => 'Run Admin Notification Test → Send SMS Test.', 'expected' => 'Admin test phone receives a GuidePaw SMS test message.'],
                ['id' => 'sms_profile_opt_in', 'text' => 'Enable SMS on Handler Profile and save a mobile phone.', 'expected' => 'SMS opt-in and normalized SMS phone save successfully.'],
                ['id' => 'sms_profile_opt_out', 'text' => 'Disable SMS on Handler Profile.', 'expected' => 'User stops receiving handler-facing SMS alerts while email still works.'],
                ['id' => 'sms_dog_access_grant', 'text' => 'Grant co-op/shared dog access to an opted-in handler.', 'expected' => 'Handler receives SMS plus email if email is enabled.'],
                ['id' => 'sms_transfer_request', 'text' => 'Send dog ownership transfer request to an opted-in handler.', 'expected' => 'Receiving handler receives SMS asking them to open GuidePaw to accept/decline.'],
                ['id' => 'sms_transfer_result', 'text' => 'Accept or decline a transfer request.', 'expected' => 'Original owner receives SMS result if opted in.'],
                ['id' => 'sms_found_dog', 'text' => 'Submit a public found-dog report for an opted-in owner.', 'expected' => 'Owner receives urgent found-dog SMS with location/map/finder info when available.'],
                ['id' => 'sms_failure_safe', 'text' => 'Confirm failed SMS does not block the underlying action.', 'expected' => 'Dog access, transfer, and found-dog actions still complete and SMS failure is logged.'],
                ['id' => 'telegram_admin_only', 'text' => 'Confirm Telegram remains admin-only.', 'expected' => 'Telegram alerts are used for admin/beta/found-dog monitoring, not required for regular handlers.'],
            ],
        ],
    ];
}
