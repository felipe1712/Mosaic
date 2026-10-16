<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MosaicStudioTest extends TestCase
{
    public function test_studio_page_loads_successfully(): void
    {
        $response = $this->get('/studio');
        $response->assertStatus(200);
        $response->assertSee('MosaicStudio');
    }

    public function test_create_project_api(): void
    {
        $response = $this->postJson('/api/projects', [
            'name' => 'Test Unit Mosaic Pro',
            'grid_size' => 60,
            'tile_res' => 50,
            'blend_alpha' => 0.20,
            'allow_flips' => true,
            'min_distance_radius' => 2,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'project' => [
                'name' => 'Test Unit Mosaic Pro',
                'status' => 'draft',
            ]
        ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Unit Mosaic Pro'
        ]);
    }

    public function test_upload_target_and_tiles(): void
    {
        Storage::fake('public');

        $project = Project::create([
            'name' => 'Upload Test Project',
            'status' => 'draft',
            'settings' => ['grid_size' => 40]
        ]);

        $targetFile = UploadedFile::fake()->image('target.jpg', 600, 400);

        $targetRes = $this->postJson("/api/projects/{$project->id}/target", [
            'target_image' => $targetFile
        ]);
        $targetRes->assertStatus(200);
        $targetRes->assertJson(['success' => true]);

        $tile1 = UploadedFile::fake()->image('tile1.jpg', 100, 100);
        $tile2 = UploadedFile::fake()->image('tile2.jpg', 100, 100);

        $tilesRes = $this->postJson("/api/projects/{$project->id}/tiles", [
            'tiles' => [$tile1, $tile2]
        ]);
        $tilesRes->assertStatus(200);
        $tilesRes->assertJson([
            'success' => true,
            'uploaded_count' => 2,
            'total_tiles' => 2
        ]);
    }
}
