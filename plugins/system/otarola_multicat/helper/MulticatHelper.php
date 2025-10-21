<?php

declare(strict_types=1);

namespace Joomla\Plugin\System\OtarolaMulticat\Helper;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

/**
 * Helper utilities for handling the multi-category bridge table and lookups.
 */
final class MulticatHelper
{
    private const EXTENSION = 'com_content';

    private function __construct()
    {
        // Prevent instantiation.
    }

    /**
     * Fetches the additional category identifiers linked to an article.
     *
     * @param  DatabaseInterface  $db        Database connector.
     * @param  int                $contentId Article identifier.
     *
     * @return int[]
     */
    public static function getAdditionalCategoryIds(DatabaseInterface $db, int $contentId): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__content_multicat'))
            ->where($db->quoteName('content_id') . ' = :contentId')
            ->bind(':contentId', $contentId, ParameterType::INTEGER);

        $db->setQuery($query);

        $ids = $db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Persists the additional categories for an article.
     *
     * @param  DatabaseInterface  $db          Database connector.
     * @param  int                $contentId   Article identifier.
     * @param  array<int, int>    $categoryIds Category identifiers to store.
     */
    public static function saveAdditionalCategories(DatabaseInterface $db, int $contentId, array $categoryIds): void
    {
        $cleanIds = array_values(array_unique(array_filter(
            ArrayHelper::toInteger($categoryIds),
            static fn (int $id): bool => $id > 0
        )));

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__content_multicat'))
            ->where($db->quoteName('content_id') . ' = :contentId')
            ->bind(':contentId', $contentId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        if ($cleanIds === []) {
            return;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__content_multicat'))
            ->columns([
                $db->quoteName('content_id'),
                $db->quoteName('catid'),
            ]);

        foreach ($cleanIds as $categoryId) {
            $query->values((int) $contentId . ', ' . (int) $categoryId);
        }

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Returns the categories for com_content ordered by nested set position.
     *
     * @param  DatabaseInterface  $db Database connector.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getContentCategories(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('level'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('published') . ' <> -2')
            ->order($db->quoteName('lft') . ' ASC')
            ->bind(':extension', self::EXTENSION);

        $db->setQuery($query);

        $rows = $db->loadAssocList() ?: [];

        $options = [];

        foreach ($rows as $row) {
            $title = (string) $row['title'];
            $level = (int) $row['level'];
            $published = (int) $row['published'];
            $prefix = str_repeat('— ', max(0, $level - 1));
            $label = trim($prefix . $title);

            if ($published === 0) {
                $label .= ' [' . Text::_('JUNPUBLISHED') . ']';
            }

            $options[] = [
                'value' => (int) $row['id'],
                'text' => $label,
            ];
        }

        return $options;
    }

    /**
     * Expands the provided category identifiers with their descendants when required.
     *
     * @param  DatabaseInterface  $db             Database connector.
     * @param  int[]              $categoryIds    Base categories.
     * @param  bool               $includeChildren Whether descendants should be included.
     * @param  int|null           $maxDepth       Optional depth limit. Null means unlimited.
     *
     * @return int[]
     */
    public static function expandWithChildren(
        DatabaseInterface $db,
        array $categoryIds,
        bool $includeChildren,
        ?int $maxDepth = null
    ): array {
        $categoryIds = array_values(array_unique(array_filter(
            ArrayHelper::toInteger($categoryIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($categoryIds === []) {
            return [];
        }

        if (!$includeChildren) {
            return $categoryIds;
        }

        $placeholders = implode(', ', $categoryIds);

        $query = $db->getQuery(true)
            ->clear('select')
            ->select('DISTINCT ' . $db->quoteName('child.id'))
            ->from($db->quoteName('#__categories', 'parent'))
            ->join(
                'INNER',
                $db->quoteName('#__categories', 'child'),
                $db->quoteName('child.lft') . ' BETWEEN ' . $db->quoteName('parent.lft')
                    . ' AND ' . $db->quoteName('parent.rgt')
            )
            ->where($db->quoteName('parent.id') . ' IN (' . $placeholders . ')')
            ->where($db->quoteName('parent.extension') . ' = :extensionParent')
            ->where($db->quoteName('child.extension') . ' = :extensionChild')
            ->bind(':extensionParent', self::EXTENSION)
            ->bind(':extensionChild', self::EXTENSION);

        if ($maxDepth !== null && $maxDepth >= 0) {
            $query->where(
                $db->quoteName('child.level') . ' <= ' . $db->quoteName('parent.level') . ' + ' . (int) $maxDepth
            );
        }

        $db->setQuery($query);

        $descendants = $db->loadColumn() ?: [];

        $combined = array_merge($categoryIds, array_map('intval', $descendants));

        return array_values(array_unique($combined));
    }
}
