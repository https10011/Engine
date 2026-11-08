# Slot Framework

> Reference for `CasinoEngine\Frameworks\Slot`. Read this when building a slot
> module. The worked example is `src/Games/Synthetic/SyntheticSlotGame.php`.

The framework exists because the 16 target slot titles do **not** share one
mathematical structure. Research into them (see [RESEARCH_NOTES.md](../project/RESEARCH_NOTES.md))
found four different win-evaluation models, several multiplier lifetimes,
mid-spin grid changes, and multiple symbol-transformation rules. A single rigid
"spin three reels and count cherries" class could not host them, so the
framework is composed from independent pieces that a module wires together.

---

## 1. The pieces

| Piece | Class | Responsibility |
| --- | --- | --- |
| Reels | `Reel\ReelConfiguration`, `Reel\ReelStrip` | Grid shape and what each reel stops on |
| Symbols | `Symbol\SymbolSet`, `Symbol\Symbol`, `Symbol\SymbolType` | The catalogue and how each symbol behaves |
| Paytable | `Paytable\Paytable` | Units paid per symbol per run length |
| Evaluation | `Evaluation\*` | Turning a grid into wins |
| Features | `Feature\*` | Free spins, cascades, multipliers, and your own |
| Engine | `SlotEngine` | Composes the above into one spin |
| State | `SlotState` | What survives between spins |

`SlotEngine::__construct` takes exactly those pieces plus `unitDivisor`, and
carries no game-specific rule. If you find yourself wanting to add a branch to
it for one game, the branch belongs in an evaluator or a feature instead.

---

## 2. Reels and grid shape

```php
$reels = new ReelConfiguration(
    [ReelStrip::fromSymbols(['A','K','Q','J','W','A','K']), /* ... one per reel ... */],
    3 // rows, or an int[] for a non-rectangular grid
);
```

* `ReelConfiguration(array $reels, int|array $rows)` — a single int for a
  rectangular grid, or an array when reels have different heights. This is what
  supports "expanding reels" and 3-4-5-4-3 shapes without a special case.
* `ReelStrip::fromSymbols([...])` — an explicit strip. Use this when the game's
  mathematics must be a fixed constant.
* `ReelStrip::fromWeights(['A' => 20, 'K' => 15, ...], $rng)` — a strip
  generated once from weights. Use this for a synthetic or exploratory game.

`spin($rng)` returns the visible window per reel; `spinAt($positions)` returns
the window for pinned positions, which is how tests and simulations force a
specific outcome without weakening production randomness.

---

## 3. Symbols

`Symbol::regular()`, `::wild()`, `::scatter()`, `::bonus()`, `::blank()`. The
`SymbolType` enum decides behaviour during evaluation:

| Type | Substitutes? | Payable? | Meaning |
| --- | --- | --- | --- |
| `Regular` | no | yes | Pays on a matching run |
| `Wild` | yes | yes | Substitutes for regulars; never for scatter/bonus |
| `Scatter` | no | yes | Pays on count anywhere in the grid |
| `Bonus` | no | no | Triggers a feature; pays nothing alone |
| `Blank` | no | no | A decorative gap that never wins |

`winMultiplier` on a symbol is the per-symbol multiplier (a x2 wild). It
multiplies the line's units when the symbol participates in a win.

---

## 4. Paytable

A paytable maps a symbol and a run length to **units**, not currency:

```php
new Paytable(
    ['A' => [3 => 100, 4 => 300, 5 => 1000], 'K' => [3 => 50, ...]],
    ['S' => [3 => 50, 4 => 100, 5 => 200]] // scatter pays by count
);
```

Runs start at 2 (a paytable entry for a run of 1 throws). Units are converted to
a payout multiplier by `SlotEngine` using `unitDivisor`:

```
payoutMultiplier = (sum of winning units × combined multiplier) / unitDivisor
```

For a 5-line game, `unitDivisor = 5`, so a line paying 100 units pays 20x.
Keeping the divisor explicit is what lets one paytable serve a payline game and
a ways game with different definitions of a unit.

---

## 5. Evaluation models

`GridEvaluatorInterface` has two methods: `modelName()` and
`evaluate(Grid, Paytable): EvaluationResult`.

| Evaluator | Model | Use when |
| --- | --- | --- |
| `PaylineEvaluator([...])` | Fixed paylines | The game pays left-to-right on defined lines |
| `WaysEvaluator` | Ways-to-win | Any matching run on adjacent reels (243, 1024, 32400 ways) |
| `ClusterEvaluator(size)` | Cluster | Adjacent-symbol clusters of a minimum size |
| `ScatterEvaluator` | Scatter pays | Scatters pay anywhere; also usable as a component |
| `CompositeEvaluator(...)` | Several at once | A game that pays lines *and* scatters, for example |

`CompositeEvaluator::__construct(GridEvaluatorInterface ...$evaluators)` sums
the wins from each sub-evaluator, which is the mechanism behind games that
combine line wins with a scatter pay and a cluster pay.

An `EvaluationResult` carries the `WinLine` list, total units, the multiplier
applied, the scatter count and the bonus count — the last two feed features.

---

## 6. Features

A feature implements `FeatureInterface`:

```php
interface FeatureInterface
{
    public function type(): string;
    public function apply(FeatureContext $context): FeatureStep;
}
```

A `FeatureContext` gives the feature the current grid, the evaluation, the
spin's bet/wager flags, the feature state carried on the session, and the RNG.
A `FeatureStep` returns the new state, an optional grid transformation, a
multiplier, whether the spin should re-evaluate (a cascade), any transitions,
and any extra flags.

Shipped features:

| Feature | Behaviour |
| --- | --- |
| `FreeSpinsFeature` | Scatter-triggered free spins with an optional retrigger and a cap |
| `CascadeFeature` | Removes winning symbols and drops new ones, re-evaluating until no win |
| `ComboMultiplierFeature` | Applies a multiplier that grows with the cascade/combo index |

Three rules the framework enforces, learned from the target games:

1. **A free spin is its own spin.** `FreeSpinsFeature` never asks for another
   evaluation of the same grid — that would pay the same line twice. It manages
   the pool of spins the session carries, and the module reports a zero wager
   multiplier for a free spin.
2. **Retriggers are capped.** `$retriggerCap` bounds the free-spin pool.
   Without it a retriggering game has an unbounded upper tail and no operator
   can price it.
3. **Cascades are bounded.** `SlotEngine`'s `$maximumFeatureIterations`
   (default 50) stops a misconfigured cascade before it loops forever.

### Writing your own feature

Copy `ComboMultiplierFeature` as a template. Return a `FeatureStep` whose
`reEvaluate` flag is true only when you have transformed the grid, and always
carry forward the state a later spin needs.

---

## 7. State

`SlotState` holds the feature map, the last grid (so a reloaded session renders
the same screen) and a spin counter. It is schema-versioned; a future change
migrates an old snapshot rather than misreading it.

```php
$state = SlotState::initial();
$state->freeSpinsRemaining(); // int
$state->isInFreeSpins();      // bool
$state->featureState('free_spins'); // array
```

---

## 8. Lifecycle of one spin

```
module.execute()
  └─ SlotEngine::spin($state, $rng, $config, $forcedPositions)
       1. is a free spin active?  → wager is 0; else the module wagers a bet
       2. spin the reels (or keep the cascade's grid)
       3. evaluate the grid
       4. loop: consult each feature; a feature may transform the grid and ask
          for another evaluation (cascade), until no feature asks again or the
          iteration cap is hit
       5. sum units across every evaluation, apply the combined multiplier,
          divide by unitDivisor → payoutMultiplier
  └─ the module returns a GameOutcome with that ratio and the next state
```

The engine then applies money, in one transaction. The slot framework never
itself decides currency.

---

## 9. Testing a slot module

Lead with unit tests on the pieces: a known grid through a known evaluator
against a known paytable must produce known units. `EvaluationModelsTest` and
`FeatureRegressionTest` do exactly this with pinned grids.

Then integration: register the module and drive full rounds through the engine,
asserting the wallet movement. For mathematics, use the simulation harness:

```bash
php bin/engine-simulate sim:run --game=synthetic.slot3x3 --rounds=100000 --seed=42 --bet=1.00
```

The report labels everything as an observed sample, prints a standard error and
a 95% confidence interval, and never claims a simulation proves a theoretical
RTP. See [TESTING_STRATEGY.md](TESTING_STRATEGY.md).
