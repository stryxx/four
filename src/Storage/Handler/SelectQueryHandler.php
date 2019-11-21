<?php

declare(strict_types=1);

namespace Bolt\Storage\Handler;

use Bolt\Entity\Content;
use Bolt\Storage\ContentQueryParser;
use Bolt\Storage\SelectQuery;
use Doctrine\ORM\Query;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;

/**
 *  Handler class to perform select query and return a resultset.
 */
class SelectQueryHandler
{
    /**
     * @return Content|Pagerfanta|null
     */
    public function __invoke(ContentQueryParser $contentQuery)
    {
        $repo = $contentQuery->getContentRepository();
        $qb = $repo->getQueryBuilder();

        /** @var SelectQuery $selectQuery */
        $selectQuery = $contentQuery->getService('select');
        $selectQuery->setSingleFetchMode(false);

        $selectQuery->setQueryBuilder($qb);
        $selectQuery->setContentTypeFilter($contentQuery->getContentTypes());
        $selectQuery->setParameters($contentQuery->getParameters());

        $contentQuery->runScopes($selectQuery);

        // This is required. Not entirely sure why.
        $selectQuery->build();

        // Bolt4 introduces an extra table for field values, so additional
        // joins are required.
        $selectQuery->doReferenceJoins();
        $selectQuery->doFieldJoins();

        $contentQuery->runDirectives($selectQuery);

        if ($selectQuery->getSingleFetchMode()) {
            return $qb
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        $query = $qb->getQuery();

        $amountPerPage = (int) $contentQuery->getDirective('limit');

        return $this->createPaginator($query, $amountPerPage);
    }

    private function createPaginator(Query $query, int $amountPerPage): Pagerfanta
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query, true, true));
        $paginator->setMaxPerPage($amountPerPage);

        $request = Request::createFromGlobals();
        $page = (int) $request->get('page', 1);

        // If we have multiple pagers on page, we shouldn't allow one of the
        // pagers to go over the maximum, thereby throwing an exception. In this
        // case, this specific pager show stay on the last page.
        $paginator->setCurrentPage(min($page, $paginator->getNbPages()));

        return $paginator;
    }
}
