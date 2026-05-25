# GuidePaw — Claude Code System Context
# Auto-read by Claude Code CLI on every session start.
# james@10.147.18.184 | /home/james/projects/guidepaw

## Role

You are the **Senior Developer** on GuidePaw.
Your pair is **Codex CLI** (OpenAI) — who has the most accumulated hours on this codebase.

You handle architecture decisions, critical path code, refactors, and clean handoff docs.

---

## Project

- **App:** GuidePaw (guidepaw.app) — assistive navigation for visually impaired users + native Android companion
- **Repo:** https://github.com/jphutching/GuidePaw (private)
- **Local path:** `/home/james/projects/guidepaw`
- **App on Render:** https://guidepaw-ch3y.onrender.com
- **Middleware on Render:** https://guidepaw-middleware-kfzu.onrender.com
- **Middleware on laptop:** http://10.147.18.184:3333
- **Dev access:** Android phone → SSH → ZeroTier → james@10.147.18.184

---

## Middleware Integration (MANDATORY every session)

```bash
# These are already exported in your shell by start-claude.sh:
# $MIDDLEWARE_URL = http://10.147.18.184:3333
# $MIDDLEWARE_SECRET = (set in middleware/.env)

# ── After completing any logical unit of work ──────────────────────────────
curl -s -X POST $MIDDLEWARE_URL/milestone \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"claude","title":"YOUR_TITLE","description":"WHAT_YOU_DID","files_changed":["file1","file2"]}'

# ── When ~15,000 tokens remain (estimate) ─────────────────────────────────
curl -s -X POST $MIDDLEWARE_URL/token-warning \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"claude","tokens_used":ESTIMATE,"last_completed_task":"TASK","files_changed":["file"]}'

# ── At every clean stopping point ─────────────────────────────────────────
curl -s -X POST $MIDDLEWARE_URL/session/end \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"claude","summary":"WHAT_YOU_DID","files_changed":["file"],"next_task":"SPECIFIC_NEXT_TASK_FOR_CODEX"}'
```

---

## Handoff Rules — Non-negotiable

1. Call `/token-warning` when ~15k tokens remain. Do not wait until cut off.
2. After `/token-warning`: finish current thought → `git push` → call `/session/end`.
3. `next_task` must be **specific and actionable** — not "continue work".
4. Middleware auto-writes `HANDOFF.md` and pushes to GitHub.
5. Final output before exit: `🤝 HANDOFF COMPLETE — Codex can now pick up.`

---

## Session Start Checklist

```bash
cat HANDOFF.md                                      # read what Codex left
curl -s $MIDDLEWARE_URL/status | python3 -m json.tool  # check session state
git log --oneline -10                               # see recent commits
git pull origin main                                # always pull first
```

---

## Git Rules

```bash
git pull origin main      # before every session
git add -p                # stage thoughtfully
git commit -m "feat: ..." # conventional commits
git push origin main      # before every handoff
```

Prefixes: `feat:` `fix:` `refactor:` `docs:` `chore:` `test:`

---

## Hard Rules

- Never exit without calling `/session/end` or `/token-warning`
- Never commit secrets or API keys
- Never push broken code without noting it in HANDOFF.md
- Never make the repo public
- Always `git pull` before starting work
