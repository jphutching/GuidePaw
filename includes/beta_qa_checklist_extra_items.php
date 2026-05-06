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
                [
                    'id' => 'checklist_page_loads',
                    'text' => 'Beta QA Checklist page loads from Menu → Support.',
                    'expected' => 'beta_qa_checklist.php opens without application error and shows progress controls.',
                ],
                [
                    'id' => 'checklist_database_save',
                    'text' => 'Check several checklist items and confirm account save status.',
                    'expected' => 'Save status changes to Saved to GuidePaw account.',
                ],
                [
                    'id' => 'checklist_notes_save',
                    'text' => 'Add testing notes and refresh the page.',
                    'expected' => 'Notes reload from the GuidePaw account save.',
                ],
                [
                    'id' => 'checklist_refresh_persists',
                    'text' => 'Refresh the checklist after checking items.',
                    'expected' => 'Checked items remain checked after refresh.',
                ],
                [
                    'id' => 'checklist_cross_device',
                    'text' => 'Open the checklist from another browser or device while logged into the same account.',
                    'expected' => 'Saved progress and notes follow the GuidePaw account.',
                ],
                [
                    'id' => 'checklist_fallback_storage',
                    'text' => 'Simulate network/save endpoint failure if possible.',
                    'expected' => 'Checklist falls back to browser storage and shows a clear save warning instead of losing progress.',
                ],
                [
                    'id' => 'checklist_reset',
                    'text' => 'Use Reset checks only after confirming it is safe.',
                    'expected' => 'Saved checks clear for the account/browser and progress returns to zero.',
                ],
                [
                    'id' => 'checklist_print',
                    'text' => 'Use Print button for a paper/manual copy.',
                    'expected' => 'Printable checklist opens without bottom nav/menu clutter.',
                ],
            ],
        ],
        [
            'id' => 'project_history_upkeep',
            'title' => 'Project History Upkeep',
            'description' => 'Keep the durable GuidePaw handoff file current so future work can resume without backtracking.',
            'items' => [
                [
                    'id' => 'history_file_exists',
                    'text' => 'Confirm GUIDEPAW_PROJECT_HISTORY.md exists in the repo.',
                    'expected' => 'The file is present on GitHub main and can be used as the durable handoff source.',
                ],
                [
                    'id' => 'history_read_before_work',
                    'text' => 'Read GUIDEPAW_PROJECT_HISTORY.md before starting a new coding session.',
                    'expected' => 'Current status, in-progress items, and next recommended work are understood before making changes.',
                ],
                [
                    'id' => 'history_updated_after_work',
                    'text' => 'Update GUIDEPAW_PROJECT_HISTORY.md after meaningful feature, bug, migration, or checklist work.',
                    'expected' => 'The file reflects what changed, what remains in progress, and the next recommended step.',
                ],
                [
                    'id' => 'history_no_secrets',
                    'text' => 'Check that no secrets were added to the project history file.',
                    'expected' => 'No passwords, API tokens, Telegram bot tokens, ZeptoMail tokens, or DB credentials are present.',
                ],
                [
                    'id' => 'history_matches_checklist',
                    'text' => 'Confirm major new work has matching Beta QA Checklist items.',
                    'expected' => 'Any new feature or workflow has a test item or section in the checklist.',
                ],
            ],
        ],
    ];
}
