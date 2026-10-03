import { defineConfig, devices } from '@playwright/test';

// Web spouští `make up` (Docker), tady se jen testuje proti běžící instanci.
export default defineConfig({
	testDir: 'tests/e2e',
	globalTeardown: './tests/e2e/global-teardown.ts',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL: process.env.BASE_URL ?? 'http://localhost:8080',
		trace: 'retain-on-failure',
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
