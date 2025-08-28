// Nova Authentication Helper for E2E Tests
// Provides common functions for login, navigation, and setup

/**
 * Login to Nova admin panel
 * @param {import('@playwright/test').Page} page 
 * @param {Object} credentials 
 */
async function loginToNova(page, credentials = {}) {
  const defaultCredentials = {
    email: process.env.NOVA_TEST_EMAIL || 'admin@example.com',
    password: process.env.NOVA_TEST_PASSWORD || 'password'
  };

  const { email, password } = { ...defaultCredentials, ...credentials };

  await page.goto('/nova/login');
  
  // Wait for login form
  await page.waitForSelector('input[name="email"], input[type="email"]');
  
  // Fill credentials
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);
  
  // Submit form
  await page.click('button[type="submit"]');
  
  // Wait for dashboard to load
  await page.waitForURL(/\/nova(?:\/dashboard)?$/);
  await page.waitForLoadState('networkidle');
}

/**
 * Navigate to menu management tool
 * @param {import('@playwright/test').Page} page 
 */
async function navigateToMenus(page) {
  await page.goto('/nova/menus');
  await page.waitForLoadState('networkidle');
  
  // Wait for page to be ready
  await page.waitForSelector('h1', { timeout: 10000 });
}

/**
 * Create a test menu and return its details
 * @param {import('@playwright/test').Page} page 
 * @param {Object} menuData 
 */
async function createTestMenu(page, menuData = {}) {
  const defaultData = {
    name: `Test Menu ${Date.now()}`,
    slug: `test-menu-${Date.now()}`,
    max_depth: 5
  };

  const data = { ...defaultData, ...menuData };

  await page.click('button:has-text("Create Menu")');
  
  // Fill form
  await page.fill('input[name="name"]', data.name);
  if (data.slug) {
    await page.fill('input[name="slug"]', data.slug);
  }
  await page.selectOption('select[name="max_depth"]', data.max_depth.toString());

  // Submit and wait for response
  await Promise.all([
    page.waitForResponse(response => 
      response.url().includes('/menus') && response.status() === 201
    ),
    page.click('button[type="submit"]:has-text("Create")')
  ]);

  return data;
}

/**
 * Delete a menu by name
 * @param {import('@playwright/test').Page} page 
 * @param {string} menuName 
 */
async function deleteTestMenu(page, menuName) {
  try {
    await navigateToMenus(page);
    
    // Set up dialog handler
    page.on('dialog', dialog => dialog.accept());
    
    const menuRow = page.locator('table tbody tr', { hasText: menuName });
    if (await menuRow.count() > 0) {
      await menuRow.locator('button:has-text("Delete")').click();
      await page.waitForResponse(response => 
        response.url().includes('/menus/') && response.status() === 200
      );
    }
  } catch (error) {
    console.log(`Failed to delete menu ${menuName}:`, error);
  }
}

/**
 * Wait for Nova to finish loading
 * @param {import('@playwright/test').Page} page 
 */
async function waitForNovaReady(page) {
  // Wait for Nova app to be ready
  await page.waitForFunction(() => {
    return window.Nova && window.Nova.config;
  }, { timeout: 30000 });
  
  await page.waitForLoadState('networkidle');
}

/**
 * Navigate to menu item management page
 * @param {import('@playwright/test').Page} page 
 * @param {string|number} menuId 
 */
async function navigateToMenuEdit(page, menuId) {
  await page.goto(`/nova/menus/${menuId}/edit`);
  await page.waitForLoadState('networkidle');
  await waitForNovaReady(page);
}

/**
 * Create a menu item in the current menu
 * @param {import('@playwright/test').Page} page 
 * @param {Object} itemData 
 */
async function createMenuItem(page, itemData = {}) {
  const defaultData = {
    name: `Test Item ${Date.now()}`,
    custom_url: `/test-${Date.now()}`,
    visibility: 'always_show'
  };

  const data = { ...defaultData, ...itemData };

  // Click add item button
  await page.click('button:has-text("Add Menu Item"), button:has-text("Add First Menu Item")');

  // Fill form
  const form = page.locator('.menu-item-form, [data-testid="menu-item-form"]').last();
  await form.locator('input[name="name"]').fill(data.name);
  
  if (data.custom_url) {
    await form.locator('input[name="custom_url"]').fill(data.custom_url);
  }

  // Set visibility if not default
  if (data.visibility === 'always_hide') {
    await form.locator('button:has-text("Always Hide")').click();
  } else if (data.visibility === 'schedule') {
    await form.locator('button:has-text("Schedule")').click();
    if (data.display_at) {
      await form.locator('input[type="datetime-local"]:first').fill(data.display_at);
    }
    if (data.hide_at) {
      await form.locator('input[type="datetime-local"]:last').fill(data.hide_at);
    }
  }

  // Save item
  await Promise.all([
    page.waitForResponse(response => 
      response.url().includes('/menu-items') && response.status() === 201
    ),
    form.locator('button[type="submit"]:has-text("Save")').click()
  ]);

  return data;
}

/**
 * Handle common Nova error scenarios
 * @param {import('@playwright/test').Page} page 
 */
async function handleNovaErrors(page) {
  // Handle common authentication errors
  const currentUrl = page.url();
  if (currentUrl.includes('/login') || currentUrl.includes('/auth')) {
    await loginToNova(page);
    return true; // Indicate that login was required
  }

  // Handle 404 or other error pages
  const errorElement = await page.locator('.error, [data-testid="error"]').first();
  if (await errorElement.isVisible()) {
    throw new Error(`Nova error: ${await errorElement.textContent()}`);
  }

  return false;
}

/**
 * Get menu ID from current URL or menu list
 * @param {import('@playwright/test').Page} page 
 * @param {string} menuName 
 */
async function getMenuId(page, menuName) {
  // Try to get from URL first
  const currentUrl = page.url();
  const urlMatch = currentUrl.match(/\/menus\/(\d+)/);
  if (urlMatch) {
    return parseInt(urlMatch[1]);
  }

  // Otherwise, look it up in the menu list
  await navigateToMenus(page);
  const menuRow = page.locator('table tbody tr', { hasText: menuName });
  
  if (await menuRow.count() === 0) {
    throw new Error(`Menu "${menuName}" not found`);
  }

  // Get menu ID from manage items button or URL
  const manageButton = menuRow.locator('button:has-text("Manage Items")');
  await manageButton.click();
  
  await page.waitForURL(/\/menus\/(\d+)\/edit/);
  const match = page.url().match(/\/menus\/(\d+)\/edit/);
  return match ? parseInt(match[1]) : null;
}

/**
 * Setup common test data and return cleanup function
 * @param {import('@playwright/test').Page} page 
 */
async function setupTestData(page) {
  const testMenus = [];
  
  return {
    async createMenu(menuData) {
      const menu = await createTestMenu(page, menuData);
      testMenus.push(menu);
      return menu;
    },
    
    async cleanup() {
      for (const menu of testMenus) {
        await deleteTestMenu(page, menu.name);
      }
    }
  };
}

module.exports = {
  loginToNova,
  navigateToMenus,
  navigateToMenuEdit,
  createTestMenu,
  deleteTestMenu,
  createMenuItem,
  waitForNovaReady,
  handleNovaErrors,
  getMenuId,
  setupTestData
};