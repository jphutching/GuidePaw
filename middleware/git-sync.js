const simpleGit = require("simple-git");

async function syncToGit(repoPath, commitMessage) {
  const git = simpleGit(repoPath);
  try {
    await git.add(["SESSION_STATE.json", "HANDOFF.md"]);
    const status = await git.status();
    if (status.staged.length === 0) { console.log("[GIT] Nothing staged"); return; }
    await git.commit(`[middleware] ${commitMessage}`, {
      "--author": "GuidePaw Middleware <middleware@guidepaw.app>"
    });
    await git.push("origin", undefined, ["--no-verify"]);
    console.log(`[GIT] Pushed: ${commitMessage}`);
  } catch (err) {
    console.error(`[GIT] Sync failed: ${err.message}`);
  }
}

module.exports = { syncToGit };
