<?php

namespace Tests\Unit\H5P\Branching;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;

/**
 * Just enough of an application for the H5P services to run WITHOUT booting
 * Laravel: `config()`, `storage_path()`, `public_path()` and `asset()`.
 *
 * Why not `Tests\TestCase`: it boots the whole framework, and a provider reads
 * `custom_module_tables` from the database during boot. phpunit.xml points that
 * database at the shared live vivek_erp, so even a test that never queries
 * anything would touch it. A container with four bindings is the whole of what
 * these services ask of the framework.
 */
trait BootsMiniApp
{
    protected string $miniAppRoot = '';

    protected function bootMiniApp(): void
    {
        $this->miniAppRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'h5p-branching-test-' . bin2hex(random_bytes(6));
        mkdir($this->miniAppRoot . '/storage/app', 0777, true);
        mkdir($this->miniAppRoot . '/public', 0777, true);

        $root = $this->miniAppRoot;
        $repoRoot = dirname(__DIR__, 4);

        $app = new class($root) extends Container {
            public function __construct(private string $root)
            {
            }

            public function storagePath($path = ''): string
            {
                return $this->root . '/storage' . ($path !== '' ? '/' . ltrim($path, '/\\') : '');
            }

            public function publicPath($path = ''): string
            {
                return $this->root . '/public' . ($path !== '' ? '/' . ltrim($path, '/\\') : '');
            }
        };

        $app->instance('config', new Repository([
            'h5p_libraries' => require $repoRoot . '/config/h5p_libraries.php',
        ]));
        $app->instance('url', new class {
            public function asset(string $path): string
            {
                return 'https://app.example.test/' . ltrim($path, '/');
            }
        });

        Container::setInstance($app);
    }

    protected function shutDownMiniApp(): void
    {
        Container::setInstance(null);

        if ($this->miniAppRoot !== '' && is_dir($this->miniAppRoot)) {
            $this->removeDir($this->miniAppRoot);
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
