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
                ['id' => 'checklist_fallback_storage', 'text' => 'Simulate network/save endpoint failure if possible.', 'expected' => 'Checklist falls back to browser storage and shows a clear save warning instead of losing progress.'],
                ['id' => 'checklist_reset', 'text' => 'Use Reset checks only after confirming it is safe.', 'expected' => 'Saved checks clear for the account/browser and progress returns to zero.'],
                ['id' => 'checklist_print', 'text' => 'Use Print button for a paper/manual copy.', 'expected' => 'Printable checklist opens without bottom nav/menu clutter.'],
            ],
        ],
        [
            'id' => 'sms_notifications',
            'title' => 'SMS Notifications',
            'description' => 'Validate opt-in SMS text alerts for handlers while keeping Telegram admin-only.',
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
        [
            'id' => 'project_history_upkeep',
            'title' => 'Project History Upkeep',
            'description' => 'Keep the durable GuidePaw handoff file current so future work can resume without backtracking.',
            'items' => [
                ['id' => 'history_file_exists', 'text' => 'Confirm GUIDEPAW_PROJECT_HISTORY.md exists in the repo.', 'expected' => 'The file is present on GitHub main and can be used as the durable handoff source.'],
                ['id' => 'history_read_before_work', 'text' => 'Read GUIDEPAW_PROJECT_HISTORY.md before starting a new coding session.', 'expected' => 'Current status, in-progress items, and next recommended work are understood before making changes.'],
                ['id' => 'history_updated_after_work', 'text' => 'Update GUIDEPAW_PROJECT_HISTORY.md after meaningful feature, bug, migration, or checklist work.', 'expected' => 'The file reflects what changed, what remains in progress, and the next recommended step.'],
                ['id' => 'history_no_secrets', 'text' => 'Check that no secrets were added to the project history file.', 'expected' => 'No passwords, API tokens, Telegram bot tokens, ZeptoMail tokens, Twilio tokens, or DB credentials are present.'],
                ['id' => 'history_matches_checklist', 'text' => 'Confirm major new work has matching Beta QA Checklist items.', 'expected' => 'Any new feature or workflow has a test item or section in the checklist.'],
            ],
        ],
    ];
}
