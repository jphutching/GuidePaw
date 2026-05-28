/**
 * GuidePaw AI Middleware Server
 * james@10.147.18.184 — /home/james/projects/guidepaw
 *
 * Runs on:
 *   Laptop  → http://10.147.18.184:3333
 *   Render  → https://guidepaw-middleware-kfzu.onrender.com
 */

require("dotenv").config();
const express = require("express");
const path = require("path");
const fs = require("fs");
const Database = require("better-sqlite3");
const cron = require("node-cron");
const { syncToGit } = require("./git-sync");
const { buildHandoffDoc } = require("./handoff-template");

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3333;
const MIDDLEWARE_SECRET = process.env.MIDDLEWARE_SECRET;
const REPO_PATH = process.env.REPO_PATH || "/home/james/projects/guidepaw";
const STATE_FILE = path.join(REPO_PATH, "SESSION_STATE.json");
const HANDOFF_FILE = path.join(REPO_PATH, "HANDOFF.md");
const DEVLOG_FILE = path.join(REPO_PATH, "DEVLOG.md");
const SESSION_TIMEOUT_MINUTES = parseInt(process.env.SESSION_TIMEOUT_MINUTES || "45");

const DB_PATH = process.env.DB_PATH || "/home/james/guidepaw-middleware/sessions.db";
const db = new Database(DB_PATH);

db.exec(`
  CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    ai TEXT NOT NULL,
    event TEXT NOT NULL,
    payload TEXT,
    ts DATETIME DEFAULT CURRENT_TIMESTAMP
  );
  CREATE TABLE IF NOT EXISTS milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT,
    ai TEXT,
    title TEXT,
    description TEXT,
    files_changed TEXT,
    ts DATETIME DEFAULT CURRENT_TIMESTAMP
  );
`);

function auth(req, res, next) {
  if (!MIDDLEWARE_SECRET) return next();
  const token = (req.headers["authorization"] || "").replace("Bearer ", "");
  if (token !== MIDDLEWARE_SECRET) return res.status(401).json({ error: "Unauthorized" });
  next();
}

function readState() {
  try { return JSON.parse(fs.readFileSync(STATE_FILE, "utf8")); }
  catch { return defaultState(); }
}

function writeState(s) {
  s.updated_at = new Date().toISOString();
  fs.writeFileSync(STATE_FILE, JSON.stringify(s, null, 2));
}

function defaultState() {
  return {
    active_ai: null, session_id: null, session_started_at: null,
    last_milestone: null, last_handoff_at: null,
    current_task: "Check HANDOFF.md for next task",
    files_in_progress: [], branch: "main",
    updated_at: new Date().toISOString()
  };
}

function logEvent(sid, ai, event, payload = {}) {
  db.prepare("INSERT INTO sessions (session_id,ai,event,payload) VALUES (?,?,?,?)")
    .run(sid || "standalone", ai, event, JSON.stringify(payload));
}

// ── Routes ────────────────────────────────────────────────────────────────────

app.get("/health", (_, res) =>
  res.json({ status: "ok", ts: new Date().toISOString(), port: PORT }));

app.get("/status", auth, (_, res) => {
  const state = readState();
  const milestones = db.prepare("SELECT * FROM milestones ORDER BY ts DESC LIMIT 5").all();
  res.json({ state, recent_milestones: milestones });
});

app.get("/handoff", auth, (_, res) => {
  try { res.json({ content: fs.readFileSync(HANDOFF_FILE, "utf8") }); }
  catch { res.json({ content: "No handoff document yet." }); }
});

app.post("/session/start", auth, (req, res) => {
  const { ai, task, branch } = req.body;
  if (!ai || !["claude", "codex"].includes(ai))
    return res.status(400).json({ error: "ai must be 'claude' or 'codex'" });

  const state = readState();
  const session_id = `${ai}-${Date.now()}`;
  state.active_ai = ai;
  state.session_id = session_id;
  state.session_started_at = new Date().toISOString();
  state.current_task = task || state.current_task;
  state.branch = branch || state.branch;
  writeState(state);
  logEvent(session_id, ai, "session_start", { task });

  let handoff = "No previous handoff.";
  try { handoff = fs.readFileSync(HANDOFF_FILE, "utf8"); } catch {}

  console.log(`[START] ${ai.toUpperCase()} session ${session_id}`);
  res.json({ session_id, state, handoff });
});

app.post("/session/end", auth, (req, res) => {
  const { ai, session_id, summary, files_changed, next_task } = req.body;
  const state = readState();
  logEvent(session_id || state.session_id, ai, "session_end", { summary });

  const doc = buildHandoffDoc({
    from_ai: ai, to_ai: ai === "claude" ? "codex" : "claude",
    summary, files_changed: files_changed || state.files_in_progress,
    next_task, branch: state.branch,
    session_id: session_id || state.session_id
  });

  fs.writeFileSync(HANDOFF_FILE, doc);

  const devlogEntry = `\n## ${new Date().toISOString().slice(0,10)} | ${(ai||"").toUpperCase()} | Session end\n\n${summary || "No summary."}\n\n**Files:** ${(files_changed || state.files_in_progress || []).join(", ") || "see git log"}\n\n**Next:** ${next_task || "See HANDOFF.md"}\n\n---\n`;
  try { fs.appendFileSync(DEVLOG_FILE, devlogEntry); } catch {}

  state.active_ai = null;
  state.last_handoff_at = new Date().toISOString();
  state.files_in_progress = [];
  writeState(state);
  syncToGit(REPO_PATH, `handoff: ${ai} session end — ${summary || "clean stop"}`).catch(console.error);

  console.log(`[END] ${ai.toUpperCase()} session ended cleanly`);
  res.json({ ok: true, handoff_written: true });
});

app.post("/milestone", auth, (req, res) => {
  const { ai, session_id, title, description, files_changed, trigger_handoff } = req.body;
  const state = readState();

  db.prepare("INSERT INTO milestones (session_id,ai,title,description,files_changed) VALUES (?,?,?,?,?)")
    .run(session_id || state.session_id, ai, title, description, JSON.stringify(files_changed || []));

  state.last_milestone = title;
  state.files_in_progress = files_changed || state.files_in_progress;
  writeState(state);
  logEvent(session_id || state.session_id, ai, "milestone", { title });

  const note = `\n\n---\n## ✅ Milestone: ${title}\n**Time:** ${new Date().toISOString()}\n**AI:** ${ai}\n\n${description || ""}\n\n**Files:** ${(files_changed || []).join(", ") || "see git diff"}\n`;
  try { fs.appendFileSync(HANDOFF_FILE, note); } catch {}

  const devlogMilestone = `\n## ${new Date().toISOString().slice(0,10)} | ${(ai||"").toUpperCase()} | Milestone: ${title}\n\n${description || ""}\n\n**Files:** ${(files_changed || []).join(", ") || "see git diff"}\n\n---\n`;
  try { fs.appendFileSync(DEVLOG_FILE, devlogMilestone); } catch {}

  if (trigger_handoff) {
    syncToGit(REPO_PATH, `milestone: ${title}`).catch(console.error);
    return res.json({ ok: true, handoff_triggered: true });
  }
  console.log(`[MILESTONE] ${ai?.toUpperCase()}: ${title}`);
  res.json({ ok: true, handoff_triggered: false });
});

app.post("/token-warning", auth, (req, res) => {
  const { ai, session_id, tokens_used, last_completed_task, files_changed } = req.body;
  const state = readState();
  logEvent(session_id || state.session_id, ai, "token_warning", { tokens_used });

  const doc = buildHandoffDoc({
    from_ai: ai, to_ai: ai === "claude" ? "codex" : "claude",
    summary: `Token limit approaching — ${last_completed_task || "session in progress"}`,
    files_changed: files_changed || state.files_in_progress,
    next_task: state.current_task,
    branch: state.branch,
    session_id: session_id || state.session_id,
    reason: "token_limit"
  });

  fs.writeFileSync(HANDOFF_FILE, doc);
  state.last_handoff_at = new Date().toISOString();
  writeState(state);
  syncToGit(REPO_PATH, `handoff: token warning — ${ai}`).catch(console.error);

  console.log(`[TOKEN WARNING] ${ai?.toUpperCase()} approaching limit`);
  res.json({ ok: true, message: "Handoff written and pushed. Wrap up and exit cleanly.", handoff_written: true });
});

app.post("/handoff", auth, (req, res) => {
  const { ai, session_id, reason, summary, files_changed, next_task } = req.body;
  const state = readState();

  const doc = buildHandoffDoc({
    from_ai: ai || state.active_ai,
    to_ai: (ai || state.active_ai) === "claude" ? "codex" : "claude",
    summary: summary || `Forced handoff — ${reason || "unknown"}`,
    files_changed: files_changed || state.files_in_progress,
    next_task: next_task || state.current_task,
    branch: state.branch,
    session_id: session_id || state.session_id, reason
  });

  fs.writeFileSync(HANDOFF_FILE, doc);
  state.last_handoff_at = new Date().toISOString();
  state.active_ai = null;
  writeState(state);
  syncToGit(REPO_PATH, `handoff: ${reason || "forced"}`).catch(console.error);

  res.json({ ok: true, handoff_written: true });
});

// ── Watchdog ──────────────────────────────────────────────────────────────────
cron.schedule("*/5 * * * *", () => {
  const state = readState();
  if (!state.active_ai || !state.session_started_at) return;
  const mins = (Date.now() - new Date(state.session_started_at).getTime()) / 60000;
  if (mins >= SESSION_TIMEOUT_MINUTES - 5) {
    console.log(`[WATCHDOG] ${mins.toFixed(1)}min elapsed — triggering handoff`);
    const doc = buildHandoffDoc({
      from_ai: state.active_ai,
      to_ai: state.active_ai === "claude" ? "codex" : "claude",
      summary: `Watchdog auto-handoff after ${SESSION_TIMEOUT_MINUTES}min`,
      files_changed: state.files_in_progress,
      next_task: state.current_task,
      branch: state.branch, session_id: state.session_id, reason: "watchdog_timeout"
    });
    fs.writeFileSync(HANDOFF_FILE, doc);
    state.last_handoff_at = new Date().toISOString();
    state.active_ai = null;
    writeState(state);
    syncToGit(REPO_PATH, `handoff: watchdog timeout`).catch(console.error);
  }
});

app.listen(PORT, () => {
  console.log(`
╔══════════════════════════════════════════════════════╗
║        GuidePaw AI Middleware — RUNNING              ║
║  Port    : ${PORT}                                      ║
║  Repo    : /home/james/projects/guidepaw             ║
║  Laptop  : http://10.147.18.184:3333                 ║
╚══════════════════════════════════════════════════════╝
  `);
});
