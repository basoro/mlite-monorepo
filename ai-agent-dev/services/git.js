const simpleGit = require("simple-git");
const path = require("path");
const fs = require("fs");

const REPO_PATH = process.env.GIT_REPO_PATH;

if (!REPO_PATH) {
  console.warn("⚠️ GIT_REPO_PATH is not defined in .env! Git operations will fail.");
}

const git = simpleGit(REPO_PATH);

module.exports = {
  async checkout(branch) {
    console.log(`[Git] Checkout branch ${branch}...`);
    try {
      const status = await git.status();
      if (!status.isClean()) {
        await git.stash(); // Save uncommitted changes just in case
      }
      // Assuming upstream is set, otherwise fetch might need remote name
      try {
         await git.fetch();
      } catch(e) { console.log("Fetch failed, continuing local checkout..."); }
      
      await git.checkout(["-b", branch]);
    } catch (e) {
      if (e.message.includes("already exists")) {
        await git.checkout(branch);
      } else {
        throw e;
      }
    }
  },

  async writeFiles(files) {
    for (const f of files) {
      const fullPath = path.join(REPO_PATH, f.path);
      console.log(`[Git] Writing file ${fullPath}...`);
      
      // Basic whitelist check based on rules
      const allowedDirs = ["modules", "plugins", "api"];
      const targetDir = f.path.split("/")[0].toLowerCase();
      
      if (!allowedDirs.includes(targetDir) && f.path.includes("/")) {
        console.warn(`[Warning] Allowed modification only in /modules, /plugins, /api. Got path: ${f.path}`);
      }

      fs.mkdirSync(path.dirname(fullPath), { recursive: true });
      fs.writeFileSync(fullPath, f.content);
    }
  },

  async deleteFile(filePath) {
      const fullPath = path.join(REPO_PATH, filePath);
      console.log(`[Git] Deleting file ${fullPath}...`);
      if (fs.existsSync(fullPath)) {
          fs.unlinkSync(fullPath);
      }
  },

  async commit(message) {
    console.log(`[Git] Adding & Committing changes: ${message}`);
    await git.add(".");
    await git.commit(message);
  },

  async push(branch) {
    console.log(`[Git] Pushing to origin ${branch}...`);
    try {
        await git.push("origin", branch, { "--set-upstream": null });
    } catch(e) {
        console.error("❌ Push ke origin gagal! Pastikan GITHUB_TOKEN sudah diset. Error Text:", e.message);
        throw new Error("Gagal melakukan push branch. Periksa konfigurasi kredensial Git/TOKEN GitHub Anda.");
    }
  }
};
