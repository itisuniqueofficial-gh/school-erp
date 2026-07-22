<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AvatarService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Serves profile pictures from outside the web root, to authenticated users
 * only. Filenames are validated (hex.ext) inside AvatarService to prevent any
 * path traversal, and responses are sent with a non-sniffing content type.
 */
final class MediaController extends Controller
{
    public function avatar(Request $request, string $file): Response
    {
        return $this->serve($file, false);
    }

    public function avatarThumb(Request $request, string $file): Response
    {
        return $this->serve($file, true);
    }

    private function serve(string $file, bool $thumb): Response
    {
        $svc = new AvatarService();
        $path = $svc->path($file, $thumb);
        if ($path === null) {
            abort(404, 'Image not found.');
        }
        return (new Response((string) file_get_contents($path), 200))
            ->header('Content-Type', $svc->mime($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"')
            ->header('Cache-Control', 'private, max-age=86400')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
