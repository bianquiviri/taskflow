import { defineConfig, devices } from '@playwright/test';

const isCI = !!process.env.CI;
const baseURL = process.env.PLAYWRIGHT_BASE_URL || (isCI ? 'http://localhost:8000' : 'https://taskflow.josebianco.local');

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCI,
    retries: isCI ? 2 : 0,
    workers: isCI ? 1 : undefined,
    reporter: isCI ? [['github'], ['html', { open: 'never' }]] : 'list',

    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: isCI
        ? {
              command: 'php -S 0.0.0.0:8000 -t public tests/e2e/router.php',
              url: 'http://localhost:8000/up',
              reuseExistingServer: true,
              env: {
                  APP_ENV: 'testing',
                  DB_CONNECTION: 'sqlite',
                  DB_DATABASE: ':memory:',
                  SESSION_DRIVER: 'file',
                  CACHE_STORE: 'array',
                  QUEUE_CONNECTION: 'sync',
                  BROADCAST_CONNECTION: 'null',
                  MAIL_MAILER: 'array',
                  APP_URL: 'http://localhost:8000',
              },
          }
        : {
              command: 'docker compose up -d --wait',
              url: 'https://taskflow.josebianco.local/up',
              reuseExistingServer: true,
          },
});