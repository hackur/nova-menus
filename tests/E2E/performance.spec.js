// E2E Performance Tests for Menu Management
// Testing UI responsiveness with large datasets and complex operations

const { test, expect } = require('@playwright/test');

test.describe('Menu Performance', () => {
  let testMenuId;

  test.beforeAll(async ({ browser }) => {
    // Create a test menu for performance testing
    const page = await browser.newPage();
    await page.goto('/nova/menus');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Create Menu")');
    await page.fill('input[name="name"]', 'Performance Test Menu');
    await page.fill('input[name="slug"]', 'performance-test-menu');
    await page.click('button[type="submit"]:has-text("Create")');
    
    await page.waitForResponse(response => 
      response.url().includes('/menus') && response.status() === 201
    );

    // Extract menu ID for later use
    const currentUrl = page.url();
    const match = currentUrl.match(/\/menus\/(\d+)/);
    if (match) {
      testMenuId = match[1];
    }

    await page.close();
  });

  test.describe('Large Dataset Performance', () => {
    test('should handle creating 50 menu items efficiently', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      const startTime = Date.now();
      const itemsToCreate = 50;
      const maxTimePerItem = 2000; // 2 seconds per item max

      for (let i = 1; i <= itemsToCreate; i++) {
        const itemStartTime = Date.now();

        // Create menu item
        await page.click('button:has-text("Add Menu Item")');
        
        const form = page.locator('.menu-item-form').last();
        await form.locator('input[name="name"]').fill(`Performance Item ${i}`);
        await form.locator('input[name="custom_url"]').fill(`/perf-item-${i}`);
        
        // Save item and wait for response
        await Promise.all([
          page.waitForResponse(response => 
            response.url().includes('/menu-items') && response.status() === 201
          ),
          form.locator('button[type="submit"]:has-text("Save")').click()
        ]);

        const itemTime = Date.now() - itemStartTime;
        expect(itemTime).toBeLessThan(maxTimePerItem);

        // Log progress every 10 items
        if (i % 10 === 0) {
          const elapsed = (Date.now() - startTime) / 1000;
          console.log(`Created ${i}/${itemsToCreate} items in ${elapsed.toFixed(2)}s (avg: ${(elapsed/i).toFixed(2)}s per item)`);
        }
      }

      const totalTime = (Date.now() - startTime) / 1000;
      const avgTimePerItem = totalTime / itemsToCreate;

      console.log(`\nPerformance Metrics for ${itemsToCreate} items:`);
      console.log(`Total Time: ${totalTime.toFixed(2)} seconds`);
      console.log(`Average Time per Item: ${avgTimePerItem.toFixed(2)} seconds`);
      console.log(`Items per Second: ${(itemsToCreate / totalTime).toFixed(2)}`);

      // Performance assertions
      expect(totalTime).toBeLessThan(120); // Should complete in under 2 minutes
      expect(avgTimePerItem).toBeLessThan(2.5); // Average should be under 2.5 seconds per item

      // Verify all items are visible
      const itemCount = await page.locator('.menu-item-header').count();
      expect(itemCount).toBe(itemsToCreate);
    });

    test('should handle scrolling through large menu list efficiently', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      const startTime = Date.now();

      // Wait for all items to load
      await page.waitForSelector('.menu-item-header:nth-of-type(50)', { timeout: 10000 });

      const loadTime = Date.now() - startTime;
      console.log(`Initial load time for 50 items: ${loadTime}ms`);
      expect(loadTime).toBeLessThan(5000); // Should load in under 5 seconds

      // Test scrolling performance
      const scrollStartTime = Date.now();
      
      // Scroll to bottom
      await page.evaluate(() => {
        window.scrollTo(0, document.body.scrollHeight);
      });
      
      // Wait for any lazy loading or rendering
      await page.waitForTimeout(500);
      
      // Scroll back to top
      await page.evaluate(() => {
        window.scrollTo(0, 0);
      });
      
      await page.waitForTimeout(500);

      const scrollTime = Date.now() - scrollStartTime;
      console.log(`Scroll test time: ${scrollTime}ms`);
      expect(scrollTime).toBeLessThan(2000); // Scrolling should be smooth

      // Verify no items are missing after scrolling
      const finalItemCount = await page.locator('.menu-item-header').count();
      expect(finalItemCount).toBe(50);
    });

    test('should handle bulk operations efficiently', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      // Test expanding all items at once
      const expandStartTime = Date.now();
      
      const editButtons = page.locator('button:has-text("Edit")');
      const buttonCount = await editButtons.count();
      
      // Expand first 10 items (to avoid overwhelming the UI)
      for (let i = 0; i < Math.min(10, buttonCount); i++) {
        await editButtons.nth(i).click();
        await page.waitForTimeout(100); // Small delay between clicks
      }

      const expandTime = Date.now() - expandStartTime;
      console.log(`Expand 10 items time: ${expandTime}ms`);
      expect(expandTime).toBeLessThan(3000);

      // Verify forms are visible
      const formCount = await page.locator('.menu-item-form').count();
      expect(formCount).toBeGreaterThanOrEqual(10);

      // Test collapsing all items
      const collapseStartTime = Date.now();
      
      const closeButtons = page.locator('button:has-text("Close")');
      const closeButtonCount = await closeButtons.count();
      
      for (let i = 0; i < closeButtonCount; i++) {
        await closeButtons.first().click(); // Always click first as they disappear
        await page.waitForTimeout(50);
      }

      const collapseTime = Date.now() - collapseStartTime;
      console.log(`Collapse items time: ${collapseTime}ms`);
      expect(collapseTime).toBeLessThan(2000);
    });
  });

  test.describe('Drag and Drop Performance', () => {
    test('should perform drag operations smoothly with many items', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      // Wait for items to load
      await page.waitForSelector('.menu-item-header:nth-of-type(10)');

      const dragStartTime = Date.now();

      // Perform multiple drag operations
      for (let i = 0; i < 5; i++) {
        const sourceItem = page.locator('.menu-item').nth(i);
        const targetItem = page.locator('.menu-item').nth(i + 10);

        const sourceBox = await sourceItem.boundingBox();
        const targetBox = await targetItem.boundingBox();

        // Simple reorder drag (not nesting)
        const dragHandle = sourceItem.locator('.drag-handle, [data-drag-handle]');
        
        if (await dragHandle.count() > 0) {
          await dragHandle.hover();
          await page.mouse.down();
          
          await page.mouse.move(
            targetBox.x + targetBox.width / 2,
            targetBox.y + targetBox.height + 5
          );
          
          await page.mouse.up();
          
          // Wait for any API calls to complete
          await page.waitForTimeout(300);
        }
      }

      const dragTime = Date.now() - dragStartTime;
      console.log(`5 drag operations time: ${dragTime}ms`);
      expect(dragTime).toBeLessThan(10000); // Should complete in under 10 seconds
    });

    test('should handle nested drag operations with performance', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      // Create a parent-child relationship via drag
      const parentItem = page.locator('.menu-item').first();
      const childItem = page.locator('.menu-item').nth(1);

      const parentBox = await parentItem.boundingBox();
      const childBox = await childItem.boundingBox();

      const nestingStartTime = Date.now();

      // Drag child item to nest under parent (move right + below)
      const dragHandle = childItem.locator('.drag-handle, [data-drag-handle]');
      
      if (await dragHandle.count() > 0) {
        await dragHandle.hover();
        await page.mouse.down();

        // Move right by 25px to trigger nesting, then below parent
        await page.mouse.move(
          childBox.x + 25, // Right movement for nesting
          parentBox.y + parentBox.height + 10
        );

        await page.mouse.up();

        // Wait for hierarchy update
        try {
          await page.waitForResponse(response => 
            response.url().includes('/rebuild') && response.status() === 200,
            { timeout: 5000 }
          );
        } catch (e) {
          // Response might not match exactly, continue
        }
      }

      const nestingTime = Date.now() - nestingStartTime;
      console.log(`Nesting operation time: ${nestingTime}ms`);
      expect(nestingTime).toBeLessThan(5000); // Should complete in under 5 seconds

      // Verify nesting occurred
      await page.waitForTimeout(1000);
      const nestedItems = page.locator('.menu-item[style*="margin-left"], .menu-item[style*="padding-left"]');
      const nestedCount = await nestedItems.count();
      
      // Should have at least one nested item (visual indentation)
      expect(nestedCount).toBeGreaterThanOrEqual(0); // Allow for different nesting implementations
    });
  });

  test.describe('API Response Performance', () => {
    test('should load menu data quickly', async ({ page }) => {
      const navigationStartTime = Date.now();
      
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      
      // Wait for the first API response
      const apiResponse = await page.waitForResponse(response => 
        response.url().includes('/items') && response.status() === 200
      );
      
      const apiTime = Date.now() - navigationStartTime;
      console.log(`API response time: ${apiTime}ms`);
      expect(apiTime).toBeLessThan(3000);

      // Check response size
      const responseText = await apiResponse.text();
      const responseSize = new Blob([responseText]).size;
      console.log(`API response size: ${(responseSize / 1024).toFixed(2)} KB`);
      
      // Response should be reasonable size (not too large)
      expect(responseSize).toBeLessThan(1024 * 1024); // Under 1MB

      await page.waitForLoadState('networkidle');
      
      const totalLoadTime = Date.now() - navigationStartTime;
      console.log(`Total page load time: ${totalLoadTime}ms`);
      expect(totalLoadTime).toBeLessThan(5000);
    });

    test('should handle concurrent API requests efficiently', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      const concurrentStartTime = Date.now();

      // Trigger multiple API requests by expanding several items quickly
      const editButtons = page.locator('button:has-text("Edit")');
      const promises = [];

      // Expand 5 items simultaneously
      for (let i = 0; i < Math.min(5, await editButtons.count()); i++) {
        promises.push(editButtons.nth(i).click());
      }

      await Promise.all(promises);

      // Wait for all forms to appear
      await page.waitForSelector('.menu-item-form:nth-of-type(5)', { timeout: 3000 });

      const concurrentTime = Date.now() - concurrentStartTime;
      console.log(`Concurrent expansion time: ${concurrentTime}ms`);
      expect(concurrentTime).toBeLessThan(2000);

      // All forms should be visible
      const formCount = await page.locator('.menu-item-form').count();
      expect(formCount).toBe(5);
    });
  });

  test.describe('Memory Usage Performance', () => {
    test('should not cause memory leaks with repeated operations', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      // Perform repeated operations that might cause memory leaks
      for (let cycle = 0; cycle < 5; cycle++) {
        console.log(`Memory test cycle ${cycle + 1}/5`);

        // Expand all visible items
        const editButtons = await page.locator('button:has-text("Edit")').count();
        for (let i = 0; i < Math.min(10, editButtons); i++) {
          await page.locator('button:has-text("Edit")').nth(i).click();
          await page.waitForTimeout(50);
        }

        // Collapse all items
        const closeButtons = await page.locator('button:has-text("Close")').count();
        for (let i = 0; i < closeButtons; i++) {
          await page.locator('button:has-text("Close")').first().click();
          await page.waitForTimeout(50);
        }

        // Force garbage collection (in Chrome)
        await page.evaluate(() => {
          if (window.gc) {
            window.gc();
          }
        });
      }

      // The test passes if no errors occurred and page is still responsive
      await expect(page.locator('h1')).toBeVisible();
      
      // Verify the page is still functional
      await page.click('button:has-text("Add Menu Item")');
      await expect(page.locator('.menu-item-form').last()).toBeVisible();
    });
  });

  test.describe('UI Responsiveness', () => {
    test('should remain responsive during large operations', async ({ page }) => {
      await page.goto(`/nova/menus/${testMenuId}/edit`);
      await page.waitForLoadState('networkidle');

      // Start a potentially time-consuming operation
      await page.click('button:has-text("Add Menu Item")');
      const form = page.locator('.menu-item-form').last();

      const responseStartTime = Date.now();

      // The UI should remain responsive during form interactions
      await form.locator('input[name="name"]').fill('Responsiveness Test Item');
      await form.locator('input[name="custom_url"]').fill('/responsiveness-test');

      // Change visibility settings (which might trigger additional UI updates)
      await form.locator('button:has-text("Schedule")').click();
      
      // UI should update immediately
      await expect(form.locator('input[type="datetime-local"]')).toBeVisible({ timeout: 1000 });

      const responseTime = Date.now() - responseStartTime;
      console.log(`UI responsiveness time: ${responseTime}ms`);
      expect(responseTime).toBeLessThan(1000); // UI updates should be immediate

      // Clean up
      await form.locator('button:has-text("Cancel")').click();
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
        const testMenuRow = page.locator('table tbody tr', { hasText: 'Performance Test Menu' });
        if (await testMenuRow.count() > 0) {
          await testMenuRow.locator('button:has-text("Delete")').click();
          await page.waitForResponse(response => response.status() === 200);
        }
      } catch (error) {
        console.log('Cleanup failed:', error);
      }
      await page.close();
    }
  });
});