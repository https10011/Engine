<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette\Settlement;

use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Roulette\Bet\Bet;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/**
 * Settles a set of bets against the winning pocket.
 *
 * La partage and en prison are implemented because they are the reason French
 * roulette exists: without them the variant would be identical to European and
 * the framework would be claiming a distinction it does not make. Both are
 * applied only to even-money bets, which is the actual rule.
 *
 * The engine's convention is that a payout multiplier is "returned including
 * the stake", so a losing bet returns 0 and a winning straight returns 36.
 */
final class RouletteSettlement
{
    /**
     * @param list<Bet> $bets
     * @return array{payout:Money,wins:list<array<string,mixed>>,total_staked:Money,net:Money,la_partage:bool,en_prison:bool}
     */
    public function settle(
        array $bets,
        string $winningPocket,
        RouletteVariant $variant,
        bool $enPrisonSecondChance = false
    ): array {
        $currency = $bets[0]->stake->currency() ?? 'PHP';
        $scale = $bets[0]->stake->scale() ?? 2;

        $totalStaked = Money::zero($currency, $scale);
        $totalPayout = Money::zero($currency, $scale);
        $wins = [];
        $laPartageApplied = false;
        $enPrisonApplied = false;

        $isZero = $winningPocket === '0' || $winningPocket === '00';

        foreach ($bets as $bet) {
            $totalStaked = $totalStaked->add($bet->stake);

            if ($bet->covers($winningPocket)) {
                $payout = $this->payoutFor($bet);
                $totalPayout = $totalPayout->add($payout);
                $wins[] = [
                    'label'   => $bet->label,
                    'type'    => $bet->type->value,
                    'stake'   => $bet->stake->minorUnits(),
                    'payout'  => $payout->minorUnits(),
                    'outcome' => 'win',
                ];
                continue;
            }

            // Zero landed and the bet is even money: the French rules may
            // return half the stake (la partage) or hold it (en prison).
            if ($isZero && $bet->type->isEvenMoney() && $variant->laPartage()) {
                $half = Money::of(
                    Ratio::of(1, 2)->applyToMinorUnits($bet->stake->minorUnits()),
                    $currency,
                    $scale
                );
                $totalPayout = $totalPayout->add($half);
                $laPartageApplied = true;
                $wins[] = [
                    'label'   => $bet->label,
                    'type'    => $bet->type->value,
                    'stake'   => $bet->stake->minorUnits(),
                    'payout'  => $half->minorUnits(),
                    'outcome' => 'la_partage',
                ];
                continue;
            }

            if ($isZero && $bet->type->isEvenMoney() && $variant->enPrison() && !$enPrisonSecondChance) {
                // The stake is held rather than lost. It is returned in full
                // when the next spin is not zero; modelled here as a push,
                // which is the expected value of the rule over one spin.
                $totalPayout = $totalPayout->add($bet->stake);
                $enPrisonApplied = true;
                $wins[] = [
                    'label'   => $bet->label,
                    'type'    => $bet->type->value,
                    'stake'   => $bet->stake->minorUnits(),
                    'payout'  => $bet->stake->minorUnits(),
                    'outcome' => 'en_prison',
                ];
                continue;
            }

            $wins[] = [
                'label'   => $bet->label,
                'type'    => $bet->type->value,
                'stake'   => $bet->stake->minorUnits(),
                'payout'  => 0,
                'outcome' => 'lose',
            ];
        }

        return [
            'payout'      => $totalPayout,
            'wins'        => $wins,
            'total_staked' => $totalStaked,
            'net'         => $totalPayout->subtract($totalStaked),
            'la_partage'  => $laPartageApplied,
            'en_prison'   => $enPrisonApplied,
        ];
    }

    private function payoutFor(Bet $bet): Money
    {
        $ratio = $bet->type->payoutRatio();

        return Money::of(
            $ratio->applyToMinorUnits($bet->stake->minorUnits()),
            $bet->stake->currency(),
            $bet->stake->scale()
        );
    }
}
