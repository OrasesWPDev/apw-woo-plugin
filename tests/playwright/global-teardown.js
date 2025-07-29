/**
 * Playwright Global Teardown for Allpoint Command Integration
 * Runs once after all tests to clean up
 */

async function globalTeardown(config) {
  console.log('🧹 Cleaning up Allpoint Command Integration test environment...');
  
  const fs = require('fs');
  const path = require('path');
  
  try {
    // Clean up temporary test files
    const configPath = path.join(__dirname, 'test-environment.json');
    if (fs.existsSync(configPath)) {
      fs.unlinkSync(configPath);
      console.log('✅ Test environment configuration cleaned up');
    }
    
    // Clean up any test artifacts
    const reportPath = path.join(__dirname, 'playwright-report');
    if (fs.existsSync(reportPath)) {
      console.log('📊 Test reports available in: tests/playwright/playwright-report/');
    }
    
    console.log('✅ Teardown completed successfully');
    console.log('');
    console.log('📋 Next Steps for Development:');
    console.log('   1. Review test results');
    console.log('   2. Implement failing integration features');
    console.log('   3. Run tests again to verify fixes');
    console.log('   4. Deploy to WP Engine staging when ready');
    console.log('');
    
  } catch (error) {
    console.error('⚠️  Teardown warning:', error.message);
  }
}

module.exports = globalTeardown;
