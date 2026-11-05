<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemMaintenanceController extends Controller
{
    /**
     * Get system health and diagnostic indicators.
     */
    public function getHealthStatus(Request $request): JsonResponse
    {
        $startDb = microtime(true);
        $dbConnected = false;
        $dbError = null;
        $dbLatencyMs = 0;

        try {
            DB::connection()->getPdo();
            $dbLatencyMs = round((microtime(true) - $startDb) * 1000, 2);
            $dbConnected = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        // Check storage write permissions
        $storageWritable = is_writable(storage_path('app'));
        $logsWritable = is_writable(storage_path('logs'));

        // Check backup files
        $backupDir = storage_path('app/backups');
        $backupCount = 0;
        $latestBackup = null;

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            $backupCount = count($files);

            if ($backupCount > 0) {
                usort($files, fn($a, $b) => $b->getMTime() - $a->getMTime());
                $latest = $files[0];
                $latestBackup = [
                    'filename' => $latest->getFilename(),
                    'size' => round($latest->getSize() / 1024, 2) . ' KB',
                    'created_at' => Carbon::createFromTimestamp($latest->getMTime())->format('Y/m/d h:i:s a'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $dbConnected && $storageWritable ? 'healthy' : 'degraded',
                'database' => [
                    'connected' => $dbConnected,
                    'latency_ms' => $dbLatencyMs,
                    'driver' => config('database.default'),
                    'name' => config('database.connections.mysql.database'),
                    'error' => $dbError,
                ],
                'storage' => [
                    'app_writable' => $storageWritable,
                    'logs_writable' => $logsWritable,
                ],
                'backups' => [
                    'count' => $backupCount,
                    'latest' => $latestBackup,
                ],
                'environment' => [
                    'app_env' => app()->environment(),
                    'debug_mode' => config('app.debug'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'server_time' => Carbon::now()->format('Y/m/d h:i:s a'),
                    'server_timezone' => config('app.timezone'),
                ],
            ],
        ]);
    }

    /**
     * List all database backups.
     */
    public function getBackupsList(Request $request): JsonResponse
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            usort($files, fn($a, $b) => $b->getMTime() - $a->getMTime());

            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['sql', 'gz'])) {
                    $sizeBytes = $file->getSize();
                    $formattedSize = round($sizeBytes / 1024, 2) . ' KB';
                    if ($sizeBytes > 1048576) {
                        $formattedSize = round($sizeBytes / 1048576, 2) . ' MB';
                    }

                    $backups[] = [
                        'filename' => $file->getFilename(),
                        'size' => $formattedSize,
                        'size_bytes' => $sizeBytes,
                        'created_at' => Carbon::createFromTimestamp($file->getMTime())->format('Y/m/d h:i:s a'),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $backups,
        ]);
    }

    /**
     * Trigger an on-demand database backup.
     */
    public function triggerBackup(Request $request): JsonResponse
    {
        try {
            $exitCode = Artisan::call('pms:backup');
            $output = Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup generated successfully.',
                    'output' => $output,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database backup command completed with errors.',
                'output' => $output,
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a specific backup file.
     */
    public function downloadBackup(Request $request, string $filename): BinaryFileResponse|JsonResponse
    {
        // Sanitize to prevent path traversal
        $safeFilename = basename($filename);
        $filePath = storage_path("app/backups/{$safeFilename}");

        if (!File::exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found.',
            ], 404);
        }

        return response()->download($filePath, $safeFilename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}
