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
const http = require("http");
const path = require("path");
const fs = require("fs");
const { execFile, spawn } = require("child_process");
const Database = require("better-sqlite3");
const cron = require("node-cron");
const { WebSocketServer } = require("ws");
const pty = require("node-pty");
const { syncToGit } = require("./git-sync");
const { buildHandoffDoc } = require("./handoff-template");

const app = express();
const httpServer = http.createServer(app);
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
  CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT,
    ai TEXT NOT NULL,
    text TEXT NOT NULL,
    answered INTEGER DEFAULT 0,
    answer TEXT,
    ts DATETIME DEFAULT CURRENT_TIMESTAMP,
    answered_at DATETIME
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
  state.handoff_saved_at_90 = false;
  writeState(state);
  logEvent(session_id, ai, "session_start", { task });

  console.log(`[START] ${ai.toUpperCase()} session ${session_id}`);
  res.json({ ok: true, session_id, ai, task: state.current_task, branch: state.branch, started_at: state.session_started_at });
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

app.post("/question", auth, (req, res) => {
  const { ai, text, session_id } = req.body;
  if (!text) return res.status(400).json({ error: "text required" });
  const state = readState();
  const sid = session_id || state.session_id;
  const result = db.prepare("INSERT INTO questions (session_id,ai,text) VALUES (?,?,?)").run(sid, ai || state.active_ai || "unknown", text);
  logEvent(sid, ai || state.active_ai || "unknown", "question", { text, question_id: result.lastInsertRowid });
  console.log(`[QUESTION] ${(ai||'?').toUpperCase()}: ${text}`);
  res.json({ ok: true, id: result.lastInsertRowid, text, answered: false });
});

app.get("/question/pending", auth, (req, res) => {
  const rows = db.prepare("SELECT * FROM questions WHERE answered=0 ORDER BY ts DESC").all();
  res.json({ pending: rows });
});

app.get("/question/:id", auth, (req, res) => {
  const row = db.prepare("SELECT * FROM questions WHERE id=?").get(req.params.id);
  if (!row) return res.status(404).json({ error: "not found" });
  res.json({ ok: true, id: row.id, text: row.text, answered: !!row.answered, answer: row.answer || null, ts: row.ts, answered_at: row.answered_at });
});

app.post("/question/:id/answer", auth, (req, res) => {
  const { answer } = req.body;
  if (!answer) return res.status(400).json({ error: "answer required" });
  const row = db.prepare("SELECT * FROM questions WHERE id=?").get(req.params.id);
  if (!row) return res.status(404).json({ error: "not found" });
  db.prepare("UPDATE questions SET answered=1, answer=?, answered_at=CURRENT_TIMESTAMP WHERE id=?").run(answer, req.params.id);
  logEvent(row.session_id, "user", "answer", { question_id: row.id, answer });
  console.log(`[ANSWER] Q${req.params.id}: ${answer}`);
  res.json({ ok: true, id: row.id, answer });
});

app.post("/note", auth, (req, res) => {
  const { text } = req.body;
  if (!text) return res.status(400).json({ error: "text required" });
  const state = readState();
  logEvent(state.session_id || "standalone", state.active_ai || "user", "note", { text });
  console.log(`[NOTE] ${text}`);
  res.json({ ok: true });
});

app.post("/session/kill", auth, (req, res) => {
  const state = readState();
  const ai = state.active_ai || req.body.ai || "unknown";
  const next_task = req.body.next_task || state.current_task || "Review git log and continue previous task.";
  const reason = req.body.reason || "killed from dashboard";

  const doc = buildHandoffDoc({
    from_ai: ai,
    to_ai: ai === "claude" ? "codex" : "claude",
    summary: `Session killed manually — ${reason}. Check git log for any uncommitted work.`,
    files_changed: state.files_in_progress || [],
    next_task,
    branch: state.branch || "main",
    session_id: state.session_id,
    reason: "session_kill"
  });

  fs.writeFileSync(HANDOFF_FILE, doc);

  const devlogEntry = `\n## ${new Date().toISOString().slice(0,10)} | ${ai.toUpperCase()} | 🛑 Session killed\n\nManually killed: ${reason}\n\n**Next:** ${next_task}\n\n---\n`;
  try { fs.appendFileSync(DEVLOG_FILE, devlogEntry); } catch {}

  logEvent(state.session_id, ai, "session_kill", { reason });
  state.active_ai = null;
  state.last_handoff_at = new Date().toISOString();
  state.files_in_progress = [];
  state.handoff_saved_at_90 = false;
  writeState(state);
  syncToGit(REPO_PATH, `handoff: session killed — ${ai}`).catch(console.error);

  console.log(`[KILL] ${ai.toUpperCase()} session killed — ${reason}`);
  res.json({ ok: true, ai, reason, next_task });
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

const DASH_HTML = path.join(__dirname, "dashboard.html");
const ALLOWED_FILES = ["HANDOFF.md","DEVLOG.md","PROJECT_STATE.md","CLAUDE_BOOT.md","CODEX_BOOT.md","CODEX_RULES.md"];
const ALLOWED_CMDS = [
  "git log --oneline -10",
  "git status --short",
  "git diff --stat HEAD",
  "git pull origin main",
  "bash scripts/deploy_local.sh",
  "php -l includes/db_connect.php",
  "systemctl status nginx --no-pager -l",
  "systemctl status php8.5-fpm --no-pager -l",
  "systemctl status postgresql --no-pager -l",
  "ls -lh downloads/*.apk",
  "tail -20 /var/log/nginx/error.log",
  "cat /tmp/middleware.log",
  "sudo -u postgres psql guidepaw -c 'SELECT version FROM users LIMIT 1;'",
  "curl -sk https://10.147.18.184/api/me.php | python3 -m json.tool",
];

app.get("/dashboard", (req, res) => {
  let html = fs.readFileSync(DASH_HTML, "utf8");
  html = html.replace("/* DASH_TOKEN injected by server */",
    `const DASH_TOKEN = ${JSON.stringify(MIDDLEWARE_SECRET || "")};`);
  res.setHeader("Content-Type", "text/html");
  res.send(html);
});

app.get("/api/dash/state", auth, (req, res) => {
  const state = readState();
  const recent_milestones = db.prepare("SELECT * FROM milestones ORDER BY ts DESC LIMIT 6").all();

  // inject app version from master.env if readable
  try {
    const env = fs.readFileSync(path.join(REPO_PATH, "master.env"), "utf8");
    const m = env.match(/^GUIDEPAW_COMPANION_VERSION_NAME=(.+)$/m);
    if (m) state.app_version = m[1].trim();
  } catch {}

  // session timing
  const timeout_min = SESSION_TIMEOUT_MINUTES;
  const warn_min    = SESSION_TIMEOUT_MINUTES - 5;
  let elapsed_min = 0, remaining_min = timeout_min, percent = 0;
  if (state.active_ai && state.session_started_at) {
    elapsed_min   = (Date.now() - new Date(state.session_started_at).getTime()) / 60000;
    remaining_min = Math.max(0, timeout_min - elapsed_min);
    percent       = Math.min(100, Math.round((elapsed_min / timeout_min) * 100));
  }

  // session history counts
  const total_sessions   = db.prepare("SELECT COUNT(*) as n FROM sessions WHERE event='session_start'").get().n;
  const watchdog_resets  = db.prepare("SELECT COUNT(*) as n FROM sessions WHERE event='session_end' AND payload LIKE '%watchdog%'").get().n;
  const claude_sessions  = db.prepare("SELECT COUNT(*) as n FROM sessions WHERE event='session_start' AND ai='claude'").get().n;
  const codex_sessions   = db.prepare("SELECT COUNT(*) as n FROM sessions WHERE event='session_start' AND ai='codex'").get().n;

  res.json({
    state, recent_milestones,
    timing: { elapsed_min: Math.round(elapsed_min * 10) / 10, remaining_min: Math.round(remaining_min * 10) / 10, percent, timeout_min, warn_min, started_at: state.session_started_at },
    counts: { total_sessions, watchdog_resets, claude_sessions, codex_sessions }
  });
});

app.get("/api/dash/events", auth, (req, res) => {
  const rows = db.prepare("SELECT * FROM sessions ORDER BY ts DESC LIMIT 40").all();
  res.json({ events: rows });
});

app.get("/api/dash/file/:name", auth, (req, res) => {
  const name = req.params.name;
  if (!ALLOWED_FILES.includes(name))
    return res.status(403).json({ error: "File not allowed" });
  try {
    const content = fs.readFileSync(path.join(REPO_PATH, name), "utf8");
    res.json({ content });
  } catch {
    res.json({ content: `(${name} not found)` });
  }
});

app.post("/api/dash/run", auth, (req, res) => {
  const { cmd } = req.body || {};
  if (!cmd || !ALLOWED_CMDS.includes(cmd))
    return res.status(403).json({ error: "Command not in allowlist" });
  const child = spawn("bash", ["-c", cmd], { cwd: REPO_PATH, env: { ...process.env, TERM: "xterm" } });
  let output = "", exitCode = 0;
  child.stdout.on("data", d => { output += d.toString(); });
  child.stderr.on("data", d => { output += d.toString(); });
  child.on("close", code => { exitCode = code || 0; res.json({ output: output.slice(0, 8000), exitCode }); });
  setTimeout(() => { try { child.kill(); } catch {} }, 30000);
});

// ── WebSocket Terminal ─────────────────────────────────────────────────────────

const wss = new WebSocketServer({ server: httpServer, path: "/terminal" });

wss.on("connection", (ws, req) => {
  // simple token check via query param ?token=...
  const url = new URL(req.url, "http://localhost");
  const token = url.searchParams.get("token") || req.headers["x-token"] || "";
  if (MIDDLEWARE_SECRET && token !== MIDDLEWARE_SECRET) {
    ws.send("\r\n\x1b[31mUnauthorized\x1b[0m\r\n");
    ws.close();
    return;
  }

  const shell = process.env.SHELL || "/bin/bash";
  const term = pty.spawn(shell, [], {
    name: "xterm-color", cols: 120, rows: 36,
    cwd: REPO_PATH, env: { ...process.env, TERM: "xterm-256color" }
  });

  term.onData(data => { try { ws.send(data); } catch {} });
  ws.on("message", raw => {
    try {
      const msg = JSON.parse(raw);
      if (msg.type === "resize") { term.resize(msg.cols, msg.rows); return; }
    } catch {}
    term.write(raw);
  });
  ws.on("close", () => term.kill());
  term.onExit(() => { try { ws.close(); } catch {} });
  console.log(`[TERMINAL] new session from ${req.socket.remoteAddress}`);
});

// ── Force handoff (dashboard button) ──────────────────────────────────────────
app.post("/api/dash/force-handoff", auth, (req, res) => {
  const state = readState();
  const ai = state.active_ai || req.body.ai || "unknown";
  const pct = state.session_started_at
    ? Math.round(((Date.now() - new Date(state.session_started_at).getTime()) / 60000 / SESSION_TIMEOUT_MINUTES) * 100)
    : 0;

  const doc = buildHandoffDoc({
    from_ai: ai,
    to_ai: ai === "claude" ? "codex" : "claude",
    summary: `Force-saved from dashboard at ${pct}% session usage — AI may still be running. Check terminal and git log for any uncommitted work.`,
    files_changed: state.files_in_progress || [],
    next_task: state.current_task || "Review git log, check for uncommitted changes, then continue previous task.",
    branch: state.branch || "main",
    session_id: state.session_id,
    reason: "force_handoff_dashboard"
  });

  fs.writeFileSync(HANDOFF_FILE, doc);
  const devlogEntry = `\n## ${new Date().toISOString().slice(0,10)} | ${ai.toUpperCase()} | ⚡ Force handoff at ${pct}%\n\nDashboard force-handoff triggered at ${pct}% session usage.\n\n**Next:** ${state.current_task || "See HANDOFF.md"}\n\n---\n`;
  try { fs.appendFileSync(DEVLOG_FILE, devlogEntry); } catch {}

  state.last_handoff_at = new Date().toISOString();
  state.active_ai = null;
  writeState(state);
  logEvent(state.session_id, ai, "force_handoff", { pct });
  syncToGit(REPO_PATH, `handoff: force-saved at ${pct}% — ${ai}`).catch(console.error);

  console.log(`[FORCE HANDOFF] ${ai.toUpperCase()} at ${pct}%`);
  res.json({ ok: true, pct, message: `Handoff saved at ${pct}%. Pushed to GitHub.` });
});

// ── Watchdog ──────────────────────────────────────────────────────────────────
const WARN_PCT  = 80;  // yellow banner on dashboard
const FORCE_PCT = 90;  // auto-save + push, red banner

cron.schedule("*/1 * * * *", () => {   // check every minute
  const state = readState();
  if (!state.active_ai || !state.session_started_at) return;
  const mins = (Date.now() - new Date(state.session_started_at).getTime()) / 60000;
  const pct  = (mins / SESSION_TIMEOUT_MINUTES) * 100;

  // 90% — auto-save handoff + push, but keep session alive so AI can finish
  if (pct >= FORCE_PCT && !state.handoff_saved_at_90) {
    console.log(`[WATCHDOG] ${pct.toFixed(0)}% — auto-saving handoff`);
    const doc = buildHandoffDoc({
      from_ai: state.active_ai,
      to_ai: state.active_ai === "claude" ? "codex" : "claude",
      summary: `AUTO-SAVE at ${pct.toFixed(0)}% — session still running. AI approaching context limit. Check terminal.`,
      files_changed: state.files_in_progress || [],
      next_task: state.current_task || "Review git log for last work, then continue.",
      branch: state.branch || "main",
      session_id: state.session_id,
      reason: "auto_save_90pct"
    });
    fs.writeFileSync(HANDOFF_FILE, doc);
    const devlogEntry = `\n## ${new Date().toISOString().slice(0,10)} | ${state.active_ai.toUpperCase()} | ⚠️ Auto-save at ${pct.toFixed(0)}%\n\nWatchdog auto-saved handoff. Session still running.\n\n---\n`;
    try { fs.appendFileSync(DEVLOG_FILE, devlogEntry); } catch {}
    state.handoff_saved_at_90 = true;  // only fire once per session
    state.last_handoff_at = new Date().toISOString();
    writeState(state);
    syncToGit(REPO_PATH, `handoff: auto-save at ${pct.toFixed(0)}%`).catch(console.error);
  }

  // 100% hard timeout — force end
  if (mins >= SESSION_TIMEOUT_MINUTES) {
    console.log(`[WATCHDOG] ${mins.toFixed(1)}min — hard timeout, ending session`);
    const doc = buildHandoffDoc({
      from_ai: state.active_ai,
      to_ai: state.active_ai === "claude" ? "codex" : "claude",
      summary: `Hard timeout after ${SESSION_TIMEOUT_MINUTES}min. Session force-ended.`,
      files_changed: state.files_in_progress || [],
      next_task: state.current_task || "Review git log and continue previous task.",
      branch: state.branch || "main",
      session_id: state.session_id,
      reason: "watchdog_timeout"
    });
    fs.writeFileSync(HANDOFF_FILE, doc);
    state.last_handoff_at = new Date().toISOString();
    state.active_ai = null;
    state.handoff_saved_at_90 = false;
    writeState(state);
    logEvent(state.session_id, state.active_ai, "session_end", { reason: "watchdog_timeout" });
    syncToGit(REPO_PATH, `handoff: watchdog hard timeout`).catch(console.error);
  }
});

// clear the 90% flag when a new session starts (already in /session/start but belt+suspenders)
function clearWatchdogFlags(state) {
  state.handoff_saved_at_90 = false;
  return state;
}

httpServer.listen(PORT, () => {
  console.log(`
╔══════════════════════════════════════════════════════╗
║        GuidePaw AI Middleware — RUNNING              ║
║  Port    : ${PORT}                                      ║
║  Repo    : /home/james/projects/guidepaw             ║
║  Laptop  : http://10.147.18.184:3333                 ║
╚══════════════════════════════════════════════════════╝
  `);
});
