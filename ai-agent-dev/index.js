require("dotenv").config();
const TelegramBot = require("node-telegram-bot-api");
const { Queue } = require("bullmq");
const Redis = require("ioredis");

const redisOptions = {
  host: process.env.REDIS_HOST || '127.0.0.1',
  port: process.env.REDIS_PORT || 6379,
  maxRetriesPerRequest: null
};

const connection = new Redis(redisOptions);
const queue = new Queue("dev-task", { connection });

const bot = new TelegramBot(process.env.TELEGRAM_TOKEN, { polling: true });

bot.onText(/\/task (.+)/, async (msg, match) => {
  const task = match[1];

  await queue.add("dev-task", {
    text: task,
    user: msg.from.username,
    chatId: msg.chat.id
  });

  bot.sendMessage(msg.chat.id, "✅ Task diterima & sedang diproses oleh AI Worker...");
});

bot.onText(/\/deploy/, async (msg) => {
  await queue.add("deploy-task", { chatId: msg.chat.id });
  bot.sendMessage(msg.chat.id, "🚀 Deploy triggered, masuk antrean...");
});

console.log("🤖 Telegram bot running... Menunggu perintah /task atau /deploy.");
