-- ---------------------------------------------------------------------------
-- Initial administrator.
--
-- The plaintext password is not stored in this repository. The value below is
-- a bcrypt hash of the initial operator password supplied for this private
-- installation. Change it from the server console after the first login:
--
--   php bin/console user:password --username=admin --password='a new long password'
--
-- The hash is only inserted if the `admin` account does not already exist, so
-- re-running the migrations never resets a changed password.
-- ---------------------------------------------------------------------------

INSERT INTO `users` (`username`, `display_name`, `status`, `role`, `created_at`, `updated_at`)
SELECT 'admin', 'Administrator', 'active', 'admin', UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

INSERT INTO `user_credentials` (`user_id`, `password_hash`, `algo`, `updated_at`)
SELECT `id`,
       '$2y$10$NXEa6SFioE7HfHzxN.wtN.zccgozxbuV.74pCTOCkaHLVgXGiVMhm',
       'bcrypt',
       UTC_TIMESTAMP()
FROM `users`
WHERE `username` = 'admin'
  AND NOT EXISTS (SELECT 1 FROM `user_credentials` WHERE `user_id` = `users`.`id`);

INSERT INTO `wallets` (`user_id`, `currency`, `scale`, `balance_minor`, `version`, `created_at`, `updated_at`)
SELECT `id`, 'PHP', 2, 0, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM `users`
WHERE `username` = 'admin'
  AND NOT EXISTS (SELECT 1 FROM `wallets` WHERE `user_id` = `users`.`id`);
