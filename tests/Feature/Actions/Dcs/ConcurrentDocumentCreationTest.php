<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * These tests run several PHP processes against PostgreSQL at the same time, which is the only way to
 * prove the locking: SQLite ignores row locks and a single test connection cannot race itself. The
 * rows have to be committed for the other processes to see them, so the test commits the transaction
 * RefreshDatabase opened and removes everything it created afterwards.
 */
beforeEach(function () {
    $this->scriptPath = storage_path('framework/testing/concurrent-dcs.php');
    File::ensureDirectoryExists(dirname($this->scriptPath));
    File::put($this->scriptPath, <<<'PHP'
        <?php

        use App\Actions\Dcs\CreateDocument;
        use App\Actions\Dcs\CreateVersion;
        use App\Models\Dcs\Document;
        use App\Models\Tenant;
        use App\Models\User;

        require __DIR__.'/../../../vendor/autoload.php';

        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        [, $mode, $tenantId, $userId, $subject, $startAt, $count] = $argv;
        $tenant = Tenant::query()->findOrFail($tenantId);
        $user = User::query()->findOrFail($userId);

        while (microtime(true) < (float) $startAt) {
            usleep(500);
        }

        $results = [];

        for ($i = 0; $i < (int) $count; $i++) {
            $results[] = $mode === 'documents'
                ? app(CreateDocument::class)->handle($user, $tenant, [
                    'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Paralel', 'ownerUserId' => null, 'manualNumber' => null,
                    'initialMajor' => null, 'initialMinor' => null, 'archiveCategory' => null, 'archiveSubFolderId' => null,
                ])['document']->document_number
                : (function () use ($user, $subject) {
                    $version = app(CreateVersion::class)->handle($user, Document::query()->findOrFail($subject), 'revisi', null, null, null);

                    return $version->major.'.'.$version->minor;
                })();
        }

        echo json_encode($results);
        PHP);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Concurrency needs PostgreSQL (composer test:pg).');

afterEach(function () {
    File::delete($this->scriptPath);
});

/**
 * @return list<list<string>> what each process printed
 */
function runConcurrently(string $script, string $mode, Tenant $tenant, User $user, int|string $subject, int $processes, int $each): array
{
    $startAt = microtime(true) + 4;
    $connection = config('database.connections.pgsql');
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => $connection['port'],
        'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    ];

    $results = Process::pool(function (Pool $pool) use ($script, $mode, $tenant, $user, $subject, $startAt, $each, $processes, $environment) {
        for ($i = 0; $i < $processes; $i++) {
            $pool->command([PHP_BINARY, $script, $mode, $tenant->id, $user->id, (string) $subject, (string) $startAt, (string) $each])
                ->env($environment)
                ->timeout(60);
        }
    })->start()->wait();

    return collect($results)->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

        return json_decode($result->output(), true);
    })->all();
}

function removeCommittedFixture(Tenant $tenant): void
{
    DB::statement('ALTER TABLE dcs_document_events DISABLE TRIGGER USER');

    foreach (['dcs_document_events', 'dcs_number_allocations'] as $table) {
        DB::table($table)->where($table === 'dcs_number_allocations' ? 'sequence_id' : 'tenant_id', '>', 0)
            ->whereIn($table === 'dcs_number_allocations' ? 'sequence_id' : 'tenant_id', $table === 'dcs_number_allocations'
                ? DB::table('dcs_number_sequences')->where('tenant_id', $tenant->id)->select('id')
                : [$tenant->id])->delete();
    }

    DB::statement('ALTER TABLE dcs_document_events ENABLE TRIGGER USER');
    DB::table('dcs_documents')->where('tenant_id', $tenant->id)->update(['current_version_id' => null]);
    DB::table('dcs_document_versions')->where('tenant_id', $tenant->id)->delete();
    DB::table('dcs_documents')->where('tenant_id', $tenant->id)->delete();
    DB::table('dcs_number_sequences')->where('tenant_id', $tenant->id)->delete();
    DB::table('dcs_departments')->where('tenant_id', $tenant->id)->delete();
    DB::table('dcs_numbering_rules')->where('tenant_id', $tenant->id)->delete();
    DB::table('dcs_document_types')->where('tenant_id', $tenant->id)->delete();
    DB::table('audit_logs')->where('tenant_id', $tenant->id)->delete();
    DB::table('users')->where('tenant_id', $tenant->id)->delete();
    DB::table('tenants')->where('id', $tenant->id)->delete();
}

it('never hands out a document number twice when many processes create documents at once', function () {
    DB::commit();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    app(SeedDcsTenantDefaults::class)->handle($tenant);

    try {
        $numbers = collect(runConcurrently($this->scriptPath, 'documents', $tenant, $user, 0, 5, 4))->flatten();

        expect($numbers)->toHaveCount(20)->and($numbers->unique(strict: true))->toHaveCount(20);
        $sequences = $numbers->map(fn (string $number) => (int) substr($number, -4))->sort()->values()->all();
        expect($sequences)->toBe(range(1, 20));
        expect(Document::query()->where('tenant_id', $tenant->id)->count())->toBe(20)
            ->and(DB::table('dcs_number_sequences')->where('tenant_id', $tenant->id)->value('current_seq'))->toBe(20);
    } finally {
        removeCommittedFixture($tenant);
    }
});

it('gives concurrent new versions of one document different numbers', function () {
    DB::commit();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($tenant)->create(['created_by_id' => $user->id, 'owner_user_id' => $user->id]);
    DocumentVersion::factory()->for($document)->create(['major' => 1, 'minor' => 0]);

    try {
        $versions = collect(runConcurrently($this->scriptPath, 'versions', $tenant, $user, $document->id, 4, 3))->flatten();

        expect($versions)->toHaveCount(12)->and($versions->unique(strict: true))->toHaveCount(12);
        expect($versions->map(fn (string $version) => (int) explode('.', $version)[1])->sort()->values()->all())->toBe(range(1, 12));
    } finally {
        removeCommittedFixture($tenant);
    }
});
