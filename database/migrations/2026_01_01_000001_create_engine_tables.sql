
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
