// E2E Tests for Drag-to-Nest Functionality
// Testing drag operations for hierarchical menu item nesting

const { test, expect } = require('@playwright/test');

test.describe('Menu Drag-to-Nest Functionality', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login to Nova admin (adjust URL based on your setup)
    await page.goto('http://localhost:8000/admin/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Navigate to menu edit page (assuming menu ID 1)
    await page.goto('http://localhost:8000/admin/menus/1/edit');
    await page.waitForLoadState('networkidle');
  });

  test('should create child item by dragging right with 20px threshold', async ({ page }) => {
    // Wait for menu items to load
    await page.waitForSelector('[data-testid="menu-item"]', { timeout: 10000 });
    
    const menuItems = await page.locator('[data-testid="menu-item"]').all();
    
    if (menuItems.length < 2) {
      // Create test items if they don't exist
      await page.click('button:has-text("Add Menu Item")');
      await page.fill('input[name="name"]', 'Parent Item');
      await page.fill('input[name="url"]', '/parent');
      await page.click('button:has-text("Save")');
      
      await page.click('button:has-text("Add Menu Item")');  
      await page.fill('input[name="name"]', 'Child Item');
      await page.fill('input[name="url"]', '/child');
      await page.click('button:has-text("Save")');
      
      // Refresh to get updated item list
      await page.reload();
      await page.waitForLoadState('networkidle');
    }
    
    // Get fresh menu items
    const parentItem = page.locator('[data-testid="menu-item"]').first();
    const childItem = page.locator('[data-testid="menu-item"]').nth(1);
    
    // Get bounding boxes for drag calculation
    const parentBox = await parentItem.boundingBox();
    const childBox = await childItem.boundingBox();
    
    console.log('Parent item position:', parentBox);
    console.log('Child item position:', childBox);
    
    // Perform drag operation with 25px horizontal movement (exceeds 20px threshold)
    await childItem.locator('.drag-handle').hover();
    await page.mouse.down();
    
    // Move horizontally right by 25px to trigger nesting, then drop below parent
    await page.mouse.move(
      childBox.x + childBox.width/2 + 25, // 25px right movement
      parentBox.y + parentBox.height + 10  // Drop just below parent
    );
    
    await page.mouse.up();
    
    // Wait for API call to complete
    await page.waitForResponse(response => 
      response.url().includes('/items/reorder') && response.status() === 200
    );
    
    // Verify nesting occurred - child should be indented
    const updatedChildItem = page.locator('[data-testid="menu-item"]').nth(1);
    const childWrapper = updatedChildItem.locator('.menu-item-wrapper');
    const paddingLeft = await childWrapper.evaluate(el => 
      window.getComputedStyle(el).paddingLeft
    );
    
    // Should be indented by 20px (1 level deep)
    expect(paddingLeft).toBe('20px');
  });

  test('should promote item by dragging left', async ({ page }) => {
    // First create a nested structure
    await page.waitForSelector('[data-testid="menu-item"]');
    
    // Assuming we have at least one nested item from previous test
    // Find an indented item (child)
    const nestedItem = page.locator('[data-testid="menu-item"]')
      .locator('.menu-item-wrapper')
      .filter({ hasText: /padding-left.*[1-9]/ });
      
    if (await nestedItem.count() === 0) {
      test.skip('No nested items found to test promotion');
    }
    
    const nestedBox = await nestedItem.boundingBox();
    
    // Drag left by 25px to promote
    await nestedItem.locator('.drag-handle').hover();
    await page.mouse.down();
    
    await page.mouse.move(
      nestedBox.x - 25, // 25px left movement  
      nestedBox.y
    );
    
    await page.mouse.up();
    
    // Wait for API response
    await page.waitForResponse(response => 
      response.url().includes('/items/reorder') && response.status() === 200
    );
    
    // Verify promotion - should have no indentation
    const promotedWrapper = nestedItem.locator('.menu-item-wrapper');
    const paddingLeft = await promotedWrapper.evaluate(el => 
      window.getComputedStyle(el).paddingLeft
    );
    
    expect(paddingLeft).toBe('0px');
  });

  test('should prevent nesting beyond max depth', async ({ page }) => {
    await page.waitForSelector('[data-testid="menu-item"]');
    
    // Get max depth from menu settings
    const maxDepthSelect = page.locator('select[name="max_depth"]');
    const maxDepth = await maxDepthSelect.inputValue();
    console.log('Max depth setting:', maxDepth);
    
    // For this test, let's ensure we have items at max depth
    // This would require setting up a deep hierarchy first
    // For now, we'll test the error condition
    
    const deepestItem = page.locator('[data-testid="menu-item"]').last();
    const targetParent = page.locator('[data-testid="menu-item"]').first();
    
    const itemBox = await deepestItem.boundingBox();
    const parentBox = await targetParent.boundingBox();
    
    // Try to nest beyond max depth
    await deepestItem.locator('.drag-handle').hover();
    await page.mouse.down();
    
    await page.mouse.move(
      itemBox.x + 25, // Right movement
      parentBox.y + parentBox.height + 10
    );
    
    await page.mouse.up();
    
    // Should see error toast
    const errorToast = page.locator('.toasted.error');
    await expect(errorToast).toBeVisible();
    await expect(errorToast).toContainText('Cannot nest deeper than');
  });

  test('should maintain drag handle accessibility', async ({ page }) => {
    await page.waitForSelector('[data-testid="menu-item"]');
    
    const dragHandle = page.locator('.drag-handle').first();
    
    // Verify drag handle is keyboard accessible
    await dragHandle.press('Tab');
    await expect(dragHandle).toBeFocused();
    
    // Verify ARIA attributes
    const ariaLabel = await dragHandle.getAttribute('aria-label');
    expect(ariaLabel).toBeTruthy();
    expect(ariaLabel).toMatch(/drag|move|reorder/i);
  });

  test('should show visual feedback during drag', async ({ page }) => {
    await page.waitForSelector('[data-testid="menu-item"]');
    
    const menuItem = page.locator('[data-testid="menu-item"]').first();
    const dragHandle = menuItem.locator('.drag-handle');
    
    // Start drag
    await dragHandle.hover();
    await page.mouse.down();
    
    // Verify visual feedback classes are applied
    const ghostClass = page.locator('.ghost');
    const dragClass = page.locator('.drag');
    
    await expect(ghostClass.or(dragClass)).toBeVisible();
    
    // End drag
    await page.mouse.up();
    
    // Visual feedback should be removed
    await expect(ghostClass).not.toBeVisible();
    await expect(dragClass).not.toBeVisible();
  });

  test('should preserve accordion functionality during drag', async ({ page }) => {
    await page.waitForSelector('[data-testid="menu-item"]');
    
    const menuItem = page.locator('[data-testid="menu-item"]').first();
    
    // Expand accordion
    const expandButton = menuItem.locator('button[aria-expanded="false"]');
    if (await expandButton.count() > 0) {
      await expandButton.click();
      await expect(expandButton).toHaveAttribute('aria-expanded', 'true');
    }
    
    // Perform a simple drag (no nesting)
    const itemBox = await menuItem.boundingBox();
    const dragHandle = menuItem.locator('.drag-handle');
    
    await dragHandle.hover();
    await page.mouse.down();
    await page.mouse.move(itemBox.x, itemBox.y + 50); // Move down slightly
    await page.mouse.up();
    
    // Wait for reorder
    await page.waitForTimeout(1000);
    
    // Accordion should still be expanded
    const stillExpandedButton = menuItem.locator('button[aria-expanded="true"]');
    await expect(stillExpandedButton).toBeVisible();
  });

  test('should validate API calls for hierarchy updates', async ({ page }) => {
    await page.waitForSelector('[data-testid="menu-item"]');
    
    const childItem = page.locator('[data-testid="menu-item"]').nth(1);
    const parentItem = page.locator('[data-testid="menu-item"]').first();
    
    const childBox = await childItem.boundingBox(); 
    const parentBox = await parentItem.boundingBox();
    
    // Listen for API calls
    const apiPromise = page.waitForRequest(request => 
      request.url().includes('/items/reorder') && 
      request.method() === 'PUT'
    );
    
    // Perform nesting drag
    await childItem.locator('.drag-handle').hover();
    await page.mouse.down();
    await page.mouse.move(
      childBox.x + 25,
      parentBox.y + parentBox.height + 10
    );
    await page.mouse.up();
    
    // Validate API request
    const apiRequest = await apiPromise;
    const postData = apiRequest.postDataJSON();
    
    expect(postData).toHaveProperty('items');
    expect(Array.isArray(postData.items)).toBe(true);
    
    // Should contain parent_id for nested item
    const nestedItem = postData.items.find(item => item.parent_id !== null);
    expect(nestedItem).toBeTruthy();
  });
});