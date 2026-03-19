import { defineConfig } from 'vite'
import { resolve } from 'path'

export default defineConfig({
    build: {
        outDir: 'src/assetbundles/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                chatbot: resolve(__dirname, 'src/assetbundles/src/js/chatbot.js'),
                dashboard: resolve(__dirname, 'src/assetbundles/src/js/dashboard.js'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: 'css/[name][extname]',
                chunkFileNames: 'js/[name].js',
            }
        },
        minify: true,
    }
})
