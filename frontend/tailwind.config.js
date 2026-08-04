/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        salvia: {
          dark: '#17302B',
          primary: '#0E5D52',
          secondary: '#5F7A73',
          bg: '#FAF5EE',
          badge: '#E3EFE9',
          orange: '#D9603E',
          surface: '#FFFFFF',
          cardBorder: '#EDE4D8',
          textSubtle: '#8FA39D',
        }
      },
      fontFamily: {
        sans: ['Figtree', 'system-ui', 'sans-serif'],
        serif: ['Instrument Serif', 'Georgia', 'serif'],
      }
    },
  },
  plugins: [],
}
