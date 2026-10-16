<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class GenerateMosaicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes for Ultra-HD rendering

    /**
     * Create a new job instance.
     */
    public function __construct(public string $projectId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = Project::with('tiles')->find($this->projectId);

        if (!$project) {
            Log::error("GenerateMosaicJob: Project {$this->projectId} not found.");
            return;
        }

        try {
            $project->update(['status' => 'rendering']);

            $disk = Storage::disk('public');
            $targetRelative = $project->target_image_path;

            if (!$targetRelative || !$disk->exists($targetRelative)) {
                throw new \Exception("Target image not found at: {$targetRelative}");
            }

            $targetFullPath = $disk->path($targetRelative);

            // Collect tile full paths
            $tilePaths = [];
            foreach ($project->tiles as $tile) {
                if ($disk->exists($tile->file_path)) {
                    $tilePaths[] = $disk->path($tile->file_path);
                }
            }

            if (empty($tilePaths)) {
                throw new \Exception("No valid tile images found on disk for project {$this->projectId}");
            }

            // Output directory
            $outputRelative = "projects/{$project->id}/output";
            $disk->makeDirectory($outputRelative);
            $outputFullPath = $disk->path($outputRelative);

            $settings = $project->settings ?? [];

            // Write temporary config file
            $config = [
                'target_image_path' => $targetFullPath,
                'output_dir' => $outputFullPath,
                'grid_size' => (int)($settings['grid_size'] ?? 80),
                'tile_res' => (int)($settings['tile_res'] ?? 60),
                'blend_alpha' => (float)($settings['blend_alpha'] ?? 0.15),
                'allow_flips' => (bool)($settings['allow_flips'] ?? true),
                'min_distance_radius' => (int)($settings['min_distance_radius'] ?? 3),
                'color_space' => $settings['color_space'] ?? 'lab',
                'tiles' => $tilePaths,
            ];

            $configPath = $disk->path("projects/{$project->id}/engine_config.json");
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Determine python binary path
            $pythonBin = env('PYTHON_BIN');
            if (!$pythonBin || !file_exists($pythonBin)) {
                $candidates = [
                    'C:\\laragon\\bin\\python\\python-3.13\\python.exe',
                    'python3',
                    'python'
                ];
                foreach ($candidates as $cand) {
                    if (file_exists($cand)) {
                        $pythonBin = $cand;
                        break;
                    }
                }
                $pythonBin = $pythonBin ?: 'python';
            }

            $scriptPath = storage_path('app/scripts/mosaic_engine.py');

            Log::info("Starting mosaic rendering for project {$project->id} with {$pythonBin}");

            $process = Process::timeout($this->timeout)->run([
                $pythonBin,
                $scriptPath,
                '--project_id',
                $project->id,
                '--config',
                $configPath,
            ]);

            if (!$process->successful()) {
                $stderr = $process->errorOutput();
                $stdout = $process->output();
                Log::error("Mosaic engine failed for project {$project->id}: {$stderr}\nSTDOUT: {$stdout}");
                throw new \Exception("Engine process failed: " . ($stderr ?: $stdout));
            }

            $rawOutput = trim($process->output());
            $result = json_decode($rawOutput, true);

            if (!$result || empty($result['success'])) {
                throw new \Exception("Invalid engine output: " . substr($rawOutput, 0, 500));
            }

            // Preview and UHD relative storage paths
            $previewRelative = "{$outputRelative}/preview_{$project->id}.jpg";
            $masterRelative = "{$outputRelative}/master_uhd_{$project->id}.jpg";

            $updatedSettings = array_merge($settings, [
                'result_stats' => [
                    'grid' => $result['grid'] ?? null,
                    'resolution' => $result['resolution'] ?? null,
                    'total_palette_size' => $result['total_palette_size'] ?? null,
                    'execution_time_seconds' => $result['execution_time_seconds'] ?? null,
                ]
            ]);

            $project->update([
                'status' => 'completed',
                'preview_mosaic_path' => $previewRelative,
                'final_mosaic_path' => $masterRelative,
                'settings' => $updatedSettings,
            ]);

            Log::info("Mosaic generation completed successfully for project {$project->id} in {$result['execution_time_seconds']}s");

        } catch (\Throwable $e) {
            Log::error("GenerateMosaicJob Exception for {$this->projectId}: " . $e->getMessage());
            $project->update([
                'status' => 'failed',
                'settings' => array_merge($project->settings ?? [], [
                    'error_message' => $e->getMessage()
                ])
            ]);
        }
    }
}
