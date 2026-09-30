import "server-only";
import { getCategories } from "./catalog";

/** Category names for header/footer navigation; never breaks the page if the DB is unavailable. */
export async function getNavCategories(): Promise<string[]> {
  try {
    return (await getCategories()).map((c) => c.name);
  } catch (err) {
    console.error("[nav] could not load categories", err);
    return [];
  }
}
