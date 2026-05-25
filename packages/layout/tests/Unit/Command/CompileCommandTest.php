<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Layout\Cache\ArtifactWriterInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Command\CompileCommand;
use Markommerce\Layout\Compiler\CompilerInterface;
use Markommerce\Layout\Exceptions\LayoutException;

// =============================================================================
// Fakes
// =============================================================================

class FakeCompiler implements CompilerInterface
{
    public bool $compiled = false;

    public ?LayoutException $throwOnCompile = null;

    /** @var array<string, PreparedTree> */
    public array $result = [];

    /**
     * @return array<string, PreparedTree>
     *
     * @throws LayoutException
     */
    public function compile(): array
    {
        if ($this->throwOnCompile !== null) {
            throw $this->throwOnCompile;
        }

        $this->compiled = true;

        return $this->result;
    }
}

class FakeArtifactWriter implements ArtifactWriterInterface
{
    public bool $written = false;

    /** @var array<string, PreparedTree>|null */
    public ?array $lastTrees = null;

    public string $path = '/fake/path/layouts.php';

    /**
     * @param array<string, PreparedTree> $trees
     */
    public function write(array $trees): void
    {
        $this->written = true;
        $this->lastTrees = $trees;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

// =============================================================================
// Helpers
// =============================================================================

function captureOutput(callable $callback): string
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);
    $output = new Output($stream);
    $callback($output);
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return $content !== false ? $content : '';
}

// =============================================================================
// Requirement 1: it registers a layout:compile command
// =============================================================================

it('registers a layout:compile command', function (): void {
    $reflection = new ReflectionClass(CompileCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $commandAttr = $attributes[0]->newInstance();
    expect($commandAttr->name)->toBe('layout:compile');

    expect(CompileCommand::class)->toImplement(CommandInterface::class);
});

// =============================================================================
// Requirement 2: it compiles all discovered layouts into the artifact file
// =============================================================================

it('compiles all discovered layouts into the artifact file', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);
    $output = new Output(fopen('php://memory', 'r+'));

    $command->execute($input, $output);

    expect($fakeCompiler->compiled)->toBeTrue();
    expect($fakeWriter->written)->toBeTrue();
});

// =============================================================================
// Requirement 3: it returns exit code zero on a successful compile
// =============================================================================

it('returns exit code zero on a successful compile', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);
    $output = new Output(fopen('php://memory', 'r+'));

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

// =============================================================================
// Requirement 4: it prints a summary of compiled handles on success
// =============================================================================

it('prints a summary of compiled handles on success', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();
    $fakeWriter->path = '/var/cache/markommerce/layouts.php';

    $tree1 = new PreparedTree(
        handleKey: 'App\Controller\HomeController::index',
        template: 'theme::layout',
        slots: [],
        context: []
    );
    $tree2 = new PreparedTree(
        handleKey: 'App\Controller\ProductController::show',
        template: 'theme::layout',
        slots: [],
        context: []
    );
    $fakeCompiler->result = ['App\Controller\HomeController::index' => $tree1, 'App\Controller\ProductController::show' => $tree2];

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);

    $content = captureOutput(function (Output $output) use ($command, $input): void {
        $command->execute($input, $output);
    });

    expect($content)->toContain('2');
    expect($content)->toContain('/var/cache/markommerce/layouts.php');
});

// =============================================================================
// Requirement 5: it returns a non-zero exit code when a layout fails validation
// =============================================================================

it('returns a non-zero exit code when a layout fails validation', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();

    $fakeCompiler->throwOnCompile = new LayoutException(
        message: 'Duplicate name found.',
        context: 'handle: test::handle',
        suggestion: 'Rename the duplicate.',
    );

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);
    $output = new Output(fopen('php://memory', 'r+'));

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->not->toBe(0);
});

// =============================================================================
// Requirement 6: it prints the message, context and suggestion of a compile error
// =============================================================================

it('prints the message, context and suggestion of a compile error', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();

    $fakeCompiler->throwOnCompile = new LayoutException(
        message: 'Duplicate placement name.',
        context: 'handle: catalog.list',
        suggestion: 'Rename one of the placements.',
    );

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);

    $content = captureOutput(function (Output $output) use ($command, $input): void {
        $command->execute($input, $output);
    });

    expect($content)->toContain('Duplicate placement name.');
    expect($content)->toContain('handle: catalog.list');
    expect($content)->toContain('Rename one of the placements.');
});

// =============================================================================
// Requirement 7: it does not write an artifact when compilation fails
// =============================================================================

it('does not write an artifact when compilation fails', function (): void {
    $fakeCompiler = new FakeCompiler();
    $fakeWriter = new FakeArtifactWriter();

    $fakeCompiler->throwOnCompile = new LayoutException(
        message: 'Some compile error.',
        context: 'ctx',
        suggestion: 'Fix it.',
    );

    $command = new CompileCommand($fakeCompiler, $fakeWriter);
    $input = new Input(['marko', 'layout:compile']);
    $output = new Output(fopen('php://memory', 'r+'));

    $command->execute($input, $output);

    expect($fakeWriter->written)->toBeFalse();
});
