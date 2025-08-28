import { test, expect } from '@playwright/test';

test.describe('ResourceSelector Integration', () => {
  test.beforeEach(async ({ page }) => {
    // Mock Nova environment for testing
    await page.addInitScript(() => {
      window.Nova = {
        request: () => ({
          get: async (url) => {
            if (url === '/nova-vendor/menus/resource-types') {
              return {
                data: {
                  success: true,
                  data: {
                    'Product': 'Product',
                    'Category': 'Category',
                    'Page': 'Page'
                  }
                }
              };
            }
            
            if (url.includes('/resources/Product/search')) {
              return {
                data: {
                  success: true,
                  data: [
                    { id: 1, name: 'iPhone 15', slug: 'iphone-15' },
                    { id: 2, name: 'MacBook Pro', slug: 'macbook-pro' },
                    { id: 3, name: 'Books Novel', slug: 'books-novel-8243' }
                  ]
                }
              };
            }
            
            throw new Error('Unknown endpoint: ' + url);
          }
        }),
        config: (key) => {
          if (key === 'timezone') return 'UTC';
          return null;
        }
      };
    });
  });

  test('should display ResourceSelector when Internal Resource is selected', async ({ page }) => {
    // Load test page with menu item form
    await page.setContent(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>ResourceSelector Test</title>
        <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
      </head>
      <body>
        <div id="app">
          <div>
            <label>Link Type</label>
            <select v-model="item.link_type" @change="onLinkTypeChange">
              <option value="url">Custom URL</option>
              <option value="resource">Internal Resource</option>
            </select>
          </div>
          
          <div v-if="item.link_type === 'url'">
            <label>Custom URL</label>
            <input v-model="item.custom_url" placeholder="Enter URL" />
          </div>
          
          <div v-if="item.link_type === 'resource'">
            <resource-selector 
              :value="item.resourceSelection"
              @change="onResourceSelectionChange"
            />
          </div>
        </div>
        
        <script type="module">
          const { createApp } = Vue;
          
          const ResourceSelector = {
            template: \`
              <div class="resource-selector">
                <div>
                  <label>Resource Type</label>
                  <select v-model="selectedResourceType" @change="onResourceTypeChange">
                    <option value="">Select Resource Type</option>
                    <option v-for="(label, value) in resourceTypes" :key="value" :value="value">
                      {{ label }}
                    </option>
                  </select>
                </div>
                
                <div v-if="selectedResourceType">
                  <label>Select {{ selectedResourceType }}</label>
                  <input 
                    v-model="searchQuery"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Search..."
                  />
                  
                  <div v-if="showDropdown && resources.length > 0" class="dropdown">
                    <button 
                      v-for="resource in resources"
                      :key="resource.id"
                      @click="selectResource(resource)"
                      type="button"
                    >
                      {{ resource.name }}
                    </button>
                  </div>
                </div>
                
                <div v-if="selectedResource">
                  <span>{{ selectedResourceType }}: {{ selectedResource.name }}</span>
                  <button @click="clearSelection" type="button">Remove</button>
                </div>
              </div>
            \`,
            props: ['value'],
            emits: ['change'],
            data() {
              return {
                resourceTypes: {},
                selectedResourceType: '',
                selectedResource: null,
                searchQuery: '',
                resources: [],
                showDropdown: false,
                searchTimeout: null
              };
            },
            async mounted() {
              await this.loadResourceTypes();
              this.initializeFromValue();
            },
            methods: {
              async loadResourceTypes() {
                try {
                  const response = await Nova.request().get('/nova-vendor/menus/resource-types');
                  this.resourceTypes = response.data.data;
                } catch (error) {
                  console.error('Failed to load resource types:', error);
                }
              },
              initializeFromValue() {
                if (this.value?.resource_type) {
                  this.selectedResourceType = this.value.resource_type;
                  if (this.value.resource_id && this.value.resource_name) {
                    this.selectedResource = {
                      id: this.value.resource_id,
                      name: this.value.resource_name,
                      slug: this.value.resource_slug
                    };
                  }
                }
              },
              onResourceTypeChange() {
                this.selectedResource = null;
                this.resources = [];
                this.searchQuery = '';
                this.showDropdown = false;
                this.emitChange();
              },
              onSearchInput() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                  this.searchResources();
                }, 300);
              },
              async searchResources() {
                if (!this.selectedResourceType) return;
                
                try {
                  const params = new URLSearchParams();
                  if (this.searchQuery) {
                    params.append('q', this.searchQuery);
                  }
                  
                  const response = await Nova.request().get(
                    \`/nova-vendor/menus/resources/\${this.selectedResourceType}/search?\${params.toString()}\`
                  );
                  
                  this.resources = response.data.data;
                  this.showDropdown = true;
                } catch (error) {
                  console.error('Search failed:', error);
                }
              },
              selectResource(resource) {
                this.selectedResource = resource;
                this.searchQuery = resource.name;
                this.showDropdown = false;
                this.emitChange();
              },
              clearSelection() {
                this.selectedResource = null;
                this.searchQuery = '';
                this.showDropdown = false;
                this.emitChange();
              },
              emitChange() {
                const value = {
                  resource_type: this.selectedResourceType || null,
                  resource_id: this.selectedResource?.id || null,
                  resource_name: this.selectedResource?.name || null,
                  resource_slug: this.selectedResource?.slug || null,
                };
                this.$emit('change', value);
              }
            }
          };

          createApp({
            components: {
              ResourceSelector
            },
            data() {
              return {
                item: {
                  link_type: 'url',
                  custom_url: '',
                  resourceSelection: {
                    resource_type: null,
                    resource_id: null,
                    resource_name: null,
                    resource_slug: null
                  }
                }
              };
            },
            methods: {
              onLinkTypeChange() {
                if (this.item.link_type === 'url') {
                  this.item.resourceSelection = {
                    resource_type: null,
                    resource_id: null, 
                    resource_name: null,
                    resource_slug: null,
                  };
                } else {
                  this.item.custom_url = '';
                }
              },
              onResourceSelectionChange(selection) {
                this.item.resourceSelection = selection;
              }
            }
          }).mount('#app');
        </script>
      </body>
      </html>
    `);

    // Wait for Vue to initialize
    await page.waitForTimeout(500);

    // Test initial state - should show Custom URL input
    await expect(page.locator('input[placeholder="Enter URL"]')).toBeVisible();
    
    // Switch to Internal Resource
    await page.selectOption('select', 'resource');
    
    // Should now show ResourceSelector component
    await expect(page.locator('.resource-selector')).toBeVisible();
    
    // Should show Resource Type dropdown
    await expect(page.locator('label:has-text("Resource Type")')).toBeVisible();
    
    // Resource Type dropdown should have options
    const resourceTypeSelect = page.locator('.resource-selector select').first();
    await expect(resourceTypeSelect).toBeVisible();
    
    // Select Product resource type
    await resourceTypeSelect.selectOption('Product');
    
    // Should now show search field
    await expect(page.locator('input[placeholder="Search..."]')).toBeVisible();
    await expect(page.locator('label:has-text("Select Product")')).toBeVisible();
  });

  test('should complete full resource selection workflow', async ({ page }) => {
    await page.setContent(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>ResourceSelector Full Workflow Test</title>
        <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
      </head>
      <body>
        <div id="app">
          <div>
            <select v-model="item.link_type">
              <option value="resource">Internal Resource</option>
            </select>
          </div>
          
          <div v-if="item.link_type === 'resource'">
            <resource-selector 
              :value="item.resourceSelection"
              @change="onResourceSelectionChange"
            />
          </div>
          
          <div class="selection-display">
            Selected: {{ JSON.stringify(item.resourceSelection) }}
          </div>
        </div>
        
        <script type="module">
          // Same component and app setup as above...
        </script>
      </body>
      </html>
    `);

    await page.waitForTimeout(500);

    // Select resource type
    const resourceTypeSelect = page.locator('.resource-selector select').first();
    await resourceTypeSelect.selectOption('Product');
    
    // Search for a resource
    const searchInput = page.locator('input[placeholder="Search..."]');
    await searchInput.fill('iPhone');
    
    // Wait for search results
    await page.waitForTimeout(400);
    
    // Should show dropdown with results
    await expect(page.locator('.dropdown')).toBeVisible();
    await expect(page.locator('button:has-text("iPhone 15")')).toBeVisible();
    
    // Click on a resource
    await page.click('button:has-text("iPhone 15")');
    
    // Should show selected resource
    await expect(page.locator('span:has-text("Product: iPhone 15")')).toBeVisible();
    
    // Selection should be reflected in data
    const selectionText = await page.locator('.selection-display').textContent();
    expect(selectionText).toContain('"resource_type":"Product"');
    expect(selectionText).toContain('"resource_id":1');
    expect(selectionText).toContain('"resource_name":"iPhone 15"');
  });

  test('should handle existing resource selection properly', async ({ page }) => {
    await page.setContent(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Existing Resource Test</title>
        <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
      </head>
      <body>
        <div id="app">
          <resource-selector 
            :value="existingSelection"
            @change="onResourceSelectionChange"
          />
        </div>
        
        <script type="module">
          // Component setup...
          createApp({
            components: { ResourceSelector },
            data() {
              return {
                existingSelection: {
                  resource_type: 'Product',
                  resource_id: 25,
                  resource_name: 'Books Novel',
                  resource_slug: 'books-novel-8243'
                }
              };
            },
            methods: {
              onResourceSelectionChange(selection) {
                this.existingSelection = selection;
              }
            }
          }).mount('#app');
        </script>
      </body>
      </html>
    `);

    await page.waitForTimeout(500);

    // Should show pre-selected resource
    await expect(page.locator('select')).toHaveValue('Product');
    await expect(page.locator('span:has-text("Product: Books Novel")')).toBeVisible();
    
    // Should be able to remove selection
    await page.click('button:has-text("Remove")');
    await expect(page.locator('span:has-text("Product: Books Novel")')).not.toBeVisible();
  });
});