<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;

/**
 * @extends Repository<CategoryTree>
 */
class CategoryTreeRepository extends Repository implements CategoryTreeRepositoryInterface
{
    protected const string ENTITY_CLASS = CategoryTree::class;

    /**
     * @throws RepositoryException
     */
    public function findByCode(string $code): ?CategoryTree
    {
        /** @var CategoryTree|null */
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @throws DefaultTreeMissingException|RepositoryException
     */
    public function findDefault(): CategoryTree
    {
        /** @var CategoryTree|null */
        $tree = $this->findOneBy(['isDefault' => true]);

        if ($tree === null) {
            throw DefaultTreeMissingException::forResolution();
        }

        return $tree;
    }
}
