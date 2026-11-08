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
