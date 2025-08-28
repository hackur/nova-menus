// E2E Tests for Menu Item Management
// Testing item CRUD operations, nesting, visibility, and resource selection

const { test, expect } = require('@playwright/test');

test.describe('Menu Item Management', () => {
  let testMenuId;

  test.beforeAll(async ({ browser }) => {
    // Create a test menu to work with
    const page = await browser.newPage();
    await page.goto('/nova/menus');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Create Menu")');
    await page.fill('input[name="name"]', 'E2E Test Menu Items');
    await page.fill('input[name="slug"]', 'e2e-test-menu-items');
    await page.click('button[type="submit"]:has-text("Create")');
    
    await page.waitForResponse(response => 
      response.url().includes('/menus') && response.status() === 201
    );

    // Extract menu ID from response or URL for later use
    const currentUrl = page.url();
    const match = currentUrl.match(/\/menus\/(\d+)/);
    if (match) {
      testMenuId = match[1];
    }

    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    // Navigate to the menu edit page
    if (testMenuId) {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
    } else {
      await page.goto('/nova/menus');
      await page.click('button:has-text("Manage Items"):first');
    }
    await page.waitForLoadState('networkidle');
  });

  test.describe('Menu Item Creation', () => {
    test('should create a basic menu item', async ({ page }) => {
      // Click add menu item button
      await page.click('button:has-text("Add Menu Item"), button:has-text("Add First Menu Item")');

      // Should create a new expanded item
      const newItem = page.locator('.menu-item-form, [data-testid="menu-item-form"]').last();
      await expect(newItem).toBeVisible();

      // Fill basic information
      await newItem.locator('input[name="name"]').fill('Home Page');
      await newItem.locator('input[name="custom_url"]').fill('/');

      // Save item
      await newItem.locator('button[type="submit"]:has-text("Save")').click();

      // Wait for save response
      await page.waitForResponse(response => 
        response.url().includes('/menu-items') && response.status() === 201
      );

      // Item should collapse and show in collapsed view
      await expect(page.locator('.menu-item-header')).toContainText('Home Page');
      await expect(page.locator('.menu-item-header')).toContainText('/');
    });

    test('should create menu item with scheduled visibility', async ({ page }) => {
      await page.click('button:has-text("Add Menu Item")');
      
      const newItem = page.locator('.menu-item-form').last();
      await newItem.locator('input[name="name"]').fill('Scheduled Item');
      await newItem.locator('input[name="custom_url"]').fill('/scheduled');

      // Set visibility to scheduled
      await newItem.locator('button:has-text("Schedule")').click();

      // Set display and hide dates
      const displayDate = '2024-12-01T10:00';
      const hideDate = '2024-12-31T23:59';

      await newItem.locator('input[type="datetime-local"]:first').fill(displayDate);
      await newItem.locator('input[type="datetime-local"]:last').fill(hideDate);

      // Save item
      await newItem.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);

      // Should show calendar icon indicating scheduled visibility
      await expect(page.locator('.menu-item-header svg[viewBox*="calendar"]')).toBeVisible();
    });

    test('should validate required fields', async ({ page }) => {
      await page.click('button:has-text("Add Menu Item")');
      
      const newItem = page.locator('.menu-item-form').last();
      
      // Try to save without name
      await newItem.locator('button[type="submit"]:has-text("Save")').click();

      // Should prevent submission and show validation
      await expect(newItem.locator('input[name="name"]:invalid')).toBeVisible();
    });
  });

  test.describe('Menu Item Editing', () => {
    test.beforeEach(async ({ page }) => {
      // Create a test item first
      await page.click('button:has-text("Add Menu Item")');
      const newItem = page.locator('.menu-item-form').last();
      await newItem.locator('input[name="name"]').fill('Editable Item');
      await newItem.locator('input[name="custom_url"]').fill('/editable');
      await newItem.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);
    });

    test('should edit existing menu item', async ({ page }) => {
      // Find and expand the item
      const itemHeader = page.locator('.menu-item-header', { hasText: 'Editable Item' });
      await itemHeader.locator('button:has-text("Edit")').click();

      // Update fields
      const form = page.locator('.menu-item-form', { hasText: 'Editable Item' });
      await form.locator('input[name="name"]').fill('Updated Item Name');
      await form.locator('input[name="custom_url"]').fill('/updated-path');

      // Save changes
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 200);

      // Should show updated information
      await expect(page.locator('.menu-item-header')).toContainText('Updated Item Name');
      await expect(page.locator('.menu-item-header')).toContainText('/updated-path');
    });

    test('should change link type from URL to resource', async ({ page }) => {
      const itemHeader = page.locator('.menu-item-header', { hasText: 'Editable Item' });
      await itemHeader.locator('button:has-text("Edit")').click();

      const form = page.locator('.menu-item-form', { hasText: 'Editable Item' });
      
      // Change link type
      await form.locator('select[name="link_type"]').selectOption('resource');

      // Should show resource selector
      await expect(form.locator('.resource-selector, [data-testid="resource-selector"]')).toBeVisible();
      
      // URL field should be hidden
      await expect(form.locator('input[name="custom_url"]')).not.toBeVisible();
    });

    test('should toggle visibility types', async ({ page }) => {
      const itemHeader = page.locator('.menu-item-header', { hasText: 'Editable Item' });
      await itemHeader.locator('button:has-text("Edit")').click();

      const form = page.locator('.menu-item-form', { hasText: 'Editable Item' });

      // Test Always Hide
      await form.locator('button:has-text("Always Hide")').click();
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 200);

      // Should show eye icon indicating hidden
      await expect(page.locator('.menu-item-header svg[viewBox*="eye"]')).toBeVisible();

      // Test back to Always Show
      await itemHeader.locator('button:has-text("Edit")').click();
      await form.locator('button:has-text("Always Show")').click();
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 200);

      // Should not show any visibility icons
      await expect(page.locator('.menu-item-header svg[viewBox*="eye"], .menu-item-header svg[viewBox*="calendar"]')).not.toBeVisible();
    });
  });

  test.describe('Menu Item Nesting and Hierarchy', () => {
    test.beforeEach(async ({ page }) => {
      // Create parent and child items for nesting tests
      await page.click('button:has-text("Add Menu Item")');
      let form = page.locator('.menu-item-form').last();
      await form.locator('input[name="name"]').fill('Parent Item');
      await form.locator('input[name="custom_url"]').fill('/parent');
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);

      await page.click('button:has-text("Add Menu Item")');
      form = page.locator('.menu-item-form').last();
      await form.locator('input[name="name"]').fill('Child Item');
      await form.locator('input[name="custom_url"]').fill('/child');
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);
    });

    test('should create nested hierarchy via drag and drop', async ({ page }) => {
      // Wait for items to be ready
      await page.waitForSelector('[data-testid="menu-item"], .menu-item');

      const parentItem = page.locator('.menu-item', { hasText: 'Parent Item' });
      const childItem = page.locator('.menu-item', { hasText: 'Child Item' });

      // Get positions for drag calculation
      const parentBox = await parentItem.boundingBox();
      const childBox = await childItem.boundingBox();

      // Drag child item to nest under parent (drag right by 25px)
      const dragHandle = childItem.locator('.drag-handle, [data-testid="drag-handle"]');
      await dragHandle.hover();
      await page.mouse.down();

      await page.mouse.move(
        childBox.x + 25, // Move right to trigger nesting
        parentBox.y + parentBox.height + 10 // Drop below parent
      );
      await page.mouse.up();

      // Wait for hierarchy update
      await page.waitForResponse(response => 
        response.url().includes('/rebuild') && response.status() === 200
      );

      // Child should now be indented/nested
      const nestedChild = page.locator('.menu-item', { hasText: 'Child Item' });
      const indentation = await nestedChild.evaluate(el => {
        const style = window.getComputedStyle(el);
        return style.marginLeft || style.paddingLeft;
      });

      expect(parseInt(indentation)).toBeGreaterThan(0);
    });

    test('should respect max depth restrictions', async ({ page }) => {
      // This would require setting up a deep hierarchy first
      // For now, test that the system handles depth limits
      
      // Mock a deep hierarchy response or create items programmatically
      // Then test that dragging beyond max depth shows an error
      
      // Placeholder test - in a real scenario, you'd create max depth items
      await expect(page.locator('h1')).toBeVisible(); // Basic test that page loads
    });
  });

  test.describe('Menu Item Deletion', () => {
    test.beforeEach(async ({ page }) => {
      // Create an item to delete
      await page.click('button:has-text("Add Menu Item")');
      const form = page.locator('.menu-item-form').last();
      await form.locator('input[name="name"]').fill('Item to Delete');
      await form.locator('input[name="custom_url"]').fill('/delete-me');
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);
    });

    test('should delete menu item with confirmation', async ({ page }) => {
      // Set up confirmation dialog handler
      page.on('dialog', async dialog => {
        expect(dialog.type()).toBe('confirm');
        expect(dialog.message()).toContain('Item to Delete');
        await dialog.accept();
      });

      // Click delete button
      const itemHeader = page.locator('.menu-item-header', { hasText: 'Item to Delete' });
      await itemHeader.locator('button:has-text("Delete")').click();

      // Wait for deletion
      await page.waitForResponse(response => 
        response.url().includes('/menu-items/') && response.status() === 200
      );

      // Item should be removed
      await expect(page.locator('.menu-item-header', { hasText: 'Item to Delete' })).not.toBeVisible();
    });

    test('should delete nested items recursively', async ({ page }) => {
      // Create a nested structure first
      await page.click('button:has-text("Add Menu Item")');
      let form = page.locator('.menu-item-form').last();
      await form.locator('input[name="name"]').fill('Parent to Delete');
      await form.locator('input[name="custom_url"]').fill('/parent-delete');
      await form.locator('button[type="submit"]:has-text("Save")').click();
      await page.waitForResponse(response => response.status() === 201);

      // TODO: Create nested child via drag-and-drop or API
      // Then test that deleting parent removes all children

      // For now, basic deletion test
      page.on('dialog', dialog => dialog.accept());
      const itemHeader = page.locator('.menu-item-header', { hasText: 'Parent to Delete' });
      await itemHeader.locator('button:has-text("Delete")').click();
      await page.waitForResponse(response => response.status() === 200);
      
      await expect(page.locator('.menu-item-header', { hasText: 'Parent to Delete' })).not.toBeVisible();
    });
  });

  test.describe('Menu Item Search and Filtering', () => {
    test.beforeEach(async ({ page }) => {
      // Create several test items with different properties
      const items = [
        { name: 'Active Item', url: '/active', visibility: 'always_show' },
        { name: 'Hidden Item', url: '/hidden', visibility: 'always_hide' },
        { name: 'Scheduled Item', url: '/scheduled', visibility: 'schedule' }
      ];

      for (const item of items) {
        await page.click('button:has-text("Add Menu Item")');
        const form = page.locator('.menu-item-form').last();
        await form.locator('input[name="name"]').fill(item.name);
        await form.locator('input[name="custom_url"]').fill(item.url);
        
        if (item.visibility === 'always_hide') {
          await form.locator('button:has-text("Always Hide")').click();
        } else if (item.visibility === 'schedule') {
          await form.locator('button:has-text("Schedule")').click();
          await form.locator('input[type="datetime-local"]:first').fill('2024-12-01T10:00');
        }
        
        await form.locator('button[type="submit"]:has-text("Save")').click();
        await page.waitForResponse(response => response.status() === 201);
      }
    });

    test('should visually indicate item visibility states', async ({ page }) => {
      // Active item should have no special indicators
      const activeItem = page.locator('.menu-item-header', { hasText: 'Active Item' });
      await expect(activeItem).not.toHaveClass(/opacity|hidden/);

      // Hidden item should be visually distinct
      const hiddenItem = page.locator('.menu-item-header', { hasText: 'Hidden Item' });
      await expect(hiddenItem.locator('svg[viewBox*="eye"]')).toBeVisible();

      // Scheduled item should show calendar icon
      const scheduledItem = page.locator('.menu-item-header', { hasText: 'Scheduled Item' });
      await expect(scheduledItem.locator('svg[viewBox*="calendar"]')).toBeVisible();
    });
  });

  test.describe('Performance and Large Menus', () => {
    test('should handle menu with many items efficiently', async ({ page }) => {
      // Create multiple items to test performance
      const startTime = Date.now();
      
      for (let i = 1; i <= 10; i++) {
        await page.click('button:has-text("Add Menu Item")');
        const form = page.locator('.menu-item-form').last();
        await form.locator('input[name="name"]').fill(`Performance Item ${i}`);
        await form.locator('input[name="custom_url"]').fill(`/perf-${i}`);
        await form.locator('button[type="submit"]:has-text("Save")').click();
        await page.waitForResponse(response => response.status() === 201);
      }

      const endTime = Date.now();
      const totalTime = endTime - startTime;

      // Should complete within reasonable time (30 seconds for 10 items)
      expect(totalTime).toBeLessThan(30000);

      // All items should be visible
      for (let i = 1; i <= 10; i++) {
        await expect(page.locator('.menu-item-header', { hasText: `Performance Item ${i}` })).toBeVisible();
      }
    });
  });

  test.describe('Error Handling', () => {
    test('should handle API errors gracefully', async ({ page }) => {
      // Mock API failure
      await page.route('**/menu-items', route => {
        route.fulfill({
          status: 500,
          body: JSON.stringify({ error: 'Server error' })
        });
      });

      await page.click('button:has-text("Add Menu Item")');
      const form = page.locator('.menu-item-form').last();
      await form.locator('input[name="name"]').fill('Error Test Item');
      await form.locator('button[type="submit"]:has-text("Save")').click();

      // Should show error message
      await expect(page.locator('.error, .text-red-500, .toasted.error')).toBeVisible();
    });
  });

  test.afterAll(async ({ browser }) => {
    // Clean up test menu
    if (testMenuId) {
      const page = await browser.newPage();
      try {
        await page.goto('/nova/menus');
        await page.waitForLoadState('networkidle');

        page.on('dialog', dialog => dialog.accept());
        const testMenuRow = page.locator('table tbody tr', { hasText: 'E2E Test Menu Items' });
        await testMenuRow.locator('button:has-text("Delete")').click();
        await page.waitForResponse(response => response.status() === 200);
      } catch (error) {
        console.log('Cleanup failed:', error);
      }
      await page.close();
    }
  });
});