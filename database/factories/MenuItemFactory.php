<?php

namespace Skylark\Menus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Skylark\Menus\Models\Menu;
use Skylark\Menus\Models\MenuItem;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'name' => $this->faker->words(2, true),
            'custom_url' => $this->faker->optional(0.3)->url(),
            'resource_type' => $this->faker->optional(0.3)->randomElement(['App\Models\Page', 'App\Models\Post']),
            'resource_id' => fn (array $attributes) => $attributes['resource_type'] ? $this->faker->numberBetween(1, 100) : null,
            'resource_slug' => $this->faker->optional(0.3)->slug(),
            'display_at' => $this->faker->optional(0.2)->dateTimeBetween('-1 month', '+1 month'),
            'hide_at' => $this->faker->optional(0.2)->dateTimeBetween('+1 month', '+6 months'),
        ];
    }

    public function forMenu(Menu $menu): static
    {
        return $this->state(fn (array $attributes) => [
            'menu_id' => $menu->id,
        ]);
    }

    public function withCustomUrl(string $url): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_url' => $url,
            'resource_type' => null,
            'resource_id' => null,
            'resource_slug' => null,
        ]);
    }

    public function withResource(string $type, int $id, ?string $slug = null): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_url' => null,
            'resource_type' => $type,
            'resource_id' => $id,
            'resource_slug' => $slug,
        ]);
    }

    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'hide_at' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_at' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'hide_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_at' => $this->faker->dateTimeBetween('-6 months', '-2 months'),
            'hide_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    public function asRoot(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }
}
