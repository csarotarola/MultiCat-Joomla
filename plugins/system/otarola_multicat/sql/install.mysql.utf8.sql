CREATE TABLE IF NOT EXISTS `#__content_multicat` (
    `content_id` INT UNSIGNED NOT NULL,
    `catid` INT UNSIGNED NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`content_id`, `catid`),
    KEY `idx_content_multicat_catid` (`catid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
