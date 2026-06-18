<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

// --- Fixture config classes ---

class SetCommandStringConfig
{
    #[Config(key: 'cli/set.string_val')]
    public string $stringVal = 'default';
}

class SetCommandIntConfig
{
    #[Config(key: 'cli/set.int_val')]
    public int $intVal = 10;
}

class SetCommandFloatConfig
{
    #[Config(key: 'cli/set.float_val')]
    public float $floatVal = 1.5;
}

class SetCommandBoolConfig
{
    #[Config(key: 'cli/set.bool_val')]
    public bool $boolVal = true;
}

class SetCommandArrayConfig
{
    #[Config(key: 'cli/set.array_val')]
    public array $arrayVal = [];
}

enum SetCommandColor: string
{
    case Red = 'red';
    case Blue = 'blue';
}

class SetCommandEnumConfig
{
    #[Config(key: 'cli/set.enum_val')]
    public SetCommandColor $enumVal = SetCommandColor::Red;
}

// --- Helpers ---

function buildSetCommandRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [
            SetCommandStringConfig::class,
            SetCommandIntConfig::class,
            SetCommandFloatConfig::class,
            SetCommandBoolConfig::class,
            SetCommandArrayConfig::class,
            SetCommandEnumConfig::class,
        ],
    );
}

function buildSetCommand(InMemoryConfigStorage $storage): SetCommand
{
    $registry = buildSetCommandRegistry();
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );

    return new SetCommand(
        registry: $registry,
        writer: $writer,
    );
}

function makeSetInput(string ...$args): Input
{
    return new Input(['marko', 'config:set', ...$args]);
}

function captureSetOutput(SetCommand $command, Input $input): array
{
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $exitCode = $command->execute($input, $output);
    rewind($stream);
    $text = stream_get_contents($stream);
    fclose($stream);

    return ['exitCode' => $exitCode, 'output' => $text];
}

// --- Tests ---

it('sets a global value via the SetCommand without parsing any scope option', function (): void {
    $storage = new InMemoryConfigStorage();
    $command = buildSetCommand($storage);

    $input = makeSetInput('cli/set.string_val', 'hello-world');
    $result = captureSetOutput($command, $input);

    expect($result['exitCode'])->toBe(0);

    $row = $storage->load('cli/set.string_val');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('hello-world');
});

it(
    'ignores any --scope option passed to SetCommand execute and treats the call as a global write (descoped command no longer reads the option)',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $command = buildSetCommand($storage);

        // Pass --scope option — must be silently ignored and treated as a global write
        $input = makeSetInput('cli/set.string_val', 'store-value', '--scope=store=1');
        $result = captureSetOutput($command, $input);

        expect($result['exitCode'])->toBe(0);

        $row = $storage->load('cli/set.string_val');
        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('store-value');
    },
);

it('parses the shell <value> argument according to the property\'s declared type', function (
    string $key,
    string $rawValue,
    mixed $expectedParsed,
): void {
    $storage = new InMemoryConfigStorage();
    $command = buildSetCommand($storage);

    $input = makeSetInput($key, $rawValue);
    $result = captureSetOutput($command, $input);

    expect($result['exitCode'])->toBe(0);

    $row = $storage->load($key);
    expect($row)->not->toBeNull()
        ->and($row->value)->toEqual($expectedParsed);
})->with([
    'int' => ['cli/set.int_val', '42', 42],
    'float' => ['cli/set.float_val', '3.14', 3.14],
    'bool true' => ['cli/set.bool_val', 'true', true],
    'bool false' => ['cli/set.bool_val', 'false', false],
    'bool yes' => ['cli/set.bool_val', 'yes', true],
    'bool no' => ['cli/set.bool_val', 'no', false],
    'bool 1' => ['cli/set.bool_val', '1', true],
    'bool 0' => ['cli/set.bool_val', '0', false],
    'array json object' => ['cli/set.array_val', '{"a":1}', ['a' => 1]],
    'array json list' => ['cli/set.array_val', '[1,2,3]', [1, 2, 3]],
    'enum' => ['cli/set.enum_val', 'blue', SetCommandColor::Blue],
    'string passthrough' => ['cli/set.string_val', 'hello', 'hello'],
]);

// --- Contention storage for StaleConfigWriteException tests ---

class SetCommandContentiousStorage implements ConfigStorageInterface
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

it('rejects an unparseable value with a clear error citing the expected type', function (
    string $key,
    string $rawValue,
    string $expectedType,
): void {
    $storage = new InMemoryConfigStorage();
    $command = buildSetCommand($storage);

    $input = makeSetInput($key, $rawValue);
    $result = captureSetOutput($command, $input);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain($expectedType);

    // Value must not be persisted
    expect($storage->load($key))->toBeNull();
})->with([
    'int invalid' => ['cli/set.int_val', 'not-a-number', 'int'],
    'float invalid' => ['cli/set.float_val', 'not-a-float', 'float'],
    'bool invalid' => ['cli/set.bool_val', 'maybe', 'bool'],
    'array invalid json' => ['cli/set.array_val', 'not-json', 'array'],
    'enum invalid' => ['cli/set.enum_val', 'purple', SetCommandColor::class],
]);

it('exits non-zero on StaleConfigWriteException without retrying', function (): void {
    // Writer already retries 3 times; if all fail, it throws StaleConfigWriteException.
    // The CLI command must catch it and exit non-zero — no additional retries.
    $storage = new SetCommandContentiousStorage(failCount: 3); // exhaust all writer retries
    $registry = buildSetCommandRegistry();
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );
    $command = new SetCommand(registry: $registry, writer: $writer);

    $input = makeSetInput('cli/set.string_val', 'hello');
    $result = captureSetOutput($command, $input);

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain('cli/set.string_val');

    // Writer made exactly 3 attempts (its own retry count), CLI did NOT add more
    expect($storage->attempts)->toBe(3);
});
