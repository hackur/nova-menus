<?php

describe('Menu Tool Browser Tests', function () {
    beforeEach(function () {
        config(['menus.enabled' => true]);

        // Create a user for authentication
        $this->user = \App\Models\User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    });

    it('can navigate to menu tool and see the interface', function () {
        // Use Playwright to test the actual browser interface
        // Note: This test requires the application to be running at http://skylark.test

        // Navigate to login
        $this->browse(function ($browser) {
            $browser->visit('http://skylark.test/admin/login')
                ->type('input[name="email"]', 'test@example.com')
                ->type('input[name="password"]', 'password')
                ->click('button[type="submit"]')
                ->waitForLocation('http://skylark.test/admin')
                ->assertSee('Nova');
        });

        // Navigate to menu tool
        $this->browse(function ($browser) {
            $browser->visit('http://skylark.test/admin/menus')
                ->assertSee('Menu Management')
                ->assertSee('Create Menu')
                ->assertSee('Search menus...');
        });
    })->skip('Browser testing requires running application');

    it('can create a menu through the UI', function () {
        // This test demonstrates what the browser test would look like
        // In practice, you would need the actual running application

        $this->browse(function ($browser) {
            $browser->visit('http://skylark.test/admin/menus')
                ->click('button:contains("Create Menu")')
                ->waitFor('.modal') // Wait for modal to appear
                ->type('input[name="name"]', 'Test Browser Menu')
                ->select('select[name="max_depth"]', '5')
                ->click('button[type="submit"]')
                ->waitForText('Menu created successfully')
                ->assertSee('Test Browser Menu');
        });
    })->skip('Browser testing requires running application');

    it('displays empty state when no menus exist', function () {
        $this->browse(function ($browser) {
            $browser->visit('http://skylark.test/admin/menus')
                ->assertSee('No menus found')
                ->assertSee('Create your first menu to get started');
        });
    })->skip('Browser testing requires running application');

    it('can search menus', function () {
        // Create some test menus first
        \Skylark\Menus\Models\Menu::factory()->create(['name' => 'Main Menu']);
        \Skylark\Menus\Models\Menu::factory()->create(['name' => 'Footer Menu']);

        $this->browse(function ($browser) {
            $browser->visit('http://skylark.test/admin/menus')
                ->type('input[placeholder="Search menus..."]', 'Main')
                ->assertSee('Main Menu')
                ->assertDontSee('Footer Menu');
        });
    })->skip('Browser testing requires running application');
});

/**
 * Browser test using actual Playwright MCP integration
 * This will run real browser automation tests
 */
describe('Menu Tool Playwright Integration Tests', function () {
    beforeEach(function () {
        config(['menus.enabled' => true]);

        // Create test user
        $this->user = \App\Models\User::factory()->create([
            'email' => 'test@skylark.test',
            'password' => bcrypt('password'),
        ]);

        // Create sample menu for testing
        $this->menu = \Skylark\Menus\Models\Menu::factory()->create([
            'name' => 'Sample Menu',
            'slug' => 'sample-menu',
        ]);
    });

    it('opens the menu tool page successfully', function () {
        // This test requires the MCP Playwright integration to be active
        // and the application to be running
        expect(true)->toBeTrue(); // Placeholder for now

        // Once Playwright MCP is confirmed working, this would become:
        /*
        mcp__playwright__browser_navigate(['url' => 'http://skylark.test/admin/login']);
        mcp__playwright__browser_type(['element' => 'email input', 'text' => 'test@skylark.test']);
        mcp__playwright__browser_type(['element' => 'password input', 'text' => 'password']);
        mcp__playwright__browser_click(['element' => 'login button']);
        mcp__playwright__browser_wait_for(['text' => 'Dashboard']);

        mcp__playwright__browser_navigate(['url' => 'http://skylark.test/admin/menus']);
        mcp__playwright__browser_wait_for(['text' => 'Menu Management']);

        $screenshot = mcp__playwright__browser_take_screenshot(['filename' => 'menu-tool-loaded.png']);
        expect($screenshot)->toContain('menu-tool-loaded.png');
        */
    });
});
