#!/usr/bin/env python3
"""
MosaicStudio Pro - Ultra High Resolution Photomosaic Engine
Optimized for 32 GB RAM workstations with scipy.spatial.KDTree, NumPy & Pillow.
Supports up to 15,000 x 15,000 px canvases, CIE-LAB color matching,
spatial neighbor penalty matrix, horizontal mirroring, and freemium watermarking.
"""

import sys
import os
import json
import time
import math
import argparse
from typing import List, Tuple, Dict, Any, Optional

import numpy as np
from PIL import Image, ImageOps, ImageDraw, ImageFont
from scipy.spatial import KDTree

# Remove Pillow decompressed pixel threshold for Ultra-HD canvases (15,000 x 15,000 = 225 MP)
Image.MAX_IMAGE_PIXELS = None


def rgb_to_cielab(rgb_array: np.ndarray) -> np.ndarray:
    """
    Vectorized standard RGB (0-255) to CIE-LAB D65 conversion.
    Input: numpy array shape (..., 3) in range [0, 255].
    Output: numpy array shape (..., 3) with [L, a, b].
    """
    # Normalize sRGB to [0, 1]
    rgb = np.asarray(rgb_array, dtype=np.float64) / 255.0

    # Gamma expansion
    mask = rgb > 0.04045
    rgb[mask] = np.power((rgb[mask] + 0.055) / 1.055, 2.4)
    rgb[~mask] = rgb[~mask] / 12.92

    # Observer. = 2°, Illuminant = D65 (sRGB to XYZ matrix)
    xyz_matrix = np.array([
        [0.4124564, 0.3575761, 0.1804375],
        [0.2126729, 0.7151522, 0.0721750],
        [0.0193339, 0.1191920, 0.9503041]
    ])
    xyz = np.dot(rgb, xyz_matrix.T)

    # Reference white D65
    xn, yn, zn = 0.95047, 1.00000, 1.08883
    xyz[..., 0] /= xn
    xyz[..., 1] /= yn
    xyz[..., 2] /= zn

    # f(t) transformation
    epsilon = 0.008856
    kappa = 903.3
    mask_xyz = xyz > epsilon
    f_xyz = np.zeros_like(xyz)
    f_xyz[mask_xyz] = np.cbrt(xyz[mask_xyz])
    f_xyz[~mask_xyz] = (kappa * xyz[~mask_xyz] + 16.0) / 116.0

    # CIE-LAB values
    L = np.maximum(0.0, 116.0 * f_xyz[..., 1] - 16.0)
    a = 500.0 * (f_xyz[..., 0] - f_xyz[..., 1])
    b = 200.0 * (f_xyz[..., 1] - f_xyz[..., 2])

    return np.stack([L, a, b], axis=-1)


def compute_average_color(img: Image.Image, color_space: str = "lab") -> Tuple[float, float, float]:
    """
    Computes average color of a PIL image in RGB or CIE-LAB space.
    """
    # Downscale for instant average calculation
    small = img.resize((1, 1), Image.Resampling.BOX)
    r, g, b = small.convert("RGB").getpixel((0, 0))
    if color_space == "lab":
        lab = rgb_to_cielab(np.array([r, g, b], dtype=np.float64))
        return float(lab[0]), float(lab[1]), float(lab[2])
    return float(r), float(g), float(b)


def apply_freemium_watermark(image: Image.Image, watermark_text: str = "MosaicStudio Pro • Preview") -> Image.Image:
    """
    Overlays a subtle diagonal repeating watermark on the preview image.
    """
    watermark_layer = Image.new("RGBA", image.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(watermark_layer)

    w, h = image.size
    # Font size relative to preview dimension
    font_size = max(24, int(min(w, h) * 0.045))
    try:
        font = ImageFont.truetype("arial.ttf", font_size)
    except Exception:
        font = ImageFont.load_default()

    # Step distances for repeating text
    step_x = max(250, int(font_size * 10))
    step_y = max(180, int(font_size * 5))

    # Create temporary text tile
    text_bbox = draw.textbbox((0, 0), watermark_text, font=font)
    text_w = text_bbox[2] - text_bbox[0]
    text_h = text_bbox[3] - text_bbox[1]

    text_img = Image.new("RGBA", (text_w + 40, text_h + 30), (0, 0, 0, 0))
    tdraw = ImageDraw.Draw(text_img)
    # Subtle drop shadow
    tdraw.text((12, 12), watermark_text, fill=(0, 0, 0, 70), font=font)
    # Semi-transparent white
    tdraw.text((10, 10), watermark_text, fill=(255, 255, 255, 120), font=font)

    # Rotate 30 degrees
    rotated_text = text_img.rotate(30, expand=True, resample=Image.Resampling.BILINEAR)
    rw, rh = rotated_text.size

    for y in range(-rh, h + rh, step_y):
        for x in range(-rw, w + rw, step_x):
            watermark_layer.paste(rotated_text, (x, y), rotated_text)

    # Composite onto RGB image
    converted = image.convert("RGBA")
    combined = Image.alpha_composite(converted, watermark_layer)
    return combined.convert("RGB")


def render_photomosaic(
    target_image_path: str,
    tile_paths: List[str],
    output_dir: str,
    grid_columns: int = 80,
    tile_resolution: int = 60,
    blend_alpha: float = 0.15,
    allow_flips: bool = True,
    min_distance_radius: int = 3,
    color_space: str = "lab",
    project_id: str = "default"
) -> Dict[str, Any]:
    """
    Main mosaic generation pipeline using KDTree and RAM pre-loaded tiles.
    """
    start_time = time.time()
    os.makedirs(output_dir, exist_ok=True)

    if not os.path.exists(target_image_path):
        raise FileNotFoundError(f"Target image not found: {target_image_path}")

    if not tile_paths:
        raise ValueError("Tile collection is empty. At least 1 tile image is required.")

    # 1. Load target image and compute dimensions
    with Image.open(target_image_path) as orig_target:
        target_rgb = orig_target.convert("RGB")
        orig_w, orig_h = target_rgb.size
        aspect_ratio = orig_h / orig_w

    # Calculate grid dimensions
    grid_cols = max(10, min(300, int(grid_columns)))
    grid_rows = max(10, int(round(grid_cols * aspect_ratio)))
    total_cells = grid_cols * grid_rows

    tile_res = max(20, min(300, int(tile_resolution)))
    canvas_w = grid_cols * tile_res
    canvas_h = grid_rows * tile_res

    # Clamp total canvas resolution to safe maximum (e.g., 16000 px)
    max_dim = 16000
    if canvas_w > max_dim or canvas_h > max_dim:
        scale_down = max_dim / max(canvas_w, canvas_h)
        tile_res = max(15, int(tile_res * scale_down))
        canvas_w = grid_cols * tile_res
        canvas_h = grid_rows * tile_res

    # 2. Downscale target image to grid size to get cell average colors
    grid_target = target_rgb.resize((grid_cols, grid_rows), Image.Resampling.BOX)
    grid_np = np.array(grid_target, dtype=np.float64)  # Shape (grid_rows, grid_cols, 3)

    if color_space == "lab":
        grid_colors = rgb_to_cielab(grid_np)
    else:
        grid_colors = grid_np

    # 3. Pre-load, resize, and index tile library in RAM
    tiles_ram: List[Image.Image] = []
    tile_features: List[Tuple[float, float, float]] = []
    tile_meta: List[Dict[str, Any]] = []

    for idx, path in enumerate(tile_paths):
        if not os.path.exists(path):
            continue
        try:
            with Image.open(path) as t_img:
                # Square crop and resize
                t_rgb = t_img.convert("RGB")
                tw, th = t_rgb.size
                min_dim = min(tw, th)
                left = (tw - min_dim) // 2
                top = (th - min_dim) // 2
                cropped = t_rgb.crop((left, top, left + min_dim, top + min_dim))
                resized = cropped.resize((tile_res, tile_res), Image.Resampling.LANCZOS)

                # Store primary tile
                avg_col = compute_average_color(resized, color_space=color_space)
                tile_idx = len(tiles_ram)
                tiles_ram.append(resized)
                tile_features.append(avg_col)
                tile_meta.append({"id": idx, "is_flipped": False, "path": path})

                # If horizontal flips allowed, double palette
                if allow_flips:
                    flipped = ImageOps.mirror(resized)
                    avg_col_flip = compute_average_color(flipped, color_space=color_space)
                    tiles_ram.append(flipped)
                    tile_features.append(avg_col_flip)
                    tile_meta.append({"id": idx, "is_flipped": True, "path": path})

        except Exception as e:
            # Skip unreadable or corrupted files gracefully
            continue

    if not tiles_ram:
        raise ValueError("None of the provided tiles could be successfully processed.")

    palette_size = len(tiles_ram)

    # 4. Build KD-Tree for fast spatial nearest neighbor search
    kd_tree = KDTree(np.array(tile_features, dtype=np.float64))

    # 5. Spatial Neighbor Exclusion & Assignment
    # usage_grid stores the raw base tile ID (to prevent repeating the same base photo)
    usage_grid = np.full((grid_rows, grid_cols), -1, dtype=np.int32)
    selected_indices = np.zeros((grid_rows, grid_cols), dtype=np.int32)

    k_neighbors = min(palette_size, max(15, (min_distance_radius * 2 + 1) ** 2 * 2))

    for r in range(grid_rows):
        for c in range(grid_cols):
            target_cell_color = grid_colors[r, c]

            # Query top K candidates
            distances, candidates = kd_tree.query(target_cell_color, k=k_neighbors)
            if np.isscalar(candidates):
                candidates = np.array([candidates])

            chosen_candidate = candidates[0]

            if min_distance_radius > 0 and palette_size > 1:
                # Calculate neighborhood bounds
                r_min = max(0, r - min_distance_radius)
                r_max = min(grid_rows, r + min_distance_radius + 1)
                c_min = max(0, c - min_distance_radius)
                c_max = min(grid_cols, c + min_distance_radius + 1)

                neighborhood_used = usage_grid[r_min:r_max, c_min:c_max]
                used_set = set(neighborhood_used[neighborhood_used != -1].flatten())

                # Find first candidate not used recently in vicinity
                found = False
                for cand in candidates:
                    base_id = tile_meta[cand]["id"]
                    if base_id not in used_set:
                        chosen_candidate = cand
                        found = True
                        break

                if not found:
                    # Fallback to candidate with minimum usage count in the neighborhood
                    candidate_counts = [
                        (cand, np.sum(neighborhood_used == tile_meta[cand]["id"]))
                        for cand in candidates
                    ]
                    candidate_counts.sort(key=lambda item: item[1])
                    chosen_candidate = candidate_counts[0][0]

            selected_indices[r, c] = chosen_candidate
            usage_grid[r, c] = tile_meta[chosen_candidate]["id"]

    # 6. Assemble Ultra-HD Master Canvas in RAM
    master_canvas = Image.new("RGB", (canvas_w, canvas_h))

    for r in range(grid_rows):
        y_pos = r * tile_res
        for c in range(grid_cols):
            x_pos = c * tile_res
            cand_idx = selected_indices[r, c]
            tile_img = tiles_ram[cand_idx]
            master_canvas.paste(tile_img, (x_pos, y_pos))

    # 7. Apply blending with original target image
    blend_val = max(0.0, min(0.50, float(blend_alpha)))
    if blend_val > 0.001:
        target_scaled = target_rgb.resize((canvas_w, canvas_h), Image.Resampling.BILINEAR)
        blended_canvas = Image.blend(master_canvas, target_scaled, blend_val)
    else:
        blended_canvas = master_canvas

    # 8. Save Master Ultra-HD
    master_path = os.path.join(output_dir, f"master_uhd_{project_id}.jpg")
    blended_canvas.save(master_path, "JPEG", quality=95, optimize=True)

    # 9. Save Preview with Freemium Watermark
    preview_path = os.path.join(output_dir, f"preview_{project_id}.jpg")
    preview_max_dim = 1800
    if max(canvas_w, canvas_h) > preview_max_dim:
        scale_preview = preview_max_dim / max(canvas_w, canvas_h)
        pw = max(100, int(canvas_w * scale_preview))
        ph = max(100, int(canvas_h * scale_preview))
        preview_img = blended_canvas.resize((pw, ph), Image.Resampling.LANCZOS)
    else:
        preview_img = blended_canvas.copy()

    watermarked_preview = apply_freemium_watermark(preview_img, "MosaicStudio Pro • Preview")
    watermarked_preview.save(preview_path, "JPEG", quality=85, optimize=True)

    elapsed_time = round(time.time() - start_time, 2)

    return {
        "success": True,
        "project_id": project_id,
        "preview_path": preview_path,
        "final_path": master_path,
        "grid": {
            "columns": grid_cols,
            "rows": grid_rows,
            "total_cells": total_cells
        },
        "resolution": {
            "width": canvas_w,
            "height": canvas_h,
            "tile_size": tile_res,
            "megapixels": round((canvas_w * canvas_h) / 1_000_000, 2)
        },
        "tiles_loaded": len(tile_paths),
        "total_palette_size": palette_size,
        "flips_enabled": allow_flips,
        "blend_alpha": blend_val,
        "min_distance_radius": min_distance_radius,
        "execution_time_seconds": elapsed_time
    }


def main():
    parser = argparse.ArgumentParser(description="MosaicStudio Pro Mathematical Core")
    parser.add_argument("--project_id", type=str, required=True, help="Project UUID or Identifier")
    parser.add_argument("--config", type=str, required=False, help="Path to config JSON file or JSON string")
    parser.add_argument("--target", type=str, required=False, help="Target image path")
    parser.add_argument("--tiles_dir", type=str, required=False, help="Directory containing tile images")
    parser.add_argument("--output_dir", type=str, required=False, help="Output directory")
    parser.add_argument("--columns", type=int, default=80, help="Grid columns (40-250)")
    parser.add_argument("--tile_res", type=int, default=60, help="Resolution per tile (40-200 px)")
    parser.add_argument("--blend", type=float, default=0.15, help="Blend alpha (0.0 - 0.50)")
    parser.add_argument("--allow_flips", action="store_true", default=True, help="Enable horizontal mirror tiles")
    parser.add_argument("--min_distance", type=int, default=3, help="Neighbor repetition exclusion radius")
    parser.add_argument("--color_space", type=str, default="lab", choices=["lab", "rgb"], help="Color matching space")

    args = parser.parse_args()

    try:
        # Load config if JSON provided
        config_data = {}
        if args.config:
            if os.path.exists(args.config):
                with open(args.config, "r", encoding="utf-8") as f:
                    config_data = json.load(f)
            else:
                config_data = json.loads(args.config)

        target = config_data.get("target_image_path", args.target)
        output_dir = config_data.get("output_dir", args.output_dir or "./output")
        columns = config_data.get("grid_size", config_data.get("columns", args.columns))
        tile_res = config_data.get("tile_res", args.tile_res)
        blend = config_data.get("blend_alpha", args.blend)
        allow_flips = config_data.get("allow_flips", args.allow_flips)
        min_distance = config_data.get("min_distance_radius", args.min_distance)
        color_space = config_data.get("color_space", args.color_space)

        # Collect tile file paths
        tiles: List[str] = []
        if "tiles" in config_data and isinstance(config_data["tiles"], list):
            tiles = config_data["tiles"]
        elif args.tiles_dir and os.path.isdir(args.tiles_dir):
            valid_exts = {".jpg", ".jpeg", ".png", ".webp", ".bmp"}
            for root, _, files in os.walk(args.tiles_dir):
                for file in files:
                    if os.path.splitext(file.lower())[1] in valid_exts:
                        tiles.append(os.path.join(root, file))

        result = render_photomosaic(
            target_image_path=target,
            tile_paths=tiles,
            output_dir=output_dir,
            grid_columns=int(columns),
            tile_resolution=int(tile_res),
            blend_alpha=float(blend),
            allow_flips=bool(allow_flips),
            min_distance_radius=int(min_distance),
            color_space=color_space,
            project_id=args.project_id
        )

        print(json.dumps(result, indent=2))
        sys.exit(0)

    except Exception as e:
        error_payload = {
            "success": False,
            "project_id": args.project_id,
            "error": str(e)
        }
        print(json.dumps(error_payload, indent=2), file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
