/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./modules/**/*.php",
    "./includes/**/*.php",
    "./database/**/*.php",
    "./assets/**/*.js"
  ],
  safelist: [
    'animate-fade-in',
    'animate-fade-up'
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        display: ['Outfit', 'sans-serif'],
      },
      colors: {
        vibe: {
          'primary': '#0F172A',
          'primary-container': '#1E293B',
          'primary-light': '#F8FAFC',
          'primary-dim': '#E2E8F0',
          'on-primary': '#FFFFFF',
          'secondary': '#0F766E',
          'secondary-container': '#CCFBF1',
          'on-secondary': '#FFFFFF',
          'tertiary': '#334155',
          'tertiary-container': '#475569',
          'tertiary-light': '#F1F5F9',
          'on-tertiary': '#FFFFFF',
          'error': '#DC2626',
          'error-container': '#FEE2E2',
          'accent': '#D97706',
          'accent-light': '#FEF3C7',
          'bg': '#FFFFFF',
          'surface': '#FFFFFF',
          'surface-dim': '#F8FAFC',
          'surface-container': '#F1F5F9',
          'surface-high': '#E2E8F0',
          'on-surface': '#020617',
          'on-surface-variant': '#475569',
          'outline': '#E2E8F0',
          'outline-variant': '#CBD5E1',
          'inverse-surface': '#0F172A',
          'inverse-on-surface': '#F8FAFC',
        },
        theme: {
          'evergreen': '#020617',
          'leaf': '#0F766E',
          'sage': '#0F172A',
          'muted-olive': '#F1F5F9',
          'olive': '#475569',
          'bg': '#FFFFFF',
          'ocean': '#0F172A',
          'ocean-light': '#1E293B',
          'coral': '#DC2626',
          'coral-light': '#FEE2E2',
          'sun': '#D97706',
          'sun-light': '#FEF3C7',
          'twilight': '#334155',
        }
      },
      boxShadow: {
        'card': 'none',
        'card-hover': 'none',
        'elevated': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
      },
      borderRadius: {
        'sm': '0.125rem',
        'DEFAULT': '0.25rem',
        'md': '0.375rem',
        'lg': '0.5rem',
        'xl': '0.5rem',
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out forwards',
        'fade-up': 'fadeUp 0.3s ease-out forwards',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeUp: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      }
    }
  },
  plugins: [],
}
