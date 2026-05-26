<?php

namespace App\Services;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlSpreadsheetWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\ODText;
use PhpOffice\PhpWord\Writer\RTF;
use RuntimeException;
use Symfony\Component\Process\Process;

class ConversionService
{
    private array $textSources = ['txt', 'htm', 'html', 'json', 'xml', 'csv', 'md', 'rst', 'tex', 'fb2', 'svg', 'doc', 'docx', 'xls', 'xlsx', 'xlsm', 'ods'];
    // Every text/document format the engine can emit from extracted text. Used both for the
    // advertised map and for routing inside convert(); keep the two in sync.
    private array $documentTextTargets = ['pdf', 'txt', 'rtf', 'odt', 'html', 'doc', 'docx', 'csv', 'xml', 'xls', 'xlsx', 'epub', 'ppt', 'pptx', 'mobi'];
    private array $documentImageTargets = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff'];
    private array $spreadsheetSources = ['xls', 'xlsx', 'xlsm', 'ods'];
    private array $presentationSources = ['pptx'];
    private array $presentationTargets = ['pdf', 'docx', 'jpg', 'png', 'gif', 'tiff', 'mp4', 'wmv', 'ppt', 'odp', 'html'];
    private array $csvTargets = ['xls', 'xlsx'];
    private array $audioSources = ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'wma'];
    private array $videoSources = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', '3gp', 'm4v'];
    private array $audioTargets = ['mp3', 'wav', 'ogg', 'aac', 'flac', 'wma'];
    private array $videoTargets = ['avi', 'mkv', 'mov', 'mp4', 'webm', 'wmv', 'flv', '3gp', 'm4v'];
    private array $frameTargets = ['jpg', 'jpeg', 'png', 'webp'];
    private array $imageSources = ['avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp'];
    private array $imageTargets = ['avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp', 'tif', 'tiff'];

    /** @var array<string, bool> Cache of executable availability lookups. */
    private array $executableCache = [];
    private array $fontSources = ['ttf', 'otf', 'woff', 'woff2'];
    private array $archiveFormats = [
        '7z', 'ace', 'alz', 'arc', 'arj', 'bz', 'bz2', 'cab', 'cpio', 'deb', 'dmg',
        'gz', 'img', 'iso', 'jar', 'lha', 'lz', 'lzma', 'lzo', 'rar', 'rpm', 'rz',
        'tar', 'tar.7z', 'tar.bz', 'tar.bz2', 'tar.gz', 'tar.lzo', 'tar.xz', 'tar.z',
        'tbz', 'tbz2', 'tgz', 'tz', 'tzo', 'xz', 'z', 'zip',
    ];

    public function supportedConversionMap(): array
    {
        $map = [];

        // Document / text formats: advertise every target the text engine can emit.
        // MOBI only when Calibre's ebook-convert is available.
        $documentTargets = $this->documentTextTargets;
        if (! $this->hasExecutable('ebook-convert')) {
            $documentTargets = array_values(array_diff($documentTargets, ['mobi']));
        }
        $documentTargets = array_merge($documentTargets, $this->documentImageTargets);

        foreach ($this->textSources as $source) {
            $map[strtoupper($source)] = $this->normalizeTargets(array_diff($documentTargets, [$source]));
        }
        $map['PDF'] = $this->normalizeTargets(array_diff($documentTargets, ['pdf']));

        // Archives require 7-Zip.
        if ($this->has7z()) {
            foreach ($this->archiveFormats as $source) {
                $map[strtoupper($source)] = $this->normalizeTargets(array_diff($this->archiveFormats, [$source]));
            }
        }

        // Presentations. MP4/WMV video export needs FFmpeg.
        $presentationTargets = $this->presentationTargets;
        if (! $this->hasExecutable('ffmpeg')) {
            $presentationTargets = array_diff($presentationTargets, ['mp4', 'wmv']);
        }
        foreach ($this->presentationSources as $source) {
            $map[strtoupper($source)] = $this->normalizeTargets(array_diff($presentationTargets, [$source]));
        }

        // Audio & video require FFmpeg.
        if ($this->hasExecutable('ffmpeg')) {
            foreach ($this->audioSources as $source) {
                $map[strtoupper($source)] = $this->normalizeTargets(array_diff($this->audioTargets, [$source]));
            }
            foreach ($this->videoSources as $source) {
                $targets = array_diff(array_merge($this->audioTargets, $this->videoTargets, $this->frameTargets, ['gif']), [$source]);
                $map[strtoupper($source)] = $this->normalizeTargets($targets);
            }
        }

        // Images (GD).
        $imageTargets = $this->supportedImageTargets();
        foreach ($this->supportedImageSources() as $source) {
            $map[strtoupper($source)] = $this->normalizeTargets(array_diff($imageTargets, [$source]));
        }

        // Fonts require Python with fontTools.
        if ($this->hasExecutable('python') || $this->hasExecutable('py')) {
            foreach (['ttf', 'otf'] as $source) {
                $map[strtoupper($source)] = ['WOFF', 'WOFF2'];
            }
            $map['WOFF'] = ['TTF', 'WOFF2'];
            $map['WOFF2'] = ['TTF', 'WOFF'];
        }

        ksort($map);

        return $map;
    }

    private function normalizeTargets(array $targets): array
    {
        $targets = array_values(array_unique(array_map('strtoupper', $targets)));
        sort($targets);

        return $targets;
    }

    private function hasExecutable(string $name): bool
    {
        if (! array_key_exists($name, $this->executableCache)) {
            $this->executableCache[$name] = $this->findExecutable($name) !== null;
        }

        return $this->executableCache[$name];
    }

    private function has7z(): bool
    {
        return $this->hasExecutable('7z') || $this->hasExecutable('7za');
    }

    public function supportSummary(array $formatGroups): array
    {
        $map = $this->supportedConversionMap();
        $sources = array_keys($map);
        $targets = array_values(array_unique(array_merge(...array_values($map))));
        $supportedAnywhere = array_unique(array_merge($sources, $targets));
        $supportedSourcesByGroup = [];
        $unsupportedSourcesByGroup = [];
        $unsupportedFormatsByGroup = [];

        foreach ($formatGroups as $group) {
            $name = $group['name'];
            $formats = $group['formats'];
            $supportedSourcesByGroup[$name] = array_values(array_intersect($formats, $sources));
            $unsupportedSourcesByGroup[$name] = array_values(array_diff($formats, $sources));
            $unsupportedFormatsByGroup[$name] = array_values(array_diff($formats, $supportedAnywhere));
        }

        return [
            'supported_source_count' => count($sources),
            'supported_target_count' => count($targets),
            'listed_format_count' => array_sum(array_map(fn ($group) => count($group['formats']), $formatGroups)),
            'supported_sources_by_group' => array_filter($supportedSourcesByGroup),
            'unsupported_sources_by_group' => array_filter($unsupportedSourcesByGroup),
            'unsupported_formats_by_group' => array_filter($unsupportedFormatsByGroup),
        ];
    }

    public function convert(string $sourcePath, string $targetFormat, string $destinationPath): void
    {
        $target = strtolower($targetFormat);
        $source = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $map = $this->supportedConversionMap();
        if (! in_array(strtoupper($target), $map[strtoupper($source)] ?? [], true)) {
            throw new RuntimeException(strtoupper($source) . ' to ' . strtoupper($target) . ' conversion is not supported yet.');
        }

        if ($source === 'pdf' && in_array($target, $this->documentImageTargets, true)) {
            $this->convertTextToImage($sourcePath, $target, $destinationPath);
            return;
        }
        if ($source === 'pdf' && in_array($target, $this->documentTextTargets, true)) {
            $this->convertTextDocument($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->presentationSources, true) && in_array($target, $this->presentationTargets, true)) {
            $this->convertPresentationDocument($sourcePath, $target, $destinationPath);
            return;
        }
        // CSV keeps its real column layout when going to a spreadsheet.
        if ($source === 'csv' && in_array($target, $this->csvTargets, true)) {
            $this->convertCsv($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->textSources, true) && in_array($target, $this->documentImageTargets, true)) {
            $this->convertTextToImage($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->textSources, true) && in_array($target, $this->documentTextTargets, true)) {
            $this->convertTextDocument($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->audioSources, true) || in_array($source, $this->videoSources, true)) {
            $this->convertWithFfmpeg($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->archiveFormats, true) && in_array($target, $this->archiveFormats, true)) {
            $this->convertArchive($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->imageSources, true) && in_array($target, $this->imageTargets, true)) {
            $this->convertImage($sourcePath, $target, $destinationPath);
            return;
        }
        if (in_array($source, $this->fontSources, true) && in_array($target, ['ttf', 'otf', 'woff', 'woff2'], true)) {
            $this->convertFontFile($sourcePath, $target, $destinationPath);
            return;
        }

        throw new RuntimeException('Conversion failed for this file type.');
    }

    /**
     * Join two or more audio files into one. Output format follows the first file's extension.
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergeAudio(array $sourcePaths, string $destinationPath): void
    {
        $ffmpeg = $this->findExecutable('ffmpeg');
        if (! $ffmpeg) {
            throw new RuntimeException('Audio merging requires FFmpeg on PATH.');
        }

        $target = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION)) ?: 'mp3';
        $count = count($sourcePaths);

        $command = [$ffmpeg, '-y'];
        foreach ($sourcePaths as $path) {
            array_push($command, '-i', $path);
        }

        // Normalise every input to a common sample rate / layout before concatenating.
        $filter = '';
        $labels = '';
        foreach (range(0, $count - 1) as $index) {
            $filter .= "[{$index}:a]aresample=44100,aformat=channel_layouts=stereo[a{$index}];";
            $labels .= "[a{$index}]";
        }
        $filter .= $labels . 'concat=n=' . $count . ':v=0:a=1[out]';

        array_push($command, '-filter_complex', $filter, '-map', '[out]');
        $command = array_merge($command, $this->audioEncoderArgs($target));
        $command[] = $destinationPath;

        $this->runProcess($command, $destinationPath, 'Audio merge failed.');
    }

    /**
     * Join two or more video files into one. Inputs are normalised to a common
     * resolution/framerate (and silent audio is added where missing) so any mix works.
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergeVideo(array $sourcePaths, string $destinationPath): void
    {
        $ffmpeg = $this->findExecutable('ffmpeg');
        if (! $ffmpeg) {
            throw new RuntimeException('Video merging requires FFmpeg on PATH.');
        }

        $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flux-merge-' . bin2hex(random_bytes(8));
        if (! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Video merge failed.');
        }

        try {
            $segments = [];
            $scale = 'scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,setsar=1';

            foreach ($sourcePaths as $index => $path) {
                $segment = $workDir . DIRECTORY_SEPARATOR . "seg{$index}.ts";

                if ($this->mediaHasAudio($path)) {
                    $command = [
                        $ffmpeg, '-y', '-i', $path,
                        '-vf', $scale, '-r', '30',
                        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                        '-c:a', 'aac', '-ar', '48000', '-ac', '2',
                        '-f', 'mpegts', $segment,
                    ];
                } else {
                    $command = [
                        $ffmpeg, '-y', '-i', $path,
                        '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=48000',
                        '-map', '0:v:0', '-map', '1:a',
                        '-vf', $scale, '-r', '30',
                        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                        '-c:a', 'aac', '-shortest',
                        '-f', 'mpegts', $segment,
                    ];
                }

                $this->runProcess($command, $segment, 'Video merge failed while preparing a clip.');
                $segments[] = $segment;
            }

            $concat = 'concat:' . implode('|', $segments);
            $this->runProcess(
                [$ffmpeg, '-y', '-i', $concat, '-c', 'copy', '-bsf:a', 'aac_adtstoasc', $destinationPath],
                $destinationPath,
                'Video merge failed.'
            );
            $this->validateMediaFile($destinationPath);
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * Combine two or more images into a single multi-page PDF (one image per page).
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergeImagesToPdf(array $sourcePaths, string $destinationPath): void
    {
        $pages = '';
        foreach ($sourcePaths as $path) {
            $data = @file_get_contents($path);
            if ($data === false || $data === '') {
                continue;
            }
            $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
            $base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
            $pages .= '<div style="page-break-after:always;text-align:center;">'
                . '<img src="' . $base64 . '" style="max-width:100%;max-height:100%;"/></div>';
        }

        if ($pages === '') {
            throw new RuntimeException('No readable images to merge.');
        }

        $dompdf = new Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml('<html><body>' . $pages . '</body></html>');
        $dompdf->render();
        file_put_contents($destinationPath, $dompdf->output());
    }

    /**
     * Combine two or more text documents (txt, doc, docx, html, md, pdf …) into one PDF,
     * each source starting on a new page.
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergeDocumentsToPdf(array $sourcePaths, string $destinationPath): void
    {
        $html = '<html><body style="font-family:Arial,sans-serif;">';
        $any = false;
        foreach ($sourcePaths as $path) {
            $text = trim($this->extractText($path));
            if ($text === '') {
                continue;
            }
            $any = true;
            $html .= '<div style="page-break-after:always;">'
                . '<pre style="white-space:pre-wrap;font-size:13px;line-height:1.45;">' . e($text) . '</pre></div>';
        }
        $html .= '</body></html>';

        if (! $any) {
            throw new RuntimeException('No readable document text to merge.');
        }

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        file_put_contents($destinationPath, $dompdf->output());
    }

    /**
     * Combine two or more spreadsheets (xlsx, xls, csv, ods) into one workbook,
     * each source file (and its sheets) appended as separate worksheets.
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergeSpreadsheets(array $sourcePaths, string $destinationPath): void
    {
        $output = new Spreadsheet();
        $first = true;
        $usedTitles = [];

        foreach ($sourcePaths as $index => $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $sheets = [];

            if ($extension === 'csv') {
                $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), file($path) ?: []);
                $sheets[] = ['File ' . ($index + 1), $rows];
            } else {
                $input = SpreadsheetIOFactory::load($path);
                foreach ($input->getWorksheetIterator() as $worksheet) {
                    $sheets[] = [$worksheet->getTitle(), $worksheet->toArray(null, true, true, false)];
                }
                $input->disconnectWorksheets();
            }

            foreach ($sheets as [$title, $matrix]) {
                $sheet = $first ? $output->getActiveSheet() : $output->createSheet();
                $first = false;

                $title = $this->uniqueSheetTitle((string) $title, $usedTitles);
                $sheet->setTitle($title);

                foreach ($matrix as $rowIndex => $row) {
                    foreach ($row as $columnIndex => $value) {
                        if ($value !== null && $value !== '') {
                            $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
                        }
                    }
                }
            }
        }

        (new Xlsx($output))->save($destinationPath);
        $output->disconnectWorksheets();
    }

    /**
     * Combine two or more presentations (pptx) into one, concatenating their slide text.
     *
     * @param array<int, string> $sourcePaths
     */
    public function mergePresentationsToPptx(array $sourcePaths, string $destinationPath): void
    {
        $texts = [];
        foreach ($sourcePaths as $path) {
            $text = trim($this->extractText($path));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        if ($texts === []) {
            throw new RuntimeException('No readable slide text to merge.');
        }

        $this->convertTextToPptx(implode("\n\n", $texts), $destinationPath);
    }

    private function uniqueSheetTitle(string $title, array &$used): string
    {
        // Excel sheet titles: max 31 chars, no []*/\?: characters, must be unique.
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title) ?: 'Sheet';
        $title = trim(substr($title, 0, 31)) ?: 'Sheet';
        $base = $title;
        $n = 1;
        while (in_array(strtolower($title), $used, true)) {
            $suffix = ' (' . (++$n) . ')';
            $title = substr($base, 0, 31 - strlen($suffix)) . $suffix;
        }
        $used[] = strtolower($title);

        return $title;
    }

    private function audioEncoderArgs(string $target): array
    {
        return match ($target) {
            'mp3' => ['-codec:a', 'libmp3lame', '-q:a', '2'],
            'wav' => ['-codec:a', 'pcm_s16le'],
            'ogg' => ['-codec:a', 'libvorbis', '-q:a', '4'],
            'aac', 'm4a' => ['-codec:a', 'aac', '-b:a', '192k'],
            'flac' => ['-codec:a', 'flac'],
            'wma' => ['-codec:a', 'wmav2', '-b:a', '192k'],
            default => ['-codec:a', 'libmp3lame', '-q:a', '2'],
        };
    }

    private function mediaHasAudio(string $path): bool
    {
        $ffprobe = $this->findExecutable('ffprobe');
        if (! $ffprobe) {
            return true; // Assume audio is present when we cannot probe.
        }

        $process = new Process([
            $ffprobe, '-v', 'error', '-select_streams', 'a',
            '-show_entries', 'stream=index', '-of', 'csv=p=0', $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        return trim($process->getOutput()) !== '';
    }

    private function convertArchive(string $sourcePath, string $target, string $destinationPath): void
    {
        $sevenZip = $this->findExecutable('7z') ?: $this->findExecutable('7za');
        if (! $sevenZip) {
            throw new RuntimeException('Archive conversion requires 7-Zip. Install 7-Zip and restart php artisan serve.');
        }

        $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flux-archive-' . bin2hex(random_bytes(8));
        $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';
        $archivePath = $workDir . DIRECTORY_SEPARATOR . 'converted.' . $target;
        if (! mkdir($extractDir, 0775, true) && ! is_dir($extractDir)) {
            throw new RuntimeException('Archive conversion failed.');
        }

        try {
            $this->runBasicProcess([$sevenZip, 'x', '-y', '-o' . $extractDir, $sourcePath], 'Archive extraction failed.');
            $this->runProcess([$sevenZip, 'a', '-y', $archivePath, $extractDir . DIRECTORY_SEPARATOR . '*'], $archivePath, 'Archive conversion failed.');
            if (! is_file($archivePath) || filesize($archivePath) <= 0) {
                throw new RuntimeException('Archive conversion produced an empty file.');
            }
            if (! copy($archivePath, $destinationPath)) {
                throw new RuntimeException('Archive conversion failed.');
            }
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function convertTextDocument(string $sourcePath, string $target, string $destinationPath): void
    {
        $text = $this->extractText($sourcePath);
        if ($text === '') {
            $source = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            if ($source === 'pdf') {
                throw new RuntimeException('This PDF does not contain selectable text. It looks scanned or image-only, so text export needs OCR.');
            }

            throw new RuntimeException('This file did not contain readable text.');
        }

        if ($target === 'txt') {
            file_put_contents($destinationPath, $text);
        } elseif ($target === 'html' || $target === 'doc') {
            file_put_contents($destinationPath, '<html><body>' . nl2br(e($text)) . '</body></html>');
        } elseif ($target === 'pdf') {
            $dompdf = new Dompdf();
            $dompdf->loadHtml('<html><body>' . nl2br(e($text)) . '</body></html>');
            $dompdf->render();
            file_put_contents($destinationPath, $dompdf->output());
        } elseif (in_array($target, ['csv', 'xml'], true)) {
            $this->convertTextToStructuredData($text, $target, $destinationPath);
        } elseif (in_array($target, ['xls', 'xlsx'], true)) {
            $this->convertTextToSpreadsheet($text, $target, $destinationPath);
        } elseif ($target === 'ppt') {
            $this->convertTextToHtmlPresentation($text, $destinationPath);
        } elseif ($target === 'pptx') {
            $this->convertTextToPptx($text, $destinationPath);
        } elseif ($target === 'epub') {
            $this->convertTextToEpub($text, $destinationPath);
        } elseif ($target === 'mobi') {
            $this->convertTextToMobi($text, $destinationPath);
        } elseif (in_array($target, ['docx', 'odt', 'rtf'], true)) {
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();
            foreach (preg_split('/\R/', $text) ?: [''] as $line) {
                $section->addText($line);
            }
            $writer = match ($target) {
                'docx' => \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007'),
                'odt' => new ODText($phpWord),
                'rtf' => new RTF($phpWord),
            };
            $writer->save($destinationPath);
        }
    }

    private function convertPresentationDocument(string $sourcePath, string $target, string $destinationPath): void
    {
        $text = $this->extractText($sourcePath);
        if ($text === '') {
            throw new RuntimeException('This presentation did not contain readable slide text.');
        }

        if (in_array($target, ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff'], true)) {
            $this->convertTextToImageText($text, $target, $destinationPath);
            return;
        }

        if (in_array($target, ['mp4', 'wmv'], true)) {
            $this->convertTextToVideo($text, $target, $destinationPath);
            return;
        }

        if ($target === 'odp') {
            $this->convertTextToOdp($text, $destinationPath);
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'flux-presentation-');
        if ($tempPath === false) {
            throw new RuntimeException('Presentation conversion failed.');
        }

        try {
            file_put_contents($tempPath, $text);
            $this->convertTextDocument($tempPath, $target, $destinationPath);
        } finally {
            @unlink($tempPath);
        }
    }

    private function convertTextToImageText(string $text, string $target, string $destinationPath): void
    {
        $image = $this->createTextImage($text);
        $this->saveGdImage($image, $target, $destinationPath);
        imagedestroy($image);
    }

    private function convertTextToStructuredData(string $text, string $target, string $destinationPath): void
    {
        $lines = $this->textLines($text);
        if ($target === 'csv') {
            $handle = fopen($destinationPath, 'wb');
            if (! $handle) {
                throw new RuntimeException('CSV conversion failed.');
            }
            foreach ($lines as $index => $line) {
                fputcsv($handle, [$index + 1, $line]);
            }
            fclose($handle);
            return;
        }

        $xml = new \SimpleXMLElement('<document/>');
        foreach ($lines as $index => $line) {
            $entry = $xml->addChild('line', htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $entry->addAttribute('number', (string) ($index + 1));
        }
        $xml->asXML($destinationPath);
    }

    private function convertTextToSpreadsheet(string $text, string $target, string $destinationPath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Line');
        $sheet->setCellValue('B1', 'Text');

        foreach ($this->textLines($text) as $index => $line) {
            $row = $index + 2;
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $line);
        }
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setWidth(100);

        $writer = $target === 'xlsx' ? new Xlsx($spreadsheet) : new Xls($spreadsheet);
        $writer->save($destinationPath);
        $spreadsheet->disconnectWorksheets();
    }

    private function convertTextToHtmlPresentation(string $text, string $destinationPath): void
    {
        $slides = $this->textSlides($text);
        $html = '<html><head><meta charset="UTF-8"><title>Converted PDF</title></head><body>';
        foreach ($slides as $slide) {
            $html .= '<section style="page-break-after:always;font-family:Arial,sans-serif;padding:48px;">';
            $html .= '<h1 style="font-size:28px;">Converted PDF</h1>';
            $html .= '<pre style="white-space:pre-wrap;font-size:18px;line-height:1.45;">' . e($slide) . '</pre>';
            $html .= '</section>';
        }
        $html .= '</body></html>';
        file_put_contents($destinationPath, $html);
    }

    private function convertTextToPptx(string $text, string $destinationPath): void
    {
        $slides = $this->textSlides($text);
        $zip = new \ZipArchive();
        if ($zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('PPTX conversion failed.');
        }

        $slideCount = count($slides);
        $zip->addFromString('[Content_Types].xml', $this->pptxContentTypes($slideCount));
        $zip->addFromString('_rels/.rels', $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/presentation.xml', $this->pptxPresentation($slideCount));
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $this->pptxPresentationRels($slideCount));
        $zip->addFromString('ppt/slideMasters/slideMaster1.xml', $this->pptxSlideMaster());
        $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
        $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $this->pptxSlideLayout());
        $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');
        $zip->addFromString('ppt/theme/theme1.xml', $this->pptxTheme());

        foreach ($slides as $index => $slide) {
            $number = $index + 1;
            $zip->addFromString("ppt/slides/slide{$number}.xml", $this->pptxSlide($slide));
            $zip->addFromString("ppt/slides/_rels/slide{$number}.xml.rels", $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
        }

        $zip->close();
    }

    private function convertTextToEpub(string $text, string $destinationPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('EPUB conversion failed.');
        }

        $chapter = '<!doctype html><html xmlns="http://www.w3.org/1999/xhtml"><head><title>Converted PDF</title></head><body><h1>Converted PDF</h1><pre style="white-space:pre-wrap;">' . e($text) . '</pre></body></html>';
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', $this->xmlHeader() . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OEBPS/content.opf', $this->xmlHeader() . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="bookid">converted-pdf</dc:identifier><dc:title>Converted PDF</dc:title><dc:language>en</dc:language></metadata><manifest><item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="chapter1"/></spine></package>');
        $zip->addFromString('OEBPS/chapter1.xhtml', $chapter);
        $zip->close();
    }

    private function convertTextToMobi(string $text, string $destinationPath): void
    {
        $ebookConvert = $this->findExecutable('ebook-convert');
        if (! $ebookConvert) {
            throw new RuntimeException('MOBI conversion requires Calibre ebook-convert. Install Calibre, then restart php artisan serve.');
        }

        $epubPath = tempnam(sys_get_temp_dir(), 'flux-epub-');
        if ($epubPath === false) {
            throw new RuntimeException('MOBI conversion failed.');
        }

        $epubPath .= '.epub';
        try {
            $this->convertTextToEpub($text, $epubPath);
            $this->runProcess([$ebookConvert, $epubPath, $destinationPath], $destinationPath, 'MOBI conversion failed.');
        } finally {
            @unlink($epubPath);
        }
    }

    private function convertTextToOdp(string $text, string $destinationPath): void
    {
        $safeText = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $zip = new \ZipArchive();
        if ($zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ODP conversion failed.');
        }

        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.presentation');
        $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.presentation"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/></manifest:manifest>');
        $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" office:version="1.2"><office:body><office:presentation><presentation:page presentation:name="Slide 1"><text:p>' . $safeText . '</text:p></presentation:page></office:presentation></office:body></office:document-content>');
        $zip->close();
    }

    private function textLines(string $text): array
    {
        $lines = array_map('trim', preg_split('/\R/', $text) ?: []);
        $lines = array_values(array_filter($lines, fn (string $line) => $line !== ''));

        return $lines ?: [''];
    }

    private function textSlides(string $text): array
    {
        $wrapped = wordwrap($text, 80);
        $chunks = str_split($wrapped, 900);
        $slides = array_values(array_filter(array_map('trim', $chunks)));

        return $slides ?: [''];
    }

    private function xmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    }

    private function pptxContentTypes(int $slideCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }

        return $this->xmlHeader() . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' . $overrides . '</Types>';
    }

    private function pptxPresentation(int $slideCount): string
    {
        $ids = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $ids .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . $i . '"/>';
        }

        return $this->xmlHeader() . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId' . ($slideCount + 1) . '"/></p:sldMasterIdLst><p:sldIdLst>' . $ids . '</p:sldIdLst><p:sldSz cx="9144000" cy="6858000" type="screen4x3"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>';
    }

    private function pptxPresentationRels(int $slideCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($slideCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
        $rels .= '<Relationship Id="rId' . ($slideCount + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>';

        return $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function pptxSlide(string $text): string
    {
        $safeText = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return $this->xmlHeader() . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr><p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="274638"/><a:ext cx="8229600" cy="640080"/></a:xfrm></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US" sz="3200" b="1"/><a:t>Converted PDF</a:t></a:r></a:p></p:txBody></p:sp><p:sp><p:nvSpPr><p:cNvPr id="3" name="Content"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="body"/></p:nvPr></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="1097280"/><a:ext cx="8229600" cy="5029200"/></a:xfrm></p:spPr><p:txBody><a:bodyPr wrap="square"/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US" sz="1800"/><a:t>' . $safeText . '</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    private function pptxSlideMaster(): string
    {
        return $this->xmlHeader() . '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:effectLst/></p:bgPr></p:bg><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles></p:sldMaster>';
    }

    private function pptxSlideLayout(): string
    {
        return $this->xmlHeader() . '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="titleAndObj" preserve="1"><p:cSld name="Title and Content"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>';
    }

    private function pptxTheme(): string
    {
        return $this->xmlHeader() . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="FluxConvert"><a:themeElements><a:clrScheme name="Office"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F8FAFC"/></a:lt2><a:accent1><a:srgbClr val="2563EB"/></a:accent1><a:accent2><a:srgbClr val="0F766E"/></a:accent2><a:accent3><a:srgbClr val="D97706"/></a:accent3><a:accent4><a:srgbClr val="DC2626"/></a:accent4><a:accent5><a:srgbClr val="7C3AED"/></a:accent5><a:accent6><a:srgbClr val="0891B2"/></a:accent6><a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme><a:fontScheme name="Office"><a:majorFont><a:latin typeface="Arial"/></a:majorFont><a:minorFont><a:latin typeface="Arial"/></a:minorFont></a:fontScheme><a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>';
    }

    private function convertCsv(string $sourcePath, string $target, string $destinationPath): void
    {
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), file($sourcePath) ?: []);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
            }
        }

        $writer = match ($target) {
            'xlsx' => new Xlsx($spreadsheet),
            'xls' => new Xls($spreadsheet),
            default => new HtmlSpreadsheetWriter($spreadsheet),
        };
        $writer->save($destinationPath);
        $spreadsheet->disconnectWorksheets();
    }

    private function convertTextToImage(string $sourcePath, string $target, string $destinationPath): void
    {
        $image = $this->createTextImage($this->extractText($sourcePath));
        $this->saveGdImage($image, $target, $destinationPath);
        imagedestroy($image);
    }

    private function createTextImage(string $text): \GdImage
    {
        $text = wordwrap($text, 90);
        $image = imagecreatetruecolor(1200, 900);
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 24, 33, 43);
        imagefill($image, 0, 0, $white);
        imagestring($image, 3, 36, 36, substr($text, 0, 5000), $dark);

        return $image;
    }

    private function convertImage(string $sourcePath, string $target, string $destinationPath): void
    {
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        if (! $image) {
            throw new RuntimeException('Image conversion failed.');
        }
        $this->saveGdImage($image, $target, $destinationPath);
        imagedestroy($image);
    }

    private function convertPdfToImage(string $sourcePath, string $target, string $destinationPath): void
    {
        if (! class_exists(\Imagick::class)) {
            throw new RuntimeException('PDF to image conversion requires the PHP Imagick extension and Ghostscript.');
        }

        $image = new \Imagick();
        $image->setResolution(150, 150);
        $image->readImage($sourcePath . '[0]');
        $image->setImageBackgroundColor('white');
        $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $image->setImageFormat($target === 'jpg' ? 'jpeg' : $target);
        if (in_array($target, ['jpg', 'jpeg', 'webp'], true)) {
            $image->setImageCompressionQuality(90);
        }
        $image->writeImage($destinationPath);
        $image->clear();
        $image->destroy();
    }

    private function saveGdImage(\GdImage $image, string $target, string $destinationPath): void
    {
        $saved = match ($target) {
            'jpg', 'jpeg' => imagejpeg($image, $destinationPath, 95),
            'png' => imagepng($image, $destinationPath),
            'webp' => imagewebp($image, $destinationPath, 95),
            'gif' => imagegif($image, $destinationPath),
            'bmp' => function_exists('imagebmp') && imagebmp($image, $destinationPath),
            'avif' => function_exists('imageavif') && imageavif($image, $destinationPath, 90),
            'tif', 'tiff' => $this->saveTiffImage($image, $destinationPath),
            default => false,
        };

        if (! $saved) {
            throw new RuntimeException(strtoupper($target) . ' image export requires Imagick or extra PHP support.');
        }
    }

    private function saveTiffImage(\GdImage $image, string $destinationPath): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $pixelData = '';

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $pixelData .= chr(($rgb >> 16) & 0xFF) . chr(($rgb >> 8) & 0xFF) . chr($rgb & 0xFF);
            }
        }

        $ifdOffset = 8;
        $entries = [
            [256, 4, 1, $width],
            [257, 4, 1, $height],
            [258, 3, 3, 0],
            [259, 3, 1, 1],
            [262, 3, 1, 2],
            [273, 4, 1, 0],
            [277, 3, 1, 3],
            [278, 4, 1, $height],
            [279, 4, 1, strlen($pixelData)],
            [284, 3, 1, 1],
        ];

        $bitsOffset = $ifdOffset + 2 + count($entries) * 12 + 4;
        $dataOffset = $bitsOffset + 6;
        $entries[2][3] = $bitsOffset;
        $entries[5][3] = $dataOffset;

        $contents = 'II' . pack('v', 42) . pack('V', $ifdOffset);
        $contents .= pack('v', count($entries));
        foreach ($entries as [$tag, $type, $count, $value]) {
            $contents .= pack('vvV', $tag, $type, $count);
            $contents .= in_array($type, [3], true) && $count === 1
                ? pack('v', $value) . "\0\0"
                : pack('V', $value);
        }
        $contents .= pack('V', 0);
        $contents .= pack('vvv', 8, 8, 8);
        $contents .= $pixelData;

        return file_put_contents($destinationPath, $contents) !== false;
    }

    private function convertTextToVideo(string $text, string $target, string $destinationPath): void
    {
        $ffmpeg = $this->findExecutable('ffmpeg');
        if (! $ffmpeg) {
            throw new RuntimeException(strtoupper($target) . ' export requires FFmpeg on PATH.');
        }

        $imagePath = tempnam(sys_get_temp_dir(), 'flux-slide-');
        if ($imagePath === false) {
            throw new RuntimeException('Presentation video conversion failed.');
        }
        $imagePath .= '.png';

        try {
            $image = $this->createTextImage($text);
            imagepng($image, $imagePath);
            imagedestroy($image);

            $command = [$ffmpeg, '-y', '-loop', '1', '-i', $imagePath, '-t', '4', '-r', '24'];
            $command = array_merge($command, $target === 'mp4'
                ? ['-c:v', 'libx264', '-pix_fmt', 'yuv420p']
                : ['-c:v', 'wmv2', '-b:v', '1800k']
            );
            $command[] = $destinationPath;
            $this->runProcess($command, $destinationPath, strtoupper($target) . ' conversion failed.');
        } finally {
            @unlink($imagePath);
        }
    }

    private function supportedImageSources(): array
    {
        return array_values(array_filter($this->imageSources, fn (string $format) => in_array($format, $this->supportedImageTargets(), true)));
    }

    private function supportedImageTargets(): array
    {
        $targets = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff'];
        if (function_exists('imagewebp')) {
            $targets[] = 'webp';
        }
        if (function_exists('imagebmp')) {
            $targets[] = 'bmp';
        }
        if (function_exists('imageavif')) {
            $targets[] = 'avif';
        }

        return $targets;
    }

    private function convertWithFfmpeg(string $sourcePath, string $target, string $destinationPath): void
    {
        $ffmpeg = $this->findExecutable('ffmpeg');
        if (! $ffmpeg) {
            throw new RuntimeException('FFmpeg is not installed or not available on PATH.');
        }

        $source = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $command = [$ffmpeg, '-y', '-i', $sourcePath];

        if ($target === 'gif') {
            $command = [$ffmpeg, '-y', '-i', $sourcePath, '-vf', 'fps=12,scale=640:-1:flags=lanczos', '-loop', '0', $destinationPath];
        } elseif (in_array($target, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $command = [$ffmpeg, '-y', '-i', $sourcePath, '-frames:v', '1'];
            if (in_array($target, ['jpg', 'jpeg'], true)) {
                array_push($command, '-q:v', '2');
            } elseif ($target === 'webp') {
                array_push($command, '-q:v', '80');
            }
            $command[] = $destinationPath;
        } elseif (in_array($target, $this->videoTargets, true)) {
            if (! in_array($source, $this->videoSources, true)) {
                throw new RuntimeException(strtoupper($source) . ' to ' . strtoupper($target) . ' conversion is not supported yet.');
            }

            $command = array_merge($command, match ($target) {
                'mp4', 'mov', 'mkv', 'm4v' => ['-map', '0:v:0', '-map', '0:a?', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-c:a', 'aac', '-b:a', '160k'],
                'webm' => ['-map', '0:v:0', '-map', '0:a?', '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '34', '-c:a', 'libopus', '-b:a', '128k'],
                'avi' => ['-map', '0:v:0', '-map', '0:a?', '-c:v', 'mpeg4', '-q:v', '5', '-c:a', 'libmp3lame', '-q:a', '4'],
                'wmv' => ['-map', '0:v:0', '-map', '0:a?', '-c:v', 'wmv2', '-b:v', '1800k', '-c:a', 'wmav2', '-b:a', '160k'],
                'flv' => ['-map', '0:v:0', '-map', '0:a?', '-c:v', 'flv', '-q:v', '5', '-c:a', 'libmp3lame', '-q:a', '4'],
                '3gp' => ['-map', '0:v:0', '-map', '0:a?', '-vf', 'scale=352:288:force_original_aspect_ratio=decrease,pad=352:288:(ow-iw)/2:(oh-ih)/2,setsar=1', '-r', '15', '-c:v', 'h263', '-b:v', '384k', '-c:a', 'libopencore_amrnb', '-b:a', '12.2k', '-ar', '8000', '-ac', '1', '-f', '3gp'],
            });
            $command[] = $destinationPath;
        } else {
            $command = array_merge($command, match ($target) {
                'mp3' => ['-vn', '-codec:a', 'libmp3lame', '-q:a', '2'],
                'wav' => ['-vn', '-codec:a', 'pcm_s16le'],
                'ogg' => ['-vn', '-codec:a', 'libvorbis', '-q:a', '4'],
                'aac' => ['-vn', '-codec:a', 'aac', '-b:a', '192k'],
                'flac' => ['-vn', '-codec:a', 'flac'],
                'wma' => ['-vn', '-codec:a', 'wmav2', '-b:a', '192k'],
                default => throw new RuntimeException('Media target is not supported.'),
            });
            $command[] = $destinationPath;
        }

        $this->runProcess($command, $destinationPath, 'Media conversion failed.');
        $this->validateMediaFile($destinationPath);
    }

    private function findExecutable(string $name): ?string
    {
        $commands = PHP_OS_FAMILY === 'Windows'
            ? [
                ['where.exe', $name],
                ['powershell', '-NoProfile', '-Command', "(Get-Command {$name} -ErrorAction SilentlyContinue).Source"],
            ]
            : [['which', $name]];

        foreach ($commands as $command) {
            $process = new Process($command);
            $process->run();
            if (! $process->isSuccessful()) {
                continue;
            }

            $paths = array_values(array_filter(array_map('trim', preg_split('/\R/', $process->getOutput()) ?: [])));
            foreach ($paths as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $fallbacks = [
                "C:\\ffmpeg\\bin\\{$name}.exe",
                "D:\\ffmpeg\\bin\\{$name}.exe",
                // Standard installer locations for tools that rarely add themselves to PATH.
                "C:\\Program Files\\7-Zip\\{$name}.exe",
                "C:\\Program Files (x86)\\7-Zip\\{$name}.exe",
                "C:\\Program Files\\Calibre2\\{$name}.exe",
                "C:\\Program Files (x86)\\Calibre2\\{$name}.exe",
            ];
            foreach (glob("D:\\ffmpeg-*\\*\\bin\\{$name}.exe") ?: [] as $path) {
                $fallbacks[] = $path;
            }
            foreach (glob("C:\\ffmpeg-*\\*\\bin\\{$name}.exe") ?: [] as $path) {
                $fallbacks[] = $path;
            }
            // winget-installed builds (e.g. Gyan.FFmpeg, 7zip.7zip) live under the user's
            // WinGet Packages folder and are not always added to PATH.
            $localAppData = getenv('LOCALAPPDATA');
            if ($localAppData) {
                foreach (glob($localAppData . "\\Microsoft\\WinGet\\Packages\\*\\*\\bin\\{$name}.exe") ?: [] as $path) {
                    $fallbacks[] = $path;
                }
                foreach (glob($localAppData . "\\Microsoft\\WinGet\\Packages\\*\\{$name}.exe") ?: [] as $path) {
                    $fallbacks[] = $path;
                }
            }

            foreach ($fallbacks as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function runProcess(array $command, string $destinationPath, string $fallbackMessage): void
    {
        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        clearstatcache(true, $destinationPath);
        if (! $process->isSuccessful() || ! is_file($destinationPath) || filesize($destinationPath) <= 0) {
            @unlink($destinationPath);
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : $fallbackMessage);
        }
    }

    private function runBasicProcess(array $command, string $fallbackMessage): void
    {
        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : $fallbackMessage);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getPathname();
            $item->isDir() ? @rmdir($path) : @unlink($path);
        }

        @rmdir($directory);
    }

    private function validateMediaFile(string $destinationPath): void
    {
        $ffprobe = $this->findExecutable('ffprobe');
        if (! $ffprobe) {
            return;
        }

        $process = new Process([
            $ffprobe,
            '-v',
            'error',
            '-show_entries',
            'format=duration,size',
            '-of',
            'default=noprint_wrappers=1',
            $destinationPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
            @unlink($destinationPath);
            throw new RuntimeException('Media conversion created a file that could not be played. Please try another target format.');
        }
    }

    private function extractText(string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $raw = file_get_contents($sourcePath) ?: '';
        if (in_array($extension, ['html', 'htm', 'fb2', 'doc'], true)) {
            return trim(strip_tags($raw));
        }
        if ($extension === 'json') {
            return json_encode(json_decode($raw, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $raw;
        }
        if ($extension === 'csv') {
            $rows = array_map('str_getcsv', file($sourcePath) ?: []);
            return implode("\n", array_map(fn ($row) => implode("\t", $row), $rows));
        }
        if (in_array($extension, ['xls', 'xlsx', 'xlsm', 'ods'], true)) {
            return $this->extractSpreadsheetText($sourcePath);
        }
        if ($extension === 'docx') {
            return $this->extractDocxText($sourcePath);
        }
        if ($extension === 'pptx') {
            return $this->extractPptxText($sourcePath);
        }
        if ($extension === 'pdf') {
            return $this->extractPdfText($sourcePath);
        }
        return trim($raw);
    }

    private function extractDocxText(string $sourcePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('DOCX conversion failed.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('DOCX conversion failed.');
        }

        return $this->xmlText($xml);
    }

    private function extractPptxText(string $sourcePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('PPTX conversion failed.');
        }

        $slides = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $matches)) {
                $slides[(int) $matches[1]] = $zip->getFromName($name);
            }
        }
        $zip->close();

        ksort($slides);
        $text = [];
        foreach ($slides as $number => $xml) {
            if (! is_string($xml) || trim($xml) === '') {
                continue;
            }
            $slideText = $this->xmlText($xml);
            if ($slideText !== '') {
                $text[] = "Slide {$number}\n" . $slideText;
            }
        }

        return trim(implode("\n\n", $text));
    }

    private function extractPdfText(string $sourcePath): string
    {
        $pdftotext = trim((string) shell_exec('where pdftotext 2>NUL'));
        if ($pdftotext !== '') {
            $command = escapeshellarg($pdftotext) . ' -layout ' . escapeshellarg($sourcePath) . ' -';
            $text = shell_exec($command);
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $parser = new \Smalot\PdfParser\Parser();
            $text = trim($parser->parseFile($sourcePath)->getText());
            if ($text !== '') {
                return $text;
            }

            throw new RuntimeException('This PDF does not contain selectable text. It looks scanned or image-only, so text export needs OCR.');
        }

        throw new RuntimeException('PDF text conversion requires Poppler pdftotext or smalot/pdfparser.');
    }

    private function xmlText(string $content): string
    {
        $content = str_replace(['</w:p>', '</p>', '<br>', '<br/>', '<br />'], "\n", $content);
        $text = html_entity_decode(strip_tags($content));
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function convertFontFile(string $sourcePath, string $target, string $destinationPath): void
    {
        $python = $this->findExecutable('python') ?: $this->findExecutable('py');
        if (! $python) {
            throw new RuntimeException('Font conversion requires Python with fontTools installed.');
        }

        $script = <<<'PY'
import sys
from fontTools.ttLib import TTFont

source_path, target_format, destination_path = sys.argv[1:4]
font = TTFont(source_path)
try:
    outline = "otf" if font.sfntVersion == "OTTO" else "ttf"
    if target_format in {"woff", "woff2"}:
        font.flavor = target_format
        font.save(destination_path)
    elif target_format == outline:
        font.flavor = None
        font.save(destination_path)
    else:
        raise SystemExit(f"{outline.upper()} to {target_format.upper()} is not supported yet.")
finally:
    font.close()
PY;

        $this->runProcess([$python, '-c', $script, $sourcePath, $target, $destinationPath], $destinationPath, 'Font conversion failed.');
    }

    private function extractSpreadsheetText(string $sourcePath): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($sourcePath);
        $collected = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $collected[] = '[' . $worksheet->getTitle() . ']';
            foreach ($worksheet->toArray(null, true, true, false) as $row) {
                $values = array_map(
                    fn ($value) => $value === null ? '' : trim((string) $value),
                    $row
                );
                if (implode('', $values) !== '') {
                    $collected[] = rtrim(implode("\t", $values));
                }
            }
            $collected[] = '';
        }

        $spreadsheet->disconnectWorksheets();

        return trim(implode("\n", $collected));
    }
}
