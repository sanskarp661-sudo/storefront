import "server-only";
import postgres from "postgres";
import { env } from "./env";

declare global {
  var __storefrontSql: postgres.Sql | undefined;
}

/**
 * Shared Postgres client. `prepare: false` keeps it compatible with
 * transaction-mode poolers (Neon / Supabase / PgBouncer), which is what you
 * want on serverless hosts like Vercel.
 */
function createClient() {
  return postgres(env.databaseUrl, {
    max: Number(process.env.DATABASE_POOL_MAX || 5),
    prepare: false,
    idle_timeout: 20,
    connect_timeout: 10,
    types: {
      // Return NUMERIC columns as JS numbers (prices are 2dp, well within float precision).
      numeric: {
        to: 1700,
        from: [1700],
        serialize: (x: number) => String(x),
        parse: (x: string) => Number(x),
      },
    },
  });
}

export function db(): postgres.Sql {
  if (!globalThis.__storefrontSql) globalThis.__storefrontSql = createClient();
  return globalThis.__storefrontSql;
}
