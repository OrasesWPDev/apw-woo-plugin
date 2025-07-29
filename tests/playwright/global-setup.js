/**
 * Playwright Global Setup for Allpoint Command Integration
 * Runs once before all tests to prepare the environment
 */

const { chromium } = require('@playwright/test');

async function globalSetup(config) {
  console.log('🚀 Setting up Allpoint Command Integration Test Environment...');
  
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  
  try {
    // Verify Local WordPress site is accessible
    console.log('📊 Checking Local WordPress site accessibility...');
    await page.goto('http://localhost:10013');
    await page.waitForLoadState('networkidle');
    
    // Check if WordPress is running
    const isWordPress = await page.locator('meta[name="generator"]').getAttribute('content');
    if (isWordPress && isWordPress.includes('WordPress')) {
      console.log('✅ WordPress detected and accessible');
    } else {
      throw new Error('WordPress not detected - ensure Local site is running');
    }
    
    // Verify WooCommerce is active
    await page.goto('http://localhost:10013/shop');
    const shopExists = await page.locator('body.woocommerce-shop').isVisible({ timeout: 5000 }).catch(() => false);
    
    if (shopExists) {
      console.log('✅ WooCommerce shop page accessible');
    } else {
      console.log('⚠️  WooCommerce shop page not detected - may need plugin activation');
    }
    
    // Check for APW plugin presence
    await page.goto('http://localhost:10013/wp-admin');
    
    // Try to login (this may fail if credentials are different, which is OK for setup)
    try {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'password');
      await page.click('#wp-submit');
      
      // Check if we're logged in
      const isLoggedIn = await page.locator('#wpadminbar').isVisible({ timeout: 5000 }).catch(() => false);
      
      if (isLoggedIn) {
        console.log('✅ WordPress admin access confirmed');
        
        // Check for APW plugin in admin
        await page.goto('http://localhost:10013/wp-admin/plugins.php');
        const apwPlugin = await page.locator('text=APW WooCommerce Plugin').isVisible({ timeout: 5000 }).catch(() => false);
        
        if (apwPlugin) {
          console.log('✅ APW WooCommerce Plugin detected in admin');
        } else {
          console.log('⚠️  APW WooCommerce Plugin not found - ensure plugin is deployed to Local site');
        }
      } else {
        console.log('⚠️  Could not access WordPress admin - check credentials');
        console.log('   Default credentials: admin/password');
        console.log('   Update in playwright.config.js if different');
      }
    } catch (error) {
      console.log('⚠️  Admin login failed - this is OK if credentials differ from defaults');
    }
    
    // Verify PDF file accessibility
    console.log('📄 Checking rental agreement PDF accessibility...');
    const pdfResponse = await page.goto('http://localhost:10013/wp-content/plugins/apw-woo-plugin/assets/pdf/Allpoint%20Wireless%20Rental%20Agreement.pdf');
    
    if (pdfResponse && pdfResponse.status() === 200) {
      console.log('✅ Rental agreement PDF accessible');
    } else {
      console.log('⚠️  Rental agreement PDF not accessible - ensure plugin is deployed');
    }
    
    // Set up test environment markers
    console.log('🔧 Configuring test environment...');
    
    // Store environment configuration for tests
    const environmentConfig = {
      local_site_url: 'http://localhost:10013',
      pdf_file: 'Allpoint Wireless Rental Agreement.pdf',
      test_customer_email: 'playwright@allpointwireless.com',
      setup_timestamp: new Date().toISOString(),
      wordpress_detected: !!isWordPress,
      woocommerce_detected: shopExists
    };
    
    // Save config to temp file for tests to use
    const fs = require('fs');
    const path = require('path');
    const configPath = path.join(__dirname, 'test-environment.json');
    fs.writeFileSync(configPath, JSON.stringify(environmentConfig, null, 2));
    
    console.log('✅ Test environment configuration saved');
    
    console.log('🎉 Allpoint Command Integration test environment ready!');
    console.log('');
    console.log('🚀 Ready for development:');
    console.log('   1. Develop in: /projects/apw/apw-woo-plugin');
    console.log('   2. Test against: http://localhost:10013');
    console.log('   3. Deploy to WP Engine staging when ready');
    console.log('');
    
  } catch (error) {
    console.error('❌ Setup failed:', error.message);
    console.log('');
    console.log('🔧 Troubleshooting:');
    console.log('   1. Ensure Local by Flywheel is running');
    console.log('   2. Verify APW Build site is started');
    console.log('   3. Check that site URL is http://localhost:10013');
    console.log('   4. Ensure APW plugin is deployed to Local site');
    console.log('');
    throw error;
  } finally {
    await browser.close();
  }
}

module.exports = globalSetup;
