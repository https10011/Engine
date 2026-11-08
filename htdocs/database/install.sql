-- ---------------------------------------------------------------------------
-- AURUM / Universal Casino Game Engine — complete schema for a fresh database.
--
-- Import this ONE file into the database created by your hosting control panel
-- (phpMyAdmin → Import, or `mysql dbname < install.sql`). It creates every
-- table and records the migrations as applied, so `db:migrate` will report the
-- database as up to date instead of trying to apply them again.
--
-- Import into an EMPTY database, once. It is not a migration for an existing
-- installation; use `php bin/console db:migrate` to upgrade one of those.
--
-- It creates no user account and no demo data. Register the first player in the
-- browser, or use: php bin/console user:create --username=... --password=...
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Migration: 2026_01_01_000001_create_engine_tables.sql
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Universal Casino Game Engine — initial schema.
--
-- Conventions used throughout:
--   * All money is stored as BIGINT in minor units (integer cents of the
--     configured currency). No FLOAT or DOUBLE column ever holds an amount.
--   * All timestamps are UTC.
--   * InnoDB with utf8mb4 so foreign keys and emoji in display names work.
--   * Virtual credits only. There is no deposit, withdrawal or payment table,
--     and none will be added.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `migrations` (
    `version`     VARCHAR(64)  NOT NULL,
    `name`        VARCHAR(191) NOT NULL,
    `applied_at`  DATETIME     NOT NULL,
    `checksum`    CHAR(64)     NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Identity
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)     NOT NULL,
    `display_name`  VARCHAR(128)    NOT NULL,
    `status`        ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
    `created_at`    DATETIME        NOT NULL,
    `updated_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_username` (`username`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_credentials` (
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `algo`          VARCHAR(32)     NOT NULL,
    `updated_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_credentials_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `token_hash`  CHAR(64)        NOT NULL,
    `expires_at`  DATETIME        NOT NULL,
    `revoked_at`  DATETIME        NULL,
    `created_at`  DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_api_tokens_hash` (`token_hash`),
    KEY `idx_api_tokens_user` (`user_id`),
    CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Wallet. One row per user; the balance is the single source of truth and is
-- only ever changed inside a transaction that holds a row lock on it.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallets` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        BIGINT UNSIGNED NOT NULL,
    `currency`       CHAR(3)         NOT NULL,
    `scale`          TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `balance_minor`  BIGINT          NOT NULL DEFAULT 0,
    `version`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`     DATETIME        NOT NULL,
    `updated_at`     DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_wallets_user` (`user_id`),
    CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_wallets_balance_non_negative` CHECK (`balance_minor` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Games registry mirror. The authoritative game list is the PHP registry;
-- this table stores operator-controlled enablement and configuration overrides.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `games` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id`     VARCHAR(64)     NOT NULL,
    `name`        VARCHAR(191)    NOT NULL,
    `category`    ENUM('slot','card','roulette') NOT NULL,
    `version`     VARCHAR(32)     NOT NULL,
    `provider`    VARCHAR(64)     NOT NULL,
    `enabled`     TINYINT(1)      NOT NULL DEFAULT 1,
    `config`      JSON            NULL,
    `created_at`  DATETIME        NOT NULL,
    `updated_at`  DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_games_game_id` (`game_id`),
    KEY `idx_games_category_enabled` (`category`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Sessions
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_sessions` (
    `id`           CHAR(26)        NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `game_id`      VARCHAR(64)     NOT NULL,
    `status`       ENUM('active','closed','expired') NOT NULL DEFAULT 'active',
    `round_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `state`        JSON            NOT NULL,
    `created_at`   DATETIME        NOT NULL,
    `updated_at`   DATETIME        NOT NULL,
    `expires_at`   DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user_game` (`user_id`, `game_id`),
    KEY `idx_sessions_status_expires` (`status`, `expires_at`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Rounds: one row per executed action. This is the game history.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_rounds` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`            CHAR(26)        NOT NULL,
    `user_id`               BIGINT UNSIGNED NOT NULL,
    `game_id`               VARCHAR(64)     NOT NULL,
    `round_number`          INT UNSIGNED    NOT NULL,
    `action`                VARCHAR(32)     NOT NULL,
    `bet_minor`             BIGINT          NOT NULL DEFAULT 0,
    `payout_minor`          BIGINT          NOT NULL DEFAULT 0,
    `net_minor`             BIGINT          NOT NULL DEFAULT 0,
    `payout_multiplier_num` INT UNSIGNED    NOT NULL DEFAULT 0,
    `payout_multiplier_den` INT UNSIGNED    NOT NULL DEFAULT 1,
    `detail`                JSON            NULL,
    `features`              JSON            NULL,
    `created_at`            DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rounds_session_number` (`session_id`, `round_number`),
    KEY `idx_rounds_user_created` (`user_id`, `created_at`),
    KEY `idx_rounds_game_created` (`game_id`, `created_at`),
    CONSTRAINT `fk_rounds_session` FOREIGN KEY (`session_id`)
        REFERENCES `game_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rounds_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Bets and payouts. Kept as separate tables from rounds because a single
-- round can involve more than one wager (blackjack split hands) or more than
-- one payout (insurance plus the main hand).
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bets` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `round_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `amount_minor` BIGINT          NOT NULL,
    `currency`     CHAR(3)         NOT NULL,
    `label`        VARCHAR(32)     NOT NULL DEFAULT 'main',
    `created_at`   DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_bets_round` (`round_id`),
    KEY `idx_bets_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_bets_round` FOREIGN KEY (`round_id`)
        REFERENCES `game_rounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payouts` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `round_id`         BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `amount_minor`     BIGINT          NOT NULL,
    `currency`         CHAR(3)         NOT NULL,
    `multiplier_num`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `multiplier_den`   INT UNSIGNED    NOT NULL DEFAULT 1,
    `label`            VARCHAR(32)     NOT NULL DEFAULT 'main',
    `created_at`       DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_payouts_round` (`round_id`),
    KEY `idx_payouts_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_payouts_round` FOREIGN KEY (`round_id`)
        REFERENCES `game_rounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Wallet ledger. Every balance change writes exactly one row here, inside the
-- same transaction that updates `wallets`. The unique reference prevents the
-- same logical movement from being recorded twice.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wallet_id`           BIGINT UNSIGNED NOT NULL,
    `user_id`             BIGINT UNSIGNED NOT NULL,
    `round_id`            BIGINT UNSIGNED NULL,
    `type`                ENUM('credit','debit') NOT NULL,
    `reason`              VARCHAR(32)     NOT NULL,
    `amount_minor`        BIGINT          NOT NULL,
    `balance_after_minor` BIGINT          NOT NULL,
    `reference`           VARCHAR(128)    NOT NULL,
    `created_at`          DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_wallet_tx_reference` (`reference`),
    KEY `idx_wallet_tx_user_created` (`user_id`, `created_at`),
    KEY `idx_wallet_tx_wallet` (`wallet_id`, `id`),
    CONSTRAINT `fk_wallet_tx_wallet` FOREIGN KEY (`wallet_id`)
        REFERENCES `wallets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wallet_tx_round` FOREIGN KEY (`round_id`)
        REFERENCES `game_rounds` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Idempotency. The unique key is what actually guarantees a retried request
-- cannot debit twice: two concurrent inserts, one wins, the loser reads the
-- stored response instead of re-executing.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`             BIGINT UNSIGNED NOT NULL,
    `game_id`             VARCHAR(64)     NOT NULL,
    `key_hash`            CHAR(64)        NOT NULL,
    `request_fingerprint` CHAR(64)        NOT NULL,
    `status`              ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    `response`            JSON            NULL,
    `round_id`            BIGINT UNSIGNED NULL,
    `created_at`          DATETIME        NOT NULL,
    `expires_at`          DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_idem_user_game_key` (`user_id`, `game_id`, `key_hash`),
    KEY `idx_idem_expires` (`expires_at`),
    CONSTRAINT `fk_idem_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Daily aggregate statistics. Derived from committed rounds; never authored
-- by a client.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `statistics_daily` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        BIGINT UNSIGNED NOT NULL,
    `game_id`        VARCHAR(64)     NOT NULL,
    `day`            DATE            NOT NULL,
    `rounds`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `wagered_minor`  BIGINT          NOT NULL DEFAULT 0,
    `returned_minor` BIGINT          NOT NULL DEFAULT 0,
    `wins`           INT UNSIGNED    NOT NULL DEFAULT 0,
    `losses`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `largest_win_minor` BIGINT       NOT NULL DEFAULT 0,
    `updated_at`     DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_stats_user_game_day` (`user_id`, `game_id`, `day`),
    KEY `idx_stats_game_day` (`game_id`, `day`),
    CONSTRAINT `fk_stats_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Audit trail for security-relevant events (login, token issue, session
-- close, admin action). Deliberately not a general-purpose log sink.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NULL,
    `event`       VARCHAR(64)     NOT NULL,
    `context`     JSON            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `created_at`  DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user_created` (`user_id`, `created_at`),
    KEY `idx_audit_event_created` (`event`, `created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Migration: 2026_01_01_000002_create_rate_limit_buckets.sql
-- ---------------------------------------------------------------------------

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

-- ---------------------------------------------------------------------------
-- Migration: 2026_01_02_000001_create_probability_tables.sql
-- ---------------------------------------------------------------------------

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

-- ---------------------------------------------------------------------------
-- Migration: 2026_01_02_000002_add_admin_role.sql
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Administrator role.
--
-- Administrator authorisation is resolved server-side from this column, never
-- from anything the client sends. A player token cannot become an
-- administrator token by manipulating a request.
-- ---------------------------------------------------------------------------

ALTER TABLE `users`
    ADD COLUMN `role` ENUM('player','admin') NOT NULL DEFAULT 'player' AFTER `status`;

ALTER TABLE `users`
    ADD KEY `idx_users_role` (`role`);

-- ---------------------------------------------------------------------------
-- Migration: 2026_01_02_000003_widen_multiplier_columns.sql
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Widen stored payout multipliers.
--
-- The slot framework keeps payout multipliers as exact rationals, so a win is
-- `numerator / denominator` with no float rounding. Denominators come from the
-- way-combination and cluster evaluations and legitimately reach 2.5e9 and
-- beyond; a `BIGINT`-scale payout then multiplies that again. `INT UNSIGNED`
-- tops out at 4,294,967,295, so the first big win on a ways game fails the
-- insert with "Out of range value for column 'payout_multiplier_num'".
--
-- Found by the all-games integration test, which runs a real round of each of
-- the eighteen games through `RoundService`. The per-game unit tests never hit
-- it because they assert against the returned `Ratio` and never persist.
--
-- Widening the column changes no engine logic and no game mathematics: the
-- stored value is the same rational it always was, it just fits now.
-- ---------------------------------------------------------------------------

ALTER TABLE `game_rounds`
    MODIFY COLUMN `payout_multiplier_num` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    MODIFY COLUMN `payout_multiplier_den` BIGINT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE `payouts`
    MODIFY COLUMN `multiplier_num` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    MODIFY COLUMN `multiplier_den` BIGINT UNSIGNED NOT NULL DEFAULT 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Record what this file applied, so the runtime migration runner agrees.
INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`) VALUES ('2026_01_01_000001_create_engine_tables', '2026_01_01_000001_create_engine_tables.sql', UTC_TIMESTAMP(), '1ef615be78630828cd9f59469f13170e94605815d8e7b9bda91e6ec01fad67dd');
INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`) VALUES ('2026_01_01_000002_create_rate_limit_buckets', '2026_01_01_000002_create_rate_limit_buckets.sql', UTC_TIMESTAMP(), '13273c8ac1595511a5331f11cc4b0c3715f343405338767c08ce13732429bfff');
INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`) VALUES ('2026_01_02_000001_create_probability_tables', '2026_01_02_000001_create_probability_tables.sql', UTC_TIMESTAMP(), '2e0744b48880a32bf4914c9f0586bea410edabecca13ab3d2b6f8c8e2f6d4758');
INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`) VALUES ('2026_01_02_000002_add_admin_role', '2026_01_02_000002_add_admin_role.sql', UTC_TIMESTAMP(), '86d59cb18a7a3b26fff3996f208f787063a3df6fe51a7fe984d28218af0ad7b8');
INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`) VALUES ('2026_01_02_000003_widen_multiplier_columns', '2026_01_02_000003_widen_multiplier_columns.sql', UTC_TIMESTAMP(), '06f7f137aadeb5ac928bf847d8da2c3b2c5f2c0eee784f68c90e4724de632e32');
