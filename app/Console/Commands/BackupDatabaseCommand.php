<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pms:backup {--retention=7 : Number of days to retain backup files}';

    /**
     * The console command description.
     */
    protected $description = 'Perform a database backup and cleanup stale backups beyond retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Pharmacy PMS Database Backup...');

        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Ymd_His');
        $dbName = config('database.connections.mysql.database', 'pharmacy_pms');
        $fileName = "pms_backup_{$dbName}_{$timestamp}.sql";
        $filePath = "{$backupDir}/{$fileName}";

        try {
            $handle = fopen($filePath, 'w');
            if (!$handle) {
                throw new \RuntimeException("Cannot open file for writing: {$filePath}");
            }

            // Write SQL Dump Header
            fwrite($handle, "-- ========================================================\n");
            fwrite($handle, "-- Afya Meds Pharmacy Management System — Database Backup\n");
            fwrite($handle, "-- Database: {$dbName}\n");
            fwrite($handle, "-- Timestamp: " . Carbon::now()->toIso8601String() . "\n");
            fwrite($handle, "-- ========================================================\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "START TRANSACTION;\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

            // Fetch all tables
            $tables = DB::select('SHOW TABLES');
            $tableKey = "Tables_in_{$dbName}";

            foreach ($tables as $tableObj) {
                // Determine table name property
                $tableProps = (array) $tableObj;
                $tableName = reset($tableProps);

                $this->line("Dumping table: <info>{$tableName}</info>");

                fwrite($handle, "\n-- --------------------------------------------------------\n");
                fwrite($handle, "-- Table structure for table `{$tableName}`\n");
                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");

                $createTableRes = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createArr = (array) $createTableRes[0];
                $createSql = $createArr['Create Table'] ?? reset($createArr);
                fwrite($handle, $createSql . ";\n\n");

                // Dump table rows
                $rows = DB::table($tableName)->get();
                if ($rows->count() > 0) {
                    fwrite($handle, "-- Dumping data for table `{$tableName}`\n");

                    $chunks = $rows->chunk(100);
                    foreach ($chunks as $chunk) {
                        $valuesList = [];
                        foreach ($chunk as $row) {
                            $rowArr = (array) $row;
                            $escapedValues = array_map(function ($val) {
                                if (is_null($val)) return 'NULL';
                                return "'" . addslashes((string) $val) . "'";
                            }, $rowArr);
                            $valuesList[] = "(" . implode(', ', $escapedValues) . ")";
                        }

                        $firstRow = (array) $chunk->first();
                        $columns = array_keys($firstRow);
                        $colList = implode('`, `', $columns);

                        fwrite($handle, "INSERT INTO `{$tableName}` (`{$colList}`) VALUES \n" . implode(",\n", $valuesList) . ";\n");
                    }
                    fwrite($handle, "\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fwrite($handle, "COMMIT;\n");
            fclose($handle);

            $fileSize = filesize($filePath);
            $formattedSize = round($fileSize / 1024, 2) . ' KB';
            if ($fileSize > 1048576) {
                $formattedSize = round($fileSize / 1048576, 2) . ' MB';
            }

            $this->info("Backup created successfully: {$fileName} ({$formattedSize})");
            Log::info("PMS Database backup generated: {$fileName} ({$formattedSize})");

            // Purge stale backups beyond retention period
            $retentionDays = (int) $this->option('retention');
            $this->purgeStaleBackups($backupDir, $retentionDays);

            return 0;
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            Log::error("Database backup failed: " . $e->getMessage(), ['exception' => $e]);
            return 1;
        }
    }

    /**
     * Purge old backup files exceeding retention days.
     */
    protected function purgeStaleBackups(string $dir, int $days): void
    {
        $cutoff = Carbon::now()->subDays($days);
        $files = File::files($dir);
        $deletedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() === 'sql' || $file->getExtension() === 'gz') {
                $lastModified = Carbon::createFromTimestamp($file->getMTime());
                if ($lastModified->lt($cutoff)) {
                    File::delete($file->getRealPath());
                    $deletedCount++;
                }
            }
        }

        if ($deletedCount > 0) {
            $this->info("Purged {$deletedCount} stale backup file(s) older than {$days} days.");
        }
    }
}
