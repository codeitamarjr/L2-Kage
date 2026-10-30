DROP TABLE IF EXISTS `relics`;
CREATE TABLE `relics` (
  `accountName` varchar(45) NOT NULL DEFAULT '',
  `relicId` int(11) unsigned NOT NULL DEFAULT 0,
  `relicLevel` int(11) unsigned NOT NULL DEFAULT 0,
  `relicCount` int(11) unsigned NOT NULL,
  `relicIndex` int(11) unsigned NOT NULL DEFAULT 0,
  `relicSummonTime` bigint(13) NOT NULL DEFAULT 0,
  `replaceAttemptsUsed` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`accountName`,`relicId`,`relicIndex`)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;