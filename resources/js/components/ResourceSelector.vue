<template>
    <div class="resource-selector">
        <!-- Resource Type Selection -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Resource Type
            </label>
            <select
                v-model="selectedResourceType"
                @change="onResourceTypeChange"
                class="form-control form-select form-input-bordered w-full"
                :class="{ 'border-red-300': error }"
                :disabled="loading"
            >
                <option value="">Select Resource Type</option>
                <option
                    v-for="(label, value) in resourceTypes"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
        </div>

        <!-- Resource Instance Selection -->
        <div v-if="selectedResourceType" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Select {{ selectedResourceType }}
            </label>
            <div class="relative">
                <input
                    v-model="searchQuery"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Search..."
                    class="form-control form-input form-input-bordered w-full pr-10"
                    :class="{ 'border-red-300': error }"
                    :disabled="loading"
                />
                <div
                    v-if="loading"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                >
                    <svg
                        class="animate-spin h-4 w-4 text-gray-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                </div>
            </div>

            <!-- Dropdown Results -->
            <div
                v-if="showDropdown && resources.length > 0"
                class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md border border-gray-200 overflow-hidden"
            >
                <div class="overflow-auto max-h-60">
                    <button
                        v-for="resource in resources"
                        :key="resource.id"
                        @click="selectResource(resource)"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 focus:bg-gray-50 focus:outline-none border-b border-gray-100 last:border-b-0"
                        type="button"
                    >
                        {{ resource.name }}
                    </button>
                </div>
            </div>

            <!-- No Results -->
            <div
                v-else-if="showDropdown && searchQuery && resources.length === 0 && !loading"
                class="absolute z-50 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 text-sm text-gray-500"
            >
                No results found
            </div>
        </div>

        <!-- Selected Resource Display -->
        <div
            v-if="selectedResource"
            class="mb-4 p-3 bg-gray-50 rounded-md border border-gray-200"
        >
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm font-medium text-gray-900">
                        {{ selectedResourceType }}: {{ selectedResource.name }}
                    </span>
                    <span class="text-xs text-gray-500 ml-2">
                        ID: {{ selectedResource.id }}
                    </span>
                </div>
                <button
                    @click="clearSelection"
                    type="button"
                    class="text-red-600 hover:text-red-800 text-sm font-medium"
                >
                    Remove
                </button>
            </div>
        </div>

        <!-- Error Display -->
        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <div class="text-sm text-red-600">{{ error }}</div>
        </div>

    </div>
</template>

<script>
export default {
    name: 'ResourceSelector',
    
    props: {
        value: {
            type: Object,
            default: () => ({})
        }
    },

    emits: ['input', 'change'],

    data() {
        return {
            resourceTypes: {},
            selectedResourceType: '',
            selectedResource: null,
            searchQuery: '',
            resources: [],
            loading: false,
            showDropdown: false,
            error: null,
            searchTimeout: null
        }
    },

    mounted() {
        this.loadResourceTypes();
        this.initializeFromValue();
    },

    watch: {
        value: {
            handler: 'initializeFromValue',
            deep: true
        }
    },

    methods: {
        async loadResourceTypes() {
            try {
                this.loading = true;
                this.error = null;

                // Check if Nova is available
                if (typeof Nova === 'undefined') {
                    throw new Error('Nova object not available');
                }

                const response = await Nova.request().get('/nova-vendor/menus/resource-types');
                
                if (response.data && response.data.success) {
                    this.resourceTypes = response.data.data;
                } else {
                    throw new Error(response.data?.message || 'Failed to load resource types');
                }
            } catch (error) {
                console.error('Failed to load resource types:', error);
                this.error = `Failed to load resource types: ${error.message}`;
            } finally {
                this.loading = false;
            }
        },

        initializeFromValue() {
            if (this.value && this.value.resource_type) {
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
            this.error = null;
            this.showDropdown = false;
            this.emitChange();
        },

        onSearchInput() {
            // Clear previous timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Debounce search
            this.searchTimeout = setTimeout(() => {
                this.searchResources();
            }, 300);
        },

        async searchResources() {
            if (!this.selectedResourceType) {
                return;
            }

            try {
                this.loading = true;
                this.error = null;
                this.showDropdown = true;

                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append('q', this.searchQuery);
                }
                
                const response = await Nova.request().get(
                    `/nova-vendor/menus/resources/${this.selectedResourceType}/search?${params.toString()}`
                );
                
                if (response.data.success) {
                    this.resources = response.data.data;
                } else {
                    throw new Error(response.data.message || 'Failed to search resources');
                }
            } catch (error) {
                console.error('Failed to search resources:', error);
                this.error = 'Failed to search resources. Please try again.';
                this.resources = [];
            } finally {
                this.loading = false;
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

            this.$emit('input', value);
            this.$emit('change', value);
        }
    }
}
</script>

<style scoped>
.resource-selector {
    position: relative;
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
  box-shadow: 0 0 0 1px rgb(37, 99, 235);
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
  box-shadow: 0 0 0 1px rgb(37, 99, 235);
  outline: none;
}

.form-select:disabled {
  background-color: rgb(249, 250, 251);
  color: rgb(156, 163, 175);
  cursor: not-allowed;
}

.form-input-bordered:disabled {
  background-color: rgb(249, 250, 251);
  color: rgb(156, 163, 175);
  cursor: not-allowed;
}

/* Error states */
.border-red-300 {
  border-color: rgb(252, 165, 165) !important;
}

.border-red-300:focus {
  border-color: rgb(252, 165, 165) !important;
  box-shadow: 0 0 0 1px rgb(252, 165, 165) !important;
}
</style>