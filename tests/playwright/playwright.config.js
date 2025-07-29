const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright Configuration for Allpoint Command Integration Testing
 * 
 * DEVELOPMENT SETUP:
 * - Development: /projects/apw/apw-woo-plugin
 * - Local Testing: http://localhost:10013/
 * - Staging Deploy: WP Engine after local testing
 */

module.exports = defineConfig({
  testDir: './',
  fullyParallel: false, // Run tests sequentially for integration testing
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1, // Single worker for local testing
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['line'],
    ['json', { outputFile: 'test-results.json' }]
  ],
  use: {
    baseURL: 'http://localhost:10013',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    // Allpoint Command integration specific settings
    extraHTTPHeaders: {
      'X-Test-Environment': 'local-development'
    }
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    
    // Enable additional browsers for comprehensive testing
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    
    // Mobile testing for responsive checkout
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    }
  ],

  // Local development server configuration
  webServer: {
    command: 'echo "Using Local by Flywheel server at http://localhost:10013"',
    url: 'http://localhost:10013',
    reuseExistingServer: true,
    timeout: 120 * 1000, // 2 minutes
  },
  
  // Test configuration specific to Allpoint Command integration
  timeout: 30 * 1000, // 30 seconds per test
  expect: {
    timeout: 10 * 1000, // 10 seconds for assertions
  },
  
  // Global setup for Allpoint testing
  globalSetup: require.resolve('./global-setup.js'),
  globalTeardown: require.resolve('./global-teardown.js'),
});
