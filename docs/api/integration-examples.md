# Frontend Integration Examples

This document provides practical examples for integrating the Menu API into frontend applications using various technologies and patterns.

## React Integration

### Basic Menu Fetching Hook

```javascript
import { useState, useEffect } from 'react';

export const useMenu = (menuSlug) => {
  const [menu, setMenu] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchMenu = async () => {
      try {
        setLoading(true);
        const response = await fetch(`/api/menus/${menuSlug}`);
        
        if (!response.ok) {
          throw new Error(`Failed to fetch menu: ${response.statusText}`);
        }
        
        const data = await response.json();
        setMenu(data);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchMenu();
  }, [menuSlug]);

  return { menu, loading, error };
};
```

### Multi-Menu Hook

```javascript
import { useState, useEffect } from 'react';

export const useMenus = (menuSlugs) => {
  const [menus, setMenus] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchMenus = async () => {
      try {
        setLoading(true);
        const slugs = Array.isArray(menuSlugs) ? menuSlugs.join(',') : menuSlugs;
        const response = await fetch(`/api/menus?menus=${slugs}`);
        
        if (!response.ok) {
          throw new Error(`Failed to fetch menus: ${response.statusText}`);
        }
        
        const data = await response.json();
        setMenus(data.menus);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchMenus();
  }, [menuSlugs]);

  return { menus, loading, error };
};
```

### Menu Component

```jsx
import React from 'react';
import { useMenu } from './hooks/useMenu';

const MenuItem = ({ item, level = 0 }) => {
  const hasChildren = item.children && item.children.length > 0;
  
  return (
    <li className={`menu-item level-${level} ${item.css_class || ''}`}>
      <a 
        href={item.url} 
        target={item.target}
        className="menu-link"
      >
        {item.icon && <i className={`icon ${item.icon}`}></i>}
        {item.name}
      </a>
      
      {hasChildren && (
        <ul className="submenu">
          {item.children.map(child => (
            <MenuItem key={child.id} item={child} level={level + 1} />
          ))}
        </ul>
      )}
    </li>
  );
};

const Menu = ({ slug }) => {
  const { menu, loading, error } = useMenu(slug);

  if (loading) return <div className="menu-loading">Loading menu...</div>;
  if (error) return <div className="menu-error">Error: {error}</div>;
  if (!menu || !menu.items) return null;

  return (
    <nav className={`menu menu-${slug}`}>
      <ul className="menu-list">
        {menu.items.map(item => (
          <MenuItem key={item.id} item={item} />
        ))}
      </ul>
    </nav>
  );
};

export default Menu;
```

## Vue.js Integration

### Menu Store (Vuex/Pinia)

```javascript
// stores/menu.js
import { defineStore } from 'pinia';

export const useMenuStore = defineStore('menu', {
  state: () => ({
    menus: {},
    loading: false,
    error: null
  }),

  getters: {
    getMenu: (state) => (slug) => state.menus[slug] || null,
    
    hasMenu: (state) => (slug) => Boolean(state.menus[slug])
  },

  actions: {
    async fetchMenu(slug) {
      if (this.menus[slug]) return this.menus[slug];

      this.loading = true;
      this.error = null;

      try {
        const response = await fetch(`/api/menus/${slug}`);
        
        if (!response.ok) {
          throw new Error(`Failed to fetch menu: ${response.statusText}`);
        }
        
        const data = await response.json();
        this.menus[slug] = data;
        
        return data;
      } catch (error) {
        this.error = error.message;
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchMultipleMenus(slugs) {
      this.loading = true;
      this.error = null;

      try {
        const slugString = Array.isArray(slugs) ? slugs.join(',') : slugs;
        const response = await fetch(`/api/menus?menus=${slugString}`);
        
        if (!response.ok) {
          throw new Error(`Failed to fetch menus: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        // Store each menu individually
        Object.keys(data.menus).forEach(slug => {
          if (!data.menus[slug].error) {
            this.menus[slug] = {
              slug,
              name: data.menus[slug].name,
              items: data.menus[slug].items,
              timestamp: data.timestamp
            };
          }
        });
        
        return data.menus;
      } catch (error) {
        this.error = error.message;
        throw error;
      } finally {
        this.loading = false;
      }
    }
  }
});
```

### Menu Component (Vue 3)

```vue
<template>
  <nav v-if="menu" :class="`menu menu-${slug}`">
    <ul class="menu-list">
      <MenuItemComponent 
        v-for="item in menu.items" 
        :key="item.id" 
        :item="item" 
        :level="0"
      />
    </ul>
  </nav>
  
  <div v-else-if="loading" class="menu-loading">
    Loading menu...
  </div>
  
  <div v-else-if="error" class="menu-error">
    Error: {{ error }}
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useMenuStore } from '@/stores/menu';
import MenuItemComponent from './MenuItemComponent.vue';

const props = defineProps({
  slug: {
    type: String,
    required: true
  }
});

const menuStore = useMenuStore();

const menu = computed(() => menuStore.getMenu(props.slug));
const loading = computed(() => menuStore.loading);
const error = computed(() => menuStore.error);

onMounted(() => {
  if (!menuStore.hasMenu(props.slug)) {
    menuStore.fetchMenu(props.slug);
  }
});
</script>
```

### Menu Item Component

```vue
<template>
  <li :class="itemClasses">
    <a 
      :href="item.url" 
      :target="item.target"
      class="menu-link"
    >
      <i v-if="item.icon" :class="`icon ${item.icon}`"></i>
      {{ item.name }}
    </a>
    
    <ul v-if="hasChildren" class="submenu">
      <MenuItemComponent 
        v-for="child in item.children" 
        :key="child.id" 
        :item="child" 
        :level="level + 1"
      />
    </ul>
  </li>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  level: {
    type: Number,
    default: 0
  }
});

const hasChildren = computed(() => 
  props.item.children && props.item.children.length > 0
);

const itemClasses = computed(() => [
  'menu-item',
  `level-${props.level}`,
  props.item.css_class || ''
]);
</script>
```

## JavaScript (Vanilla) Integration

### Menu Manager Class

```javascript
class MenuManager {
  constructor(options = {}) {
    this.cache = new Map();
    this.cacheTimeout = options.cacheTimeout || 5 * 60 * 1000; // 5 minutes
    this.baseUrl = options.baseUrl || '/api';
  }

  async getMenu(slug, useCache = true) {
    const cacheKey = `menu:${slug}`;
    
    if (useCache && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey).data;
    }

    try {
      const response = await fetch(`${this.baseUrl}/menus/${slug}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      
      this.cache.set(cacheKey, {
        data,
        timestamp: Date.now()
      });
      
      return data;
    } catch (error) {
      console.error(`Failed to fetch menu ${slug}:`, error);
      throw error;
    }
  }

  async getMenus(slugs, useCache = true) {
    const slugArray = Array.isArray(slugs) ? slugs : [slugs];
    const slugString = slugArray.join(',');
    const cacheKey = `menus:${slugString}`;
    
    if (useCache && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey).data;
    }

    try {
      const response = await fetch(`${this.baseUrl}/menus?menus=${slugString}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      
      this.cache.set(cacheKey, {
        data,
        timestamp: Date.now()
      });
      
      return data;
    } catch (error) {
      console.error(`Failed to fetch menus ${slugString}:`, error);
      throw error;
    }
  }

  isCacheValid(key) {
    if (!this.cache.has(key)) return false;
    
    const cached = this.cache.get(key);
    return (Date.now() - cached.timestamp) < this.cacheTimeout;
  }

  renderMenu(container, menuData, options = {}) {
    const nav = document.createElement('nav');
    nav.className = `menu menu-${menuData.slug}`;
    
    const ul = document.createElement('ul');
    ul.className = 'menu-list';
    
    menuData.items.forEach(item => {
      ul.appendChild(this.renderMenuItem(item, 0, options));
    });
    
    nav.appendChild(ul);
    
    if (typeof container === 'string') {
      container = document.querySelector(container);
    }
    
    container.innerHTML = '';
    container.appendChild(nav);
  }

  renderMenuItem(item, level = 0, options = {}) {
    const li = document.createElement('li');
    li.className = `menu-item level-${level}`;
    
    if (item.css_class) {
      li.className += ` ${item.css_class}`;
    }
    
    const a = document.createElement('a');
    a.href = item.url || '#';
    a.target = item.target || '_self';
    a.className = 'menu-link';
    
    if (item.icon) {
      const icon = document.createElement('i');
      icon.className = `icon ${item.icon}`;
      a.appendChild(icon);
    }
    
    a.appendChild(document.createTextNode(item.name));
    li.appendChild(a);
    
    if (item.children && item.children.length > 0) {
      const submenu = document.createElement('ul');
      submenu.className = 'submenu';
      
      item.children.forEach(child => {
        submenu.appendChild(this.renderMenuItem(child, level + 1, options));
      });
      
      li.appendChild(submenu);
    }
    
    return li;
  }
}

// Usage
const menuManager = new MenuManager();

// Load and render single menu
menuManager.getMenu('main-menu').then(menu => {
  menuManager.renderMenu('#main-navigation', menu);
});

// Load multiple menus
menuManager.getMenus(['main-menu', 'footer-menu']).then(response => {
  Object.keys(response.menus).forEach(slug => {
    if (!response.menus[slug].error) {
      menuManager.renderMenu(`#${slug}`, {
        slug,
        ...response.menus[slug]
      });
    }
  });
});
```

## CSS Styling Examples

### Basic Menu Styles

```css
.menu {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.menu-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.menu-item {
  position: relative;
}

.menu-link {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  color: #374151;
  text-decoration: none;
  transition: all 0.2s ease;
}

.menu-link:hover {
  background-color: #f9fafb;
  color: #111827;
}

.menu-link .icon {
  margin-right: 8px;
  width: 16px;
  height: 16px;
}

.submenu {
  list-style: none;
  margin: 0;
  padding: 0;
  background-color: #f9fafb;
}

.submenu .menu-link {
  padding-left: 32px;
  font-size: 0.9em;
}

.menu-item.level-2 .menu-link {
  padding-left: 48px;
}

.menu-loading,
.menu-error {
  padding: 16px;
  text-align: center;
  color: #6b7280;
  font-style: italic;
}

.menu-error {
  color: #ef4444;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 4px;
}
```

## Error Handling Best Practices

### Graceful Fallbacks

```javascript
// React example with fallback content
const Menu = ({ slug, fallbackItems = [] }) => {
  const { menu, loading, error } = useMenu(slug);

  if (error && fallbackItems.length > 0) {
    return (
      <nav className={`menu menu-${slug} menu-fallback`}>
        <ul className="menu-list">
          {fallbackItems.map((item, index) => (
            <li key={index} className="menu-item">
              <a href={item.url} className="menu-link">
                {item.name}
              </a>
            </li>
          ))}
        </ul>
      </nav>
    );
  }

  // ... rest of component
};
```

### Retry Logic

```javascript
const fetchWithRetry = async (url, options = {}, retries = 3) => {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.ok) return response;
      
      if (response.status >= 400 && response.status < 500) {
        // Client errors shouldn't be retried
        throw new Error(`Client error: ${response.status}`);
      }
    } catch (error) {
      if (i === retries - 1) throw error;
      
      // Exponential backoff
      await new Promise(resolve => 
        setTimeout(resolve, Math.pow(2, i) * 1000)
      );
    }
  }
};
```

## Performance Optimization

### Menu Preloading

```javascript
// Preload critical menus on page load
document.addEventListener('DOMContentLoaded', () => {
  const menuManager = new MenuManager();
  
  // Preload main navigation
  menuManager.getMenus(['main-menu', 'footer-menu']);
});
```

### Intersection Observer for Lazy Loading

```javascript
// Load menu only when container becomes visible
const observeMenu = (container, slug) => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        loadMenu(slug, entry.target);
        observer.unobserve(entry.target);
      }
    });
  });
  
  observer.observe(container);
};
```

These examples provide a solid foundation for integrating the Menu API into various frontend applications while maintaining good performance and user experience.