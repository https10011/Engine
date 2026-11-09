-- ---------------------------------------------------------------------------
-- Player profile fields.
--
-- Ordinary players now sign in with a phone number and never choose a public
-- username. `username` stays as the internal, unique account identifier (the
-- CLI still creates operator accounts with one); `phone` is the player-facing
-- login and is nullable for those operator accounts.
-- ---------------------------------------------------------------------------

ALTER TABLE `users`
    ADD COLUMN `phone` VARCHAR(20) NULL AFTER `username`,
    ADD COLUMN `age` TINYINT UNSIGNED NULL AFTER `display_name`;

ALTER TABLE `users`
    ADD UNIQUE KEY `uniq_users_phone` (`phone`);
