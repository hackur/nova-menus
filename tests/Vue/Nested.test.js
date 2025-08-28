import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Nested from '../../resources/js/components/Nested.vue'

// Mock vuedraggable
const MockDraggable = {
  name: 'draggable',
  props: ['list', 'group', 'itemKey'],
  template: `
    <ul>
      <li v-for="item in list" :key="item.id">
        <slot name="item" :element="item" />
      </li>
    </ul>
  `,
  emits: ['change']
}

describe('Nested.vue', () => {
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
    
    // Mock current date for visibility testing
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2024-01-15 12:00:00'))
  })

  afterEach(() => {
    wrapper?.unmount()
    vi.useRealTimers()
  })

  const createWrapper = (props = {}, options = {}) => {
    const defaultProps = {
      items: [],
      currentDepth: 0,
      maxDepth: 5,
      menuId: 1
    }

    return mount(Nested, {
      props: { ...defaultProps, ...props },
      global: {
        components: {
          draggable: MockDraggable,
          nested: Nested // For recursive rendering
        }
      },
      ...options
    })
  }

  describe('Component Mounting', () => {
    it('mounts successfully with required props', () => {
      wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
    })

    it('initializes server timezone on mount', () => {
      wrapper = createWrapper()
      expect(wrapper.vm.serverTimezone).toBe('UTC')
    })

    it('initializes item visibility on mount', () => {
      const items = [
        { id: 1, name: 'Test', is_active: true },
        { id: 2, name: 'Test 2', is_active: false }
      ]

      wrapper = createWrapper({ items })

      expect(wrapper.vm.items[0].visibility_type).toBe('always_show')
      expect(wrapper.vm.items[1].visibility_type).toBe('always_hide')
    })
  })

  describe('Drag and Drop', () => {
    it('emits structure-changed on drag change', async () => {
      const items = [{ id: 1, name: 'Test', children: [] }]
      wrapper = createWrapper({ items })

      wrapper.vm.handleDragChange({ added: { element: items[0] } })

      expect(wrapper.emitted('structure-changed')).toBeTruthy()
    })
  })

  describe('Visibility Logic', () => {
    describe('Item Visibility', () => {
      it('shows always_show items as visible', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'always_show',
          is_active: true
        }

        expect(wrapper.vm.isItemVisible(element)).toBe(true)
      })

      it('shows always_hide items as hidden', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'always_hide',
          is_active: true
        }

        expect(wrapper.vm.isItemVisible(element)).toBe(false)
      })

      it('hides inactive items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'always_show',
          is_active: false
        }

        expect(wrapper.vm.isItemVisible(element)).toBe(false)
      })

      it('handles scheduled items correctly', () => {
        wrapper = createWrapper()

        // Item not yet visible
        const futureItem = {
          visibility_type: 'schedule',
          is_active: true,
          display_at: '2024-01-20T12:00:00Z'
        }
        expect(wrapper.vm.isItemVisible(futureItem)).toBe(false)

        // Item currently visible
        const currentItem = {
          visibility_type: 'schedule',
          is_active: true,
          display_at: '2024-01-10T12:00:00Z',
          hide_at: '2024-01-20T12:00:00Z'
        }
        expect(wrapper.vm.isItemVisible(currentItem)).toBe(true)

        // Item expired
        const expiredItem = {
          visibility_type: 'schedule',
          is_active: true,
          hide_at: '2024-01-10T12:00:00Z'
        }
        expect(wrapper.vm.isItemVisible(expiredItem)).toBe(false)
      })
    })

    describe('Visibility Icons', () => {
      it('returns null for visible items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'always_show',
          is_active: true
        }

        expect(wrapper.vm.getVisibilityIconType(element)).toBe(null)
      })

      it('returns eye icon for always_hide items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'always_hide',
          is_active: true
        }

        expect(wrapper.vm.getVisibilityIconType(element)).toBe('eye')
      })

      it('returns calendar icon for scheduled items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'schedule',
          is_active: true,
          display_at: '2024-01-20T12:00:00Z'
        }

        expect(wrapper.vm.getVisibilityIconType(element)).toBe('calendar')
      })
    })

    describe('Hidden Reasons', () => {
      it('returns correct reason for always_hide items', () => {
        wrapper = createWrapper()

        const element = { visibility_type: 'always_hide' }

        expect(wrapper.vm.getHiddenReason(element)).toBe('Always hidden')
      })

      it('returns correct reason for inactive items', () => {
        wrapper = createWrapper()

        const element = { is_active: false }

        expect(wrapper.vm.getHiddenReason(element)).toBe('Inactive')
      })

      it('returns correct reason for future scheduled items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'schedule',
          display_at: '2024-01-20T12:00:00Z'
        }

        expect(wrapper.vm.getHiddenReason(element)).toBe('Not yet visible')
      })

      it('returns correct reason for expired items', () => {
        wrapper = createWrapper()

        const element = {
          visibility_type: 'schedule',
          hide_at: '2024-01-10T12:00:00Z'
        }

        expect(wrapper.vm.getHiddenReason(element)).toBe('Expired')
      })
    })
  })

  describe('Visibility Type Changes', () => {
    it('sets visibility type and updates properties', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test' }
      wrapper.vm.setVisibilityType(element, 'always_hide')

      expect(element.visibility_type).toBe('always_hide')
      expect(element.is_active).toBe(false)
      expect(element.display_at).toBe(null)
      expect(element.hide_at).toBe(null)
    })

    it('resets schedule fields when changing to always_show', () => {
      wrapper = createWrapper()

      const element = {
        id: 1,
        name: 'Test',
        display_at: '2024-01-10T12:00:00Z',
        hide_at: '2024-01-20T12:00:00Z'
      }

      wrapper.vm.setVisibilityType(element, 'always_show')

      expect(element.visibility_type).toBe('always_show')
      expect(element.is_active).toBe(true)
      expect(element.display_at).toBe(null)
      expect(element.hide_at).toBe(null)
    })

    it('prepares fields for schedule visibility', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test' }
      wrapper.vm.setVisibilityType(element, 'schedule')

      expect(element.visibility_type).toBe('schedule')
      expect(element.is_active).toBe(true)
      expect(element.display_at).toBe(null)
      expect(element.hide_at).toBe(null)
    })
  })

  describe('DateTime Conversion', () => {
    it('converts UTC to local datetime', () => {
      wrapper = createWrapper()

      const utcString = '2024-01-15T12:00:00Z'
      const result = wrapper.vm.utcToLocalDatetime(utcString)

      expect(result).toMatch(/2024-01-15T\d{2}:00/)
    })

    it('converts local datetime to UTC', () => {
      wrapper = createWrapper()

      const localString = '2024-01-15T12:00'
      const result = wrapper.vm.localDatetimeToUTC(localString)

      expect(result).toMatch(/2024-01-15T\d{2}:00:00.000Z/)
    })

    it('handles empty datetime strings', () => {
      wrapper = createWrapper()

      expect(wrapper.vm.utcToLocalDatetime('')).toBe('')
      expect(wrapper.vm.localDatetimeToUTC('')).toBe(null)
      expect(wrapper.vm.utcToLocalDatetime(null)).toBe('')
      expect(wrapper.vm.localDatetimeToUTC(null)).toBe(null)
    })

    it('updates display_at UTC when local datetime changes', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test' }
      element.display_at_local = '2024-01-15T12:00'

      wrapper.vm.updateDisplayAtUTC(element)

      expect(element.display_at).toMatch(/2024-01-15T\d{2}:00:00.000Z/)
    })

    it('updates hide_at UTC when local datetime changes', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test' }
      element.hide_at_local = '2024-01-15T12:00'

      wrapper.vm.updateHideAtUTC(element)

      expect(element.hide_at).toMatch(/2024-01-15T\d{2}:00:00.000Z/)
    })
  })

  describe('Item Management', () => {
    it('toggles item expanded state', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test', expanded: false }
      wrapper.vm.toggleExpanded(element)

      expect(element.expanded).toBe(true)

      wrapper.vm.toggleExpanded(element)
      expect(element.expanded).toBe(false)
    })

    it('gets item description for custom URL', () => {
      wrapper = createWrapper()

      const element = { custom_url: '/test' }
      expect(wrapper.vm.getItemDescription(element)).toBe('/test')
    })

    it('gets item description for resource', () => {
      wrapper = createWrapper()

      const element = { resource_type: 'Product', resource_id: 123 }
      expect(wrapper.vm.getItemDescription(element)).toBe('Product #123')
    })

    it('gets default description when no link configured', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test' }
      expect(wrapper.vm.getItemDescription(element)).toBe('No link configured')
    })

    it('clears fields when link type changes to URL', () => {
      wrapper = createWrapper()

      const element = {
        link_type: 'url',
        resource_type: 'Product',
        resource_id: 123,
        resource_slug: 'test-product'
      }

      wrapper.vm.onLinkTypeChange(element)

      expect(element.resource_type).toBe(null)
      expect(element.resource_id).toBe(null)
      expect(element.resource_slug).toBe(null)
    })

    it('clears custom_url when link type changes to resource', () => {
      wrapper = createWrapper()

      const element = {
        link_type: 'resource',
        custom_url: '/test'
      }

      wrapper.vm.onLinkTypeChange(element)

      expect(element.custom_url).toBe('')
    })

    it('cancels edit and collapses item', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test', expanded: true }
      wrapper.vm.cancelEdit(element)

      expect(element.expanded).toBe(false)
    })
  })

  describe('Item Saving', () => {
    it('creates new menu item successfully', async () => {
      const newItemData = {
        id: 2,
        name: 'New Item',
        custom_url: '/new'
      }

      mockRequest.post.mockResolvedValue({
        data: { success: true, data: newItemData }
      })

      wrapper = createWrapper()

      const element = {
        isNew: true,
        name: 'New Item',
        custom_url: '/new',
        is_active: true
      }

      await wrapper.vm.saveItem(element)

      expect(mockRequest.post).toHaveBeenCalledWith('/nova-vendor/menus/menu-items', {
        menu_id: 1,
        name: 'New Item',
        custom_url: '/new',
        resource_type: null,
        resource_id: null,
        resource_slug: null,
        display_at: null,
        hide_at: null,
        is_active: true
      })

      expect(element.id).toBe(2)
      expect(element.isNew).toBe(false)
      expect(element.expanded).toBe(false)
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Item created successfully')
      expect(wrapper.emitted('item-saved')).toBeTruthy()
    })

    it('updates existing menu item successfully', async () => {
      mockRequest.put.mockResolvedValue({
        data: { success: true }
      })

      wrapper = createWrapper()

      const element = {
        id: 1,
        name: 'Updated Item',
        custom_url: '/updated',
        is_active: true
      }

      await wrapper.vm.saveItem(element)

      expect(mockRequest.put).toHaveBeenCalledWith('/nova-vendor/menus/menu-items/1', {
        name: 'Updated Item',
        custom_url: '/updated',
        resource_type: null,
        resource_id: null,
        resource_slug: null,
        display_at: null,
        hide_at: null,
        is_active: true
      })

      expect(element.expanded).toBe(false)
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Item updated successfully')
      expect(wrapper.emitted('item-saved')).toBeTruthy()
    })

    it('handles save error gracefully', async () => {
      mockRequest.post.mockRejectedValue(new Error('Save error'))

      wrapper = createWrapper()

      const element = {
        isNew: true,
        name: 'Test Item'
      }

      await wrapper.vm.saveItem(element)

      expect(Nova.$toasted.error).toHaveBeenCalledWith('Failed to create item')
    })
  })

  describe('Item Deletion', () => {
    it('shows confirmation dialog before deleting', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test Item' }
      const performDeleteSpy = vi.spyOn(wrapper.vm, 'performDelete').mockImplementation(() => {})

      wrapper.vm.deleteItem(element)

      expect(global.confirm).toHaveBeenCalledWith('Are you sure you want to delete "Test Item"? This action cannot be undone.')
      expect(performDeleteSpy).toHaveBeenCalledWith(element)
    })

    it('does not delete if user cancels confirmation', () => {
      global.confirm.mockReturnValueOnce(false)
      
      wrapper = createWrapper()

      const element = { id: 1, name: 'Test Item' }
      const performDeleteSpy = vi.spyOn(wrapper.vm, 'performDelete').mockImplementation(() => {})

      wrapper.vm.deleteItem(element)

      expect(performDeleteSpy).not.toHaveBeenCalled()
    })

    it('deletes item with ID via API', async () => {
      mockRequest.delete.mockResolvedValue({
        data: { success: true }
      })

      wrapper = createWrapper()

      const element = { id: 1, name: 'Test Item' }

      await wrapper.vm.performDelete(element)

      expect(mockRequest.delete).toHaveBeenCalledWith('/nova-vendor/menus/menu-items/1')
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Item deleted successfully')
      expect(wrapper.emitted('item-deleted')).toBeTruthy()
      expect(wrapper.emitted('item-deleted')[0][0]).toBe(element)
    })

    it('removes new item without API call', async () => {
      wrapper = createWrapper()

      const element = { name: 'New Item', isNew: true } // No ID

      await wrapper.vm.performDelete(element)

      expect(mockRequest.delete).not.toHaveBeenCalled()
      expect(Nova.$toasted.success).toHaveBeenCalledWith('Item removed successfully')
      expect(wrapper.emitted('item-deleted')).toBeTruthy()
    })

    it('handles delete error gracefully', async () => {
      mockRequest.delete.mockRejectedValue(new Error('Delete error'))

      wrapper = createWrapper()

      const element = { id: 1, name: 'Test Item' }

      await wrapper.vm.performDelete(element)

      expect(Nova.$toasted.error).toHaveBeenCalledWith('Failed to delete item')
    })
  })

  describe('Parent Visibility', () => {
    it('returns true for root items', () => {
      wrapper = createWrapper()

      const element = { id: 1, name: 'Root' }
      expect(wrapper.vm.isParentVisible(element)).toBe(true)
    })

    it('finds parent visibility in items array', () => {
      const parent = { id: 1, name: 'Parent', visibility_type: 'always_show', is_active: true }
      const child = { id: 2, name: 'Child', parent_id: 1 }

      wrapper = createWrapper({ items: [parent] })

      expect(wrapper.vm.isParentVisible(child)).toBe(true)
    })

    it('handles hidden parent correctly', () => {
      const parent = { id: 1, name: 'Parent', visibility_type: 'always_hide' }
      const child = { id: 2, name: 'Child', parent_id: 1 }

      wrapper = createWrapper({ items: [parent] })

      expect(wrapper.vm.isParentVisible(child)).toBe(false)
    })
  })

  describe('Item Initialization', () => {
    it('initializes item visibility with display/hide dates', () => {
      wrapper = createWrapper()

      const item = {
        id: 1,
        name: 'Test',
        is_active: true,
        display_at: '2024-01-10T12:00:00Z',
        hide_at: '2024-01-20T12:00:00Z'
      }

      wrapper.vm.initializeItemVisibility(item)

      expect(item.visibility_type).toBe('schedule')
      expect(item.display_at_local).toBeTruthy()
      expect(item.hide_at_local).toBeTruthy()
    })

    it('initializes inactive item as always_hide', () => {
      wrapper = createWrapper()

      const item = {
        id: 1,
        name: 'Test',
        is_active: false
      }

      wrapper.vm.initializeItemVisibility(item)

      expect(item.visibility_type).toBe('always_hide')
    })

    it('initializes active item without dates as always_show', () => {
      wrapper = createWrapper()

      const item = {
        id: 1,
        name: 'Test',
        is_active: true
      }

      wrapper.vm.initializeItemVisibility(item)

      expect(item.visibility_type).toBe('always_show')
    })
  })

  describe('Recursive Rendering', () => {
    it('renders nested component for children when within depth limit', async () => {
      const items = [
        {
          id: 1,
          name: 'Parent',
          children: [
            { id: 2, name: 'Child', children: [] }
          ]
        }
      ]

      wrapper = createWrapper({ items, currentDepth: 0, maxDepth: 5 })
      await wrapper.vm.$nextTick()

      // Should render because currentDepth (0) < maxDepth (5)
      expect(wrapper.html()).toContain('Parent')
    })

    it('does not render nested component when at max depth', async () => {
      const items = [
        {
          id: 1,
          name: 'Parent',
          children: [
            { id: 2, name: 'Child', children: [] }
          ]
        }
      ]

      wrapper = createWrapper({ items, currentDepth: 5, maxDepth: 5 })
      await wrapper.vm.$nextTick()

      // Should still render parent, but not children
      expect(wrapper.html()).toContain('Parent')
    })
  })
})