<?php

declare(strict_types=1);

namespace Bolt\Storage\Query\Directive;

use Bolt\Storage\Query\QueryInterface;

/**
 *  Directive to alter query based on 'order' parameter.
 *
 *  eg: 'pages', ['order'=>'-publishedAt']
 */
class OrderDirective
{
    private $coreFields = [
        'id',
        'createdAt',
        'modifiedAt',
        'publishedAt',
        'depublishedAt',
        'author',
        'status',
    ];

    public function __invoke(QueryInterface $query, string $order): void
    {
        if ($order === '') {
            return;
        }

        // remove default order
        $query->getQueryBuilder()->resetDQLPart('orderBy');

        $separatedOrders = $this->getOrderBys($order);
        $index = 1;
        foreach ($separatedOrders as $order) {
            $order = trim($order);
            if (mb_strpos($order, '-') === 0) {
                $direction = 'DESC';
                $order = mb_substr($order, 1);
            } elseif (mb_strpos($order, ' DESC') !== false) {
                $direction = 'DESC';
                $order = str_replace(' DESC', '', $order);
            } else {
                $direction = null;
            }

            if (in_array($order, $this->coreFields, true)) {
                $query->getQueryBuilder()->addOrderBy('content.' . $order, $direction);
            } else {
                $fieldsAlias = 'fields_order_' . $index;
                $fieldAlias = 'order_' . $index;
                $query
                    ->getQueryBuilder()
                    ->leftJoin('content.fields', $fieldsAlias)
                    ->andWhere($fieldsAlias . '.name = :' . $fieldAlias)
                    ->addOrderBy($fieldsAlias . '.value', $direction)
                    ->setParameter($fieldAlias, $order);

                ++$index;
            }
        }
    }

    protected function getOrderBys(string $order): array
    {
        $separatedOrders = [$order];

        if ($this->isMultiOrderQuery($order)) {
            $separatedOrders = explode(',', $order);
        }

        return $separatedOrders;
    }

    protected function isMultiOrderQuery(string $order): bool
    {
        return mb_strpos($order, ',') !== false;
    }
}
