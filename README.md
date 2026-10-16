# MosaicStudio Pro

Plataforma web de alto rendimiento para la generación de fotomosaicos de ultra alta resolución (hasta **15,000 × 15,000 px / 225 MP**), construida con **Laravel 11**, **Bootstrap 5.3 (Dark Mode)** y un motor matemático en **Python 3** acelerado espacialmente con `scipy.spatial.KDTree`, `NumPy` y `Pillow` en el espacio de color **CIE-LAB**.

---

## Arquitectura y Stack Tecnológico

- **Backend:** Laravel 11 (PHP 8.2+) con colas asíncronas (`GenerateMosaicJob`) y comunicación de procesos vía `Illuminate\Support\Facades\Process`.
- **Motor Matemático (`storage/app/scripts/mosaic_engine.py`):**
  - Conversión vectorial a espacio de color **CIE-LAB** perceptualmente uniforme.
  - Árbol espacial `scipy.spatial.KDTree` en RAM para búsqueda $O(\log N)$ de micro-fotos.
  - Matriz de restricción vecinal (`min_distance_radius`) para evitar repeticiones cercanas de la misma foto.
  - Espejado horizontal opcional (`allow_flips`) para duplicar la variedad cromática de la paleta en RAM.
  - Fusión ponderada (`Image.blend`) con la imagen original y generación dual:
    - `preview_{uuid}.jpg`: Versión web con marca de agua diagonal.
    - `master_uhd_{uuid}.jpg`: Versión maestra Ultra-HD (calidad JPEG 95%).
- **Frontend (`resources/views/mosaic/studio.blade.php`):**
  - Interfaz en Modo Oscuro (Bootstrap 5.3).
  - Carga masiva por lotes con toggle de **Modo Local Rápido (Directo a RAM)** vs **Modo Producción (Pre-recorte 300×300 px en HTML5 Canvas)**.
  - Visor interactivo de zoom profundo con `@panzoom/panzoom`.

---

## Instalación y Uso Local

1. **Dependencias PHP y Python:**
   ```bash
   composer install
   pip install pillow numpy scipy
   ```

2. **Configuración de Entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   ```

3. **Ejecución:**
   ```bash
   php artisan serve --port=8080
   ```
   Abrir `http://localhost:8080/studio`.
