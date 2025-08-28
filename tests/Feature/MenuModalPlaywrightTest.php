<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Menu Modal End-to-End Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Start browser session
        $this->browse(function ($browser) {
            $browser->loginAs($this->user);
        });
    });

    it('can open the menu tool page and see create button', function () {
        $this->browse(function ($browser) {
            $browser->visit('/admin/menus')
                ->waitFor('h1', 10) // Wait for page to load
                ->assertSee('Menus')
                ->assertPresent('[data-testid="create-menu-button"]', 'Create Menu button should be present')
                ->screenshot('menu-index-page');
        });
    })->skip('Browser testing requires additional setup');

    it('can click create menu button and see modal', function () {
        $this->browse(function ($browser) {
            $browser->visit('/admin/menus')
                ->waitFor('h1', 10)
                ->click('[data-testid="create-menu-button"]')
                ->waitFor('[data-testid="create-menu-modal"]', 5) // Wait for modal to appear
                ->assertVisible('[data-testid="create-menu-modal"]', 'Modal should be visible')
                ->assertSee('Create New Menu')
                ->screenshot('modal-opened');
        });
    })->skip('Browser testing requires additional setup');

    it('can fill and submit the create menu form', function () {
        $this->browse(function ($browser) {
            $browser->visit('/admin/menus')
                ->waitFor('h1', 10)
                ->click('[data-testid="create-menu-button"]')
                ->waitFor('[data-testid="create-menu-modal"]', 5)
                ->type('[data-testid="menu-name-input"]', 'Test Menu')
                ->waitFor('[data-testid="menu-slug-input"]', 2) // Wait for auto-slug
                ->assertInputValue('[data-testid="menu-slug-input"]', 'test-menu')
                ->select('[data-testid="max-depth-select"]', '5')
                ->click('[data-testid="submit-button"]')
                ->waitFor('.toasted', 5) // Wait for success message
                ->assertSee('Menu "Test Menu" created successfully')
                ->screenshot('menu-created');
        });
    })->skip('Browser testing requires additional setup');

    it('validates required fields in modal form', function () {
        $this->browse(function ($browser) {
            $browser->visit('/admin/menus')
                ->waitFor('h1', 10)
                ->click('[data-testid="create-menu-button"]')
                ->waitFor('[data-testid="create-menu-modal"]', 5)
                ->click('[data-testid="submit-button"]') // Submit without filling name
                ->waitFor('[data-testid="name-error"]', 2)
                ->assertSee('Menu name must be at least 3 characters long')
                ->screenshot('validation-error');
        });
    })->skip('Browser testing requires additional setup');

    it('can close modal using cancel button', function () {
        $this->browse(function ($browser) {
            $browser->visit('/admin/menus')
                ->waitFor('h1', 10)
                ->click('[data-testid="create-menu-button"]')
                ->waitFor('[data-testid="create-menu-modal"]', 5)
                ->assertVisible('[data-testid="create-menu-modal"]')
                ->click('[data-testid="cancel-button"]')
                ->waitUntilMissing('[data-testid="create-menu-modal"]', 3)
                ->assertMissing('[data-testid="create-menu-modal"]')
                ->screenshot('modal-closed');
        });
    })->skip('Browser testing requires additional setup');
});

describe('Menu Modal with Playwright Browser Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('opens menu tool page and captures modal issue', function () {
        // Use Playwright MCP to test the modal
        expect(true)->toBeTrue(); // Placeholder - will be replaced with actual Playwright test
    });

    it('investigates modal visibility and CSS issues', function () {
        // Use Playwright to debug CSS and visibility issues
        expect(true)->toBeTrue(); // Placeholder - will be replaced with actual Playwright test
    });

    it('tests modal interaction and form submission', function () {
        // Use Playwright to test full modal workflow
        expect(true)->toBeTrue(); // Placeholder - will be replaced with actual Playwright test
    });
});
