<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class AttachmentService
{
    private const IMAGE_EXTS   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    private const VIDEO_EXTS   = ['mp4', 'mov', 'avi', 'webm'];
    private const IMAGE_MIMES  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
    private const VIDEO_MIMES  = ['video/mp4', 'video/quicktime', 'video/avi', 'video/webm', 'video/x-msvideo'];
    private const MAX_DIMENSION = 1000;
    private const JPEG_QUALITY  = 85;

    public function storeForPenalty(array $files, int $penaltyId): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $this->store($file, $penaltyId);
        }
    }

    public function deleteForPenalty(int $penaltyId): void
    {
        Storage::disk('public')->deleteDirectory('penalties/' . $penaltyId);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = Attachment::find($attachmentId);
        if (!$attachment) return;
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    private function store(UploadedFile $file, int $penaltyId): Attachment
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType() ?? '';

        if ($this->isImage($ext, $mime)) {
            return $this->storeImage($file, $penaltyId);
        }

        return $this->storeVideo($file, $penaltyId);
    }

    private function storeImage(UploadedFile $file, int $penaltyId): Attachment
    {
        $manager = $this->makeImageManager();
        $image   = $manager->read($file->getPathname());

        // Scale down to fit within 500×500, preserve aspect ratio, no upscaling
        $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

        $dir      = 'penalties/' . $penaltyId;
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name     = ($baseName ?: 'img') . '_' . Str::random(8) . '.jpg';
        $path     = $dir . '/' . $name;

        Storage::disk('public')->put($path, (string) $image->toJpeg(self::JPEG_QUALITY));

        return Attachment::create([
            'penalty_id'  => $penaltyId,
            'filename'    => $file->getClientOriginalName(),
            'path'        => $path,
            'disk'        => 'public',
            'type'        => 'image',
            'mime_type'   => 'image/jpeg',
            'size'        => Storage::disk('public')->size($path),
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function storeVideo(UploadedFile $file, int $penaltyId): Attachment
    {
        $ext      = strtolower($file->getClientOriginalExtension()) ?: 'mp4';
        $dir      = 'penalties/' . $penaltyId;
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name     = ($baseName ?: 'video') . '_' . Str::random(8) . '.' . $ext;
        $path     = $dir . '/' . $name;

        Storage::disk('public')->putFileAs($dir, $file, $name);

        return Attachment::create([
            'penalty_id'  => $penaltyId,
            'filename'    => $file->getClientOriginalName(),
            'path'        => $path,
            'disk'        => 'public',
            'type'        => 'video',
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function isImage(string $ext, string $mime): bool
    {
        return in_array($ext, self::IMAGE_EXTS) || in_array($mime, self::IMAGE_MIMES);
    }

    private function makeImageManager(): ImageManager
    {
        // Prefer Imagick — required for HEIC/HEIF support
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver());
        }

        // GD fallback: handles jpg/png/gif/webp but NOT heic
        return new ImageManager(new GdDriver());
    }
}
