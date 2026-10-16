<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'status',
        'target_image_path',
        'preview_mosaic_path',
        'final_mosaic_path',
        'settings',
        'is_paid',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_paid' => 'boolean',
    ];

    protected $appends = [
        'target_url',
        'preview_url',
        'final_url',
        'tiles_count'
    ];

    public function tiles(): HasMany
    {
        return $this->hasMany(ProjectTile::class);
    }

    public function getTargetUrlAttribute(): ?string
    {
        return $this->target_image_path ? Storage::disk('public')->url($this->target_image_path) : null;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return $this->preview_mosaic_path ? Storage::disk('public')->url($this->preview_mosaic_path) : null;
    }

    public function getFinalUrlAttribute(): ?string
    {
        return $this->final_mosaic_path ? Storage::disk('public')->url($this->final_mosaic_path) : null;
    }

    public function getTilesCountAttribute(): int
    {
        return $this->tiles()->count();
    }
}
