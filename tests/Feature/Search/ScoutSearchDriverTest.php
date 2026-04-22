<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Search\DatabaseSearchDriver;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;

class FakeScoutBuilder
{
    /** @var callable(EloquentBuilder): void|null */
    public $callback = null;

    /** @var array<int, mixed> */
    public array $paginateArgs = [];

    public function __construct(
        public readonly LengthAwarePaginator $paginateResult,
        public readonly EloquentCollection $getResult
    ) {}

    public function query(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function paginate(int $perPage, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $this->paginateArgs = [$perPage, $pageName, $page];

        return $this->paginateResult;
    }

    public function get(): EloquentCollection
    {
        return $this->getResult;
    }
}

// ── constructor guard ─────────────────────────────────────────────────────────

it('throws RuntimeException when laravel/scout is not installed', function () {
    // laravel/scout is only suggested, not required — class_exists() returns false
    // in the default test environment, so the constructor guard fires.
    expect(fn () => new ScoutSearchDriver(app(DatabaseSearchDriver::class)))
        ->toThrow(RuntimeException::class, 'ScoutSearchDriver requires laravel/scout');
})->skip(
    fn () => class_exists(Builder::class),
    'laravel/scout is installed — RuntimeException guard is not triggered'
);

// ── when Scout is available ───────────────────────────────────────────────────

it('can be instantiated when laravel/scout is installed', function () {
    $driver = new ScoutSearchDriver(app(DatabaseSearchDriver::class));

    expect($driver)->toBeInstanceOf(ScoutSearchDriver::class);
})->skip(
    fn () => ! class_exists(Builder::class),
    'laravel/scout is not installed'
);

// ── internal flow without requiring laravel/scout in this test environment ───

it('paginate() delegates to the scout builder and applies filters and explicit sort', function () {
    $filterDriver = new class extends DatabaseSearchDriver
    {
        /** @var array<int, array<string, mixed>> */
        public array $filterCalls = [];

        /** @var array<int, array{sort_by: string, direction: string}> */
        public array $sortCalls = [];

        public function applyFilters(EloquentBuilder $builder, array $filters): void
        {
            $this->filterCalls[] = $filters;
        }

        public function applySort(EloquentBuilder $builder, string $sortBy, string $direction): void
        {
            $this->sortCalls[] = ['sort_by' => $sortBy, 'direction' => $direction];
        }
    };

    $paginator = new LengthAwarePaginator([], 0, 15, 2);
    $fakeBuilder = new FakeScoutBuilder($paginator, new EloquentCollection);

    $driver = new class($filterDriver, $fakeBuilder) extends ScoutSearchDriver
    {
        public string $capturedQuery = '';

        public function __construct(
            DatabaseSearchDriver $filterDriver,
            private readonly FakeScoutBuilder $fakeBuilder
        ) {
            parent::__construct($filterDriver);
        }

        protected function scoutIsInstalled(): bool
        {
            return true;
        }

        protected function makeScoutBuilder(string $query): object
        {
            $this->capturedQuery = $query;

            return $this->fakeBuilder;
        }
    };

    $result = $driver->paginate('kemeja', ['status' => 'published', 'sort_by' => 'name', 'sort_direction' => 'asc'], 15, 2);

    expect($result)->toBe($paginator)
        ->and($driver->capturedQuery)->toBe('kemeja')
        ->and($fakeBuilder->paginateArgs)->toBe([15, 'page', 2])
        ->and($fakeBuilder->callback)->not->toBeNull();

    ($fakeBuilder->callback)(Product::query());

    expect($filterDriver->filterCalls)->toHaveCount(1)
        ->and($filterDriver->filterCalls[0])->toMatchArray(['status' => 'published', 'sort_by' => 'name', 'sort_direction' => 'asc'])
        ->and($filterDriver->sortCalls)->toBe([
            ['sort_by' => 'name', 'direction' => 'asc'],
        ]);
});

it('get() delegates to the scout builder and skips applySort() when no explicit sort is given', function () {
    $filterDriver = new class extends DatabaseSearchDriver
    {
        /** @var array<int, array<string, mixed>> */
        public array $filterCalls = [];

        public int $sortCallCount = 0;

        public function applyFilters(EloquentBuilder $builder, array $filters): void
        {
            $this->filterCalls[] = $filters;
        }

        public function applySort(EloquentBuilder $builder, string $sortBy, string $direction): void
        {
            $this->sortCallCount++;
        }
    };

    $collection = new EloquentCollection([Product::factory()->make(['name' => 'Kemeja Scout'])]);
    $fakeBuilder = new FakeScoutBuilder(new LengthAwarePaginator([], 0, 15, 1), $collection);

    $driver = new class($filterDriver, $fakeBuilder) extends ScoutSearchDriver
    {
        public function __construct(
            DatabaseSearchDriver $filterDriver,
            private readonly FakeScoutBuilder $fakeBuilder
        ) {
            parent::__construct($filterDriver);
        }

        protected function scoutIsInstalled(): bool
        {
            return true;
        }

        protected function makeScoutBuilder(string $query): object
        {
            return $this->fakeBuilder;
        }
    };

    $result = $driver->get('kemeja', ['status' => 'published']);

    expect($result)->toBe($collection)
        ->and($fakeBuilder->callback)->not->toBeNull();

    ($fakeBuilder->callback)(Product::query());

    expect($filterDriver->filterCalls)->toBe([
        ['status' => 'published'],
    ])->and($filterDriver->sortCallCount)->toBe(0);
});
