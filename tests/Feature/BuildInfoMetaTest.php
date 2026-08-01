<?php

namespace Tests\Feature;

use App\Support\BuildInfo;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuildInfoMetaTest extends TestCase
{
    public function test_meta_endpoint_returns_build_info(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertJsonStructure(['name', 'version', 'build', 'commit', 'display']);

        $this->assertSame(trim((string) File::get(base_path('VERSION'))), $response->json('version'));
        $this->assertSame('dev', $response->json('build'));
    }

    public function test_build_info_prefers_config_overrides(): void
    {
        config([
            'app.name' => 'Authzio',
            'authzio.release.version' => '1.2.0',
            'authzio.release.build' => '217',
            'authzio.release.commit' => '82af91c',
        ]);

        $info = BuildInfo::toArray();

        $this->assertSame('1.2.0', $info['version']);
        $this->assertSame('217', $info['build']);
        $this->assertSame('82af91c', $info['commit']);
        $this->assertSame('Authzio 1.2.0 (Build 217)', $info['display']);
    }

    public function test_build_info_reads_build_info_json(): void
    {
        config([
            'app.name' => 'Authzio',
            'authzio.release.version' => null,
            'authzio.release.build' => null,
            'authzio.release.commit' => null,
        ]);

        $path = base_path('build-info.json');
        $previous = File::exists($path) ? File::get($path) : null;

        try {
            File::put($path, json_encode([
                'version' => '9.9.9',
                'build' => '42',
                'commit' => 'abcdef1',
            ], JSON_THROW_ON_ERROR));

            $info = BuildInfo::toArray();

            $this->assertSame('9.9.9', $info['version']);
            $this->assertSame('42', $info['build']);
            $this->assertSame('abcdef1', $info['commit']);
            $this->assertSame('Authzio 9.9.9 (Build 42)', $info['display']);
        } finally {
            if ($previous === null) {
                File::delete($path);
            } else {
                File::put($path, $previous);
            }
        }
    }
}
