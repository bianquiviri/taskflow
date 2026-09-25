import { defineConfig } from 'eslint/config';
import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';

export default defineConfig([
    {
        files: ['resources/js/**/*.js', 'resources/js/**/*.vue'],
        ignores: ['**/dist/**', '**/build/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        languageOptions: {
            globals: {
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                process: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
]);