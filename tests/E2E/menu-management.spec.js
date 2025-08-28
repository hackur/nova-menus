// E2E Tests for Basic Menu Management
// Testing CRUD operations, navigation, and UI functionality

const { test, expect } = require('@playwright/test');

test.describe('Menu Management', () => {
  
  // Test data
  const testMenuData = {
    name: 'Test Menu E2E',
    slug: 'test-menu-e2e',
    maxDepth: 5
  };

  test.beforeEach(async ({ page }) => {
    // Navigate to Nova admin menu tool
    await page.goto('/nova/menus');
    await page.waitForLoadState('networkidle');
  });

  test.describe('Menu List Management', () => {
    test('should display menu list interface', async ({ page }) => {
      // Verify page title and heading
      await expect(page).toHaveTitle(/Menus/);
      await expect(page.locator('h1')).toContainText('Menus');

      // Verify search functionality exists
      await expect(page.locator('input[placeholder*="Search"]')).toBeVisible();

      // Verify create button exists
      await expect(page.locator('button', { hasText: 'Create Menu' })).toBeVisible();
    });

    test('should search and filter menus', async ({ page }) => {
      // Create test menu first if needed
      await page.click('button:has-text("Create Menu")');
      await page.fill('input[name="name"]', testMenuData.name);
      await page.click('button[type="submit"]:has-text("Create")');
      await page.waitForLoadState('networkidle');

      // Search for the created menu
      const searchInput = page.locator('input[placeholder*="Search"]');
      await searchInput.fill('Test Menu');
      
      // Should show only matching results
      await expect(page.locator('table tbody tr')).toContainText(testMenuData.name);
      
      // Clear search
      await searchInput.clear();
      
      // Should show all menus again
      await page.waitForTimeout(500);
      await expect(page.locator('table')).toBeVisible();
    });
  });

  test.describe('Menu Creation', () => {
    test('should create a new menu with all fields', async ({ page }) => {
      // Open create modal
      await page.click('button:has-text("Create Menu")');
      
      // Verify modal appears
      await expect(page.locator('.modal, [role="dialog"]')).toBeVisible();

      // Fill form fields
      await page.fill('input[name="name"]', testMenuData.name);
      await page.fill('input[name="slug"]', testMenuData.slug);
      await page.selectOption('select[name="max_depth"]', testMenuData.maxDepth.toString());

      // Submit form
      await page.click('button[type="submit"]:has-text("Create")');

      // Wait for success and modal to close
      await page.waitForResponse(response => 
        response.url().includes('/menus') && response.status() === 201
      );
      await expect(page.locator('.modal, [role="dialog"]')).not.toBeVisible();

      // Verify menu appears in list
      await expect(page.locator('table')).toContainText(testMenuData.name);
      await expect(page.locator('table')).toContainText(testMenuData.slug);
    });

    test('should auto-generate slug from name', async ({ page }) => {
      await page.click('button:has-text("Create Menu")');
      
      const nameInput = page.locator('input[name="name"]');
      const slugInput = page.locator('input[name="slug"]');

      // Type name and check if slug is generated
      await nameInput.fill('Auto Generated Menu Name');
      await nameInput.blur(); // Trigger slug generation

      await expect(slugInput).toHaveValue('auto-generated-menu-name');
    });

    test('should validate required fields', async ({ page }) => {
      await page.click('button:has-text("Create Menu")');
      
      // Try to submit empty form
      await page.click('button[type="submit"]:has-text("Create")');

      // Should show validation errors
      await expect(page.locator('.text-red-500, .error')).toBeVisible();
    });
  });

  test.describe('Menu Editing', () => {
    test.beforeEach(async ({ page }) => {
      // Create a test menu for editing
      await page.click('button:has-text("Create Menu")');
      await page.fill('input[name="name"]', 'Menu for Editing');
      await page.fill('input[name="slug"]', 'menu-for-editing');
      await page.click('button[type="submit"]:has-text("Create")');
      await page.waitForLoadState('networkidle');
    });

    test('should edit menu properties', async ({ page }) => {
      // Find and click edit button for our test menu
      const menuRow = page.locator('table tbody tr', { hasText: 'Menu for Editing' });
      await menuRow.locator('button:has-text("Edit")').click();

      // Update fields
      await page.fill('input[name="name"]', 'Updated Menu Name');
      await page.selectOption('select[name="max_depth"]', '8');

      // Save changes
      await page.click('button[type="submit"]:has-text("Update")');

      // Wait for success
      await page.waitForResponse(response => 
        response.status() === 200 && response.url().includes('/menus/')
      );

      // Verify changes in the list
      await expect(page.locator('table')).toContainText('Updated Menu Name');
    });

    test('should navigate to menu item management', async ({ page }) => {
      const menuRow = page.locator('table tbody tr', { hasText: 'Menu for Editing' });
      await menuRow.locator('button:has-text("Manage Items")').click();

      // Should navigate to edit page
      await expect(page).toHaveURL(/\/menus\/\d+\/edit/);
      await expect(page.locator('h1')).toContainText('Menu for Editing');
    });
  });

  test.describe('Menu Deletion', () => {
    test('should delete menu with confirmation', async ({ page }) => {
      // Create a menu to delete
      await page.click('button:has-text("Create Menu")');
      await page.fill('input[name="name"]', 'Menu to Delete');
      await page.click('button[type="submit"]:has-text("Create")');
      await page.waitForLoadState('networkidle');

      // Set up dialog handler
      page.on('dialog', async dialog => {
        expect(dialog.type()).toBe('confirm');
        expect(dialog.message()).toContain('Menu to Delete');
        await dialog.accept();
      });

      // Click delete button
      const menuRow = page.locator('table tbody tr', { hasText: 'Menu to Delete' });
      await menuRow.locator('button:has-text("Delete")').click();

      // Wait for deletion
      await page.waitForResponse(response => 
        response.status() === 200 && response.url().includes('/menus/')
      );

      // Verify menu is removed from list
      await expect(page.locator('table')).not.toContainText('Menu to Delete');
    });

    test('should cancel deletion when user rejects confirmation', async ({ page }) => {
      // Create a menu
      await page.click('button:has-text("Create Menu")');
      await page.fill('input[name="name"]', 'Menu to Keep');
      await page.click('button[type="submit"]:has-text("Create")');
      await page.waitForLoadState('networkidle');

      // Set up dialog handler to cancel
      page.on('dialog', async dialog => {
        await dialog.dismiss();
      });

      // Click delete button
      const menuRow = page.locator('table tbody tr', { hasText: 'Menu to Keep' });
      await menuRow.locator('button:has-text("Delete")').click();

      // Menu should still exist
      await expect(page.locator('table')).toContainText('Menu to Keep');
    });
  });

  test.describe('Error Handling', () => {
    test('should handle network errors gracefully', async ({ page }) => {
      // Intercept and fail API request
      await page.route('**/nova-vendor/menus/menus', route => {
        route.fulfill({
          status: 500,
          body: JSON.stringify({ error: 'Server error' })
        });
      });

      await page.reload();
      await page.waitForLoadState('networkidle');

      // Should show error message
      await expect(page.locator('.error, .text-red-500')).toBeVisible();
    });

    test('should handle validation errors on creation', async ({ page }) => {
      // Mock validation error response
      await page.route('**/nova-vendor/menus/menus', route => {
        if (route.request().method() === 'POST') {
          route.fulfill({
            status: 422,
            body: JSON.stringify({
              errors: {
                name: ['The name field is required.'],
                slug: ['The slug has already been taken.']
              }
            })
          });
        } else {
          route.continue();
        }
      });

      await page.click('button:has-text("Create Menu")');
      await page.fill('input[name="name"]', 'Test');
      await page.click('button[type="submit"]:has-text("Create")');

      // Should display validation errors
      await expect(page.locator('.text-red-500')).toContainText('required');
      await expect(page.locator('.text-red-500')).toContainText('already been taken');
    });
  });

  test.describe('Responsive Design', () => {
    test('should work on mobile viewport', async ({ page, isMobile }) => {
      if (!isMobile) {
        await page.setViewportSize({ width: 375, height: 667 });
      }

      // Basic functionality should still work
      await expect(page.locator('h1')).toBeVisible();
      await expect(page.locator('button:has-text("Create Menu")')).toBeVisible();

      // Create menu should work on mobile
      await page.click('button:has-text("Create Menu")');
      await expect(page.locator('.modal, [role="dialog"]')).toBeVisible();
    });
  });

  test.describe('Accessibility', () => {
    test('should have proper ARIA labels and keyboard navigation', async ({ page }) => {
      // Check for proper headings
      const heading = page.locator('h1');
      await expect(heading).toBeVisible();

      // Check button accessibility
      const createButton = page.locator('button:has-text("Create Menu")');
      await expect(createButton).toBeVisible();

      // Test keyboard navigation
      await page.keyboard.press('Tab');
      await expect(page.locator(':focus')).toBeVisible();

      // Form accessibility
      await createButton.click();
      const nameInput = page.locator('input[name="name"]');
      const nameLabel = page.locator('label[for*="name"], label:has-text("Name")');
      
      await expect(nameInput).toBeVisible();
      await expect(nameLabel).toBeVisible();
    });

    test('should support screen reader navigation', async ({ page }) => {
      // Check for semantic HTML elements
      await expect(page.locator('main, [role="main"]')).toBeVisible();
      await expect(page.locator('table[role="table"], table')).toBeVisible();

      // Check for proper table structure
      const table = page.locator('table');
      await expect(table.locator('thead')).toBeVisible();
      await expect(table.locator('tbody')).toBeVisible();
      await expect(table.locator('th')).toHaveCount.greaterThan(0);
    });
  });

  test.afterAll(async ({ page }) => {
    // Clean up test data
    try {
      await page.goto('/nova/menus');
      
      // Delete any remaining test menus
      const testMenus = page.locator('table tbody tr', { 
        hasText: /Test Menu|Menu for|Menu to/ 
      });
      
      const count = await testMenus.count();
      for (let i = 0; i < count; i++) {
        const menu = testMenus.nth(i);
        
        // Set up dialog handler for each deletion
        const dialogPromise = page.waitForEvent('dialog');
        await menu.locator('button:has-text("Delete")').click();
        const dialog = await dialogPromise;
        await dialog.accept();
        
        await page.waitForTimeout(500); // Wait between deletions
      }
    } catch (error) {
      console.log('Cleanup failed:', error);
    }
  });
});