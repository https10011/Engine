-- ---------------------------------------------------------------------------
-- Operator controls that are independent of the probability profile.
--
-- A game with no row here is available and not marked friendly-play. The
-- missing row is the default, so the table starts empty and no game has to be
-- seeded before the application can run.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `game_controls` (
    `game_id`       VARCHAR(64)     NOT NULL,
    `available`     TINYINT(1)      NOT NULL DEFAULT 1,
    `friendly_play` TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_by`    BIGINT UNSIGNED NULL,
    `updated_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`game_id`),
    KEY `idx_game_controls_available` (`available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
