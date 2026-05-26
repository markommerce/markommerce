<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Signature\ScopeSignature;

// --- Fixture config classes ---

class WriterGlobalConfig
{
    #[Config(key: 'writer/general.page_size')]
    public int $pageSize = 10;
}

class WriterScopedConfig
{
    #[Config(key: 'writer/scoped.value')]
    #[Scoped(axes: ['store', 'website'])]
    public string $value = 'default';
}

// --- Helpers ---

function buildWriterRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [WriterGlobalConfig::class, WriterScopedConfig::class],
        new FakeScopeRegistry(['store', 'website']),
    );
}

function buildWriter(ConfigStorageInterface $storage): ConfigWriter
{
    return new ConfigWriter(
        registry: buildWriterRegistry(),
        storage: $storage,
        cipher: new NullSecretCipher(),
    );
}

// --- ContentiousStorage test double ---

class ContentiousStorage implements ConfigStorageInterface
{
    public int $attempts = 0;

    private InMemoryConfigStorage $inner;

    public function __construct(public int $failCount)
    {
        $this->inner = new InMemoryConfigStorage();
    }

    public function load(string $key): ?ConfigRow
    {
        return $this->inner->load($key);
    }

    /**
     * @param list<string> $keys
     * @return array<string, ConfigRow>
     */
    public function loadMany(array $keys): array
    {
        return $this->inner->loadMany($keys);
    }

    public function compareAndSave(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool {
        $this->attempts++;

        if ($this->attempts <= $this->failCount) {
            return false;
        }

        return $this->inner->compareAndSave($key, $row, $expectedVersion);
    }
}

// --- Tests ---

it('persists a new global value via setGlobal when the key has no existing row', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/general.page_size', 25);

    $row = $storage->load('writer/general.page_size');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBe(25);
});

it('removes the global value via unsetGlobal while keeping per-scope overrides intact', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/scoped.value', 'global-value');
    $writer->setOverride('writer/scoped.value', new ScopeSignature(['store' => '1']), 'store-override');
    $writer->unsetGlobal('writer/scoped.value');

    $row = $storage->load('writer/scoped.value');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBeNull()
        ->and($row->overrides)->toHaveKey('store:1');
});

it(
    'replaces an existing global value via setGlobal preserving any per-scope overrides on the same key',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $writer = buildWriter($storage);

        $writer->setGlobal('writer/scoped.value', 'first');
        $writer->setOverride('writer/scoped.value', new ScopeSignature(['store' => '1']), 'store-override');
        $writer->setGlobal('writer/scoped.value', 'second');

        $row = $storage->load('writer/scoped.value');
        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('second')
            ->and($row->overrides)->toHaveKey('store:1')
            ->and($row->overrides['store:1'])->toBe('store-override');
    },
);

it('persists a new per-scope override via setOverride keyed by the signature string', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $signature = new ScopeSignature(['store' => '5']);
    $writer->setOverride('writer/scoped.value', $signature, 'store-5-value');

    $row = $storage->load('writer/scoped.value');
    expect($row)->not->toBeNull()
        ->and($row->overrides)->toHaveKey($signature->toString())
        ->and($row->overrides[$signature->toString()])->toBe('store-5-value');
});

it('replaces an existing per-scope override for the same signature', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $signature = new ScopeSignature(['store' => '5']);
    $writer->setOverride('writer/scoped.value', $signature, 'first');
    $writer->setOverride('writer/scoped.value', $signature, 'second');

    $row = $storage->load('writer/scoped.value');
    expect($row)->not->toBeNull()
        ->and($row->overrides)->toHaveCount(1)
        ->and($row->overrides[$signature->toString()])->toBe('second');
});

it(
    'accepts SecretCipherInterface as a constructor dependency but does NOT invoke it for non-secret writes in this task\'s tests',
    function (): void {
        $storage = new InMemoryConfigStorage();

        // NullSecretCipher throws when called — if it's invoked, this test fails
        $writer = new ConfigWriter(
            registry: buildWriterRegistry(),
            storage: $storage,
            cipher: new NullSecretCipher(),
        );

        // WriterGlobalConfig is not a secret config, so cipher must not be called
        $writer->setGlobal('writer/general.page_size', 50);

        $row = $storage->load('writer/general.page_size');
        expect($row)->not->toBeNull()->and($row->value)->toBe(50);
    },
);

it(
    'produces an identical row mutation when setOverride is called with null as when unsetOverride is called',
    function (): void {
        $sig = new ScopeSignature(['store' => '7']);

        $storageA = new InMemoryConfigStorage();
        $writerA = buildWriter($storageA);
        $writerA->setOverride('writer/scoped.value', $sig, 'some-value');
        $writerA->setOverride('writer/scoped.value', $sig, null);

        $storageB = new InMemoryConfigStorage();
        $writerB = buildWriter($storageB);
        $writerB->setOverride('writer/scoped.value', $sig, 'some-value');
        $writerB->unsetOverride('writer/scoped.value', $sig);

        // Both remove the override, resulting in empty row (deleted)
        expect($storageA->load('writer/scoped.value'))->toBeNull()
                ->and($storageB->load('writer/scoped.value'))->toBeNull();
    },
);

it(
    'produces an identical row mutation when setGlobal is called with null as when unsetGlobal is called',
    function (): void {
        $storageA = new InMemoryConfigStorage();
        $writerA = buildWriter($storageA);
        $writerA->setGlobal('writer/general.page_size', 42);
        $writerA->setGlobal('writer/general.page_size', null);

        $storageB = new InMemoryConfigStorage();
        $writerB = buildWriter($storageB);
        $writerB->setGlobal('writer/general.page_size', 42);
        $writerB->unsetGlobal('writer/general.page_size');

        // Both should produce the same result: no row (deleted because empty)
        expect($storageA->load('writer/general.page_size'))->toBeNull()
                ->and($storageB->load('writer/general.page_size'))->toBeNull();
    },
);

it(
    'returns successfully when unsetOverride is called for a signature that was never set (idempotent no-op via storage contract)',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $writer = buildWriter($storage);

        $signature = new ScopeSignature(['store' => '99']);

        // Should not throw - storage contract handles the no-op
        $writer->unsetOverride('writer/scoped.value', $signature);

        expect($storage->load('writer/scoped.value'))->toBeNull();
    },
);

it('bumps the row version after a successful write (visible via subsequent load)', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/general.page_size', 10);
    $rowAfterFirst = $storage->load('writer/general.page_size');
    expect($rowAfterFirst)->not->toBeNull()->and($rowAfterFirst->version)->toBe(1);

    $writer->setGlobal('writer/general.page_size', 20);
    $rowAfterSecond = $storage->load('writer/general.page_size');
    expect($rowAfterSecond)->not->toBeNull()->and($rowAfterSecond->version)->toBe(2);
});

it(
    'retries up to 3 times on compareAndSave version conflict before throwing StaleConfigWriteException',
    function (): void {
        $storage = new ContentiousStorage(failCount: 3);
        $writer = buildWriter($storage);

        expect(fn () => $writer->setGlobal('writer/general.page_size', 99))
            ->toThrow(StaleConfigWriteException::class);

        expect($storage->attempts)->toBe(3);
    },
);

it(
    'throws AxisNotDeclaredException when setOverride\'s signature uses an axis not declared on the property',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $writer = buildWriter($storage);

        // writer/general.page_size has no axes declared
        $signature = new ScopeSignature(['store' => '1']);
        expect(fn () => $writer->setOverride('writer/general.page_size', $signature, 42))
            ->toThrow(AxisNotDeclaredException::class);
    },
);

it('throws ConfigNotFoundException when writing to a key that no ConfigDefinition exists for', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    expect(fn () => $writer->setGlobal('nonexistent/key.value', 42))
        ->toThrow(ConfigNotFoundException::class);
});

it('removes a specific per-scope override via unsetOverride leaving other overrides untouched', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $sig1 = new ScopeSignature(['store' => '1']);
    $sig2 = new ScopeSignature(['store' => '2']);
    $writer->setOverride('writer/scoped.value', $sig1, 'store-1-value');
    $writer->setOverride('writer/scoped.value', $sig2, 'store-2-value');
    $writer->unsetOverride('writer/scoped.value', $sig1);

    $row = $storage->load('writer/scoped.value');
    expect($row)->not->toBeNull()
        ->and($row->overrides)->not->toHaveKey($sig1->toString())
        ->and($row->overrides)->toHaveKey($sig2->toString())
        ->and($row->overrides[$sig2->toString()])->toBe('store-2-value');
});
