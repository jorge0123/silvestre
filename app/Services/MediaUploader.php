<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda fotos y videos que suben los negocios.
 *
 * Fotos: se reorientan según la cámara, se reducen y se vuelven a guardar como
 * JPEG. Re-guardarlas BORRA los metadatos, incluida la ubicación GPS: una
 * emprendedora que toma fotos en su casa no debería publicar dónde vive.
 *
 * Videos: se valida la duración y se genera un póster (la imagen que se ve
 * mientras carga) con ffmpeg, si está instalado.
 */
class MediaUploader
{
    public const IMAGE_RULES = ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'];

    public const VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

    public const MAX_VIDEO_KB = 61440; // 60 MB

    public function isVideo(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), self::VIDEO_MIMES, true);
    }

    /**
     * @param  array{0: int, 1: int}|null  $crop  Recorte exacto [ancho, alto] centrado.
     */
    public function storeImage(UploadedFile $file, string $dir, int $maxSide = 1600, ?array $crop = null): string
    {
        $data = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($data);

        if (! $image) {
            throw ValidationException::withMessages(['media' => 'No pudimos leer esa imagen. Prueba con otra foto (JPG o PNG).']);
        }

        $image = $this->orient($image, $this->exifOrientation($data));
        $image = $crop ? $this->cropCover($image, $crop[0], $crop[1]) : $this->fit($image, $maxSide);

        $path = trim($dir, '/').'/'.Str::ulid().'.jpg';

        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, 82);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($image);

        return $path;
    }

    /**
     * @return array{path: string, poster: ?string, seconds: ?float}
     */
    public function storeVideo(UploadedFile $file, string $dir, int $maxSeconds): array
    {
        $seconds = $this->duration($file->getRealPath());

        if ($seconds !== null && $seconds > $maxSeconds + 0.5) {
            throw ValidationException::withMessages([
                'media' => 'El video dura '.round($seconds)." segundos. El máximo es {$maxSeconds}.",
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $path = $file->storeAs(trim($dir, '/'), Str::ulid().'.'.$extension, 'public');

        return ['path' => $path, 'poster' => $this->poster($path), 'seconds' => $seconds];
    }

    public function delete(?string $path): void
    {
        // Nunca se borran los archivos de demostración compartidos.
        if ($path && ! str_starts_with($path, 'demo/')) {
            Storage::disk('public')->delete($path);
        }
    }

    // ---------------------------------------------------------------- fotos

    private function fit(\GdImage $image, int $maxSide): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));

        if ($scale === 1) {
            return $image;
        }

        $resized = imagescale($image, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
        imagedestroy($image);

        return $resized;
    }

    private function cropCover(\GdImage $image, int $targetW, int $targetH): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = max($targetW / $w, $targetH / $h);
        $srcW = (int) round($targetW / $scale);
        $srcH = (int) round($targetH / $scale);

        $out = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($out, $image, 0, 0, (int) (($w - $srcW) / 2), (int) (($h - $srcH) / 2), $targetW, $targetH, $srcW, $srcH);
        imagedestroy($image);

        return $out;
    }

    private function orient(\GdImage $image, int $orientation): \GdImage
    {
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /**
     * Lee la orientación EXIF de un JPEG sin la extensión exif de PHP.
     * Los celulares guardan la foto "acostada" y anotan cómo girarla.
     */
    private function exifOrientation(string $data): int
    {
        if (! str_starts_with($data, "\xFF\xD8")) {
            return 1;
        }

        $offset = 2;
        $length = strlen($data);

        while ($offset + 4 < $length) {
            if ($data[$offset] !== "\xFF") {
                return 1;
            }

            $marker = ord($data[$offset + 1]);
            $size = unpack('n', substr($data, $offset + 2, 2))[1];

            if ($marker === 0xE1 && substr($data, $offset + 4, 6) === "Exif\0\0") {
                $tiff = $offset + 10;
                $little = substr($data, $tiff, 2) === 'II';
                $u16 = fn (int $at) => unpack($little ? 'v' : 'n', substr($data, $at, 2))[1];
                $u32 = fn (int $at) => unpack($little ? 'V' : 'N', substr($data, $at, 4))[1];

                $ifd = $tiff + $u32($tiff + 4);
                $entries = $u16($ifd);

                for ($i = 0; $i < $entries; $i++) {
                    $entry = $ifd + 2 + $i * 12;
                    if ($u16($entry) === 0x0112) {
                        return $u16($entry + 8);
                    }
                }

                return 1;
            }

            if ($marker === 0xDA) {
                return 1; // empezó la imagen: no hay EXIF
            }

            $offset += 2 + $size;
        }

        return 1;
    }

    // --------------------------------------------------------------- videos

    private function duration(string $realPath): ?float
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $realPath,
        ]);

        return $result->successful() && is_numeric(trim($result->output()))
            ? (float) trim($result->output())
            : null;
    }

    private function poster(string $videoPath): ?string
    {
        $disk = Storage::disk('public');
        $poster = preg_replace('/\.\w+$/', '-poster.jpg', $videoPath);

        $result = Process::timeout(60)->run([
            'ffmpeg', '-loglevel', 'error', '-y', '-ss', '0.5', '-i', $disk->path($videoPath),
            '-frames:v', '1', '-vf', 'scale=720:-2', '-q:v', '4', $disk->path($poster),
        ]);

        return $result->successful() && $disk->exists($poster) ? $poster : null;
    }
}
