import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Tool from '../../resources/js/pages/Tool.vue'

// Mock Nova components
const MockCard = {
  name: 'Card',
  template: '<div class="mock-card"><slot /></div>'
}

const MockHeading = {
  name: 'Heading', 
  template: '<h1><slot /></h1>'
}

const MockModal = {
  name: 'Modal',
  props: ['show', 'maxWidth'],
  template: '<div v-if="show" class="mock-modal"><slot /></div>',
  emits: ['close']
}

const MockHead = {
  name: 'Head',
  props: ['title'],
  template: '<div></div>'
}

describe('Tool.vue', () => {
  let wrapper
  let mockRequest

  beforeEach(() => {
    // Reset mocks
    vi.clearAllMocks()
    
    // Setup request mock
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

  const createWrapper = (options = {}) => {
    return mount(Tool, {
      global: {
        components: {
          Card: MockCard,
          Heading: MockHeading, 
          Modal: MockModal,
          Head: MockHead
        }
      },
      ...options
    })
  }

  describe('Component Mounting', () => {
    it('mounts successfully', () => {
      wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
    })

    it('calls loadMenus on mount', async () => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(mockRequest.get).toHaveBeenCalledWith('/nova-vendor/menus/menus')
    })
  })

  describe('Menu Loading', () => {
    it('displays loading state initially', async () => {
      mockRequest.get.mockImplementation(() => new Promise(() => {})) // Never resolves
      
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.loading).toBe(true)
      expect(wrapper.find('.animate-spin').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading menus...')
    })

    it('loads menus successfully', async () => {
      const mockMenus = [
        { id: 1, name: 'Main Menu', slug: 'main-menu', items_count: 5, max_depth: 6 },
        { id: 2, name: 'Footer Menu', slug: 'footer-menu', items_count: 2, max_depth: 3 }
      ]

      mockRequest.get.mockResolvedValue({
        data: { data: mockMenus, success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick() // Wait for async operation

      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.menus).toEqual(mockMenus)
    })

    it('handles loading error gracefully', async () => {
      mockRequest.get.mockRejectedValue(new Error('Network error'))

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.menus).toEqual([])
      expect(Nova.$toasted.error).toHaveBeenCalledWith('Failed to load menus. Please try again.')
    })
  })

  describe('Menu Display', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('shows empty state when no menus', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('No menus found')
      expect(wrapper.text()).toContain('Create your first menu to get started')
    })

    it('displays menus table when menus exist', async () => {
      const mockMenus = [
        { id: 1, name: 'Main Menu', slug: 'main-menu', items_count: 5, max_depth: 6 }
      ]

      wrapper = createWrapper()
      wrapper.vm.menus = mockMenus
      await wrapper.vm.$nextTick()

      expect(wrapper.find('table').exists()).toBe(true)
      expect(wrapper.text()).toContain('Main Menu')
      expect(wrapper.text()).toContain('main-menu')
      expect(wrapper.text()).toContain('5') // items count
      expect(wrapper.text()).toContain('6') // max depth
    })
  })

  describe('Search Functionality', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('filters menus by name', async () => {
      const mockMenus = [
        { id: 1, name: 'Main Menu', slug: 'main-menu', items_count: 5, max_depth: 6 },
        { id: 2, name: 'Footer Menu', slug: 'footer-menu', items_count: 2, max_depth: 3 }
      ]

      wrapper = createWrapper()
      wrapper.vm.menus = mockMenus
      wrapper.vm.search = 'main'
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.filteredMenus).toEqual([mockMenus[0]])
    })

    it('filters menus by slug', async () => {
      const mockMenus = [
        { id: 1, name: 'Main Menu', slug: 'main-menu', items_count: 5, max_depth: 6 },
        { id: 2, name: 'Footer Menu', slug: 'footer-menu', items_count: 2, max_depth: 3 }
      ]

      wrapper = createWrapper()
      wrapper.vm.menus = mockMenus
      wrapper.vm.search = 'footer'
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.filteredMenus).toEqual([mockMenus[1]])
    })

    it('returns all menus when search is empty', async () => {
      const mockMenus = [
        { id: 1, name: 'Main Menu', slug: 'main-menu', items_count: 5, max_depth: 6 },
        { id: 2, name: 'Footer Menu', slug: 'footer-menu', items_count: 2, max_depth: 3 }
      ]

      wrapper = createWrapper()
      wrapper.vm.menus = mockMenus
      wrapper.vm.search = ''
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.filteredMenus).toEqual(mockMenus)
    })
  })

  describe('Create Menu Modal', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('shows create modal when button clicked', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const createButton = wrapper.find('button:contains("Create Menu")')
      expect(createButton.exists()).toBe(true)

      await createButton.trigger('click')
      
      expect(wrapper.vm.showCreateModal).toBe(true)
      expect(wrapper.find('.mock-modal').exists()).toBe(true)
    })

    it('generates slug from name', async () => {
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.newMenu.name = 'Test Menu With Spaces'
      wrapper.vm.generateSlug()

      expect(wrapper.vm.newMenu.slug).toBe('test-menu-with-spaces')
    })

    it('creates menu successfully', async () => {
      const newMenuData = {
        id: 3,
        name: 'New Menu',
        slug: 'new-menu',
        max_depth: 6,
        items_count: 0
      }

      mockRequest.post.mockResolvedValue({
        data: { data: newMenuData, success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.newMenu = {
        name: 'New Menu',
        slug: 'new-menu',
        max_depth: 6
      }

      await wrapper.vm.createMenu()

      expect(mockRequest.post).toHaveBeenCalledWith('/nova-vendor/menus/menus', wrapper.vm.newMenu)
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Menu "New Menu" created successfully')
      expect(wrapper.vm.showCreateModal).toBe(false)
    })

    it('handles create validation errors', async () => {
      const validationErrors = {
        response: {
          status: 422,
          data: {
            errors: {
              name: ['The name field is required.']
            }
          }
        }
      }

      mockRequest.post.mockRejectedValue(validationErrors)

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      await wrapper.vm.createMenu()

      expect(wrapper.vm.errors).toEqual({ name: ['The name field is required.'] })
      expect(Nova.$toasted.error).toHaveBeenCalledWith('Please correct the errors and try again.')
    })
  })

  describe('Edit Menu Modal', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('shows edit modal with menu data', async () => {
      const menu = { id: 1, name: 'Test Menu', slug: 'test-menu', max_depth: 5 }
      
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.editMenuProperties(menu)

      expect(wrapper.vm.showEditModal).toBe(true)
      expect(wrapper.vm.editingMenu).toEqual(menu)
    })

    it('updates menu successfully', async () => {
      const updatedMenuData = {
        id: 1,
        name: 'Updated Menu',
        slug: 'updated-menu',
        max_depth: 8
      }

      mockRequest.put.mockResolvedValue({
        data: { data: updatedMenuData, success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      wrapper.vm.menus = [{ id: 1, name: 'Old Menu', slug: 'old-menu', max_depth: 5 }]
      wrapper.vm.editingMenu = {
        id: 1,
        name: 'Updated Menu',
        slug: 'updated-menu',
        max_depth: 8
      }

      await wrapper.vm.updateMenu()

      expect(mockRequest.put).toHaveBeenCalledWith('/nova-vendor/menus/menus/1', {
        name: 'Updated Menu',
        slug: 'updated-menu',
        max_depth: 8
      })
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Menu "Updated Menu" updated successfully')
      expect(wrapper.vm.showEditModal).toBe(false)
    })
  })

  describe('Delete Menu', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('deletes menu after confirmation', async () => {
      mockRequest.delete.mockResolvedValue({
        data: { success: true }
      })

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const menu = { id: 1, name: 'Test Menu' }
      wrapper.vm.menus = [menu]

      await wrapper.vm.deleteMenu(menu)

      expect(global.confirm).toHaveBeenCalledWith('Are you sure you want to delete the menu "Test Menu"? This action cannot be undone.')
      expect(mockRequest.delete).toHaveBeenCalledWith('/nova-vendor/menus/menus/1')
      expect(wrapper.vm.menus).toEqual([])
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Menu "Test Menu" deleted successfully')
    })

    it('does not delete if user cancels confirmation', async () => {
      global.confirm.mockReturnValueOnce(false)

      wrapper = createWrapper()
      await wrapper.vm.$nextTick()

      const menu = { id: 1, name: 'Test Menu' }
      wrapper.vm.menus = [menu]

      await wrapper.vm.deleteMenu(menu)

      expect(mockRequest.delete).not.toHaveBeenCalled()
      expect(wrapper.vm.menus).toEqual([menu])
    })
  })

  describe('Navigation', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('navigates to menu edit page', () => {
      wrapper = createWrapper()
      
      const menu = { id: 1, name: 'Test Menu' }
      wrapper.vm.manageItems(menu)

      expect(Nova.visit).toHaveBeenCalledWith('/menus/1/edit')
    })
  })

  describe('Form Reset Functions', () => {
    beforeEach(() => {
      mockRequest.get.mockResolvedValue({
        data: { data: [], success: true }
      })
    })

    it('resets new menu form', () => {
      wrapper = createWrapper()
      
      wrapper.vm.newMenu = { name: 'Test', slug: 'test', max_depth: 8 }
      wrapper.vm.resetNewMenu()

      expect(wrapper.vm.newMenu).toEqual({
        name: '',
        slug: '',
        max_depth: 6
      })
    })

    it('resets editing menu form', () => {
      wrapper = createWrapper()
      
      wrapper.vm.editingMenu = { id: 1, name: 'Test', slug: 'test', max_depth: 8 }
      wrapper.vm.resetEditingMenu()

      expect(wrapper.vm.editingMenu).toEqual({
        id: null,
        name: '',
        slug: '',
        max_depth: 6
      })
    })

    it('closes create modal and resets form', () => {
      wrapper = createWrapper()
      
      wrapper.vm.showCreateModal = true
      wrapper.vm.errors = { name: ['Error'] }
      wrapper.vm.closeModal()

      expect(wrapper.vm.showCreateModal).toBe(false)
      expect(wrapper.vm.errors).toEqual({})
      expect(wrapper.vm.newMenu).toEqual({
        name: '',
        slug: '',
        max_depth: 6
      })
    })
  })
})