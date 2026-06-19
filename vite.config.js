import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// В docker-контейнере frontend выставляется DOCKER=true (см. docker-compose.yml).
const inDocker = process.env.DOCKER === 'true';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: true,            // слушать 0.0.0.0 — иначе dev-сервер недоступен с хоста
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost', // браузер подключается к HMR по http://localhost:5173
        },
        // На bind-mount (особенно Windows) inotify-события не доходят — нужен поллинг.
        watch: inDocker ? { usePolling: true } : undefined,
    },
});
