<?php

namespace Skylark\Menus\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ResourceLinkService
{
    /**
     * Get all configured resource types.
     */
    public function getResourceTypes(): array
    {
        return array_keys($this->getResourceConfiguration());
    }

    /**
     * Get the full resource configuration.
     */
    public function getResourceConfiguration(): array
    {
        return Config::get('menus.resources', []);
    }

    /**
     * Get configuration for a specific resource type.
     *
     * @throws InvalidArgumentException
     */
    public function getResourceConfig(string $resourceType): array
    {
        $config = $this->getResourceConfiguration();

        if (! isset($config[$resourceType])) {
            throw new InvalidArgumentException("Resource type '{$resourceType}' is not configured.");
        }

        $resourceConfig = $config[$resourceType];
        $this->validateResourceConfig($resourceType, $resourceConfig);

        return $resourceConfig;
    }

    /**
     * Search resources of a specific type.
     *
     * @throws InvalidArgumentException
     */
    public function searchResources(string $resourceType, string $searchTerm = '', int $limit = 50): Collection
    {
        $config = $this->getResourceConfig($resourceType);
        $model = $this->getModelInstance($config['model']);

        $query = $model::query();

        // Add search filter if provided
        if (! empty($searchTerm)) {
            $query->where($config['name_field'], 'LIKE', "%{$searchTerm}%");
        }

        // Exclude soft-deleted records if model supports soft deletes
        $usesSoftDeletes = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model));
        if ($usesSoftDeletes) {
            $query->whereNull($model->getDeletedAtColumn());
        }

        $results = $query->limit($limit)
            ->get([$model->getKeyName(), $config['name_field'], $config['slug_field']])
            ->map(function ($resource) use ($config) {
                return [
                    'id' => $resource->getKey(),
                    'name' => $resource->{$config['name_field']},
                    'slug' => $resource->{$config['slug_field']},
                ];
            });

        return $results;
    }

    /**
     * Get a specific resource by type and ID.
     *
     * @param  mixed  $resourceId
     *
     * @throws InvalidArgumentException
     */
    public function getResource(string $resourceType, $resourceId): ?array
    {
        $config = $this->getResourceConfig($resourceType);
        $model = $this->getModelInstance($config['model']);

        // Check if model supports soft deletes
        $usesSoftDeletes = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model));

        if ($usesSoftDeletes) {
            $resource = $model::withTrashed()->find($resourceId);
        } else {
            $resource = $model::find($resourceId);
        }

        if (! $resource) {
            return null;
        }

        return [
            'id' => $resource->getKey(),
            'name' => $resource->{$config['name_field']},
            'slug' => $resource->{$config['slug_field']},
            'is_deleted' => $usesSoftDeletes ? ($resource->trashed() ?? false) : false,
        ];
    }

    /**
     * Generate frontend URL for a resource.
     *
     * @throws InvalidArgumentException
     */
    public function generateUrl(string $resourceType, string $resourceSlug): string
    {
        $config = $this->getResourceConfig($resourceType);

        return str_replace('{slug}', $resourceSlug, $config['route_pattern']);
    }

    /**
     * Validate resource configuration.
     *
     * @throws InvalidArgumentException
     */
    protected function validateResourceConfig(string $resourceType, array $config): void
    {
        $requiredKeys = ['model', 'name_field', 'slug_field', 'route_pattern'];

        foreach ($requiredKeys as $key) {
            if (! isset($config[$key]) || empty($config[$key])) {
                throw new InvalidArgumentException(
                    "Resource configuration for '{$resourceType}' missing required key: {$key}"
                );
            }
        }

        // Validate model class exists
        if (! class_exists($config['model'])) {
            throw new InvalidArgumentException(
                "Model class '{$config['model']}' does not exist for resource type '{$resourceType}'"
            );
        }

        // Validate route pattern contains {slug} placeholder
        if (! str_contains($config['route_pattern'], '{slug}')) {
            throw new InvalidArgumentException(
                "Route pattern for '{$resourceType}' must contain {slug} placeholder"
            );
        }
    }

    /**
     * Get model instance for validation.
     *
     * @throws InvalidArgumentException
     */
    protected function getModelInstance(string $modelClass): Model
    {
        try {
            $instance = new $modelClass;

            if (! $instance instanceof Model) {
                throw new InvalidArgumentException(
                    "Class '{$modelClass}' must be an instance of Illuminate\\Database\\Eloquent\\Model"
                );
            }

            return $instance;
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Failed to instantiate model '{$modelClass}': ".$e->getMessage()
            );
        }
    }
}
