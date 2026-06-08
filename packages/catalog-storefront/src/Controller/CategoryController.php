<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;

class CategoryController
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private ?PaginationOptionsResolver $paginationOptionsResolver = null,
        private ?ConfigResolverInterface $configResolver = null,
        private ?CategoryAssignmentService $categoryAssignmentService = null,
    ) {}

    #[Get('/catalog/category/{id}')]
    public function show(
        int $id,
        Request $request,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return Response::html('', 404);
        }

        $page = max(1, (int) ($request->query('page') ?? 1));
        $size = (int) ($request->query('size') ?? 0);
        $sort = (string) ($request->query('sort') ?? '');

        $resolvedOptions = null;

        if ($this->paginationOptionsResolver !== null) {
            try {
                $resolvedOptions = $this->paginationOptionsResolver->resolve(
                    $page,
                    $size > 0 ? $size : null,
                    $sort !== '' ? $sort : null,
                );
            } catch (PageDepthExceededException) {
                return Response::html('', 410);
            }
        }

        $viewAll = $this->isViewAllActive($id, $request, $resolvedOptions);
        $canonicalUrl = $this->buildCanonicalUrl($request, $id, $page, $size, $sort, $resolvedOptions, $viewAll);

        return new Response(
            body: '',
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=utf-8',
                'Link'         => '<' . $canonicalUrl . '>; rel="canonical"',
            ],
        );
    }

    private function buildCanonicalUrl(
        Request $request,
        int $id,
        int $page,
        int $size,
        string $sort,
        ?ResolvedPaginationOptions $resolvedOptions,
        bool $viewAll = false,
    ): string {
        $host = $request->header('Host') ?? 'localhost';
        $scheme = ($request->header('X-Forwarded-Proto') ?? 'http');
        $basePath = '/catalog/category/' . $id;

        if ($viewAll) {
            return $scheme . '://' . $host . $basePath . '?view=all';
        }

        $params = [];

        if ($page > 1) {
            $params['page'] = $page;
        }

        // Normalize size: omit if it equals the resolved default (or if zero/not set)
        if ($resolvedOptions !== null) {
            $defaultOptions = null;
            try {
                $defaultOptions = $this->paginationOptionsResolver?->resolve(null, null, null);
            } catch (PageDepthExceededException) {
                // Should not happen for page 1
            }

            $defaultSize = $defaultOptions?->pageRequest->size ?? 0;
            $resolvedSize = $resolvedOptions->pageRequest->size;
            if ($resolvedSize !== $defaultSize) {
                $params['size'] = $resolvedSize;
            }

            $defaultSort = $defaultOptions?->pageRequest->sort->fields[0]->column ?? '';
            $resolvedSort = $resolvedOptions->pageRequest->sort->fields[0]->column ?? '';
            if ($resolvedSort !== '' && $resolvedSort !== $defaultSort) {
                $params['sort'] = $resolvedSort;
            }
        } elseif ($size > 0) {
            $params['size'] = $size;
            if ($sort !== '') {
                $params['sort'] = $sort;
            }
        }

        $query = $params !== [] ? '?' . http_build_query($params) : '';

        return $scheme . '://' . $host . $basePath . $query;
    }

    private function isViewAllActive(
        int $categoryId,
        Request $request,
        ?ResolvedPaginationOptions $resolvedOptions,
    ): bool {
        if (!$this->viewAllEnabled()) {
            return false;
        }

        $viewAllRequested = $request->query('view') === 'all';
        $threshold = $this->getViewAllThreshold();

        // view=all explicitly requested — honor it if threshold allows
        if ($viewAllRequested) {
            return true;
        }

        // Paginated page: check if the category qualifies for view-all
        // (total products <= threshold), so canonical points to view=all
        if ($this->categoryAssignmentService !== null && $resolvedOptions !== null) {
            try {
                $defaultOptions = $this->paginationOptionsResolver?->resolve(null, null, null);
                if ($defaultOptions !== null) {
                    $resultPage = $this->categoryAssignmentService->paginatedProductsInCategory(
                        $categoryId,
                        $defaultOptions,
                    );
                    if ($resultPage instanceof RandomAccessPageInterface) {
                        return $resultPage->totalItems() <= $threshold;
                    }
                }
            } catch (PageDepthExceededException) {
                // Should not happen for page 1
            }
        }

        return false;
    }

    private function viewAllEnabled(): bool
    {
        if ($this->configResolver === null) {
            return false;
        }

        return $this->getViewAllThreshold() > 0;
    }

    private function getViewAllThreshold(): int
    {
        if ($this->configResolver === null) {
            return 0;
        }

        /** @var int $threshold */
        $threshold = $this->configResolver->resolved(CatalogPaginationConfig::class, 'viewAllThreshold');

        return $threshold;
    }

    /**
     * @throws PageDepthExceededException
     */
    #[Get('/catalog/category/{id}/page')]
    public function pageFragment(
        int $id,
        Request $request,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return Response::html('', 404);
        }

        $page = (int) ($request->query('page') ?? 1);
        $size = (int) ($request->query('size') ?? 0);
        $sort = (string) ($request->query('sort') ?? '');

        if ($this->paginationOptionsResolver !== null) {
            try {
                $this->paginationOptionsResolver->resolve(
                    $page > 0 ? $page : null,
                    $size > 0 ? $size : null,
                    $sort !== '' ? $sort : null,
                );
            } catch (PageDepthExceededException) {
                return Response::html('', 410);
            }
        }

        return Response::html('', 200);
    }
}
