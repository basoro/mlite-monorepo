require("dotenv").config();
const { Worker } = require("bullmq");
const TelegramBot = require("node-telegram-bot-api");
const Redis = require("ioredis");

const redisOptions = {
  host: process.env.REDIS_HOST || '127.0.0.1',
  port: process.env.REDIS_PORT || 6379,
  maxRetriesPerRequest: null
};

const connection = new Redis(redisOptions);
const bot = new TelegramBot(process.env.TELEGRAM_TOKEN);

const agent = require("./services/agent");
const git = require("./services/git");
const deploy = require("./services/deploy");

new Worker(
  "dev-task",
  async (job) => {
    const { text, chatId } = job.data;
    console.log("🧠 [Worker] Running AI task:", text);

    bot.sendMessage(chatId, "⏳ AI sedang menganalisis instruksi & membuat kode...");

    try {
      const result = await agent.run(text);
      if (!result) throw new Error("Invalid AI response");

      bot.sendMessage(chatId, "✅ Analisis selesai. Menerapkan ke branch baru...");

      const branch = `feature/ai-${Date.now()}`;
      await git.checkout(branch);
      
      let commitMessage = "AI generated code";
      if (result.actions && Array.isArray(result.actions)) {
        let filesToWrite = [];
        for (const action of result.actions) {
          if (action.type === 'create_file' || action.type === 'update_file') {
             filesToWrite.push({ path: action.path, content: action.content });
             console.log(`📝 Action: ${action.type} -> ${action.path}`);
          } else if (action.type === 'delete_file') {
             await git.deleteFile(action.path);
          } else if (action.type === 'git' && action.command === 'commit') {
             commitMessage = action.message || commitMessage;
          }
        }
        if (filesToWrite.length > 0) {
           await git.writeFiles(filesToWrite);
        }
      }

      await git.commit(commitMessage);
      await git.push(branch);

      bot.sendMessage(chatId, `🎉 Task berhasil diselesaikan!\n\n**Plan:** ${result.plan || 'Selesai'}\n**Notes:** ${result.notes || '-'}\n\nBranch: \`${branch}\``, { parse_mode: 'Markdown' });
      console.log("✅ Code pushed to branch:", branch);

    } catch (err) {
      console.error("❌ Task error:", err);
      bot.sendMessage(chatId, `❌ Gagal memproses task. Error: ${err.message}`);
    }
  },
  { connection }
);

new Worker(
  "deploy-task",
  async (job) => {
    const { chatId } = job.data;
    console.log("🚀 [Worker] Deploying to Papuyu...");
    bot.sendMessage(chatId, "⏳ Memulai proses deploy ke Papuyu...");
    
    try {
      await deploy.run();
      bot.sendMessage(chatId, "✅ Deploy berhasil dilakukan!");
    } catch (err) {
      console.error("❌ Deploy error:", err);
      bot.sendMessage(chatId, `❌ Deploy gagal. Error: ${err.message}`);
    }
  },
  { connection }
);

console.log("👷 Worker is running & waiting for jobs...");
