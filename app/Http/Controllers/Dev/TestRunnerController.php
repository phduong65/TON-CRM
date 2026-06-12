<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TestRunnerController extends Controller
{
    public function index()
    {
        abort_unless(app()->isLocal(), 403, 'Test runner only available in local environment.');
        return view('dev.test-runner');
    }

    public function run(): JsonResponse
    {
        abort_unless(app()->isLocal(), 403);

        $phpBinary  = PHP_BINARY;
        $artisan    = base_path('artisan');
        $projectDir = base_path();

        // PHP caches sys_get_temp_dir() at startup, so putenv() cannot change it.
        // On Windows web servers sys_get_temp_dir() often points to C:\Windows\ which
        // is not writable. Symfony\Process\WindowsPipes uses sys_get_temp_dir() for its
        // IPC temp files, so we bypass Symfony Process entirely and use proc_open()
        // with explicit file descriptors inside a directory we control.
        $tmpDir = storage_path('tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $stdoutFile = $tmpDir . DIRECTORY_SEPARATOR . 'phpunit_stdout.txt';
        $stderrFile = $tmpDir . DIRECTORY_SEPARATOR . 'phpunit_stderr.txt';

        // APP_ENV=testing must be set as an OS-level env var before the child process
        // starts, so Laravel's LoadEnvironmentVariables bootstrap loads .env.testing
        // (SQLite) instead of .env (MySQL).
        putenv('APP_ENV=testing');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutFile, 'w'],
            2 => ['file', $stderrFile, 'w'],
        ];

        $handle = proc_open(
            [$phpBinary, $artisan, 'test', '--no-coverage'],
            $descriptors,
            $pipes,
            $projectDir
        );

        if (! is_resource($handle)) {
            return response()->json([
                'success'  => false,
                'exitCode' => -1,
                'summary'  => ['total' => 0, 'passed' => 0, 'failed' => 0, 'errors' => 0, 'skipped' => 0, 'time' => null],
                'output'   => 'Failed to start test process.',
                'lines'    => [],
            ]);
        }

        fclose($pipes[0]);

        $deadline = time() + 300;
        while (proc_get_status($handle)['running']) {
            if (time() > $deadline) {
                proc_terminate($handle);
                break;
            }
            usleep(200_000);
        }

        $exitCode = proc_close($handle);

        $rawOutput   = file_exists($stdoutFile) ? file_get_contents($stdoutFile) : '';
        $errorOutput = file_exists($stderrFile) ? file_get_contents($stderrFile) : '';
        @unlink($stdoutFile);
        @unlink($stderrFile);

        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $rawOutput . $errorOutput);

        $passed  = 0;
        $failed  = 0;
        $errors  = 0;
        $skipped = 0;
        $total   = 0;
        $time    = null;

        if (preg_match('/Tests:\s*(\d+)/',   $cleanOutput, $m)) $total   = (int) $m[1];
        if (preg_match('/Failures:\s*(\d+)/', $cleanOutput, $m)) $failed  = (int) $m[1];
        if (preg_match('/Errors:\s*(\d+)/',   $cleanOutput, $m)) $errors  = (int) $m[1];
        if (preg_match('/Skipped:\s*(\d+)/',  $cleanOutput, $m)) $skipped = (int) $m[1];
        if (preg_match('/Time:\s*([\d.]+)\s*second/', $cleanOutput, $m)) $time = $m[1] . 's';
        $passed = max(0, $total - $failed - $errors - $skipped);

        $testLines = [];
        foreach (explode("\n", $cleanOutput) as $line) {
            $line = trim($line);
            if (preg_match('/^(PASS|FAIL|ERROR|WARN)\s+(.+)$/', $line, $m)) {
                $testLines[] = ['status' => $m[1], 'suite' => $m[2]];
            }
            if (preg_match('/^\s+(✓|✗|!)\s+(.+)$/', $line, $m)) {
                $testLines[] = [
                    'status' => match ($m[1]) { '✓' => 'PASS', '✗' => 'FAIL', default => 'SKIP' },
                    'suite'  => $m[2],
                ];
            }
        }

        return response()->json([
            'success'  => $exitCode === 0,
            'exitCode' => $exitCode,
            'summary'  => compact('total', 'passed', 'failed', 'errors', 'skipped', 'time'),
            'output'   => $cleanOutput,
            'lines'    => $testLines,
        ]);
    }
}
