import "server-only";

function required(name: string): string {
  const value = process.env[name];
  if (!value) throw new Error(`Missing required environment variable ${name}`);
  return value;
}

/**
 * Server-only configuration. Importing this module from a Client Component
 * fails the build (via `server-only`), which is what keeps ERP_API_KEY out of
 * browser bundles. Never prefix these variables with NEXT_PUBLIC_.
 */
export const env = {
  get erpApiKey() {
    return required("ERP_API_KEY");
  },
  get erpBaseUrl() {
    const base = process.env.ERP_API_BASE_URL || "https://erp.mosaicengine.in/api/v1/";
    return base.endsWith("/") ? base : `${base}/`;
  },
  get databaseUrl() {
    return required("DATABASE_URL");
  },
  get cronSecret() {
    return process.env.CRON_SECRET || "";
  },
};
