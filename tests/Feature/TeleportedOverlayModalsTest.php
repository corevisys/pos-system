<?php

namespace Tests\Feature;

use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the "modal opens but the dialog is clipped" bug.
 *
 * Root cause: the app shell ([`layouts/app.blade.php`]) nests every page inside
 * `overflow-hidden` + `overflow-y-auto` scroll containers. A `position: fixed`
 * overlay left in that subtree shows its backdrop but the centred dialog is
 * clipped/invisible and the page behind becomes unusable.
 *
 * The project already solved this once for row dropdowns via
 * `x-teleport="body"` ([`components/dropdown.blade.php`]) and for the purchase /
 * item quick-add modals. The settings + SMS list modals were missed by that
 * sweep, so this file locks the invariant in for them.
 *
 * Invariant asserted per source file: every `class="fixed inset-0 …` overlay is
 * wrapped in its own `<template x-teleport="body">`.
 */
class TeleportedOverlayModalsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Files that render a full-screen overlay modal inside the app shell.
     * Add a new entry here whenever a page gains such a modal.
     */
    private const OVERLAY_FILES = [
        'module/settings/languages/index.blade.php',
        'module/settings/units_list.blade.php',
        'module/settings/tax_list.blade.php',
        'module/settings/payment_types.blade.php',
        'module/settings/currency_list.blade.php',
        'module/settings/store.blade.php',
        'module/sms/blacklist.blade.php',
    ];

    public function test_every_overlay_modal_in_these_views_is_teleported_to_body(): void
    {
        foreach (self::OVERLAY_FILES as $file) {
            $path = resource_path('views/' . $file);
            $this->assertFileExists($path, "{$file} is missing.");

            $src = file_get_contents($path);

            // Match the MODAL ROOT only. A bare 'class="fixed inset-0' would also
            // match each modal's inner backdrop (`fixed inset-0 transition-opacity`),
            // which is not teleported independently.
            $overlays  = substr_count($src, 'class="fixed inset-0 z-50 overflow-y-auto"');
            $teleports = substr_count($src, 'x-teleport="body"');

            $this->assertGreaterThan(
                0,
                $overlays,
                "{$file}: expected at least one full-screen overlay modal."
            );

            $this->assertGreaterThanOrEqual(
                $overlays,
                $teleports,
                "{$file}: found {$overlays} overlay modal(s) but only {$teleports} "
                . 'x-teleport="body" wrapper(s). Each overlay must be teleported to '
                . '<body>, otherwise the app shell\'s overflow-hidden/overflow-y-auto '
                . 'ancestors clip the dialog (the reported "modal opens but is '
                . 'invisible and the page is stuck" bug).'
            );
        }
    }

    public function test_languages_page_renders_its_confirm_modal_inside_a_teleport_template(): void
    {
        DbStore::create(['id' => 1, 'store_name' => 'Teleport Store', 'status' => 1, 'mobile' => '01700000033']);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => $role->id], ['store_id' => 1, 'permissions' => ['language_view']]);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);

        DbLanguage::create(['language' => 'English', 'status' => 1]);
        DbLanguage::create(['language' => 'Bangla', 'status' => 0]);

        $html = $this->actingAs($user)->get(route('settings.languages.index'))->assertOk()->getContent();

        // The confirm overlay must be preceded by an (unclosed) teleport template.
        // Anchor on the modal's OWN show binding so we do not accidentally target
        // the layout's mobile-sidebar overlay (`z-40`), which is not teleported.
        $needle = '<div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto"';
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, 'Languages: confirm modal overlay not found.');

        $tplPos = strrpos(substr($html, 0, $pos), '<template x-teleport="body">');
        $this->assertNotFalse($tplPos, 'Languages: modal overlay is not inside a teleport template.');

        $between = substr($html, $tplPos, $pos - $tplPos);
        $this->assertStringNotContainsString(
            '</template>',
            $between,
            'Languages: the teleport template closed before the modal overlay (wrapper is misplaced).'
        );

        // The teleported markup must still carry the Alpine wiring.
        $this->assertStringContainsString('showConfirmModal', $html);
        $this->assertStringContainsString('openActivateConfirm', $html);
        $teleported = substr($html, $tplPos, 8000);
        $this->assertStringContainsString('x-show="showConfirmModal"', $teleported);
        $this->assertStringContainsString("+ targetLanguageId + '/activate'", $teleported);
    }
}
