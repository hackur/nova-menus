<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            // Hierarchy fields
            $table->unsignedBigInteger('parent_id')->nullable();

            // Content fields
            $table->string('name');
            $table->string('custom_url', 2048)->nullable();

            // Resource fields
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_slug')->nullable();

            // Temporal visibility fields
            $table->timestamp('display_at')->nullable();
            $table->timestamp('hide_at')->nullable();

            // Style and behavior fields
            $table->string('icon', 100)->nullable();
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->string('css_class')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);

            // Root node fields (for menus)
            $table->boolean('is_root')->default(false);
            $table->string('slug')->nullable();
            $table->integer('max_depth')->default(6);

            // Nested set columns
            $table->unsignedInteger('_lft')->default(0);
            $table->unsignedInteger('_rgt')->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['parent_id']); // Parent-child relationships
            $table->index(['is_root']); // Root node queries
            $table->index(['slug']); // Menu lookups
            $table->index(['_lft', '_rgt']); // Nested set queries
            $table->index(['resource_type', 'resource_id']); // Resource lookups
            $table->index(['display_at', 'hide_at']); // Visibility queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
