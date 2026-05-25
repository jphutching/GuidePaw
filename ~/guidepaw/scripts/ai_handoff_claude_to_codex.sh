#!/bin/bash
echo "=== Claude to Codex Handoff (No-Think Mode) ==="
echo "Paste Claude's full response below, then press Ctrl+D on a new line:"

cat > /tmp/claude_input.txt

cat > /tmp/codex_ready_prompt.txt << 'EOP'
REFERENCE: docs/HANDOVER_2026-05-22.md

Claude provided the following instructions and code:

"""
$(cat /tmp/claude_input.txt)
"""

Continue this task in Codex. 
Follow all HANDOVER rules strictly.
Small steps only. 
Output full files with exact paths.
Be concise.
EOP

echo ""
echo "✅ Ready! Prompt saved to: /tmp/codex_ready_prompt.txt"
echo ""
echo "Copy everything below and paste into ChatGPT/Codex:"
echo "-----------------------------------------------------"
cat /tmp/codex_ready_prompt.txt
echo "-----------------------------------------------------"
