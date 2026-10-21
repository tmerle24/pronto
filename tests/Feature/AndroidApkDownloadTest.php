<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AndroidApk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AndroidApkDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_page_and_download_need_team_login(): void
    {
        $this->get('/team/app')->assertRedirect('/team/login');
        $this->get('/team/app/tiamo-pronto.apk')->assertRedirect('/team/login');
    }

    public function test_page_without_apk(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/team/app')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Team/AndroidApp')->where('apk', null));

        $this->get('/team/app/tiamo-pronto.apk')->assertNotFound();
    }

    public function test_published_apk_is_offered_and_downloadable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'apk').'.apk';
        file_put_contents($file, "PK\x03\x04fake-apk");

        $this->assertSame(0, Artisan::call('android:publish-apk', ['file' => $file, '--app-version' => '1.0.3']));

        $this->actingAs(User::factory()->create())
            ->get('/team/app')
            ->assertInertia(fn ($page) => $page->where('apk.version', '1.0.3')->where('apk.sha256', hash_file('sha256', $file)));

        $response = $this->get('/team/app/tiamo-pronto.apk')->assertOk();
        $this->assertStringContainsString('tiamo-pronto.apk', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        Storage::disk('local')->assertExists(AndroidApk::PATH);
    }

    public function test_command_rejects_non_apk(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'aab').'.aab';
        file_put_contents($file, "PK\x03\x04");

        $this->assertSame(1, Artisan::call('android:publish-apk', ['file' => $file, '--app-version' => '1.0.0']));
        Storage::disk('local')->assertMissing(AndroidApk::PATH);
    }
}
