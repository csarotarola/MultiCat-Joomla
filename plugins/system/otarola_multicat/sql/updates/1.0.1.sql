ALTER TABLE `#__content_multicat`
    ADD COLUMN IF NOT EXISTS `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `catid`;

ALTER TABLE `#__content_multicat`
    ADD INDEX IF NOT EXISTS `idx_content_multicat_catid` (`catid`);
