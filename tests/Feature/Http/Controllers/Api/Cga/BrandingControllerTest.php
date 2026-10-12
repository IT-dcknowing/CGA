<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\Role;
use App\Models\Cga;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_gerant_uploads_logo_and_stamp_to_the_private_disk(): void
    {
        Storage::fake('local');
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson('/api/v1/cga/profile/branding', [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'stamp' => UploadedFile::fake()->image('cachet.jpg'),
        ]);

        $response->assertOk()->assertJsonPath('data.branding', ['logo' => true, 'signature' => false, 'stamp' => true]);
        $cga->refresh();
        Storage::disk('local')->assertExists([$cga->logo_path, $cga->stamp_path]);
        $this->assertStringStartsWith("cgas/{$cga->id}/branding/", $cga->logo_path);
    }

    public function test_replacing_a_file_deletes_the_previous_one(): void
    {
        Storage::fake('local');
        $cga = Cga::factory()->create();
        Storage::disk('local')->put('cgas/ancien-logo.png', 'ancien');
        $cga->forceFill(['logo_path' => 'cgas/ancien-logo.png'])->save();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson('/api/v1/cga/profile/branding', [
            'logo' => UploadedFile::fake()->image('nouveau-logo.png'),
        ]);

        $response->assertOk();
        Storage::disk('local')->assertMissing('cgas/ancien-logo.png');
        Storage::disk('local')->assertExists($cga->fresh()->logo_path);
    }

    public function test_rejects_a_file_that_is_not_an_image(): void
    {
        Storage::fake('local');
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson('/api/v1/cga/profile/branding', [
            'signature' => UploadedFile::fake()->create('signature.pdf', 10, 'application/pdf'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['signature' => 'Le champ signature doit être une image.']);
        $this->assertNull($cga->fresh()->signature_path);
    }

    public function test_requires_at_least_one_file(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/cga/profile/branding');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'Envoyez au moins un fichier : logo, signature ou cachet.']);
    }

    public function test_returns_403_to_cga_roles_other_than_gerant(): void
    {
        Storage::fake('local');

        $response = $this->withToken($this->tokenForRole(Role::Secretaire))->postJson('/api/v1/cga/profile/branding', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cga_agent_downloads_the_stored_signature(): void
    {
        Storage::fake('local');
        $cga = Cga::factory()->create();
        Storage::disk('local')->put('cgas/signature.png', 'image-de-la-signature');
        $cga->forceFill(['signature_path' => 'cgas/signature.png'])->save();

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->get('/api/v1/cga/profile/branding/signature');

        $response->assertOk();
        $this->assertSame('image-de-la-signature', $response->streamedContent());
    }

    public function test_download_returns_404_when_the_file_was_never_uploaded(): void
    {
        Storage::fake('local');

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/cga/profile/branding/logo');

        $response->assertNotFound();
    }

    public function test_download_returns_404_for_an_unknown_asset_type(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/cga/profile/branding/photo');

        $response->assertNotFound()->assertJsonPath('code', 'not_found');
    }
}
