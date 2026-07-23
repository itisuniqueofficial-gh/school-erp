<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AvatarService;
use Core\Database\QueryBuilder;
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

    /**
     * Public branding asset (logo/favicon/stamp/signature). No authentication:
     * these must load on the login page and in the browser tab. To avoid
     * exposing the private photo store (same directory), only files whose id is
     * currently registered as a branding setting are served — anything else 404s.
     */
    public function branding(Request $request, string $file): Response
    {
        $id = pathinfo($file, PATHINFO_FILENAME); // strip any extension
        $registered = QueryBuilder::table('settings')
            ->whereIn('key', ['school_logo', 'school_stamp', 'principal_signature'])
            ->get();
        $allowed = false;
        foreach ($registered as $r) {
            $val = (string) ($r['value'] ?? '');
            if ($val !== '' && pathinfo($val, PATHINFO_FILENAME) === $id) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            abort(404, 'Image not found.');
        }
        $svc = new AvatarService();
        $path = $svc->path($file);
        if ($path === null) {
            abort(404, 'Image not found.');
        }
        return (new Response((string) file_get_contents($path), 200))
            ->header('Content-Type', $svc->mime($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"')
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('X-Content-Type-Options', 'nosniff');
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
