import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Marca upMusic — ver specs/02-design-system.md e DESIGN.md
                brand: {
                    black: '#000000',
                    ink: '#0a0a0a',
                    orange: '#ff8c1e',
                    'orange-deep': '#fa810f',
                    'orange-soft': '#f9a14f',
                },
                surface: '#f7f7f7',
                hairline: '#e5e5e5',
                steel: '#5a5a5c',
            },
        },
    },

    plugins: [forms],
};
