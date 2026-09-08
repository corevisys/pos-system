<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocsController extends Controller
{
    public function index(Request $request)
    {
        $docsPath = base_path('docs');
        $files = File::files($docsPath);

        // Sort files intuitively
        usort($files, function($a, $b) {
            $aName = $a->getFilename();
            $bName = $b->getFilename();
            if ($aName === 'README.md') return -1;
            if ($bName === 'README.md') return 1;
            return strcmp($aName, $bName);
        });

        $docs = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                $filename = $file->getFilename();
                
                // Read first 500 bytes to extract the title efficiently
                $handle = fopen($file->getPathname(), 'r');
                $head = fread($handle, 500);
                fclose($handle);
                
                $title = str_replace(['.md', '-'], ['', ' '], $filename);
                $title = ucwords($title);
                
                if (preg_match('/^#\s+(.+)$/m', $head, $matches)) {
                    $title = $matches[1];
                }

                $docs[] = [
                    'filename' => $filename,
                    'title' => $title,
                ];
            }
        }

        $activeDocFilename = $request->query('file', 'README.md');
        $activeDoc = collect($docs)->firstWhere('filename', $activeDocFilename);
        
        // Fallback to the first doc if active doc not found
        if (!$activeDoc && count($docs) > 0) {
            $activeDoc = $docs[0];
            $activeDocFilename = $activeDoc['filename'];
        }

        if ($activeDoc) {
            $content = File::get(base_path('docs/' . $activeDocFilename));
            
            // Render to HTML
            $htmlContent = Str::markdown($content);
            
            // Rewrite relative .md links to use ?file=
            // Example: href="02-architecture.md" -> href="?file=02-architecture.md"
            // Example: href="02-architecture.md#hash" -> href="?file=02-architecture.md#hash"
            $htmlContent = preg_replace('/href="([^"\/:]+\.md)(#[^"]*)?"/', 'href="?file=$1$2"', $htmlContent);
            
            $activeDoc['content'] = $htmlContent;
        }

        return view('docs.index', compact('docs', 'activeDoc', 'activeDocFilename'));
    }
}
