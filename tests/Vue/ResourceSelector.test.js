import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ResourceSelector from '../../resources/js/components/ResourceSelector.vue'

describe('ResourceSelector.vue', () => {
  let wrapper
  let mockRequest

  beforeEach(() => {
    vi.clearAllMocks()
    
    mockRequest = {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn()
    }
    
    Nova.request.mockReturnValue(mockRequest)
    
    // Use fake timers for debouncing tests
    vi.useFakeTimers()
  })

  afterEach(() => {
    wrapper?.unmount()
    vi.useRealTimers()
  })

  const createWrapper = (props = {}, options = {}) => {
    const defaultProps = {
      value: {}
    }

    return mount(ResourceSelector, {
      props: { ...defaultProps, ...props },
      ...options
    })
  }

  describe('Component Mounting', () => {
    it('mounts successfully', () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })

      wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
    })

    it('loads resource types on mount', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { 'App\\Models\\Product': 'Product' } }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(mockRequest.get).toHaveBeenCalledWith('/nova-vendor/menus/resource-types')
    })
  })

  describe('Resource Types Loading', () => {
    it('loads resource types successfully', async () => {
      const mockResourceTypes = {
        'App\\Models\\Product': 'Product',
        'App\\Models\\Category': 'Category'
      }

      mockRequest.get.mockResolvedValue({
        data: { success: true, data: mockResourceTypes }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.resourceTypes).toEqual(mockResourceTypes)
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.error).toBe(null)
    })

    it('handles resource types loading error', async () => {
      mockRequest.get.mockRejectedValue(new Error('Network error'))

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.error).toBe('Failed to load resource types. Please try again.')
    })

    it('displays resource types in select dropdown', async () => {
      const mockResourceTypes = {
        'App\\Models\\Product': 'Product',
        'App\\Models\\Category': 'Category'
      }

      mockRequest.get.mockResolvedValue({
        data: { success: true, data: mockResourceTypes }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const options = wrapper.findAll('option')
      expect(options).toHaveLength(3) // Including default "Select Resource Type"
      expect(options[1].text()).toBe('Product')
      expect(options[2].text()).toBe('Category')
    })
  })

  describe('Value Initialization', () => {
    it('initializes from value prop with resource data', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })

      const value = {
        resource_type: 'App\\Models\\Product',
        resource_id: 123,
        resource_name: 'Test Product',
        resource_slug: 'test-product'
      }

      wrapper = createWrapper({ value })
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.selectedResourceType).toBe('App\\Models\\Product')
      expect(wrapper.vm.selectedResource).toEqual({
        id: 123,
        name: 'Test Product',
        slug: 'test-product'
      })
    })

    it('handles empty value prop', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })

      wrapper = createWrapper({ value: {} })
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.selectedResourceType).toBe('')
      expect(wrapper.vm.selectedResource).toBe(null)
    })

    it('watches value prop changes', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const newValue = {
        resource_type: 'App\\Models\\Product',
        resource_id: 456,
        resource_name: 'New Product',
        resource_slug: 'new-product'
      }

      await wrapper.setProps({ value: newValue })

      expect(wrapper.vm.selectedResourceType).toBe('App\\Models\\Product')
      expect(wrapper.vm.selectedResource).toEqual({
        id: 456,
        name: 'New Product',
        slug: 'new-product'
      })
    })
  })

  describe('Resource Type Selection', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { 'App\\Models\\Product': 'Product' } }
      })
    })

    it('updates selected resource type', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const select = wrapper.find('select')
      await select.setValue('App\\Models\\Product')

      expect(wrapper.vm.selectedResourceType).toBe('App\\Models\\Product')
    })

    it('clears related data when resource type changes', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResource = { id: 1, name: 'Test' }
      wrapper.vm.resources = [{ id: 1, name: 'Test' }]
      wrapper.vm.searchQuery = 'test'

      wrapper.vm.onResourceTypeChange()

      expect(wrapper.vm.selectedResource).toBe(null)
      expect(wrapper.vm.resources).toEqual([])
      expect(wrapper.vm.searchQuery).toBe('')
      expect(wrapper.vm.error).toBe(null)
      expect(wrapper.vm.showDropdown).toBe(false)
    })

    it('emits change when resource type changes', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.onResourceTypeChange()

      expect(wrapper.emitted('input')).toBeTruthy()
      expect(wrapper.emitted('change')).toBeTruthy()
    })
  })

  describe('Resource Search', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { 'App\\Models\\Product': 'Product' } }
      })
    })

    it('debounces search input', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      const searchSpy = vi.spyOn(wrapper.vm, 'searchResources').mockImplementation(() => {})

      wrapper.vm.onSearchInput()
      
      // Should not call immediately
      expect(searchSpy).not.toHaveBeenCalled()

      // Fast forward 300ms
      vi.advanceTimersByTime(300)

      expect(searchSpy).toHaveBeenCalled()
    })

    it('clears previous timeout on new input', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      const searchSpy = vi.spyOn(wrapper.vm, 'searchResources').mockImplementation(() => {})

      wrapper.vm.onSearchInput()
      wrapper.vm.onSearchInput() // Second input should clear first timeout

      vi.advanceTimersByTime(300)

      expect(searchSpy).toHaveBeenCalledTimes(1) // Only called once
    })

    it('searches resources successfully', async () => {
      const mockResources = [
        { id: 1, name: 'Product 1', slug: 'product-1' },
        { id: 2, name: 'Product 2', slug: 'product-2' }
      ]

      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/resource-types')) {
          return Promise.resolve({ data: { success: true, data: { 'App\\Models\\Product': 'Product' } } })
        }
        if (url.includes('/search')) {
          return Promise.resolve({ data: { success: true, data: mockResources } })
        }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      wrapper.vm.searchQuery = 'product'

      await wrapper.vm.searchResources()

      expect(mockRequest.get).toHaveBeenCalledWith(
        '/nova-vendor/menus/resources/App\\Models\\Product/search?q=product'
      )
      expect(wrapper.vm.resources).toEqual(mockResources)
      expect(wrapper.vm.showDropdown).toBe(true)
      expect(wrapper.vm.loading).toBe(false)
    })

    it('handles empty search query', async () => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/resource-types')) {
          return Promise.resolve({ data: { success: true, data: { 'App\\Models\\Product': 'Product' } } })
        }
        if (url.includes('/search')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      wrapper.vm.searchQuery = ''

      await wrapper.vm.searchResources()

      expect(mockRequest.get).toHaveBeenCalledWith(
        '/nova-vendor/menus/resources/App\\Models\\Product/search?'
      )
    })

    it('handles search error gracefully', async () => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/resource-types')) {
          return Promise.resolve({ data: { success: true, data: { 'App\\Models\\Product': 'Product' } } })
        }
        if (url.includes('/search')) {
          return Promise.reject(new Error('Search error'))
        }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'

      await wrapper.vm.searchResources()

      expect(wrapper.vm.error).toBe('Failed to search resources. Please try again.')
      expect(wrapper.vm.resources).toEqual([])
      expect(wrapper.vm.loading).toBe(false)
    })

    it('does not search without selected resource type', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const originalGet = mockRequest.get
      mockRequest.get.mockClear()

      await wrapper.vm.searchResources()

      expect(mockRequest.get).not.toHaveBeenCalledWith(expect.stringContaining('/search'))
    })
  })

  describe('Resource Selection', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { 'App\\Models\\Product': 'Product' } }
      })
    })

    it('selects resource and updates form', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const resource = { id: 1, name: 'Selected Product', slug: 'selected-product' }

      wrapper.vm.selectResource(resource)

      expect(wrapper.vm.selectedResource).toBe(resource)
      expect(wrapper.vm.searchQuery).toBe('Selected Product')
      expect(wrapper.vm.showDropdown).toBe(false)
    })

    it('emits change when resource is selected', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const resource = { id: 1, name: 'Selected Product', slug: 'selected-product' }
      wrapper.vm.selectedResourceType = 'App\\Models\\Product'

      wrapper.vm.selectResource(resource)

      expect(wrapper.emitted('input')).toBeTruthy()
      expect(wrapper.emitted('change')).toBeTruthy()

      const emittedValue = wrapper.emitted('input')[0][0]
      expect(emittedValue).toEqual({
        resource_type: 'App\\Models\\Product',
        resource_id: 1,
        resource_name: 'Selected Product',
        resource_slug: 'selected-product'
      })
    })

    it('displays selected resource', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      wrapper.vm.selectedResource = { id: 1, name: 'Test Product', slug: 'test-product' }
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('App\\Models\\Product: Test Product')
      expect(wrapper.text()).toContain('ID: 1')
    })

    it('clears selection', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResource = { id: 1, name: 'Test Product' }
      wrapper.vm.searchQuery = 'Test Product'

      wrapper.vm.clearSelection()

      expect(wrapper.vm.selectedResource).toBe(null)
      expect(wrapper.vm.searchQuery).toBe('')
      expect(wrapper.vm.showDropdown).toBe(false)
    })

    it('emits change when selection is cleared', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResource = { id: 1, name: 'Test Product' }
      wrapper.vm.clearSelection()

      expect(wrapper.emitted('input')).toBeTruthy()
      expect(wrapper.emitted('change')).toBeTruthy()

      const emittedValue = wrapper.emitted('input')[wrapper.emitted('input').length - 1][0]
      expect(emittedValue).toEqual({
        resource_type: null,
        resource_id: null,
        resource_name: null,
        resource_slug: null
      })
    })
  })

  describe('Dropdown Display', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { 'App\\Models\\Product': 'Product' } }
      })
    })

    it('shows dropdown with search results', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.showDropdown = true
      wrapper.vm.resources = [
        { id: 1, name: 'Product 1' },
        { id: 2, name: 'Product 2' }
      ]
      await wrapper.vm.$nextTick()

      const buttons = wrapper.findAll('button[type="button"]')
      const resourceButtons = buttons.filter(button => 
        button.text() === 'Product 1' || button.text() === 'Product 2'
      )
      expect(resourceButtons).toHaveLength(2)
    })

    it('shows no results message', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.showDropdown = true
      wrapper.vm.searchQuery = 'nonexistent'
      wrapper.vm.resources = []
      wrapper.vm.loading = false
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('No results found')
    })

    it('hides dropdown when showDropdown is false', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.showDropdown = false
      wrapper.vm.resources = [{ id: 1, name: 'Product 1' }]
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).not.toContain('Product 1')
    })
  })

  describe('Error Display', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })
    })

    it('displays error message', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.error = 'Test error message'
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('Test error message')
      expect(wrapper.find('.bg-red-50').exists()).toBe(true)
    })

    it('hides error when no error', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.error = null
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.bg-red-50').exists()).toBe(false)
    })
  })

  describe('Loading State', () => {
    it('shows loading spinner when loading', async () => {
      mockRequest.get.mockImplementation(() => new Promise(() => {})) // Never resolves

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.loading = true
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.animate-spin').exists()).toBe(true)
    })

    it('disables inputs when loading', async () => {
      mockRequest.get.mockImplementation(() => new Promise(() => {}))

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.loading = true
      await wrapper.vm.$nextTick()

      expect(wrapper.find('select').attributes('disabled')).toBeDefined()
      expect(wrapper.find('input[type="text"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('Input/Change Events', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: {} }
      })
    })

    it('emits correct value structure', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.selectedResourceType = 'App\\Models\\Product'
      wrapper.vm.selectedResource = {
        id: 123,
        name: 'Test Product',
        slug: 'test-product'
      }

      wrapper.vm.emitChange()

      expect(wrapper.emitted('input')).toBeTruthy()
      expect(wrapper.emitted('change')).toBeTruthy()

      const emittedValue = wrapper.emitted('input')[0][0]
      expect(emittedValue).toEqual({
        resource_type: 'App\\Models\\Product',
        resource_id: 123,
        resource_name: 'Test Product',
        resource_slug: 'test-product'
      })
    })

    it('emits null values when no selection', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.emitChange()

      const emittedValue = wrapper.emitted('input')[0][0]
      expect(emittedValue).toEqual({
        resource_type: null,
        resource_id: null,
        resource_name: null,
        resource_slug: null
      })
    })
  })
})