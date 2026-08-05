/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
    ],
    darkMode: 'class',
    important: false,
    theme: {
        extend: {},
    },
    plugins: [
        require('@tailwindcss/typography'),
    ],
}
