<?php

declare(strict_types=1);

namespace Joomla\Component\Content\Site\Model;

use Joomla\Plugin\System\OtarolaMulticat\Helper\MulticatHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * Override for the frontend ArticlesModel that honours multi-category assignments.
 */
class ArticlesModel extends BaseArticlesModel
{
    private const MULTICAT_ALIAS = 'omc';

    /**
     * @var int[]|null
     */
    private ?array $otarolaCategoryFilter = null;

    private bool $otarolaIncludeChildren = false;

    private ?int $otarolaMaxDepth = null;

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        parent::populateState($ordering, $direction);

        $filter = $this->state->get('filter.category_id');

        if ($filter === null || $filter === '' || $filter === []) {
            $this->otarolaCategoryFilter = null;

            return;
        }

        $categoryIds = ArrayHelper::toInteger(is_array($filter) ? $filter : [$filter]);
        $categoryIds = array_values(array_unique(array_filter(
            $categoryIds,
            static fn (int $id): bool => $id > 0
        )));

        if ($categoryIds === []) {
            $this->otarolaCategoryFilter = [];

            return;
        }

        $this->otarolaCategoryFilter = $categoryIds;
        $this->otarolaIncludeChildren = (bool) $this->state->get('filter.subcategories', false);

        $maxLevel = $this->state->get('filter.max_category_levels');
        $maxLevel = is_numeric($maxLevel) ? (int) $maxLevel : null;

        if ($maxLevel === null || $maxLevel <= 0) {
            $this->otarolaMaxDepth = $this->otarolaIncludeChildren ? null : 0;
        } else {
            $this->otarolaMaxDepth = $maxLevel;
        }

        $this->state->set('filter.category_id', null);
    }

    /**
     * {@inheritDoc}
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();

        if ($this->otarolaCategoryFilter === null) {
            return $query;
        }

        $db = $this->getDatabase();
        $categoryIds = $this->otarolaCategoryFilter;

        $categoryIds = MulticatHelper::expandWithChildren(
            $db,
            $categoryIds,
            $this->otarolaIncludeChildren,
            $this->otarolaMaxDepth
        );

        $categoryIds = array_values(array_unique(array_filter(
            $categoryIds,
            static fn (int $id): bool => $id > 0
        )));

        if ($categoryIds === []) {
            $query->where('1 = 0');

            return $query;
        }

        $idList = implode(', ', $categoryIds);

        $query->join(
            'LEFT',
            $db->quoteName('#__content_multicat', self::MULTICAT_ALIAS),
            $db->quoteName(self::MULTICAT_ALIAS . '.content_id') . ' = ' . $db->quoteName('a.id')
        );

        $condition = '('
            . $db->quoteName('a.catid') . ' IN (' . $idList . ')'
            . ' OR '
            . $db->quoteName(self::MULTICAT_ALIAS . '.catid') . ' IN (' . $idList . ')'
            . ')';

        $query->where($condition);
        $query->group($db->quoteName('a.id'));

        $this->state->set('filter.category_id', $this->otarolaCategoryFilter);

        return $query;
    }
}
