import { defineConfig, devices } from '@playwright/test';

const executablePath = process.env['CHROMIUM_PATH'];

export default defineConfig({
  testDir: './packages/theme-blank/tests/Browser',
  use: {
    headless: true,
    viewport: { width: 1280, height: 720 },
    ...(executablePath ? { launchOptions: { executablePath } } : {}),
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
  ],
});
