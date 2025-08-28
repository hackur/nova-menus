<template>
  <div>
    <Head title="Menu Management" />

    <Heading class="mb-6">Menus</Heading>

    <div class="flex justify-between items-center mb-6">
      <div class="flex items-center">
        <!-- Search functionality -->
        <div class="relative">
          <input
            type="text"
            v-model="search"
            placeholder="Search menus..."
            class="w-64 form-control form-input form-input-bordered"
          />
        </div>
      </div>
      
      <div class="flex items-center">
        <button
          @click="showCreateModal = true"
          class="btn btn-default btn-primary"
        >
          Create Menu
        </button>
      </div>
    </div>

    <!-- Menu List -->
    <Card class="overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin inline-block w-6 h-6 border-2 border-gray-300 border-t-blue-600 rounded-full"></div>
        <p class="ml-3 text-gray-600">Loading menus...</p>
      </div>
      
      <div v-else-if="menus.length === 0" class="flex flex-col items-center justify-center py-12">
        <svg class="mb-4 h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">No menus found</h3>
        <p class="text-gray-500 mb-4">Create your first menu to get started.</p>
        <button
          @click="showCreateModal = true"
          class="btn btn-default btn-primary"
        >
          Create Menu
        </button>
      </div>
      
      <div v-else>
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
              <th class="text-left py-3 px-6 font-medium text-gray-600 dark:text-gray-400">Name</th>
              <th class="text-left py-3 px-6 font-medium text-gray-600 dark:text-gray-400">Slug</th>
              <th class="text-left py-3 px-6 font-medium text-gray-600 dark:text-gray-400">Items</th>
              <th class="text-left py-3 px-6 font-medium text-gray-600 dark:text-gray-400">Max Depth</th>
              <th class="text-right py-3 px-6 font-medium text-gray-600 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="menu in filteredMenus" 
              :key="menu.id"
              class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              <td class="py-4 px-6">
                <span class="font-semibold text-gray-900 dark:text-white">{{ menu.name }}</span>
              </td>
              <td class="py-4 px-6 text-gray-600 dark:text-gray-400">
                <code class="text-sm bg-gray-100 px-1 py-0.5 rounded">{{ menu.slug }}</code>
              </td>
              <td class="py-4 px-6 text-gray-600 dark:text-gray-400">{{ menu.items_count || 0 }}</td>
              <td class="py-4 px-6 text-gray-600 dark:text-gray-400">{{ menu.max_depth }}</td>
              <td class="py-4 px-6 text-right">
                <div class="flex justify-end space-x-2">
                  <button
                    @click.stop="manageItems(menu)"
                    class="btn btn-default btn-primary btn-sm"
                  >
                    Manage Items
                  </button>
                  <button
                    @click.stop="editMenuProperties(menu)"
                    class="btn btn-default btn-outline btn-sm"
                  >
                    Edit
                  </button>
                  <button
                    @click.stop="deleteMenu(menu)"
                    class="btn btn-default btn-outline btn-sm text-red-500 hover:text-red-600"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Create Menu Modal -->
    <Modal 
      :show="showCreateModal" 
      @close="closeModal"
      maxWidth="md"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Create New Menu</h3>
        
        <form @submit.prevent="createMenu">
          <div class="space-y-6">
            <div>
              <label for="menu-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Menu Name</label>
              <input
                id="menu-name"
                type="text"
                v-model="newMenu.name"
                :class="['form-control form-input form-input-bordered w-full', errors.name ? 'border-red-500' : '']"
                placeholder="Enter menu name"
                required
                @input="generateSlug"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-500">{{ errors.name }}</p>
            </div>
            
            <div>
              <label for="menu-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Slug</label>
              <input
                id="menu-slug"
                type="text"
                v-model="newMenu.slug"
                :class="['form-control form-input form-input-bordered w-full', errors.slug ? 'border-red-500' : '']"
                placeholder="Auto-generated from name"
              />
              <p v-if="errors.slug" class="mt-1 text-sm text-red-500">{{ errors.slug }}</p>
              <p v-else class="mt-1 text-sm text-gray-500">Leave empty to auto-generate from name</p>
            </div>
            
            <div>
              <label for="max-depth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maximum Depth</label>
              <select
                id="max-depth"
                v-model="newMenu.max_depth"
                :class="['form-control form-select form-input-bordered w-full', errors.max_depth ? 'border-red-500' : '']"
              >
                <option value="1">1 level</option>
                <option value="2">2 levels</option>
                <option value="3">3 levels</option>
                <option value="4">4 levels</option>
                <option value="5">5 levels</option>
                <option value="6">6 levels (default)</option>
                <option value="7">7 levels</option>
                <option value="8">8 levels</option>
                <option value="9">9 levels</option>
                <option value="10">10 levels</option>
              </select>
              <p v-if="errors.max_depth" class="mt-1 text-sm text-red-500">{{ errors.max_depth }}</p>
            </div>
          </div>
          
          <div class="flex justify-end space-x-3 mt-6">
            <button
              type="button"
              @click="closeModal"
              class="btn btn-default btn-outline"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="creating"
              class="btn btn-default btn-primary"
            >
              <span v-if="creating">Creating...</span>
              <span v-else>Create Menu</span>
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Edit Menu Properties Modal -->
    <Modal 
      :show="showEditModal" 
      @close="closeEditModal"
      maxWidth="md"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Edit Menu Properties</h3>
        
        <form @submit.prevent="updateMenu">
          <div class="space-y-6">
            <div>
              <label for="edit-menu-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Menu Name</label>
              <input
                id="edit-menu-name"
                type="text"
                v-model="editingMenu.name"
                :class="['form-control form-input form-input-bordered w-full', editErrors.name ? 'border-red-500' : '']"
                placeholder="Enter menu name"
                required
              />
              <p v-if="editErrors.name" class="mt-1 text-sm text-red-500">{{ editErrors.name }}</p>
            </div>
            
            <div>
              <label for="edit-menu-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Slug</label>
              <input
                id="edit-menu-slug"
                type="text"
                v-model="editingMenu.slug"
                :class="['form-control form-input form-input-bordered w-full', editErrors.slug ? 'border-red-500' : '']"
                placeholder="Auto-generated from name"
              />
              <p v-if="editErrors.slug" class="mt-1 text-sm text-red-500">{{ editErrors.slug }}</p>
              <p v-else class="mt-1 text-sm text-gray-500">Leave empty to auto-generate from name</p>
            </div>
            
            <div>
              <label for="edit-max-depth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maximum Depth</label>
              <select
                id="edit-max-depth"
                v-model="editingMenu.max_depth"
                :class="['form-control form-select form-input-bordered w-full', editErrors.max_depth ? 'border-red-500' : '']"
              >
                <option value="1">1 level</option>
                <option value="2">2 levels</option>
                <option value="3">3 levels</option>
                <option value="4">4 levels</option>
                <option value="5">5 levels</option>
                <option value="6">6 levels (default)</option>
                <option value="7">7 levels</option>
                <option value="8">8 levels</option>
                <option value="9">9 levels</option>
                <option value="10">10 levels</option>
              </select>
              <p v-if="editErrors.max_depth" class="mt-1 text-sm text-red-500">{{ editErrors.max_depth }}</p>
            </div>
          </div>
          
          <div class="flex justify-end space-x-3 mt-6">
            <button
              type="button"
              @click="closeEditModal"
              class="btn btn-default btn-outline"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="updating"
              class="btn btn-default btn-primary"
            >
              <span v-if="updating">Updating...</span>
              <span v-else>Update Menu</span>
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </div>
</template>

<script>
export default {
  name: 'MenuTool',
  
  data() {
    return {
      loading: false,
      creating: false,
      updating: false,
      search: '',
      menus: [],
      showCreateModal: false,
      showEditModal: false,
      errors: {},
      editErrors: {},
      newMenu: {
        name: '',
        slug: '',
        max_depth: 6
      },
      editingMenu: {
        id: null,
        name: '',
        slug: '',
        max_depth: 6
      }
    }
  },

  computed: {
    filteredMenus() {
      if (!this.search) {
        return this.menus;
      }
      
      const searchTerm = this.search.toLowerCase();
      return this.menus.filter(menu => 
        menu.name.toLowerCase().includes(searchTerm) ||
        menu.slug.toLowerCase().includes(searchTerm)
      );
    }
  },

  mounted() {
    this.loadMenus();
  },

  methods: {
    async loadMenus() {
      this.loading = true;
      try {
        const response = await Nova.request().get('/nova-vendor/menus/menus');
        this.menus = response.data.data || [];
      } catch (error) {
        console.error('Failed to load menus:', error);
        Nova.$toasted.error('Failed to load menus. Please try again.');
        this.menus = [];
      } finally {
        this.loading = false;
      }
    },

    async createMenu() {
      this.errors = {};
      this.creating = true;
      
      try {
        const response = await Nova.request().post('/nova-vendor/menus/menus', this.newMenu);
        this.menus.push(response.data.data);
        
        Nova.$toasted.success(`Menu "${this.newMenu.name}" created successfully`);
        this.closeModal();
        await this.loadMenus(); // Reload to get fresh data with item counts
      } catch (error) {
        console.error('Failed to create menu:', error);
        
        // Handle validation errors
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {};
          Nova.$toasted.error('Please correct the errors and try again.');
        } else {
          Nova.$toasted.error('Failed to create menu. Please try again.');
        }
      } finally {
        this.creating = false;
      }
    },

    manageItems(menu) {
      // Navigate to edit page using Nova.visit() with tool-relative path
      Nova.visit(`/menus/${menu.id}/edit`);
    },

    editMenuProperties(menu) {
      this.editingMenu = {
        id: menu.id,
        name: menu.name,
        slug: menu.slug,
        max_depth: menu.max_depth
      };
      this.showEditModal = true;
    },

    async updateMenu() {
      this.editErrors = {};
      this.updating = true;
      
      try {
        const response = await Nova.request().put(`/nova-vendor/menus/menus/${this.editingMenu.id}`, {
          name: this.editingMenu.name,
          slug: this.editingMenu.slug,
          max_depth: this.editingMenu.max_depth
        });
        
        // Update the menu in the local array
        const menuIndex = this.menus.findIndex(m => m.id === this.editingMenu.id);
        if (menuIndex !== -1) {
          this.menus[menuIndex] = { ...this.menus[menuIndex], ...response.data.data };
        }
        
        Nova.$toasted.success(`Menu "${this.editingMenu.name}" updated successfully`);
        this.closeEditModal();
      } catch (error) {
        console.error('Failed to update menu:', error);
        
        // Handle validation errors
        if (error.response?.status === 422) {
          this.editErrors = error.response.data.errors || {};
          Nova.$toasted.error('Please correct the errors and try again.');
        } else {
          Nova.$toasted.error('Failed to update menu. Please try again.');
        }
      } finally {
        this.updating = false;
      }
    },

    async deleteMenu(menu) {
      if (!confirm(`Are you sure you want to delete the menu "${menu.name}"? This action cannot be undone.`)) {
        return;
      }
      
      try {
        await Nova.request().delete(`/nova-vendor/menus/menus/${menu.id}`);
        this.menus = this.menus.filter(m => m.id !== menu.id);
        
        Nova.$toasted.success(`Menu "${menu.name}" deleted successfully`);
      } catch (error) {
        console.error('Failed to delete menu:', error);
        Nova.$toasted.error('Failed to delete menu. Please try again.');
      }
    },

    generateSlug() {
      if (!this.newMenu.slug && this.newMenu.name) {
        // Auto-generate slug from name
        this.newMenu.slug = this.newMenu.name
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/(^-|-$)/g, '');
      }
    },

    closeModal() {
      this.showCreateModal = false;
      this.resetNewMenu();
      this.errors = {};
    },

    closeEditModal() {
      this.showEditModal = false;
      this.resetEditingMenu();
      this.editErrors = {};
    },

    resetNewMenu() {
      this.newMenu = {
        name: '',
        slug: '',
        max_depth: 6
      };
    },

    resetEditingMenu() {
      this.editingMenu = {
        id: null,
        name: '',
        slug: '',
        max_depth: 6
      };
    }
  }
}
</script>

<style scoped>
/* Nova Button Styles - Using !important to override existing styles */
.btn {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 8px 16px !important;
  border-width: 1px !important;
  border-style: solid !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  border-radius: 6px !important;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
  transition: all 0.2s ease-in-out !important;
  cursor: pointer !important;
}

.btn:focus {
  outline: none !important;
  ring: 2px solid !important;
  ring-offset: 2px !important;
}

.btn-default {
  color: rgb(55, 65, 81) !important;
  background-color: white !important;
  border-color: rgb(209, 213, 219) !important;
}

.btn-default:hover {
  background-color: rgb(249, 250, 251) !important;
}

.btn-primary {
  color: white !important;
  background-color: rgb(37, 99, 235) !important;
  border-color: rgb(37, 99, 235) !important;
}

.btn-primary:hover {
  background-color: rgb(29, 78, 216) !important;
  border-color: rgb(29, 78, 216) !important;
}

.btn-primary:focus {
  ring-color: rgb(147, 197, 253) !important;
}

.btn-outline {
  color: rgb(55, 65, 81) !important;
  background-color: white !important;
  border-color: rgb(209, 213, 219) !important;
}

.btn-outline:hover {
  background-color: rgb(249, 250, 251) !important;
}

.btn-outline:focus {
  ring-color: rgb(147, 197, 253) !important;
}

.btn-sm {
  padding: 6px 12px !important;
  font-size: 12px !important;
  font-weight: 500 !important;
}

/* Nova Form Styles */
.form-input-bordered {
  @apply border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500;
}

.form-select {
  @apply appearance-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}

/* Dark mode support */
.dark .btn-default {
  color: rgb(209, 213, 219) !important;
  background-color: rgb(31, 41, 55) !important;
  border-color: rgb(75, 85, 99) !important;
}

.dark .btn-default:hover {
  background-color: rgb(55, 65, 81) !important;
}

.dark .btn-outline {
  color: rgb(209, 213, 219) !important;
  background-color: rgb(31, 41, 55) !important;
  border-color: rgb(75, 85, 99) !important;
}

.dark .btn-outline:hover {
  background-color: rgb(55, 65, 81) !important;
}

.dark .btn-primary {
  color: white !important;
  background-color: rgb(37, 99, 235) !important;
  border-color: rgb(37, 99, 235) !important;
}

.dark .btn-primary:hover {
  background-color: rgb(29, 78, 216) !important;
  border-color: rgb(29, 78, 216) !important;
}
</style>

