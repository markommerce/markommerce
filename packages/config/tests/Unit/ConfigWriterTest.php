<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

// --- Fixture config classes ---

class WriterGlobalConfig
{
    #[Config(key: 'writer/general.page_size')]
    public int $pageSize = 10;
}

// --- Helpers ---

function buildWriterRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [WriterGlobalConfig::class],
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

it('declares only setGlobal and unsetGlobal methods on ConfigWriterInterface after task completes', function (): void {
    $reflection = new ReflectionClass(ConfigWriterInterface::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('setGlobal')
        ->and($methods)->toContain('unsetGlobal')
        ->and(count($methods))->toBe(2);
});

it('does not declare a setOverride or unsetOverride method on ConfigWriterInterface', function (): void {
    $reflection = new ReflectionClass(ConfigWriterInterface::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->not->toContain('setOverride')
        ->and($methods)->not->toContain('unsetOverride');
});

it('persists a new global value via ConfigWriter setGlobal when the key has no existing row', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/general.page_size', 25);

    $row = $storage->load('writer/general.page_size');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBe(25);
});

it('replaces an existing global value via ConfigWriter setGlobal preserving version increments', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/general.page_size', 10);
    $writer->setGlobal('writer/general.page_size', 20);

    $row = $storage->load('writer/general.page_size');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBe(20)
        ->and($row->version)->toBe(2);
});

it('removes the global value via ConfigWriter unsetGlobal', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildWriter($storage);

    $writer->setGlobal('writer/general.page_size', 42);
    $writer->unsetGlobal('writer/general.page_size');

    expect($storage->load('writer/general.page_size'))->toBeNull();
});

it('bumps the row version after a successful write to ConfigWriter', function (): void {
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
    'retries up to 3 times on compareAndSave conflict before throwing StaleConfigWriteException from ConfigWriter',
    function (): void {
        $storage = new ContentiousStorage(failCount: 3);
        $writer = buildWriter($storage);
    
        expect(fn () => $writer->setGlobal('writer/general.page_size', 99))
            ->toThrow(StaleConfigWriteException::class);
    
        expect($storage->attempts)->toBe(3);
    }
);

it(
    'throws ConfigNotFoundException from ConfigWriter setGlobal when the key is not in the registry',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $writer = buildWriter($storage);
    
        expect(fn () => $writer->setGlobal('nonexistent/key.value', 42))
            ->toThrow(ConfigNotFoundException::class);
    }
);

it(
    'declares ConfigWriter\'s constructor-promoted properties (registry, storage, cipher) with protected visibility (verified via reflection)',
    function (): void {
        $reflection = new ReflectionClass(ConfigWriter::class);
    
        $registryProp = $reflection->getProperty('registry');
        $storageProp = $reflection->getProperty('storage');
        $cipherProp = $reflection->getProperty('cipher');
    
        expect($registryProp->isProtected())->toBeTrue()
            ->and($storageProp->isProtected())->toBeTrue()
            ->and($cipherProp->isProtected())->toBeTrue();
    }
);
