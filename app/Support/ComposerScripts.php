<?php

namespace App\Support;

use Composer\Script\Event;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

/**
 * Composer script callbacks that run inside Composer's own PHP process.
 *
 * Hostinger shared hosting disables proc_open, so "@php artisan ..." scripts
 * fail during composer install. This does the same work as
 * "php artisan package:discover" without starting a child process.
 */
class ComposerScripts
{
    public static function discoverPackages(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);
        $manifestPath = $basePath.'/bootstrap/cache/packages.php';

        (new PackageManifest(new Filesystem, $basePath, $manifestPath))->build();

        $packages = array_keys(require $manifestPath);
        $event->getIO()->write('Discovered packages: '.($packages ? implode(', ', $packages) : 'none'));
    }
}
