<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\RBAC\UserController;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;
use ReflectionClass;

class VerifyPermissionParity extends Command
{
    protected $signature = 'rbac:verify-parity {frontend-json? : Path to exported frontend permission keys JSON}';
    protected $description = 'Verify that the RbacSeeder canonical permission list matches what the frontend expects. Fails (non-zero exit) if there is drift.';

    public function handle(): int
    {
        $reflection = new ReflectionClass(RbacSeeder::class);
        $prop = $reflection->getConstant('PERMISSIONS');
        $backendPermissions = collect($prop);

        $jsonPath = $this->argument('frontend-json')
            ?? base_path('../resources-frontend-permissions.json'); // adjust to your export path

        if (! file_exists($jsonPath)) {
            $this->error("Frontend permission export not found at: {$jsonPath}");
            $this->line('Generate it from constants.js (see extractFrontendKeys.js script) before running this check.');
            return self::FAILURE;
        }

        $frontendPermissions = collect(json_decode(file_get_contents($jsonPath), true));

        $missingInBackend = $frontendPermissions->diff($backendPermissions);
        $unusedInFrontend  = $backendPermissions->diff($frontendPermissions);

        if ($missingInBackend->isEmpty() && $unusedInFrontend->isEmpty()) {
            $this->info('Permission catalogs are in sync.');
            return self::SUCCESS;
        }

        if ($missingInBackend->isNotEmpty()) {
            $this->error('Frontend references permissions NOT in RbacSeeder (these will 422 on save):');
            $missingInBackend->each(fn ($p) => $this->line("  - {$p}"));
        }

        if ($unusedInFrontend->isNotEmpty()) {
            $this->warn('Backend has permissions not exposed in the frontend UI (informational, not fatal):');
            $unusedInFrontend->each(fn ($p) => $this->line("  - {$p}"));
        }

        return $missingInBackend->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}