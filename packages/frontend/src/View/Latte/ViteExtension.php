<?php

declare(strict_types=1);

namespace Markommerce\Frontend\View\Latte;

use Latte\Extension;
use Latte\Runtime\Html;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Vite\Vite;
use Markommerce\Frontend\Exceptions\ViteHelperException;

class ViteExtension extends Extension
{
    public function __construct(
        private readonly Vite $vite,
        private readonly ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        return [
            'vite' => fn (?string $entry = null): Html => $this->vite($entry),
        ];
    }

    /**
     * @throws ViteHelperException
     */
    public function vite(?string $entry = null): Html
    {
        if ($entry !== null && $entry === '') {
            throw ViteHelperException::emptyEntry();
        }

        return new Html($this->vite->headTags($entry ?? $this->configRepository->getString('vite.entry')));
    }
}
