<?php

declare(strict_types=1);

it('deletes the moved files from packages/catalog/src and packages/catalog/tests, leaving no orphaned copies', function (): void {
    // __DIR__ = packages/catalog-market-category-trees/tests/Unit
    // dirname(__DIR__, 3) = packages/
    // catalog package = packages/catalog
    $catalogRoot = dirname(__DIR__, 3) . '/catalog';

    $movedFiles = [
        'src/Entity/CategoryTreeMarketAssignment.php',
        'src/Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php',
        'src/Repositories/CategoryTreeMarketAssignmentRepository.php',
        'src/Exceptions/TreeHasMarketAssignmentsException.php',
        'tests/Support/FakeCategoryTreeMarketAssignmentRepository.php',
        'tests/Unit/Entity/CategoryTreeMarketAssignmentTest.php',
        'tests/Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php',
        'tests/Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php',
        'tests/Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php',
    ];

    foreach ($movedFiles as $file) {
        expect(file_exists($catalogRoot . '/' . $file))
            ->toBeFalse("File $file should have been deleted from packages/catalog/ but still exists");
    }
});
