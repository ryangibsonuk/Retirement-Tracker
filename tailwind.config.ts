import type { Config } from "tailwindcss";

export default {
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          purple: "#9333ea",
          "purple-hover": "#7e22ce",
          soft: "#f3e8ff",
          muted: "#475569",
        },
      },
    },
  },
  plugins: [],
} satisfies Config;
