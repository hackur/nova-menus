<template>
  <draggable
    class="dragArea"
    tag="ul"
    :list="items"
    :group="{ name: 'g1' }"
    item-key="id"
    :style="{ marginLeft: currentDepth >= 1 ? (currentDepth + 20) + 'px' : '0px' }"
    @change="handleDragChange"
  >
    <template #item="{ element }">
      <li class="dragItem">
        <div 
          class="border border-gray-200 rounded-lg mb-3 overflow-hidden"
          :class="{ 
            'border-gray-300': !isItemVisible(element) || !isParentVisible(element),
            'opacity-75': !isItemVisible(element) || !isParentVisible(element)
          }"
        >
          <!-- Accordion Header -->
          <div 
            class="flex items-center justify-between py-2 px-6 bg-gray-50 hover:bg-gray-100 cursor-move"
            :class="{ 
              'bg-blue-50': element.expanded,
              'bg-gray-100 opacity-60': !isItemVisible(element) || !isParentVisible(element)
            }"
            @click="toggleExpanded(element)"
          >
            <div class="flex items-center space-x-3">
              <!-- Item Info -->
              <div :class="{ 'text-gray-500': !isItemVisible(element) || !isParentVisible(element) }">
                <div class="font-medium" :class="{ 'text-gray-900': isItemVisible(element) && isParentVisible(element), 'text-gray-500': !isItemVisible(element) || !isParentVisible(element) }">
                  {{ element.name || 'Untitled Item' }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ getItemDescription(element) }}
                  <span v-if="!isItemVisible(element)" class="ml-2 text-xs text-red-500">
                    ({{ getHiddenReason(element) }})
                  </span>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center space-x-2" @click.stop>
              <!-- Visibility Indicator -->
              <div class="flex-shrink-0" v-if="getVisibilityIconType(element)">
                <svg 
                  v-if="getVisibilityIconType(element) === 'eye'" 
                  class="w-5 h-5 text-gray-400" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  :title="getHiddenReason(element)"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                </svg>
                <svg 
                  v-else-if="getVisibilityIconType(element) === 'calendar'" 
                  class="w-5 h-5 text-gray-400" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  :title="getHiddenReason(element)"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
              
              <button
                @click="toggleExpanded(element)"
                class="btn btn-default btn-outline btn-sm"
                :class="{ 'btn-primary': element.expanded }"
              >
                {{ element.expanded ? 'Close' : 'Edit' }}
              </button>
              <button
                @click="deleteItem(element)"
                class="btn btn-default btn-outline btn-sm text-red-500 hover:text-red-600"
              >
                Delete
              </button>
            </div>
          </div>

          <!-- Accordion Body - Edit Form -->
          <div v-if="element.expanded" class="p-4 bg-white border-t border-gray-200">
            <form @submit.prevent="saveItem(element)" class="space-y-4">
              <!-- Name Field -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Item Name *
                </label>
                <input
                  type="text"
                  v-model="element.name"
                  class="form-control form-input form-input-bordered w-full"
                  placeholder="Enter menu item name"
                  required
                />
              </div>

              <!-- Link Type Field -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Link Type
                </label>
                <select
                  v-model="element.link_type"
                  class="form-control form-select form-input-bordered w-full"
                  @change="onLinkTypeChange(element)"
                >
                  <option value="url">Custom URL</option>
                  <option value="resource">Internal Resource</option>
                </select>
              </div>

              <!-- Custom URL Field -->
              <div v-if="element.link_type === 'url' || !element.link_type">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  URL
                </label>
                <input
                  type="text"
                  v-model="element.custom_url"
                  class="form-control form-input form-input-bordered w-full"
                  placeholder="/home or https://example.com"
                />
                <div class="mt-1 text-sm text-gray-500">
                  Supports both relative URLs (/home) and absolute URLs (https://example.com)
                </div>
              </div>

              <!-- Resource Selection -->
              <div v-if="element.link_type === 'resource'">
                <ResourceSelector
                  :value="element.resourceSelection"
                  @change="(selection) => onResourceSelectionChange(element, selection)"
                />
              </div>

              <!-- Visibility Schedule -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Visibility Schedule
                  </label>
                  <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 mb-3" role="group">
                    <button
                      type="button"
                      @click="setVisibilityType(element, 'always_show')"
                      class="px-4 py-2 text-sm font-medium transition-all duration-200 rounded-md"
                      :class="{
                        'bg-white text-gray-900 shadow-sm border border-gray-300': element.visibility_type === 'always_show',
                        'text-gray-500 hover:text-gray-700 hover:bg-gray-100': element.visibility_type !== 'always_show'
                      }"
                    >
                      Always Show
                    </button>
                    <button
                      type="button"
                      @click="setVisibilityType(element, 'always_hide')"
                      class="px-4 py-2 text-sm font-medium transition-all duration-200 rounded-md"
                      :class="{
                        'bg-white text-gray-900 shadow-sm border border-gray-300': element.visibility_type === 'always_hide',
                        'text-gray-500 hover:text-gray-700 hover:bg-gray-100': element.visibility_type !== 'always_hide'
                      }"
                    >
                      Always Hide
                    </button>
                    <button
                      type="button"
                      @click="setVisibilityType(element, 'schedule')"
                      class="px-4 py-2 text-sm font-medium transition-all duration-200 rounded-md"
                      :class="{
                        'bg-white text-gray-900 shadow-sm border border-gray-300': element.visibility_type === 'schedule',
                        'text-gray-500 hover:text-gray-700 hover:bg-gray-100': element.visibility_type !== 'schedule'
                      }"
                    >
                      Schedule
                    </button>
                  </div>
                </div>
                
                <div v-if="element.visibility_type === 'schedule'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Display At
                      <span class="text-xs text-gray-500">(Server Time: {{ serverTimezone }})</span>
                    </label>
                    <input
                      type="datetime-local"
                      v-model="element.display_at_local"
                      class="form-control form-input form-input-bordered w-full"
                      @change="updateDisplayAtUTC(element)"
                    />
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Hide At
                      <span class="text-xs text-gray-500">(Server Time: {{ serverTimezone }})</span>
                    </label>
                    <input
                      type="datetime-local"
                      v-model="element.hide_at_local"
                      class="form-control form-input form-input-bordered w-full"
                      @change="updateHideAtUTC(element)"
                    />
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button 
                  type="button"
                  @click="cancelEdit(element)"
                  class="btn btn-default btn-outline"
                >
                  Cancel
                </button>
                <button 
                  type="submit"
                  class="btn btn-default btn-primary"
                >
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
        <!-- Recursive nested items -->
        <nested 
          v-if="maxDepth > currentDepth" 
          :items="element.children" 
          class="childItem" 
          :current-depth="currentDepth + 1" 
          :max-depth="maxDepth"
          :menu-id="menuId"
          @structure-changed="$emit('structure-changed')"
        />
      </li>
    </template>
  </draggable>
</template>
<script>
import draggable from "vuedraggable";
import ResourceSelector from "./ResourceSelector.vue";

export default {
  components: {
    draggable,
    ResourceSelector
  },
  
  props: {
    items: {
      required: true,
      type: Array
    },
    currentDepth: {
      required: true,
      type: Number
    },
    maxDepth: {
      required: true,
      type: Number
    },
    menuId: {
      required: true,
      type: [String, Number]
    }
  },
  components: {
    draggable,
    ResourceSelector
  },
  name: "nested",
  
  emits: ['structure-changed', 'item-saved'],
  
  data() {
    return {
      serverTimezone: 'UTC' // Will be populated from server config
    };
  },
  
  mounted() {
    // Initialize visibility types and local datetime values for existing items
    this.items.forEach(item => this.initializeItemVisibility(item));
    
    // Get server timezone (assuming Nova provides this)
    this.serverTimezone = Nova.config('timezone') || 'UTC';
  },
  
  methods: {
    handleDragChange(evt) {
      console.log('Drag change detected:', evt);
      // Emit event to parent component to rebuild structure
      this.$emit('structure-changed');
    },
    
    initializeItemVisibility(item) {
      // Set default visibility_type if not already set
      if (!item.visibility_type) {
        // Determine visibility type based on existing data
        if (item.is_active === false) {
          item.visibility_type = 'always_hide';
        } else if (item.display_at || item.hide_at) {
          item.visibility_type = 'schedule';
        } else {
          item.visibility_type = 'always_show'; // Default for new items
        }
      }
      
      // Convert UTC timestamps to local datetime-local format for editing
      if (item.display_at) {
        item.display_at_local = this.utcToLocalDatetime(item.display_at);
      }
      if (item.hide_at) {
        item.hide_at_local = this.utcToLocalDatetime(item.hide_at);
      }

      // Initialize link_type based on existing data
      if (!item.link_type) {
        if (item.resource_type && item.resource_id) {
          item.link_type = 'resource';
        } else if (item.custom_url) {
          item.link_type = 'url';
        } else {
          item.link_type = 'url'; // Default for new items
        }
      }

      // Initialize resource selection data for ResourceSelector component
      if (!item.resourceSelection) {
        item.resourceSelection = {
          resource_type: item.resource_type || null,
          resource_id: item.resource_id || null,
          resource_name: item.resource_name || null, // Pass through resource name if available
          resource_slug: item.resource_slug || null,
        };
      }
    },
    
    setVisibilityType(element, type) {
      element.visibility_type = type;
      this.onVisibilityTypeChange(element);
    },
    
    onVisibilityTypeChange(element) {
      if (element.visibility_type === 'always_show') {
        element.display_at = null;
        element.hide_at = null;
        element.display_at_local = '';
        element.hide_at_local = '';
        element.is_active = true;
      } else if (element.visibility_type === 'always_hide') {
        // Use is_active flag instead of far future date
        element.display_at = null;
        element.hide_at = null;
        element.display_at_local = '';
        element.hide_at_local = '';
        element.is_active = false;
      } else if (element.visibility_type === 'schedule') {
        // Reset to null and let user set times
        element.display_at = null;
        element.hide_at = null;
        element.display_at_local = '';
        element.hide_at_local = '';
        element.is_active = true;
      }
    },
    
    updateDisplayAtUTC(element) {
      if (element.display_at_local) {
        element.display_at = this.localDatetimeToUTC(element.display_at_local);
      } else {
        element.display_at = null;
      }
    },
    
    updateHideAtUTC(element) {
      if (element.hide_at_local) {
        element.hide_at = this.localDatetimeToUTC(element.hide_at_local);
      } else {
        element.hide_at = null;
      }
    },
    
    utcToLocalDatetime(utcString) {
      if (!utcString) return '';
      const utcDate = new Date(utcString);
      const localDate = new Date(utcDate.getTime() - (utcDate.getTimezoneOffset() * 60000));
      return localDate.toISOString().slice(0, 16);
    },
    
    localDatetimeToUTC(localDatetimeString) {
      if (!localDatetimeString) return null;
      const localDate = new Date(localDatetimeString);
      const utcDate = new Date(localDate.getTime() + (localDate.getTimezoneOffset() * 60000));
      return utcDate.toISOString();
    },
    
    toggleExpanded(element) {
      element.expanded = !element.expanded;
    },

    getItemDescription(element) {
      if (element.custom_url) {
        return element.custom_url;
      }
      if (element.resource_type) {
        return `${element.resource_type}${element.resource_id ? ` #${element.resource_id}` : ' (Index)'}`;
      }
      return 'No link configured';
    },

    onLinkTypeChange(element) {
      // Clear related fields when link type changes
      if (element.link_type === 'url') {
        element.resource_type = null;
        element.resource_id = null;
        element.resource_slug = null;
        // Reset resource selection for ResourceSelector
        element.resourceSelection = {
          resource_type: null,
          resource_id: null,
          resource_name: null,
          resource_slug: null,
        };
      } else {
        element.custom_url = '';
      }
    },

    cancelEdit(element) {
      element.expanded = false;
      // TODO: Reset form data to original values
    },

    async saveItem(element) {
      try {
        const payload = {
          name: element.name,
          custom_url: element.custom_url || null,
          resource_type: element.resource_type || null,
          resource_id: element.resource_id || null,
          resource_slug: element.resource_slug || null,
          display_at: element.display_at || null,
          hide_at: element.hide_at || null,
          is_active: element.is_active !== false // Default to true if not explicitly false
        };

        let response;
        if (element.isNew || !element.id) {
          // Create new menu item using menuId prop
          payload.menu_id = this.menuId;
          response = await Nova.request().post(`/nova-vendor/menus/menu-items`, payload);
          
          if (response.data.success) {
            // Update element with the new ID and remove isNew flag
            element.id = response.data.data.id;
            element.isNew = false;
            Nova.$toasted.success('Item created successfully');
          }
        } else {
          // Update existing menu item
          response = await Nova.request().put(`/nova-vendor/menus/menu-items/${element.id}`, payload);
          
          if (response.data.success) {
            Nova.$toasted.success('Item updated successfully');
          }
        }
        
        element.expanded = false;
        
        // Emit event to parent to scroll to top after save
        this.$emit('item-saved');
      } catch (error) {
        Nova.$toasted.error(element.isNew ? 'Failed to create item' : 'Failed to update item');
        console.error('Save error:', error);
      }
    },

    deleteItem(element) {
      if (confirm(`Are you sure you want to delete "${element.name}"? This action cannot be undone.`)) {
        this.performDelete(element);
      }
    },

    async performDelete(element) {
      try {
        // If the item has no ID (new item not saved yet), just remove from frontend
        if (!element.id) {
          this.removeItemFromArray(element);
          Nova.$toasted.success('Item removed successfully');
          return;
        }

        const response = await Nova.request().delete(`/nova-vendor/menus/menu-items/${element.id}`);
        
        if (response.data.success) {
          Nova.$toasted.success('Item deleted successfully');
          this.removeItemFromArray(element);
        }
      } catch (error) {
        Nova.$toasted.error('Failed to delete item');
        console.error('Delete error:', error);
      }
    },

    removeItemFromArray(elementToRemove) {
      // Emit event to parent to remove item from the menuItems array
      this.$emit('item-deleted', elementToRemove);
    },

    /**
     * Check if an item is currently visible based on its own settings
     */
    isItemVisible(element) {
      const now = new Date();

      // Check if item is active (not always hidden)
      if (element.visibility_type === 'always_hide') {
        return false;
      }

      // Check if item is inactive 
      if (element.is_active === false) {
        return false;
      }

      // Check temporal constraints
      if (element.display_at && new Date(element.display_at) > now) {
        return false; // Not yet visible
      }

      if (element.hide_at && new Date(element.hide_at) <= now) {
        return false; // Already hidden
      }

      return true;
    },

    /**
     * Check if this item's parent hierarchy is visible
     * If any parent is hidden, this item should be considered hidden too
     */
    isParentVisible(element) {
      // For root items, parent is always visible
      if (!element.parent_id) {
        return true;
      }

      // Find parent in the current items array or search up the component tree
      const parent = this.findParentInItems(element.parent_id) || this.findParentInParentComponent(element.parent_id);
      if (parent) {
        return this.isItemVisible(parent) && this.isParentVisible(parent);
      }

      // If we can't find the parent anywhere, assume it's visible
      // This handles cases where we're only displaying a subset of the tree
      return true;
    },

    /**
     * Find parent item in the current items list
     */
    findParentInItems(parentId) {
      // Simple recursive search through all items
      const findInArray = (items) => {
        for (const item of items) {
          if (item.id === parentId) {
            return item;
          }
          if (item.children && item.children.length > 0) {
            const found = findInArray(item.children);
            if (found) return found;
          }
        }
        return null;
      };

      return findInArray(this.items);
    },

    /**
     * Find parent item by searching up the component tree
     */
    findParentInParentComponent(parentId) {
      // Try to find the parent by emitting an event to parent components
      // This is a simplified approach - in a real implementation, you might
      // want to pass the full menu tree down or use a more sophisticated lookup
      let parent = this.$parent;
      while (parent) {
        if (parent.findParentInItems && typeof parent.findParentInItems === 'function') {
          const found = parent.findParentInItems(parentId);
          if (found) return found;
        }
        parent = parent.$parent;
      }
      return null;
    },

    /**
     * Get the appropriate icon type for visibility status
     */
    getVisibilityIconType(element) {
      // Check if this item should show an icon based on its visibility settings or parent visibility
      const isHidden = !this.isItemVisible(element) || !this.isParentVisible(element);
      
      if (!isHidden) {
        return null; // Visible items don't show icon
      }
      
      // Determine icon based on visibility type or parent's visibility type
      if (!this.isParentVisible(element)) {
        // Hidden because parent is hidden - use same icon as parent would use
        const parent = this.findParentInItems(element.parent_id);
        if (parent) {
          return this.getVisibilityIconType(parent);
        }
        return 'eye'; // Default fallback
      }
      
      // Item is hidden due to its own settings
      if (element.visibility_type === 'always_hide') {
        return 'eye';
      } else if (element.visibility_type === 'schedule') {
        return 'calendar';
      } else {
        // Fallback: check actual temporal constraints for items without visibility_type set
        const now = new Date();
        if ((element.display_at && new Date(element.display_at) > now) || 
            (element.hide_at && new Date(element.hide_at) <= now)) {
          return 'calendar';
        }
        return 'eye';
      }
    },

    /**
     * Get human-readable reason why item is hidden
     */
    getHiddenReason(element) {
      const now = new Date();

      // Check parent visibility first
      if (!this.isParentVisible(element)) {
        return 'Parent hidden';
      }

      // Check visibility type settings
      if (element.visibility_type === 'always_hide') {
        return 'Always hidden';
      }

      if (element.is_active === false) {
        return 'Inactive';
      }

      // Check temporal constraints for scheduled items
      if (element.visibility_type === 'schedule') {
        if (element.display_at && new Date(element.display_at) > now) {
          return 'Not yet visible';
        }
        if (element.hide_at && new Date(element.hide_at) <= now) {
          return 'Expired';
        }
      }

      // Fallback checks for items without visibility_type
      if (element.display_at && new Date(element.display_at) > now) {
        return 'Not yet visible';
      }

      if (element.hide_at && new Date(element.hide_at) <= now) {
        return 'Expired';
      }

      return 'Hidden';
    },

    /**
     * Handle resource selection change
     */
    onResourceSelectionChange(element, selection) {
      // Update the element's resource data
      element.resource_type = selection.resource_type;
      element.resource_id = selection.resource_id;
      element.resource_slug = selection.resource_slug;
      
      // Clear custom URL when resource is selected
      if (selection.resource_type && selection.resource_id) {
        element.custom_url = '';
      }
    }
  }
};
</script>
<style scoped>
/* Nova Button Styles */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border-width: 1px;
  border-style: solid;
  font-size: 14px;
  font-weight: 500;
  border-radius: 6px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease-in-out;
  cursor: pointer;
}

.btn:focus {
  outline: none;
  ring: 2px solid;
  ring-offset: 2px;
}

.btn-default {
  color: rgb(55, 65, 81);
  background-color: white;
  border-color: rgb(209, 213, 219);
}

.btn-default:hover {
  background-color: rgb(249, 250, 251);
}

.btn-primary {
  color: white;
  background-color: rgb(37, 99, 235);
  border-color: rgb(37, 99, 235);
}

.btn-primary:hover {
  background-color: rgb(29, 78, 216);
  border-color: rgb(29, 78, 216);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-outline {
  color: rgb(55, 65, 81);
  background-color: white;
  border-color: rgb(209, 213, 219);
}

.btn-outline:hover {
  background-color: rgb(249, 250, 251);
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 500;
}

/* Nova Form Styles */
.form-input-bordered {
  border: 1px solid rgb(209, 213, 219);
  border-radius: 6px;
  padding: 8px 12px;
  background-color: white;
  color: rgb(17, 24, 39);
}

.form-input-bordered:focus {
  border-color: rgb(37, 99, 235);
  ring: 1px;
  ring-color: rgb(37, 99, 235);
  outline: none;
}

.form-select {
  appearance: none;
  background-color: white;
  color: rgb(17, 24, 39);
  border: 1px solid rgb(209, 213, 219);
  border-radius: 6px;
  padding: 8px 12px;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}

.form-select:focus {
  border-color: rgb(37, 99, 235);
  ring: 1px;
  ring-color: rgb(37, 99, 235);
  outline: none;
}
</style>
