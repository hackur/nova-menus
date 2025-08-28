import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MenuEdit from '../../resources/js/pages/MenuEdit.vue'

// Mock Nova components and child components
const MockCard = {
  name: 'Card',
  template: '<div class="mock-card"><slot /></div>'
}

const MockHeading = {
  name: 'Heading',
  template: '<h1><slot /></h1>'
}

const MockHead = {
  name: 'Head',
  props: ['title'],
  template: '<div></div>'
}

const MockNested = {
  name: 'Nested',
  props: ['items', 'currentDepth', 'maxDepth', 'menuId'],
  template: '<div class="mock-nested">{{ items.length }} items</div>',
  emits: ['structure-changed', 'item-saved', 'item-deleted']
}

describe('MenuEdit.vue', () => {
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
  })

  afterEach(() => {
    wrapper?.unmount()
  })

  const createWrapper = (props = { menuId: 1 }, options = {}) => {
    return mount(MenuEdit, {
      props,
      global: {
        components: {
          Card: MockCard,
          Heading: MockHeading,
          Head: MockHead,
          Nested: MockNested
        }
      },
      ...options
    })
  }

  describe('Component Mounting', () => {
    it('mounts successfully with menuId prop', () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })

      wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.props('menuId')).toBe(1)
    })

    it('calls loadMenu and loadMenuItems on mount', async () => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(mockRequest.get).toHaveBeenCalledWith('/nova-vendor/menus/menus/1')
      expect(mockRequest.get).toHaveBeenCalledWith('/nova-vendor/menus/menus/1/items')
    })
  })

  describe('Menu Loading', () => {
    it('displays loading state initially', async () => {
      mockRequest.get.mockImplementation(() => new Promise(() => {}))
      
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.loading).toBe(true)
      expect(wrapper.find('.animate-spin').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading menu...')
    })

    it('loads menu data successfully', async () => {
      const mockMenu = {
        id: 1,
        name: 'Test Menu',
        slug: 'test-menu',
        max_depth: 5,
        items_count: 3
      }

      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
        return Promise.resolve({ data: { success: true, data: mockMenu } })
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.menu).toEqual(mockMenu)
    })

    it('handles menu loading error gracefully', async () => {
      mockRequest.get.mockRejectedValue(new Error('Network error'))

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.menu.name).toBe('Menu Not Found')
      expect(Nova.$toasted.error).toHaveBeenCalled()
    })

    it('loads menu items successfully', async () => {
      const mockMenuItems = [
        { id: 1, name: 'Home', custom_url: '/', children: [] },
        { id: 2, name: 'About', custom_url: '/about', children: [] }
      ]

      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: mockMenuItems } })
        }
        return Promise.resolve({ data: { success: true, data: { id: 1, name: 'Test Menu' } } })
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.menuItems).toEqual(mockMenuItems)
    })

    it('handles menu items loading error', async () => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.reject(new Error('Items loading error'))
        }
        return Promise.resolve({ data: { success: true, data: { id: 1, name: 'Test Menu' } } })
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.menuItems).toEqual([])
      expect(Nova.$toasted.error).toHaveBeenCalledWith(
        expect.stringContaining('Failed to load menu items')
      )
    })
  })

  describe('Menu Display', () => {
    beforeEach(() => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
        return Promise.resolve({
          data: { 
            success: true, 
            data: { id: 1, name: 'Test Menu', max_depth: 5 } 
          }
        })
      })
    })

    it('displays menu name in heading', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('Test Menu')
    })

    it('shows empty state when no menu items', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('This menu has no items yet')
      expect(wrapper.text()).toContain('Add First Menu Item')
    })

    it('displays nested component when menu items exist', async () => {
      wrapper = createWrapper()
      wrapper.vm.menuItems = [
        { id: 1, name: 'Home', custom_url: '/', children: [] }
      ]
      await wrapper.vm.$nextTick()

      expect(wrapper.findComponent(MockNested).exists()).toBe(true)
      expect(wrapper.findComponent(MockNested).props()).toEqual({
        items: wrapper.vm.menuItems,
        currentDepth: 0,
        maxDepth: 5,
        menuId: 1
      })
    })
  })

  describe('Adding Menu Items', () => {
    beforeEach(() => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
        return Promise.resolve({
          data: { 
            success: true, 
            data: { id: 1, name: 'Test Menu', max_depth: 5 } 
          }
        })
      })
    })

    it('adds new menu item when showAddItemModal is called', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.menuItems).toHaveLength(0)

      wrapper.vm.showAddItemModal()

      expect(wrapper.vm.menuItems).toHaveLength(1)
      const newItem = wrapper.vm.menuItems[0]
      expect(newItem.isNew).toBe(true)
      expect(newItem.expanded).toBe(true)
      expect(newItem.link_type).toBe('url')
      expect(newItem.is_active).toBe(true)
      expect(newItem.visibility_type).toBe('always_show')
    })

    it('focuses on name input after adding item', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const mockInput = { focus: vi.fn() }
      const mockContainer = { scrollIntoView: vi.fn() }
      mockInput.closest = vi.fn(() => mockContainer)

      vi.spyOn(document, 'querySelector').mockReturnValue(mockInput)

      wrapper.vm.showAddItemModal()
      await wrapper.vm.$nextTick()

      expect(mockInput.focus).toHaveBeenCalled()
      expect(mockContainer.scrollIntoView).toHaveBeenCalledWith({
        behavior: 'smooth',
        block: 'nearest'
      })
    })
  })

  describe('Menu Structure Rebuilding', () => {
    beforeEach(() => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
        return Promise.resolve({
          data: { 
            success: true, 
            data: { id: 1, name: 'Test Menu', max_depth: 5 } 
          }
        })
      })
    })

    it('rebuilds menu structure successfully', async () => {
      mockRequest.put.mockResolvedValue({
        data: { success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const testItems = [
        { id: 1, name: 'Home', custom_url: '/', is_active: true },
        { id: 2, name: 'About', custom_url: '/about', is_active: true }
      ]
      wrapper.vm.menuItems = testItems

      await wrapper.vm.rebuildMenuStructure()

      expect(mockRequest.put).toHaveBeenCalledWith(
        '/nova-vendor/menus/menus/1/items/rebuild',
        expect.objectContaining({
          menu_structure: expect.any(Array)
        })
      )
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Menu structure updated successfully')
    })

    it('handles rebuild error gracefully', async () => {
      mockRequest.put.mockRejectedValue(new Error('Rebuild error'))

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      await wrapper.vm.rebuildMenuStructure()

      expect(Nova.$toasted.error).toHaveBeenCalledWith('Failed to update menu structure')
      expect(mockRequest.get).toHaveBeenCalledWith('/nova-vendor/menus/menus/1/items') // Reload items
    })
  })

  describe('Clean Menu Items for API', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })
    })

    it('cleans menu items for API submission', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const testItems = [
        {
          id: 1,
          name: 'Home',
          custom_url: '/',
          resource_type: null,
          resource_id: null,
          resource_slug: null,
          display_at: null,
          hide_at: null,
          is_active: true,
          expanded: true, // Should be removed
          isNew: false, // Should be removed
          children: [
            {
              id: 2,
              name: 'Child',
              custom_url: '/child',
              is_active: false,
              someExtraField: 'should be removed'
            }
          ]
        }
      ]

      const cleaned = wrapper.vm.cleanMenuItemsForAPI(testItems)

      expect(cleaned).toEqual([
        {
          id: 1,
          name: 'Home',
          custom_url: '/',
          resource_type: null,
          resource_id: null,
          resource_slug: null,
          display_at: null,
          hide_at: null,
          is_active: true,
          children: [
            {
              id: 2,
              name: 'Child',
              custom_url: '/child',
              resource_type: null,
              resource_id: null,
              resource_slug: null,
              display_at: null,
              hide_at: null,
              is_active: false
            }
          ]
        }
      ])
    })
  })

  describe('Remove Item from Menu Items', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })
    })

    it('removes item by ID', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.menuItems = [
        { id: 1, name: 'Home', children: [] },
        { id: 2, name: 'About', children: [] }
      ]

      wrapper.vm.removeItemFromMenuItems({ id: 1 })

      expect(wrapper.vm.menuItems).toEqual([
        { id: 2, name: 'About', children: [] }
      ])
    })

    it('removes item by reference', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const item1 = { id: 1, name: 'Home', children: [] }
      const item2 = { id: 2, name: 'About', children: [] }
      wrapper.vm.menuItems = [item1, item2]

      wrapper.vm.removeItemFromMenuItems(item1)

      expect(wrapper.vm.menuItems).toEqual([item2])
    })

    it('removes nested item from children', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const childItem = { id: 2, name: 'Child', children: [] }
      wrapper.vm.menuItems = [
        { id: 1, name: 'Parent', children: [childItem] }
      ]

      wrapper.vm.removeItemFromMenuItems(childItem)

      expect(wrapper.vm.menuItems[0].children).toEqual([])
    })
  })

  describe('Navigation', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })
    })

    it('navigates back to menus list', () => {
      wrapper = createWrapper()
      
      wrapper.vm.goBackToMenus()

      expect(Nova.visit).toHaveBeenCalledWith('/menus')
    })
  })

  describe('Scroll to Top', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { success: true, data: { id: 1, name: 'Test Menu' } }
      })
    })

    it('scrolls to top when scrollToTop is called', () => {
      wrapper = createWrapper()
      
      wrapper.vm.scrollToTop()

      expect(global.scrollTo).toHaveBeenCalledWith({
        top: 0,
        behavior: 'smooth'
      })
    })
  })

  describe('Component Integration', () => {
    beforeEach(() => {
      mockRequest.get.mockImplementation((url) => {
        if (url.includes('/items')) {
          return Promise.resolve({ data: { success: true, data: [] } })
        }
        return Promise.resolve({
          data: { 
            success: true, 
            data: { id: 1, name: 'Test Menu', max_depth: 5 } 
          }
        })
      })
    })

    it('handles nested component structure-changed event', async () => {
      const rebuildSpy = vi.spyOn(MenuEdit.methods, 'rebuildMenuStructure').mockImplementation(() => {})
      
      wrapper = createWrapper()
      wrapper.vm.menuItems = [{ id: 1, name: 'Test', children: [] }]
      await wrapper.vm.$nextTick()

      const nestedComponent = wrapper.findComponent(MockNested)
      await nestedComponent.vm.$emit('structure-changed')

      expect(rebuildSpy).toHaveBeenCalled()
    })

    it('handles nested component item-saved event', async () => {
      const scrollSpy = vi.spyOn(MenuEdit.methods, 'scrollToTop').mockImplementation(() => {})
      
      wrapper = createWrapper()
      wrapper.vm.menuItems = [{ id: 1, name: 'Test', children: [] }]
      await wrapper.vm.$nextTick()

      const nestedComponent = wrapper.findComponent(MockNested)
      await nestedComponent.vm.$emit('item-saved')

      expect(scrollSpy).toHaveBeenCalled()
    })

    it('handles nested component item-deleted event', async () => {
      const removeSpy = vi.spyOn(MenuEdit.methods, 'removeItemFromMenuItems').mockImplementation(() => {})
      
      wrapper = createWrapper()
      wrapper.vm.menuItems = [{ id: 1, name: 'Test', children: [] }]
      await wrapper.vm.$nextTick()

      const testItem = { id: 1, name: 'Test' }
      const nestedComponent = wrapper.findComponent(MockNested)
      await nestedComponent.vm.$emit('item-deleted', testItem)

      expect(removeSpy).toHaveBeenCalledWith(testItem)
    })
  })
})