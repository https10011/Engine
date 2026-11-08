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
