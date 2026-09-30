// Applies db/schema.sql to DATABASE_URL. Usage: npm run db:migrate
import { readFile } from "node:fs/promises";
import postgres from "postgres";

const url = process.env.DATABASE_URL;
if (!url) {
  console.error("DATABASE_URL is not set");
  process.exit(1);
}

const sql = postgres(url, { max: 1, prepare: false, onnotice: () => {} });
const schema = await readFile(new URL("../db/schema.sql", import.meta.url), "utf8");
try {
  await sql.unsafe(schema);
  console.log("Schema applied.");
} finally {
  await sql.end();
}
