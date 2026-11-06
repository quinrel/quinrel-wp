/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/assets/js/**/*.{js,jsx,ts,tsx}",
    "./src/Modules/**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        // Sampled from docs/quinrel-logo.jpeg. 600 is the logo blue: white text on it is 5.2:1.
        // 700 is the pressed blue. 950 is the banner navy. Status colors stay green, amber, and red.
        brand: {
          50: '#F0F5FF',
          100: '#E0ECFE',
          500: '#0060F9',
          600: '#0140AC',
          900: '#000F2E',
        },
        indigo: {
          50: '#F0F5FF',
          100: '#E0ECFE',
          200: '#BFD7FE',
          300: '#8CB7FC',
          400: '#4C90FB',
          500: '#0060F9',
          600: '#0060F9',
          700: '#0140AC',
          800: '#013083',
          900: '#021E58',
          950: '#020822',
        },
      }
    },
  },
  plugins: [],
  corePlugins: {
    preflight: false, // Prevent tailwind from overriding WP admin styles heavily
  }
}
