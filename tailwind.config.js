/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {},
    },
    plugins: [
        // подключите при необходимости
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
