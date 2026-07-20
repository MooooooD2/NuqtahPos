<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * #33 #34 رفع الصور بأمان — تحقق متعدد الطبقات
 */
class ImageUploadService
{
    // #34 الأنواع المسموحة فقط (MIME الحقيقي من محتوى الملف)
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB

    public function uploadProductImage(UploadedFile $file, ?string $oldPath = null): string
    {
        // #34 التحقق من MIME الحقيقي (ليس الامتداد فقط)
        $realMime = $file->getMimeType();
        if (! in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('نوع الملف غير مسموح به.');
        }

        // #34 التحقق من الحجم
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('الملف كبير جداً.');
        }

        // #33 اسم عشوائي — لا يعتمد على اسم المستخدم (يمنع path traversal)
        $filename = Str::uuid() . '.' . $file->extension();
        $directory = 'products/' . date('Y/m');

        // #33 حفظ في disk منفصل (public) بعيداً عن app/
        $path = $file->storeAs($directory, $filename, 'public');

        // حذف الصورة القديمة
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
