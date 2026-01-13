import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Dark mode colors (cyberpunk/hacker aesthetic)
                dark: {
                    bg: '#0a0f14',
                    surface: '#141c24',
                    border: '#1e293b',
                    text: {
                        primary: '#e2e8f0',
                        secondary: '#94a3b8',
                        muted: '#64748b',
                    },
                    accent: {
                        DEFAULT: '#00ffc8',
                        hover: '#00e6b3',
                        muted: '#00b894',
                    },
                    primary: {
                        DEFAULT: '#38bdf8',
                        hover: '#22d3ee',
                    },
                    danger: '#ef4444',
                },
                // Light mode colors
                light: {
                    bg: '#f7f8fa',
                    surface: '#ffffff',
                    border: '#e2e8f0',
                    text: {
                        primary: '#1e293b',
                        secondary: '#475569',
                        muted: '#64748b',
                    },
                    accent: {
                        DEFAULT: '#00776b',
                        hover: '#005f56',
                        muted: '#00a896',
                    },
                    primary: {
                        DEFAULT: '#0284c7',
                        hover: '#0369a1',
                    },
                    danger: '#dc2626',
                },
            },
            boxShadow: {
                'neon': '0 0 10px rgba(0, 255, 200, 0.3)',
                'neon-lg': '0 0 20px rgba(0, 255, 200, 0.4)',
            },
        },
    },

    plugins: [forms, typography],
};
