-- ---------------------------------------------------------------------------
-- Probability configuration, versioning and audit.
--
-- The directive requires centralised control of game odds that the engine
-- actually applies. This migration adds the storage for it.
--
-- Conventions match the initial schema: InnoDB, utf8mb4, UTC DATETIME, and
-- probabilities stored as DECIMAL rather than FLOAT so a value round-trips
-- exactly and compares deterministically.
--
-- VIRTUAL CREDITS ONLY. Nothing here is a payment, deposit or withdrawal.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `probability_profiles` (
    `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                      VARCHAR(64)     NOT NULL,
    `game_id`                   VARCHAR(64)     NULL,
    `any_win_target`            DECIMAL(6,5)    NULL,
    `net_positive_target`       DECIMAL(6,5)    NULL,
    `rtp_target`                DECIMAL(7,4)    NULL,
    `jackpot_probability`       DECIMAL(9,7)    NULL,
    `bonus_trigger_probability` DECIMAL(6,5)    NULL,
    `free_spin_trigger_probability` DECIMAL(6,5) NULL,
    `payout_scale`              DECIMAL(7,4)    NULL,
    `jackpot_tiers`             JSON            NULL,
    `enabled`                   TINYINT(1)      NOT NULL DEFAULT 0,
    `max_resample_attempts`     SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    `version`                   INT UNSIGNED    NOT NULL DEFAULT 1,
    `created_at`                DATETIME        NOT NULL,
    `updated_at`                DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_probability_profiles_name` (`name`),
    KEY `idx_probability_profiles_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable snapshot of a profile at each save. History is append-only: a
-- change creates a new version rather than overwriting the previous one, so a
-- previous configuration can always be inspected and restored.
CREATE TABLE IF NOT EXISTS `probability_versions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profile_name` VARCHAR(64)     NOT NULL,
    `version`      INT UNSIGNED    NOT NULL,
    `snapshot`     JSON            NOT NULL,
    `created_by`   BIGINT UNSIGNED NULL,
    `created_at`   DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_probability_versions_name_version` (`profile_name`, `version`),
    KEY `idx_probability_versions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only audit trail of every configuration change.
CREATE TABLE IF NOT EXISTS `probability_audit_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_user_id` BIGINT UNSIGNED NULL,
    `admin_username` VARCHAR(64)    NOT NULL,
    `action`        VARCHAR(64)     NOT NULL,
    `profile_name`  VARCHAR(64)     NOT NULL,
    `affected_games` JSON           NULL,
    `before_state`  JSON            NULL,
    `after_state`   JSON            NULL,
    `description`   VARCHAR(255)    NOT NULL DEFAULT '',
    `version`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_probability_audit_profile_created` (`profile_name`, `created_at`),
    KEY `idx_probability_audit_admin` (`admin_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recorded jackpot triggers, kept separate from ordinary wins so the two are
-- never conflated in reporting.
CREATE TABLE IF NOT EXISTS `jackpot_events` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id`       VARCHAR(64)     NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `tier`          VARCHAR(32)     NOT NULL DEFAULT 'default',
    `probability`   DECIMAL(9,7)    NOT NULL DEFAULT 0,
    `payout_multiplier` DECIMAL(12,4) NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_jackpot_events_game_created` (`game_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
