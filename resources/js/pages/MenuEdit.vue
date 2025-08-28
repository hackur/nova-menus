<template>
  <div>
    <Head :title="`Edit Menu: ${menu.name || 'Loading...'}`" />

    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center space-x-3">
        <button 
          @click="goBackToMenus"
          class="flex items-center text-gray-500 hover:text-gray-700 transition-colors duration-200"
          title="Back to All Menus"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
        </button>
        <Heading>{{ menu.name || 'Loading...' }}</Heading>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="animate-spin inline-block w-8 h-8 border-2 border-gray-300 border-t-blue-600 rounded-full"></div>
      <p class="mt-4 text-gray-600">Loading menu...</p>
    </div>

    <div v-else>
      <!-- Menu Builder Panel - Full Width -->
      <Card class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">Menu Structure</h3>
          <button
            @click="showAddItemModal"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!menu.id"
          >
            + Add Menu Item
          </button>
        </div>
          
          <div v-if="menuItems.length === 0" class="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg">
            <p class="text-gray-600 mb-4">This menu has no items yet.</p>
            <button
              @click="showAddItemModal"
              class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!menu.id"
            >
              + Add First Menu Item
            </button>
          </div>

          <nested v-else :items="menuItems" :currentDepth="0" :max-depth="menu.max_depth" :menu-id="menuId" @structure-changed="rebuildMenuStructure" @item-saved="scrollToTop" @item-deleted="removeItemFromMenuItems"></nested>
        </Card>
    </div>

  </div>
</template>

<script>
import Nested from '../components/Nested.vue'

export default {
  name: 'MenuEdit',
  
  components: {
    Nested
  },
  
  props: {
    menuId: {
      type: [String, Number],
      required: true
    }
  },
  
  data() {
    return {
      loading: false,
      menu: {
        id: null,
        name: '',
        slug: '',
        max_depth: 2,
        items_count: 0,
        created_at: null,
        updated_at: null
      },
      menuItems: []
    }
  },

  mounted() {
    this.loadMenu().then(() => {
      this.loadMenuItems();
    });
  },

  methods: {
    goBackToMenus() {
      Nova.visit('/menus');
    },

    showAddItemModal() {
      // Create a new menu item object and append to array
      const newMenuItem = {
        id: null, // Will be set after saving to API
        name: '',
        custom_url: '',
        resource_type: null,
        resource_id: null,
        resource_slug: null,
        display_at: null,
        hide_at: null,
        link_type: 'url', // Default to custom URL
        expanded: true, // Open accordion immediately
        children: [],
        isNew: true, // Flag to identify new items
        is_active: true, // Default to active
        visibility_type: 'always_show' // Default to always show
      };

      // Add to end of array so it appears at bottom
      this.menuItems.push(newMenuItem);
      
      // Focus on the name field and scroll to form after Vue updates the DOM
      this.$nextTick(() => {
        const nameInput = document.querySelector('input[type="text"][placeholder="Enter menu item name"]');
        if (nameInput) {
          nameInput.focus();
          
          // Scroll the new form into view
          const formContainer = nameInput.closest('.border');
          if (formContainer) {
            formContainer.scrollIntoView({ 
              behavior: 'smooth', 
              block: 'nearest'
            });
          }
        }
      });
    },

    scrollToTop() {
      // Smooth scroll to top of page after saving menu item
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    },

    async loadMenu() {
      this.loading = true;
      try {
        const response = await Nova.request().get(`/nova-vendor/menus/menus/${this.menuId}`);
        
        if (response.data.success) {
          this.menu = response.data.data;
        } else {
          throw new Error(response.data.message || 'Failed to load menu');
        }
      } catch (error) {
        console.error('Failed to load menu:', error);
        Nova.$toasted.error('Failed to load menu: ' + (error.response?.data?.message || error.message));
        
        // Set fallback data to prevent UI errors
        this.menu = {
          id: this.menuId,
          name: 'Menu Not Found',
          slug: '',
          max_depth: 2,
          items_count: 0,
          created_at: null,
          updated_at: null
        };
      } finally {
        this.loading = false;
      }
    },

    async loadMenuItems() {
      try {
        const response = await Nova.request().get(`/nova-vendor/menus/menus/${this.menuId}/items`);
        
        if (response.data.success) {
          this.menuItems = response.data.data || [];
        } else {
          throw new Error(response.data.message || 'Failed to load menu items');
        }
      } catch (error) {
        console.error('Failed to load menu items:', error);
        Nova.$toasted.error('Failed to load menu items: ' + (error.response?.data?.message || error.message));
        this.menuItems = [];
      }
    },

    // Function to rebuild menu structure via API using rebuildFromArray
    async rebuildMenuStructure() {
      this.saving = true;
      try {
        // Clean the menu items array to only include necessary database fields
        const cleanedMenuItems = this.cleanMenuItemsForAPI(this.menuItems);
        
        const response = await Nova.request().put(
          `/nova-vendor/menus/menus/${this.menuId}/items/rebuild`,
          { menu_structure: cleanedMenuItems }
        );

        if (response.data.success) {
          Nova.$toasted.success('Menu structure updated successfully');
          await this.loadMenuItems();
        } else {
          throw new Error(response.data.message);
        }
      } catch (error) {
        console.error('Failed to rebuild menu structure:', error);
        Nova.$toasted.error('Failed to update menu structure');
        await this.loadMenuItems();
      } finally {
        this.saving = false;
      }
    },

    // Clean menu items array to only include database fields
    cleanMenuItemsForAPI(items) {
      return items.map(item => {
        const cleaned = {
          id: item.id,
          name: item.name,
          custom_url: item.custom_url || null,
          resource_type: item.resource_type || null,
          resource_id: item.resource_id || null,
          resource_slug: item.resource_slug || null,
          display_at: item.display_at || null,
          hide_at: item.hide_at || null,
          is_active: item.is_active !== false
        };
        
        // Recursively clean children
        if (item.children && item.children.length > 0) {
          cleaned.children = this.cleanMenuItemsForAPI(item.children);
        }
        
        return cleaned;
      });
    },

    // Remove item from menuItems array recursively
    removeItemFromMenuItems(itemToRemove) {
      const removeFromArray = (items) => {
        for (let i = 0; i < items.length; i++) {
          if (items[i] === itemToRemove || (items[i].id && items[i].id === itemToRemove.id)) {
            items.splice(i, 1);
            return true;
          }
          
          // Check children recursively
          if (items[i].children && items[i].children.length > 0) {
            if (removeFromArray(items[i].children)) {
              return true;
            }
          }
        }
        return false;
      };

      removeFromArray(this.menuItems);
    }
  }
}
</script>

<style scoped>
/* Basic component styling */
</style>
