<?php

namespace Skylark\Menus\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Skylark\Menus\Services\ResourceLinkService;

class MenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->generateUrl(),
            'target' => $this->target ?? '_self',
            'css_class' => $this->css_class,
            'icon' => $this->icon,
            'children' => MenuItemResource::collection($this->whenLoaded('children')),
        ];
    }

    /**
     * Generate URL for the menu item
     */
    protected function generateUrl(): ?string
    {
        if ($this->custom_url) {
            return $this->custom_url;
        }

        if ($this->resource_type && $this->resource_slug) {
            try {
                $resourceService = app(ResourceLinkService::class);

                return $resourceService->generateUrl($this->resource_type, $this->resource_slug);
            } catch (\Exception $e) {
                \Log::warning('Failed to generate resource URL for menu item in API resource', [
                    'menu_item_id' => $this->id,
                    'resource_type' => $this->resource_type,
                    'resource_slug' => $this->resource_slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
