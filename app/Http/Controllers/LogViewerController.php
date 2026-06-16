<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    private const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    private const PER_PAGE = 50;
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB — đọc toàn bộ; trên mức này chỉ đọc tail

    public function index(Request $request)
    {
        $logDir = storage_path('logs');

        $files = collect(File::files($logDir))
            ->filter(fn ($f) => $f->getExtension() === 'log')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => $f->getFilename())
            ->values();

        // Validate file param — chỉ cho phép basename .log trong thư mục logs
        $selectedFile = basename($request->get('file', ''));
        if (!$selectedFile || !str_ends_with($selectedFile, '.log') || !$files->contains($selectedFile)) {
            $selectedFile = $files->first();
        }

        $filePath = $selectedFile ? ($logDir . DIRECTORY_SEPARATOR . $selectedFile) : null;
        $truncated = false;
        $entries = collect();

        if ($filePath && File::exists($filePath)) {
            $size = File::size($filePath);
            if ($size > self::MAX_BYTES) {
                $truncated = true;
                $entries = $this->parseTail($filePath);
            } else {
                $entries = $this->parseFile($filePath);
            }
        }

        // Filter level
        $levelFilter = $request->get('level');
        if ($levelFilter && in_array($levelFilter, self::LEVELS)) {
            $entries = $entries->filter(fn ($e) => $e['level'] === $levelFilter)->values();
        }

        // Filter search
        $search = trim($request->get('search', ''));
        if ($search !== '') {
            $lower = strtolower($search);
            $entries = $entries->filter(
                fn ($e) => str_contains(strtolower($e['message']), $lower)
                        || str_contains(strtolower($e['extra']), $lower)
            )->values();
        }

        $total = $entries->count();
        $page  = max(1, (int) $request->get('page', 1));

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, self::PER_PAGE),
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        $levelCounts = $entries->countBy('level');

        return view('log-viewer.index', [
            'files'        => $files,
            'selectedFile' => $selectedFile,
            'entries'      => $paginator,
            'total'        => $total,
            'levelFilter'  => $levelFilter,
            'search'       => $search,
            'levelCounts'  => $levelCounts,
            'truncated'    => $truncated,
        ]);
    }

    private function parseFile(string $path): \Illuminate\Support\Collection
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $this->groupLines($lines ?: []);
    }

    private function parseTail(string $path): \Illuminate\Support\Collection
    {
        $handle = fopen($path, 'r');
        if (!$handle) return collect();

        $size = filesize($path);
        $readBytes = min($size, self::MAX_BYTES);
        fseek($handle, -$readBytes, SEEK_END);

        $lines = [];
        if ($readBytes < $size) {
            fgets($handle); // discard partial first line
        }
        while (!feof($handle)) {
            $line = rtrim(fgets($handle), "\n\r");
            if ($line !== '') $lines[] = $line;
        }
        fclose($handle);

        return $this->groupLines($lines);
    }

    private function groupLines(array $lines): \Illuminate\Support\Collection
    {
        $entries = [];
        $current = null;
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)/';

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'datetime' => $m[1],
                    'env'      => $m[2],
                    'level'    => strtolower($m[3]),
                    'message'  => $m[4],
                    'extra'    => '',
                ];
            } elseif ($current !== null) {
                $current['extra'] .= $line . "\n";
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return collect(array_reverse($entries));
    }
}
