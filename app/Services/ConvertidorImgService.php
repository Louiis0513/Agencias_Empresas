<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ConvertidorImgService
{
    public function convertPublicImageToWebp(string $relativePath, int $quality = 80): string
    {
        if ($relativePath === '') {
            throw new RuntimeException('La ruta de la imagen no puede estar vacía.');
        }

        $disk = Storage::disk('public');

        $inputPath = $disk->path($relativePath);

        if (! file_exists($inputPath)) {
            throw new RuntimeException("No se encontró el archivo de entrada para convertir: {$relativePath}");
        }

        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        if ($extension === 'webp') {
            return $relativePath;
        }

        $directory = trim(dirname($relativePath), '/');
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);

        $outputRelativePath = ($directory !== '' ? $directory.'/' : '').$filename.'.webp';
        $outputPath = $disk->path($outputRelativePath);

        $quality = max(0, min(100, $quality));

        if ($this->imagickSupportsWebp() && $this->convertWithImagick($inputPath, $outputPath, $quality) && file_exists($outputPath)) {
            $disk->delete($relativePath);

            return $outputRelativePath;
        }

        if ($this->gdSupportsWebp() && $this->convertWithGd($inputPath, $outputPath, $quality) && file_exists($outputPath)) {
            $disk->delete($relativePath);

            return $outputRelativePath;
        }

        Log::warning('Conversión a WebP no disponible (Imagick/GD con soporte WebP), se conserva la imagen original.', [
            'relative_path' => $relativePath,
        ]);

        return $relativePath;
    }

    private function imagickSupportsWebp(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        $formats = \Imagick::queryFormats('WEBP');

        return in_array('WEBP', $formats, true);
    }

    private function gdSupportsWebp(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    private function convertWithImagick(string $inputPath, string $outputPath, int $quality): bool
    {
        try {
            $img = new \Imagick($inputPath);
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality($quality);
            $result = $img->writeImage($outputPath);
            $img->destroy();

            return $result;
        } catch (\Throwable $e) {
            Log::debug('Imagick WebP conversion failed', ['path' => $inputPath, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function convertWithGd(string $inputPath, string $outputPath, int $quality): bool
    {
        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));

        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($inputPath),
            'png' => @imagecreatefrompng($inputPath),
            'gif' => @imagecreatefromgif($inputPath),
            default => null,
        };

        if ($image === false || $image === null) {
            return false;
        }

        if ($extension === 'png') {
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        $result = imagewebp($image, $outputPath, $quality);
        imagedestroy($image);

        return $result;
    }
}
