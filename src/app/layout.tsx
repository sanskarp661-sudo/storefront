import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { CartProvider } from "@/components/cart-provider";
import { Footer } from "@/components/footer";
import { Header } from "@/components/header";
import { getNavCategories } from "@/lib/server/nav";
import { site } from "@/lib/site";
import "./globals.css";

const geistSans = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

// Navigation categories come from the synced catalog, so render per request rather than at build time.
export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: { default: site.name, template: `%s · ${site.name}` },
  description: site.tagline,
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const categories = await getNavCategories();
  return (
    <html lang="en-IN" className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-sans">
        <CartProvider>
          <Header categories={categories} />
          <main className="flex-1">{children}</main>
          <Footer categories={categories} />
        </CartProvider>
      </body>
    </html>
  );
}
