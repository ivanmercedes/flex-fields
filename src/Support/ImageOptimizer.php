<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageOptimizer
{
    /**
     * Optimize, resize and convert image with guaranteed size reduction.
     * Returns the relative path of the smallest image version.
     */
    public static function optimize(
        string $disk,
        string $relativePath,
        ?string $format = 'auto',
        ?int $maxWidth = 1920,
        ?int $maxHeight = null,
        int $quality = 75
    ): string {
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
            return $relativePath;
        }

        $fullPath = $storage->path($relativePath);

        if (! file_exists($fullPath) || filesize($fullPath) === 0) {
            return $relativePath;
        }

        $originalSize = filesize($fullPath);
        $pathInfo = pathinfo($fullPath);
        $directory = $pathInfo['dirname'];
        $filenameWithoutExt = $pathInfo['filename'];
        $originalExt = strtolower($pathInfo['extension'] ?? '');

        // Determine target formats to test
        $requestedFormat = strtolower((string) ($format ?: 'auto'));
        if ($requestedFormat === 'jpeg') {
            $requestedFormat = 'jpg';
        }

        $candidates = [];

        try {
            // Read image dimensions and mime
            $info = @getimagesize($fullPath);
            if (! $info) {
                return $relativePath;
            }

            $origWidth = $info[0];
            $origHeight = $info[1];
            $mime = $info['mime'] ?? '';

            // Calculate scaling target
            $targetWidth = $maxWidth ?: 1920;
            $needScale = ($maxWidth && $origWidth > $maxWidth) || ($maxHeight && $origHeight > $maxHeight);

            // Candidate 1: Same format with optimal compression / downscaling
            $sameFormatExt = $originalExt;
            $sameFormatPath = $directory . '/' . $filenameWithoutExt . '_opt_same.' . $sameFormatExt;

            if (self::processNative($fullPath, $sameFormatPath, $sameFormatExt, $maxWidth, $maxHeight, $quality)) {
                if (file_exists($sameFormatPath)) {
                    $candidates[$sameFormatExt] = [
                        'path' => $sameFormatPath,
                        'size' => filesize($sameFormatPath),
                        'ext' => $sameFormatExt,
                    ];
                }
            }

            // Candidate 2: WebP format
            if ($requestedFormat === 'webp' || $requestedFormat === 'auto') {
                $webpPath = $directory . '/' . $filenameWithoutExt . '_opt_webp.webp';
                if (self::processNative($fullPath, $webpPath, 'webp', $maxWidth, $maxHeight, $quality)) {
                    if (file_exists($webpPath)) {
                        $candidates['webp'] = [
                            'path' => $webpPath,
                            'size' => filesize($webpPath),
                            'ext' => 'webp',
                        ];
                    }
                }
            }

            // Candidate 3: JPG format (if original was not PNG with transparency)
            if (($requestedFormat === 'jpg' || $requestedFormat === 'jpeg') && $mime !== 'image/png') {
                $jpgPath = $directory . '/' . $filenameWithoutExt . '_opt_jpg.jpg';
                if (self::processNative($fullPath, $jpgPath, 'jpg', $maxWidth, $maxHeight, $quality)) {
                    if (file_exists($jpgPath)) {
                        $candidates['jpg'] = [
                            'path' => $jpgPath,
                            'size' => filesize($jpgPath),
                            'ext' => 'jpg',
                        ];
                    }
                }
            }

            // Find smallest candidate
            $bestCandidate = null;
            $bestSize = $originalSize;

            foreach ($candidates as $cand) {
                if ($cand['size'] < $bestSize) {
                    $bestSize = $cand['size'];
                    $bestCandidate = $cand;
                }
            }

            // If user explicitly forced a format (e.g. 'webp') and candidate exists, use it if smaller than original or scaled
            if ($requestedFormat !== 'auto' && isset($candidates[$requestedFormat])) {
                $forcedCand = $candidates[$requestedFormat];
                if ($forcedCand['size'] <= $originalSize || $needScale) {
                    $bestCandidate = $forcedCand;
                }
            }

            // Apply best candidate
            if ($bestCandidate && file_exists($bestCandidate['path'])) {
                $finalExt = $bestCandidate['ext'];
                $newFullPath = $directory . '/' . $filenameWithoutExt . '.' . $finalExt;

                if ($fullPath !== $newFullPath && file_exists($fullPath)) {
                    @unlink($fullPath);
                }

                rename($bestCandidate['path'], $newFullPath);

                // Clean up remaining temp candidates
                foreach ($candidates as $cand) {
                    if (file_exists($cand['path'])) {
                        @unlink($cand['path']);
                    }
                }

                $relDir = pathinfo($relativePath, PATHINFO_DIRNAME);
                $relDir = ($relDir === '.' || $relDir === '/') ? '' : $relDir . '/';

                return $relDir . $filenameWithoutExt . '.' . $finalExt;
            }

            // Clean up all candidates if original was smaller
            foreach ($candidates as $cand) {
                if (file_exists($cand['path'])) {
                    @unlink($cand['path']);
                }
            }
        } catch (Throwable $e) {
            foreach ($candidates as $cand) {
                if (isset($cand['path']) && file_exists($cand['path'])) {
                    @unlink($cand['path']);
                }
            }

            logger()->warning('FlexFields Image Optimization Warning: ' . $e->getMessage(), [
                'file' => $relativePath,
            ]);
        }

        return $relativePath;
    }

    /**
     * Process image using Intervention Image v3/v4 or Native GD/Imagick.
     */
    protected static function processNative(
        string $sourcePath,
        string $destPath,
        string $format,
        ?int $maxWidth,
        ?int $maxHeight,
        int $quality
    ): bool {
        // Try Intervention Image v3/v4 if available in vendor
        if (class_exists(ImageManager::class)) {
            try {
                $driverClass = extension_loaded('imagick')
                    ? Driver::class
                    : \Intervention\Image\Drivers\Gd\Driver::class;

                if (class_exists($driverClass)) {
                    $manager = new ImageManager(new $driverClass);
                    $image = $manager->read($sourcePath);

                    if ($maxWidth || $maxHeight) {
                        $image->scaleDown(width: $maxWidth ?: 1920, height: $maxHeight);
                    }

                    $encoded = match ($format) {
                        'jpg', 'jpeg' => $image->toJpeg($quality),
                        'png' => $image->toPng(),
                        'avif' => function_exists('imageavif') ? $image->toAvif($quality) : $image->toWebp($quality),
                        default => $image->toWebp($quality),
                    };

                    $encoded->save($destPath);

                    if (file_exists($destPath)) {
                        return true;
                    }
                }
            } catch (Throwable) {
                // Fallback to native GD/Imagick
            }
        }

        // Native Imagick engine
        if (extension_loaded('imagick')) {
            try {
                $im = new Imagick($sourcePath);
                $im->stripImage();

                $w = $im->getImageWidth();
                $h = $im->getImageHeight();

                if ($maxWidth && ($w > $maxWidth || ($maxHeight && $h > $maxHeight))) {
                    $im->resizeImage($maxWidth, $maxHeight ?: 0, Imagick::FILTER_LANCZOS, 1, true);
                }

                $im->setImageFormat($format);
                $im->setImageCompressionQuality($quality);
                $im->writeImage($destPath);
                $im->clear();
                $im->destroy();

                return file_exists($destPath);
            } catch (Throwable) {
                // Fallback to GD
            }
        }

        // Native GD engine
        if (! extension_loaded('gd')) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if (! $info) {
            return false;
        }

        $mime = $info['mime'] ?? '';
        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/bmp' => @imagecreatefrombmp($sourcePath),
            default => null,
        };

        if (! $srcImg) {
            return false;
        }

        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        $newW = $origW;
        $newH = $origH;

        if ($maxWidth && $origW > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int) round($origH * ($maxWidth / $origW));
        }

        if ($maxHeight && $newH > $maxHeight) {
            $newW = (int) round($newW * ($maxHeight / $newH));
            $newH = $maxHeight;
        }

        $dstImg = imagecreatetruecolor($newW, $newH);

        if ($format === 'png' || $format === 'webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
            imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
        } else {
            $bg = imagecolorallocate($dstImg, 255, 255, 255);
            imagefill($dstImg, 0, 0, $bg);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $saved = match ($format) {
            'jpg', 'jpeg' => imagejpeg($dstImg, $destPath, $quality),
            'png' => imagepng($dstImg, $destPath, 9),
            'webp' => imagewebp($dstImg, $destPath, $quality),
            default => imagewebp($dstImg, $destPath, $quality),
        };

        return $saved && file_exists($destPath);
    }
}
