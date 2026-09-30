import type { NextConfig } from "next";

const erpHost = new URL(process.env.ERP_API_BASE_URL || "https://erp.mosaicengine.in/api/v1/").hostname;

const nextConfig: NextConfig = {
  images: {
    // Product images are served from the ERP's uploads directory.
    remotePatterns: [{ protocol: "https", hostname: erpHost }],
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          // Order pages carry a private access key in the URL; don't leak it via Referer.
          { key: "Referrer-Policy", value: "same-origin" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
        ],
      },
    ];
  },
};

export default nextConfig;
