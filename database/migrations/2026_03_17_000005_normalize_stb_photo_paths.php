<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stbs') || !Schema::hasColumn('stbs', 'photo')) {
            return;
        }

        $stbs = DB::table('stbs')
            ->select(['id', 'photo'])
            ->whereNotNull('photo')
            ->get();

        foreach ($stbs as $stb) {
            $photo = (string) $stb->photo;

            if (!str_starts_with($photo, 'STB/')) {
                continue;
            }

            $segments = explode('/', $photo);
            $legacyFolder = $segments[1] ?? '';
            $legacyFilename = $segments[2] ?? '';

            if ($legacyFolder === '' || $legacyFilename === '') {
                continue;
            }

            $docId = preg_replace('/-[a-z0-9]{8}$/i', '', $legacyFolder) ?: $legacyFolder;
            $extension = pathinfo($legacyFilename, PATHINFO_EXTENSION) ?: 'jpg';
            $newPath = 'stb-photos/' . Str::of($docId)->trim()->value() . '.' . strtolower($extension);

            if (Storage::disk('public')->exists($photo)) {
                Storage::disk('public')->delete($newPath);
                Storage::disk('public')->move($photo, $newPath);
            }

            DB::table('stbs')
                ->where('id', $stb->id)
                ->update(['photo' => $newPath]);

            if (Storage::disk('public')->exists('STB/' . $legacyFolder)) {
                try {
                    Storage::disk('public')->deleteDirectory('STB/' . $legacyFolder);
                } catch (\Throwable $e) {
                    // Ignore cleanup failures; the photo path has already been normalized.
                }
            }
        }
    }

    public function down(): void
    {
    }
};
