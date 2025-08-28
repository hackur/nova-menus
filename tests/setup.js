import { vi } from 'vitest'

// Mock Nova global object with better request handling
global.Nova = {
  request: vi.fn(() => {
    const mockRequest = {
      get: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
      post: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
      put: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
      delete: vi.fn().mockResolvedValue({ data: { success: true } })
    }
    return mockRequest
  }),
  visit: vi.fn(),
  $toasted: {
    success: vi.fn(),
    error: vi.fn()
  },
  config: vi.fn((key) => {
    if (key === 'timezone') return 'UTC'
    return null
  })
}

// Mock global functions
global.confirm = vi.fn(() => true)

// Mock DOM methods
Object.defineProperty(window, 'scrollTo', {
  value: vi.fn(),
  writable: true
})

// Mock IntersectionObserver
global.IntersectionObserver = vi.fn(() => ({
  disconnect: vi.fn(),
  observe: vi.fn(),
  unobserve: vi.fn(),
}))