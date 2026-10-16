<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMosaicJob;
use App\Models\Project;
use App\Models\ProjectTile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MosaicController extends Controller
{
    /**
     * Display the main MosaicStudio Pro workspace.
     */
    public function index()
    {
        $recentProjects = Project::latest()->take(10)->get();
        return view('mosaic.studio', compact('recentProjects'));
    }

    /**
     * Create a new project.
     */
    public function createProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grid_size' => 'nullable|integer|min:20|max:250',
            'tile_res' => 'nullable|integer|min:30|max:200',
            'blend_alpha' => 'nullable|numeric|min:0|max:0.50',
            'allow_flips' => 'nullable|boolean',
            'min_distance_radius' => 'nullable|integer|min:0|max:10',
            'color_space' => 'nullable|in:lab,rgb',
        ]);

        $settings = [
            'grid_size' => (int)($validated['grid_size'] ?? 80),
            'tile_res' => (int)($validated['tile_res'] ?? 60),
            'blend_alpha' => (float)($validated['blend_alpha'] ?? 0.15),
            'allow_flips' => (bool)($validated['allow_flips'] ?? true),
            'min_distance_radius' => (int)($validated['min_distance_radius'] ?? 3),
            'color_space' => $validated['color_space'] ?? 'lab',
        ];

        $project = Project::create([
            'name' => $validated['name'],
            'status' => 'draft',
            'settings' => $settings,
            'is_paid' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project initialized successfully',
            'project' => $project,
        ], 201);
    }

    /**
     * Upload the main target image for a project.
     */
    public function uploadTarget(Request $request, string $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        $request->validate([
            'target_image' => 'required|file|image|max:102400', // up to 100MB
        ]);

        $file = $request->file('target_image');
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'target_' . time() . '.' . $ext;
        $path = $file->storeAs("projects/{$project->id}", $filename, 'public');

        $project->update([
            'target_image_path' => $path,
            'status' => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Target image uploaded successfully',
            'target_url' => $project->target_url,
            'project' => $project,
        ]);
    }

    /**
     * Upload batch of tile images.
     */
    public function uploadTiles(Request $request, string $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        // Support multiple file inputs or single dropzone batch
        $files = $request->file('tiles') ?? ($request->file('file') ? [$request->file('file')] : []);

        if (empty($files)) {
            return response()->json(['success' => false, 'message' => 'No files provided'], 422);
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $insertedCount = 0;
        $records = [];
        $now = now();

        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $uniqueName = 'tile_' . Str::random(16) . '.' . $ext;
            $path = $file->storeAs("projects/{$project->id}/tiles", $uniqueName, 'public');

            $records[] = [
                'project_id' => $project->id,
                'file_path' => $path,
                'avg_color' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $insertedCount++;

            // Batch insert every 100 items for high performance
            if (count($records) >= 100) {
                ProjectTile::insert($records);
                $records = [];
            }
        }

        if (!empty($records)) {
            ProjectTile::insert($records);
        }

        $totalTiles = $project->tiles()->count();

        return response()->json([
            'success' => true,
            'uploaded_count' => $insertedCount,
            'total_tiles' => $totalTiles,
            'message' => "Successfully uploaded {$insertedCount} tiles",
        ]);
    }

    /**
     * Trigger asynchronous mosaic generation.
     */
    public function generate(Request $request, string $id): JsonResponse
    {
        $project = Project::withCount('tiles')->findOrFail($id);

        if (!$project->target_image_path || !Storage::disk('public')->exists($project->target_image_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload a primary target image first.'
            ], 422);
        }

        if ($project->tiles_count < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least 1 tile photo.'
            ], 422);
        }

        // Update settings if passed in request
        $settings = $project->settings ?? [];
        if ($request->has('grid_size')) {
            $settings['grid_size'] = (int)$request->input('grid_size');
        }
        if ($request->has('tile_res')) {
            $settings['tile_res'] = (int)$request->input('tile_res');
        }
        if ($request->has('blend_alpha')) {
            $settings['blend_alpha'] = (float)$request->input('blend_alpha');
        }
        if ($request->has('allow_flips')) {
            $settings['allow_flips'] = filter_var($request->input('allow_flips'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('min_distance_radius')) {
            $settings['min_distance_radius'] = (int)$request->input('min_distance_radius');
        }
        if ($request->has('color_space')) {
            $settings['color_space'] = $request->input('color_space');
        }

        $project->update([
            'settings' => $settings,
            'status' => 'rendering'
        ]);

        // Dispatch Job
        GenerateMosaicJob::dispatch($project->id);

        // Reload fresh project state
        $project->refresh();

        return response()->json([
            'success' => true,
            'status' => $project->status,
            'message' => 'Mosaic generation pipeline started',
            'project' => $project,
        ]);
    }

    /**
     * Get real-time project status and details.
     */
    public function status(string $id): JsonResponse
    {
        $project = Project::withCount('tiles')->findOrFail($id);

        return response()->json([
            'success' => true,
            'project' => $project,
            'tiles_count' => $project->tiles_count,
            'target_url' => $project->target_url,
            'preview_url' => $project->preview_url,
            'final_url' => $project->final_url,
        ]);
    }

    /**
     * Download rendered mosaic (preview or UHD master).
     */
    public function download(string $id, string $type): BinaryFileResponse|JsonResponse
    {
        $project = Project::findOrFail($id);
        $disk = Storage::disk('public');

        if ($type === 'master' || $type === 'uhd') {
            if (!$project->final_mosaic_path || !$disk->exists($project->final_mosaic_path)) {
                return response()->json(['error' => 'Master Ultra-HD file not ready yet.'], 404);
            }
            $filePath = $disk->path($project->final_mosaic_path);
            $downloadName = Str::slug($project->name) . '-master-uhd.jpg';
            return response()->download($filePath, $downloadName);
        }

        // Preview fallback
        if (!$project->preview_mosaic_path || !$disk->exists($project->preview_mosaic_path)) {
            return response()->json(['error' => 'Preview mosaic file not ready yet.'], 404);
        }

        $filePath = $disk->path($project->preview_mosaic_path);
        $downloadName = Str::slug($project->name) . '-preview.jpg';
        return response()->download($filePath, $downloadName);
    }

    /**
     * List recent projects.
     */
    public function listProjects(): JsonResponse
    {
        $projects = Project::latest()->take(20)->get();
        return response()->json(['projects' => $projects]);
    }
}
