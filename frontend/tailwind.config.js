/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        primary: {
          50: "#f3fbf8",
          100: "#e0f5ed",
          200: "#c1ebdc",
          300: "#7febc3",
          400: "#30de9e",
          500: "#1db47c",
          600: "#189768",
          700: "#147b55",
          800: "#0f6143",
          900: "#0c4932",
          950: "#072e20",
        },
        navy: {
          50: "#f5f7fa",
          100: "#e7ecf4",
          200: "#cbd6e7",
          300: "#89ace1",
          400: "#3b76ce",
          500: "#27569b",
          600: "#1e4176",
          700: "#183662",
          800: "#152f56",
          900: "#132a4c",
          950: "#0b192d",
        },
        sidebar: {
          bg: "#132a4c",
          hover: "#1e4176",
          active: "#189768",
          text: "#a8b6cc",
          "text-active": "#f8fafc",
        },
        border: "hsl(var(--border))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
      },
      fontFamily: {
        sans: ["Inter", "Cairo", "ui-sans-serif", "system-ui"],
        mono: ["JetBrains Mono", "ui-monospace"],
      },
      animation: {
        "fade-in": "fadeIn 0.2s ease-in-out",
        "slide-in": "slideIn 0.3s ease-out",
        "spin-slow": "spin 3s linear infinite",
      },
      keyframes: {
        fadeIn: { from: { opacity: "0" }, to: { opacity: "1" } },
        slideIn: {
          from: { transform: "translateX(-100%)" },
          to: { transform: "translateX(0)" },
        },
      },
    },
  },
  plugins: [],
};
