# Frontend Menu API Documentation

This document provides comprehensive documentation for the public menu API endpoints that enable frontend applications to consume menu data with temporal visibility and resource linking.

## Base URL

All API endpoints are available under the `/api` prefix:

```
https://your-domain.com/api/
```

## Authentication

The menu API endpoints are public and do not require authentication. However, they are rate-limited to 60 requests per minute per IP address.

## Endpoints

### GET /api/menus/{slug}

Retrieves a single menu by its slug with hierarchical structure and active items only.

#### Parameters

| Parameter | Type   | Required | Description                |
|-----------|--------|----------|----------------------------|
| slug      | string | Yes      | The unique slug identifier for the menu |

#### Response Format

```json
{
  "slug": "main-menu",
  "name": "Main Navigation",
  "items": [
    {
      "id": 1,
      "name": "Home",
      "url": "/",
      "target": "_self",
      "css_class": "nav-home",
      "icon": "home",
      "children": []
    },
    {
      "id": 2,
      "name": "Products",
      "url": "/products",
      "target": "_self",
      "css_class": null,
      "icon": null,
      "children": [
        {
          "id": 3,
          "name": "Electronics",
          "url": "/products/electronics",
          "target": "_self",
          "css_class": null,
          "icon": null,
          "children": []
        }
      ]
    }
  ],
  "timestamp": "2025-08-28T10:30:00.000000Z"
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200  | Success - Menu found and returned |
| 404  | Menu not found |
| 429  | Rate limit exceeded |

#### Example Request

```bash
curl -X GET "https://your-domain.com/api/menus/main-menu" \
     -H "Accept: application/json"
```

### GET /api/menus

Retrieves multiple menus in a single request using comma-separated slugs.

#### Parameters

| Parameter | Type   | Required | Description                |
|-----------|--------|----------|----------------------------|
| menus     | string | Yes      | Comma-separated menu slugs (e.g., "main-menu,footer-menu") |

#### Response Format

```json
{
  "menus": {
    "main-menu": {
      "name": "Main Navigation",
      "items": [
        {
          "id": 1,
          "name": "Home",
          "url": "/",
          "target": "_self",
          "css_class": "nav-home",
          "icon": "home",
          "children": []
        }
      ]
    },
    "footer-menu": {
      "name": "Footer Navigation",
      "items": [
        {
          "id": 10,
          "name": "About",
          "url": "/about",
          "target": "_self",
          "css_class": null,
          "icon": null,
          "children": []
        }
      ]
    }
  },
  "timestamp": "2025-08-28T10:30:00.000000Z"
}
```

#### Error Handling for Individual Menus

If some requested menus are not found, the response will include error information for those menus:

```json
{
  "menus": {
    "main-menu": {
      "name": "Main Navigation",
      "items": [...]
    },
    "missing-menu": {
      "error": "Menu not found",
      "message": "Menu with slug 'missing-menu' does not exist"
    }
  },
  "timestamp": "2025-08-28T10:30:00.000000Z"
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200  | Success - Response returned (may include individual menu errors) |
| 400  | Bad request - No menu slugs provided |
| 429  | Rate limit exceeded |

#### Example Request

```bash
curl -X GET "https://your-domain.com/api/menus?menus=main-menu,footer-menu,sidebar-menu" \
     -H "Accept: application/json"
```

## Menu Item Properties

Each menu item in the response contains the following properties:

| Property  | Type     | Description |
|-----------|----------|-------------|
| id        | integer  | Unique identifier for the menu item |
| name      | string   | Display name of the menu item |
| url       | string   | Generated URL (from custom_url or resource configuration) |
| target    | string   | Link target (`_self` or `_blank`) |
| css_class | string   | Optional CSS class for styling |
| icon      | string   | Optional icon identifier |
| children  | array    | Array of child menu items (nested structure) |

## Temporal Visibility

Menu items are automatically filtered based on their temporal visibility settings:

- **Always Show**: Item is always included in API responses
- **Always Hide**: Item is never included in API responses  
- **Scheduled**: Item is included only when current server time falls within the display window

The filtering is applied server-side using UTC timestamps, ensuring consistent behavior across timezones.

### Visibility Logic

```
Item is visible when:
- display_at is null OR display_at <= current_time
- AND hide_at is null OR hide_at > current_time
```

## Resource Integration

Menu items can link to internal resources (like products, pages, etc.) or use custom URLs:

### Custom URLs
- Used directly as provided
- Support both relative (`/about`) and absolute (`https://example.com`) URLs

### Resource Links
- Generated using configured route patterns
- Automatically filtered to exclude soft-deleted resources
- Fallback to custom_url if resource is deleted and fallback is provided

## Error Responses

### Menu Not Found (404)

```json
{
  "error": "Menu not found",
  "message": "Menu with slug 'invalid-menu' does not exist"
}
```

### Bad Request (400)

```json
{
  "error": "No menus specified",
  "message": "Please provide comma-separated menu slugs via ?menus=slug1,slug2"
}
```

### Rate Limit Exceeded (429)

```json
{
  "message": "Too Many Attempts.",
  "exception": "Illuminate\\Http\\Exceptions\\ThrottleRequestsException"
}
```

## Performance Considerations

- API responses are optimized using nested set queries for efficient hierarchy retrieval
- Consider implementing caching for frequently accessed menus
- Use the multi-menu endpoint when possible to reduce HTTP requests
- Menu structures are filtered server-side to minimize response size

## Integration Examples

See the [Integration Examples](integration-examples.md) document for detailed frontend consumption patterns and implementation examples.