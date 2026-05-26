<?php

namespace App\Http\Controllers;

use App\Services\ConversionService;
use App\Services\ConversionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ConversionController extends Controller
{
    /** Maximum accepted upload size per file, in bytes (200 MB). */
    private const MAX_UPLOAD_BYTES = 200 * 1024 * 1024;

    public function convert(Request $request, ConversionService $conversionService, ConversionHistoryService $historyService): JsonResponse
    {
        $files = $request->file('files', []);
        $clientIds = $request->input('client_ids', []);
        $targetFormat = strtolower(trim((string) $request->input('target_format')));

        if (! is_array($files)) {
            $files = [$files];
        }
        if (! is_array($clientIds)) {
            $clientIds = [$clientIds];
        }
        if (count($files) === 0) {
            return response()->json(['error' => 'No files uploaded.'], 400);
        }
        if ($targetFormat === '') {
            return response()->json(['error' => 'Target format is required.'], 400);
        }

        $convertedFiles = [];
        foreach ($files as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
                return response()->json([
                    'error' => 'File "' . $file->getClientOriginalName() . '" is too large. Maximum allowed size is '
                        . (self::MAX_UPLOAD_BYTES / 1048576) . ' MB.',
                ], 422);
            }

            $originalName = $this->safeOriginalName($file->getClientOriginalName());
            $sourceId = Str::uuid()->toString();
            $uploadedName = "{$sourceId}_{$originalName}";
            $uploadedPath = $file->storeAs('uploads', $uploadedName);

            $stem = Str::limit(pathinfo($originalName, PATHINFO_FILENAME) ?: 'converted_file', 100, '');
            $convertedId = str_replace('-', '', Str::uuid()->toString());
            $convertedName = "{$stem}.{$targetFormat}";
            $downloadName = $this->safeDownloadName($convertedId, $targetFormat);
            $convertedPath = "converted/{$convertedId}_{$convertedName}";

            try {
                $destinationPath = Storage::path($convertedPath);
                $conversionService->convert(Storage::path($uploadedPath), $targetFormat, $destinationPath);

                clearstatcache(true, $destinationPath);
                if (! is_file($destinationPath) || filesize($destinationPath) <= 0) {
                    Storage::delete($convertedPath);
                    throw new \RuntimeException('Conversion produced an empty file.');
                }
            } catch (\Throwable $exception) {
                Storage::delete($uploadedPath);
                return response()->json(['error' => $exception->getMessage() ?: 'Conversion failed.'], 400);
            }

            $publicPath = $this->publishConvertedFile($destinationPath, $convertedId, $downloadName);
            $downloadUrl = route('download', ['fileId' => $convertedId], false) . '?v=' . filesize($destinationPath);

            Log::info('Conversion completed.', [
                'id' => $convertedId,
                'source_format' => strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)),
                'target_format' => $targetFormat,
                'storage_path' => $destinationPath,
                'public_path' => $publicPath,
                'download_url' => $downloadUrl,
                'size' => filesize($destinationPath),
            ]);

            $convertedFiles[] = [
                'id' => $convertedId,
                'client_id' => $clientIds[$index] ?? null,
                'original_name' => $originalName,
                'converted_name' => $downloadName,
                'download_url' => $downloadUrl,
                'inline_data' => $this->inlineDownloadData($destinationPath),
                'mime_type' => $this->mimeType($destinationPath, $downloadName),
            ];

            $historyService->add($request->session()->get('auth_user'), [
                'id' => $convertedId,
                'original_name' => $originalName,
                'converted_name' => $downloadName,
                'source_format' => strtoupper((string) pathinfo($originalName, PATHINFO_EXTENSION)),
                'target_format' => strtoupper($targetFormat),
                'size' => filesize($destinationPath),
                'download_url' => $downloadUrl,
            ]);
        }

        return response()->json(['files' => $convertedFiles]);
    }

    public function history(Request $request, ConversionHistoryService $historyService): JsonResponse
    {
        $user = $request->session()->get('auth_user');
        if (! $user) {
            return response()->json(['files' => []]);
        }

        return response()->json(['files' => $historyService->list($user)]);
    }

    /** Formats that can be merged, grouped by merge type. */
    private const MERGE_FORMATS = [
        'pdf' => ['pdf'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'wma'],
        'video' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', '3gp', 'm4v'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'tif', 'tiff'],
        'document' => ['txt', 'doc', 'docx', 'html', 'htm', 'md', 'rst', 'tex', 'fb2', 'xml', 'json', 'pdf'],
        'spreadsheet' => ['xlsx', 'xls', 'xlsm', 'ods', 'csv'],
        'presentation' => ['pptx'],
    ];

    public function mergePdf(Request $request, ConversionService $conversionService, ConversionHistoryService $historyService): JsonResponse
    {
        return $this->merge($request, $conversionService, $historyService);
    }

    public function merge(Request $request, ConversionService $conversionService, ConversionHistoryService $historyService): JsonResponse
    {
        $mergeType = strtolower(trim((string) $request->input('merge_type', 'pdf')));
        if (! array_key_exists($mergeType, self::MERGE_FORMATS)) {
            return response()->json(['error' => 'Unsupported merge type.'], 400);
        }
        $allowed = self::MERGE_FORMATS[$mergeType];
        $label = strtoupper($mergeType);

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        $files = array_values(array_filter($files));

        if (count($files) < 2) {
            return response()->json(['error' => 'Select at least 2 ' . $label . ' files to merge.'], 400);
        }

        $uploadedPaths = [];
        $firstExtension = null;
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                return response()->json(['error' => 'One or more uploaded files could not be read.'], 400);
            }

            if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
                return response()->json([
                    'error' => 'File "' . $file->getClientOriginalName() . '" is too large. Maximum allowed size is '
                        . (self::MAX_UPLOAD_BYTES / 1048576) . ' MB.',
                ], 422);
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, $allowed, true)) {
                return response()->json([
                    'error' => 'Merge ' . $label . ' only supports these formats: ' . strtoupper(implode(', ', $allowed)) . '.',
                ], 400);
            }
            $firstExtension ??= $extension;

            $originalName = $this->safeOriginalName($file->getClientOriginalName());
            $sourceId = Str::uuid()->toString();
            $uploadedPaths[] = $file->storeAs('uploads', "{$sourceId}_{$originalName}");
        }

        // Determine output: images merge into a PDF, video into MP4, audio keeps the first file's format.
        $outputExtension = match ($mergeType) {
            'pdf', 'image', 'document' => 'pdf',
            'video' => 'mp4',
            'audio' => $firstExtension ?: 'mp3',
            'spreadsheet' => 'xlsx',
            'presentation' => 'pptx',
        };

        $convertedId = str_replace('-', '', Str::uuid()->toString());
        $downloadName = $this->safeDownloadName($convertedId, $outputExtension);
        $convertedName = "merged-files.{$outputExtension}";
        $convertedPath = "converted/{$convertedId}_{$convertedName}";
        $destinationPath = Storage::path($convertedPath);
        $absoluteSources = array_map(fn (string $path): string => Storage::path($path), $uploadedPaths);

        try {
            match ($mergeType) {
                'pdf' => $this->mergePdfFiles($absoluteSources, $destinationPath),
                'image' => $conversionService->mergeImagesToPdf($absoluteSources, $destinationPath),
                'audio' => $conversionService->mergeAudio($absoluteSources, $destinationPath),
                'video' => $conversionService->mergeVideo($absoluteSources, $destinationPath),
                'document' => $conversionService->mergeDocumentsToPdf($absoluteSources, $destinationPath),
                'spreadsheet' => $conversionService->mergeSpreadsheets($absoluteSources, $destinationPath),
                'presentation' => $conversionService->mergePresentationsToPptx($absoluteSources, $destinationPath),
            };
            clearstatcache(true, $destinationPath);
            if (! is_file($destinationPath) || filesize($destinationPath) <= 0) {
                Storage::delete($convertedPath);
                throw new \RuntimeException('Merge produced an empty file.');
            }
        } catch (\Throwable $exception) {
            Storage::delete($uploadedPaths);
            Storage::delete($convertedPath);
            return response()->json(['error' => $exception->getMessage() ?: 'Merge failed.'], 400);
        }

        Storage::delete($uploadedPaths);

        $publicPath = $this->publishConvertedFile($destinationPath, $convertedId, $downloadName);
        $downloadUrl = route('download', ['fileId' => $convertedId], false) . '?v=' . filesize($destinationPath);

        Log::info('Merge completed.', [
            'id' => $convertedId,
            'merge_type' => $mergeType,
            'file_count' => count($files),
            'storage_path' => $destinationPath,
            'public_path' => $publicPath,
            'download_url' => $downloadUrl,
            'size' => filesize($destinationPath),
        ]);

        $describe = count($files) . ' ' . $label . ' files';
        $converted = [
            'id' => $convertedId,
            'client_id' => null,
            'original_name' => $describe,
            'converted_name' => $downloadName,
            'download_url' => $downloadUrl,
            'inline_data' => $this->inlineDownloadData($destinationPath),
            'mime_type' => $this->mimeType($destinationPath, $downloadName),
        ];

        $historyService->add($request->session()->get('auth_user'), [
            'id' => $convertedId,
            'original_name' => $describe,
            'converted_name' => $downloadName,
            'source_format' => $label,
            'target_format' => strtoupper($outputExtension),
            'size' => filesize($destinationPath),
            'download_url' => $downloadUrl,
        ]);

        return response()->json(['files' => [$converted]]);
    }

    public function download(string $fileId): BinaryFileResponse|JsonResponse
    {
        $path = $this->convertedPath($fileId);
        if (! $path) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        $absolutePath = Storage::path($path);
        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            Storage::delete($path);
            return response()->json(['error' => 'Converted file is empty. Please convert it again.'], 410);
        }

        $downloadName = $this->safeDownloadName($fileId, pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = $this->mimeType($absolutePath, $downloadName);
        return response()->download($absolutePath, $downloadName, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadAll(Request $request): BinaryFileResponse|JsonResponse
    {
        $fileIds = $request->input('file_ids', []);
        if (! is_array($fileIds) || count($fileIds) === 0) {
            return response()->json(['error' => 'No files selected for download.'], 400);
        }

        $zipName = 'converted-files-' . Str::random(10) . '.zip';
        $zipPath = Storage::path("converted/{$zipName}");
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($fileIds as $fileId) {
            $path = $this->convertedPath((string) $fileId);
            if (! $path) {
                $zip->close();
                @unlink($zipPath);
                return response()->json(['error' => 'One or more converted files were not found.'], 404);
            }

            $absolutePath = Storage::path($path);
            if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
                $zip->close();
                @unlink($zipPath);
                return response()->json(['error' => 'One or more converted files were empty. Please convert them again.'], 410);
            }

            $zip->addFile($absolutePath, $this->downloadName($path));
        }
        $zip->close();

        return response()->download($zipPath, 'converted-files.zip')->deleteFileAfterSend();
    }

    private function convertedPath(string $fileId): ?string
    {
        if (! preg_match('/^[a-f0-9]{32}$/i', $fileId)) {
            return null;
        }

        $matches = Storage::files('converted');
        foreach ($matches as $path) {
            if (str_starts_with(basename($path), "{$fileId}_")) {
                return $path;
            }
        }

        return null;
    }

    private function downloadName(string $path): string
    {
        $basename = basename($path);
        $name = str_contains($basename, '_') ? explode('_', $basename, 2)[1] : $basename;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME) ?: 'converted-file';
        $stem = preg_replace('/[^A-Za-z0-9_-]/', '_', $stem) ?: 'converted-file';
        $stem = preg_replace('/_+/', '_', $stem) ?: 'converted-file';
        $stem = trim($stem, '_-') ?: 'converted-file';

        return $extension !== '' ? "{$stem}.{$extension}" : $stem;
    }

    private function mergePdfFiles(array $sourcePaths, string $destinationPath): void
    {
        $pdf = new Fpdi();

        foreach ($sourcePaths as $sourcePath) {
            $pageCount = $pdf->setSourceFile($sourcePath);
            for ($page = 1; $page <= $pageCount; $page++) {
                $templateId = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output($destinationPath, 'F');
    }

    private function safeDownloadName(string $convertedId, string $extension): string
    {
        $extension = preg_replace('/[^a-z0-9]/i', '', strtolower($extension)) ?: 'bin';

        return "converted-{$convertedId}.{$extension}";
    }

    private function publishConvertedFile(string $sourcePath, string $convertedId, string $downloadName): string
    {
        $directory = public_path('converted-downloads');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $extension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
        $publicName = $extension !== '' ? "{$convertedId}.{$extension}" : $convertedId;
        $publicPath = $directory . DIRECTORY_SEPARATOR . $publicName;
        if (! copy($sourcePath, $publicPath)) {
            throw new \RuntimeException('Converted file could not be prepared for download.');
        }
        clearstatcache(true, $publicPath);
        if (! is_file($publicPath) || filesize($publicPath) !== filesize($sourcePath)) {
            @unlink($publicPath);
            throw new \RuntimeException('Converted file could not be verified for download.');
        }

        return $publicPath;
    }

    private function mimeType(string $absolutePath, string $downloadName): string
    {
        $extension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
        $known = [
            'mp4' => 'video/mp4',
            'mkv' => 'video/x-matroska',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            '3gp' => 'video/3gpp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];

        return $known[$extension] ?? (mime_content_type($absolutePath) ?: 'application/octet-stream');
    }

    private function inlineDownloadData(string $absolutePath): ?string
    {
        clearstatcache(true, $absolutePath);
        if (! is_file($absolutePath) || filesize($absolutePath) > 25 * 1024 * 1024) {
            return null;
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            return null;
        }

        return base64_encode($contents);
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'uploaded_file';
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME) ?: 'uploaded_file';
        $stem = preg_replace('/_+/', '_', $stem) ?: 'uploaded_file';
        $stem = trim($stem, '._-') ?: 'uploaded_file';
        $stem = Str::limit($stem, 100, '');

        return $extension !== '' ? "{$stem}.{$extension}" : $stem;
    }
}
