import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                purple: require('tailwindcss/colors').violet,
            },
            fontFamily: {
                sans: ['Public Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
