-- Rate-limit buckets for the HTTP API.
--
-- A single row per (bucket, window) with an atomic increment. This is the
-- shared-hosting-friendly alternative to Redis/APCu: it needs nothing but the
-- database the engine already has, and the `ON DUPLICATE KEY UPDATE` makes
-- concurrent increments safe. Rows are disposable; a cron purge keeps the
-- table small.

CREATE TABLE IF NOT EXISTS `rate_limit_buckets` (
    `bucket`        VARCHAR(191)    NOT NULL,
    `window_start`  DATETIME        NOT NULL,
    `hits`          INT UNSIGNED    NOT NULL DEFAULT 0,
    `updated_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`bucket`, `window_start`),
    KEY `idx_rate_limit_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
