<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\SMS\Services\SmsTriggerService;

class BackupController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function index()
    {
        $backups = [];
        $appName = config('backup.backup.name', env('APP_NAME', 'laravel-backup'));
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($appName);
        
        if (File::exists($path)) {
            $files = File::files($path);
            foreach ($files as $file) {
                if ($file->getExtension() === 'zip') {
                    $backups[] = [
                        'id' => md5($file->getFilename()),
                        'name' => $file->getFilename(),
                        'size' => $this->formatBytes($file->getSize()),
                        'date' => Carbon::createFromTimestamp($file->getMTime())->toDateTimeString(),
                    ];
                }
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return view('module.settings.database_backup', compact('backups'));
    }

    public function create()
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $SystemRoot = getenv('SystemRoot') ?: 'C:\Windows';
                $windir = getenv('windir') ?: 'C:\Windows';
                putenv("SystemRoot=$SystemRoot");
                putenv("windir=$windir");
                
                // Also update $_ENV and $_SERVER just in case
                $_ENV['SystemRoot'] = $SystemRoot;
                $_SERVER['SystemRoot'] = $SystemRoot;
                $_ENV['windir'] = $windir;
                $_SERVER['windir'] = $windir;
            }

            // Artisan::call doesn't throw exceptions on command failure, it returns exit code
            $exitCode = \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
            
            if ($exitCode === 0) {
                // Trigger SMS notification
                try {
                    $this->smsTriggerService->trigger('BackupCompletedAlert', []);
                } catch (\Exception $e) {
                    \Log::error('Backup SMS failed: ' . $e->getMessage());
                }

                return response()->json(['success' => true, 'message' => 'Backup created successfully']);
            } else {
                $output = \Illuminate\Support\Facades\Artisan::output();
                return response()->json(['success' => false, 'message' => 'Backup failed: ' . $output], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function download($filename)
    {
        $appName = config('backup.backup.name', env('APP_NAME', 'laravel-backup'));
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($appName . '/' . $filename);

        if (File::exists($path)) {
            return response()->download($path);
        }

        return redirect()->back()->with('error', 'File not found.');
    }

    public function destroy($filename)
    {
        $appName = config('backup.backup.name', env('APP_NAME', 'laravel-backup'));
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($appName . '/' . $filename);

        if (File::exists($path)) {
            File::delete($path);
            return response()->json(['success' => true, 'message' => 'Backup deleted successfully']);
        }

        return response()->json(['success' => false, 'message' => 'File not found.'], 404);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
