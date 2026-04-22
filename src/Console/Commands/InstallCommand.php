<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'catalog:install
                            {--migrate : Run migrations automatically after publishing}';

    protected $description = 'Publish config and migrations for Laravel Product Catalog';

    public function handle(): int
    {
        $this->components->info('Installing Laravel Product Catalog...');

        $this->components->task('Publishing config', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'product-catalog-config',
            ]);
        });

        $this->components->task('Publishing migrations', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'product-catalog-migrations',
            ]);
        });

        if ($this->option('migrate')) {
            $this->newLine();
            $this->call('migrate', ['--graceful' => true]);
        }

        $this->newLine();
        $this->components->info('Installation complete.');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('    1. Review <info>config/product-catalog.php</info>');

        if (! $this->option('migrate')) {
            $this->line('    2. Run: <info>php artisan migrate</info>');
        }

        $this->line('    3. Optional: <info>php artisan catalog:seed-demo</info>');
        $this->newLine();

        return self::SUCCESS;
    }
}
