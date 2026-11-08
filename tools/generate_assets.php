<?php
declare(strict_types=1);

/**
 * Generate AURUM's original SVG artwork from the engine catalogue.
 *
 * Everything this script writes is drawn here from scratch: no provider art,
 * no tenant banner and no reference-site asset is copied or traced. The game
 * palettes and emblems come from the engine's own ThemeCatalogue (exposed by
 * GET /api/v1/games/lobby), so a new registered title gets a usable tile with
 * no manual design work.
 *
 * Usage:
 *   php tools/generate_assets.php /tmp/lobby.json
 */

$source = $argv[1] ?? '/tmp/lobby.json';
$outRoot = $argv[2] ?? 'public/assets';

if (!is_file($source)) {
    fwrite(STDERR, "Catalogue JSON not found: {$source}\n");
    exit(1);
}

$json = json_decode((string) file_get_contents($source), true);
$games = $json['data']['games'] ?? null;
if (!is_array($games)) {
    fwrite(STDERR, "Unexpected catalogue shape in {$source}\n");
    exit(1);
}

foreach (['brand', 'banners', 'games'] as $dir) {
    if (!is_dir("{$outRoot}/{$dir}") && !mkdir("{$outRoot}/{$dir}", 0775, true) && !is_dir("{$outRoot}/{$dir}")) {
        fwrite(STDERR, "Cannot create {$outRoot}/{$dir}\n");
        exit(1);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function providerShort(string $provider): string
{
    $known = [
        'JILI' => 'JILI',
        'PG Soft' => 'PG SOFT',
        'Pragmatic' => 'PRAGMATIC',
        'Engine Original' => 'ENGINE ORIGINAL',
        'Engine Examples' => 'ENGINE EXAMPLE',
    ];
    foreach ($known as $needle => $label) {
        if (stripos($provider, $needle) !== false) {
            return $label;
        }
    }
    return strtoupper(substr($provider, 0, 20));
}

/**
 * One 400×400 tile.
 *
 * @param array<string, mixed> $entry
 */
function gameTile(array $entry): string
{
    $game = $entry['game'];
    $theme = $entry['theme'] ?? [];

    $id = (string) ($game['id'] ?? 'game');
    $name = (string) ($game['name'] ?? 'Game');
    $category = (string) ($game['category'] ?? 'slot');
    $provider = providerShort((string) ($game['provider'] ?? ''));

    $base = (string) ($theme['base'] ?? '#0b3f2f');
    $panel = (string) ($theme['panel'] ?? '#115740');
    $accent = (string) ($theme['accent'] ?? '#f6c744');
    $accent2 = (string) ($theme['accent2'] ?? '#ffffff');
    $ink = (string) ($theme['ink'] ?? '#ffffff');
    $emblem = (string) ($theme['emblem'] ?? '◆');

    $seed = crc32($id);
    $rotation = $seed % 30;
    $ring = 96 + ($seed % 12);
    $sparkles = '';
    for ($i = 0; $i < 7; $i++) {
        $sx = 40 + (($seed >> ($i * 3)) % 320);
        $sy = 40 + (($seed >> ($i * 4)) % 250);
        $r = 1.5 + (($seed >> $i) % 22) / 10;
        $sparkles .= sprintf(
            '<circle cx="%d" cy="%d" r="%.1f" fill="%s" opacity="%.2f"/>',
            $sx,
            $sy,
            $r,
            $i % 3 === 0 ? $accent2 : $ink,
            0.18 + (($seed >> $i) % 30) / 100
        );
    }
    $rays = '';
    for ($i = 0; $i < 24; $i++) {
        $rays .= sprintf('<rect x="199" y="24" width="2" height="46" rx="1" fill="%s" opacity=".12" transform="rotate(%d 200 200)"/>', $ink, $i * 15);
    }

    $motif = '';
    if ($category === 'slot') {
        $motif = '<g opacity=".10" stroke="' . $ink . '" fill="none" stroke-width="2">';
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $motif .= sprintf('<rect x="%d" y="%d" width="86" height="86" rx="16"/>', 20 + $c * 122, 300 + $r * 0, 86);
            }
        }
        $motif .= '</g>';
        // Replaced by a single subtle reel band at the bottom.
        $motif = '<g opacity=".14" fill="none" stroke="' . $ink . '" stroke-width="2">'
            . '<rect x="18" y="286" width="364" height="66" rx="18"/>'
            . '<path d="M139 286v66M261 286v66"/>'
            . '<circle cx="78" cy="319" r="15"/><circle cx="200" cy="319" r="15"/><circle cx="322" cy="319" r="15"/>'
            . '</g>';
    } elseif ($category === 'card') {
        $motif = '<g opacity=".14" fill="none" stroke="' . $ink . '" stroke-width="2">'
            . '<rect x="286" y="52" width="76" height="106" rx="12" transform="rotate(14 324 105)"/>'
            . '<rect x="262" y="64" width="76" height="106" rx="12" transform="rotate(-8 300 117)"/>'
            . '</g>';
    } elseif ($category === 'roulette') {
        $motif = '<g opacity=".14" fill="none" stroke="' . $ink . '" stroke-width="2">'
            . '<circle cx="318" cy="92" r="46"/><circle cx="318" cy="92" r="16"/>'
            . '<path d="M318 38v16M318 130v16M264 92h16M356 92h16M280 54l11 11M345 119l11 11M356 54l-11 11M291 119l-11 11"/>'
            . '</g>';
    } else {
        $motif = '<circle cx="318" cy="86" r="40" fill="none" stroke="' . $ink . '" stroke-opacity=".16" stroke-width="2"/>';
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="{$name} artwork">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$base}"/>
      <stop offset="1" stop-color="{$panel}"/>
    </linearGradient>
    <radialGradient id="glow" cx="50%" cy="40%" r="62%">
      <stop offset="0" stop-color="{$accent}" stop-opacity=".55"/>
      <stop offset=".62" stop-color="{$accent}" stop-opacity=".10"/>
      <stop offset="1" stop-color="{$accent}" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffe58f"/>
      <stop offset=".55" stop-color="#f6c744"/>
      <stop offset="1" stop-color="#d99a12"/>
    </linearGradient>
    <pattern id="dots" width="28" height="28" patternUnits="userSpaceOnUse">
      <circle cx="2" cy="2" r="1.3" fill="{$ink}" opacity=".10"/>
    </pattern>
  </defs>

  <rect width="400" height="400" fill="url(#bg)"/>
  <rect width="400" height="400" fill="url(#dots)"/>
  <circle cx="200" cy="188" r="188" fill="url(#glow)"/>
  <g transform="rotate({$rotation} 200 200)">{$rays}</g>
  {$sparkles}
  {$motif}

  <circle cx="200" cy="184" r="{$ring}" fill="none" stroke="{$accent2}" stroke-opacity=".45" stroke-width="2"/>
  <circle cx="200" cy="184" r="{$ring}" fill="none" stroke="{$accent}" stroke-opacity=".30" stroke-width="10" stroke-dasharray="3 14"/>
  <circle cx="200" cy="184" r="78" fill="{$base}" fill-opacity=".55" stroke="{$accent}" stroke-opacity=".35" stroke-width="1.5"/>

  <text x="200" y="238" font-size="132" text-anchor="middle" fill="url(#gold)"
        font-family="'SF Pro Display','Segoe UI',system-ui,sans-serif" font-weight="800">{$emblem}</text>

  <text x="200" y="366" font-size="17" letter-spacing="5" text-anchor="middle"
        fill="{$ink}" fill-opacity=".62"
        font-family="'SF Pro Text','Segoe UI',system-ui,sans-serif" font-weight="700">{$provider}</text>
</svg>
SVG;
}

/* ------------------------------------------------------------------ brand */

$mark = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="AURUM mark">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffe58f"/><stop offset=".55" stop-color="#f6c744"/><stop offset="1" stop-color="#d99a12"/>
    </linearGradient>
  </defs>
  <circle cx="32" cy="32" r="30" fill="url(#g)"/>
  <circle cx="32" cy="32" r="24" fill="none" stroke="#083326" stroke-opacity=".35" stroke-width="2"/>
  <path d="M20 40h24l-2.4-14-6.1 5.6L32 23l-3.5 8.6-6.1-5.6z" fill="#083326"/>
  <circle cx="32" cy="44.5" r="2.4" fill="#083326"/>
</svg>
SVG;

$logo = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 84" role="img" aria-label="AURUM">
  <defs>
    <linearGradient id="lg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fff2b8"/><stop offset=".5" stop-color="#f6c744"/><stop offset="1" stop-color="#d99a12"/>
    </linearGradient>
  </defs>
  <g transform="translate(2 6)">
    <circle cx="32" cy="32" r="29" fill="url(#lg)"/>
    <circle cx="32" cy="32" r="23" fill="none" stroke="#083326" stroke-opacity=".3" stroke-width="2"/>
    <path d="M20.5 40h23l-2.3-13.5-5.8 5.4L32 24l-3.4 7.9-5.8-5.4z" fill="#083326"/>
    <circle cx="32" cy="44" r="2.3" fill="#083326"/>
  </g>
  <text x="76" y="50" font-family="'SF Pro Display','Segoe UI',system-ui,sans-serif"
        font-size="42" font-weight="800" letter-spacing="2.5" fill="url(#lg)">AURUM</text>
  <text x="78" y="70" font-family="'SF Pro Text','Segoe UI',system-ui,sans-serif"
        font-size="9.5" font-weight="700" letter-spacing="4.6" fill="#f6c744" fill-opacity=".62">VIRTUAL CASINO</text>
</svg>
SVG;

$favicon = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <rect width="64" height="64" rx="16" fill="#083326"/>
  <circle cx="32" cy="32" r="20" fill="#f6c744"/>
  <path d="M23 38h18l-1.8-10.4-4.6 4.2L32 25l-2.6 6.8-4.6-4.2z" fill="#083326"/>
</svg>
SVG;

$appIcon = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">
  <defs>
    <linearGradient id="green" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#115740"/><stop offset="1" stop-color="#062a1e"/>
    </linearGradient>
    <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffe58f"/><stop offset="1" stop-color="#d99a12"/>
    </linearGradient>
  </defs>
  <rect width="192" height="192" rx="42" fill="url(#green)"/>
  <circle cx="96" cy="96" r="58" fill="url(#gold)"/>
  <circle cx="96" cy="96" r="46" fill="none" stroke="#062a1e" stroke-opacity=".3" stroke-width="4"/>
  <path d="M68 116h56l-5.5-32-14 13L96 76l-8.5 21-14-13z" fill="#062a1e"/>
  <circle cx="96" cy="128" r="5" fill="#062a1e"/>
</svg>
SVG;

file_put_contents("{$outRoot}/brand/aurum-logo.svg", $logo);
file_put_contents("{$outRoot}/brand/aurum-mark.svg", $mark);
file_put_contents("{$outRoot}/brand/favicon.svg", $favicon);
file_put_contents("{$outRoot}/brand/app-icon.svg", $appIcon);

/* ---------------------------------------------------------------- banners */

$banners = [
    // Gold coins on the right, quiet left side for the overlaid headline.
    <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 750 308" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Gold coin artwork">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0d4a36"/><stop offset="1" stop-color="#062a1e"/></linearGradient>
    <radialGradient id="glow" cx="78%" cy="42%" r="52%"><stop offset="0" stop-color="#f6c744" stop-opacity=".42"/><stop offset="1" stop-color="#f6c744" stop-opacity="0"/></radialGradient>
    <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe58f"/><stop offset=".55" stop-color="#f6c744"/><stop offset="1" stop-color="#c88c0c"/></linearGradient>
    <linearGradient id="fade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#062a1e" stop-opacity=".92"/><stop offset=".55" stop-color="#062a1e" stop-opacity=".45"/><stop offset="1" stop-color="#062a1e" stop-opacity="0"/></linearGradient>
  </defs>
  <rect width="750" height="308" fill="url(#bg)"/>
  <circle cx="588" cy="130" r="190" fill="url(#glow)"/>
  <g opacity=".22" stroke="#f6c744" stroke-width="2">
    <path d="M588 20v34M588 206v34M478 120h34M664 120h34M510 42l24 24M642 174l24 24M666 42l-24 24M534 174l-24 24"/>
  </g>
  <circle cx="588" cy="120" r="86" fill="url(#gold)" opacity=".95"/>
  <circle cx="588" cy="120" r="68" fill="none" stroke="#8a5c05" stroke-opacity=".45" stroke-width="5"/>
  <text x="588" y="146" font-size="76" text-anchor="middle" fill="#8a5c05" font-family="'SF Pro Display',system-ui,sans-serif" font-weight="800">A</text>
  <circle cx="700" cy="238" r="46" fill="url(#gold)" opacity=".8"/>
  <circle cx="474" cy="250" r="34" fill="url(#gold)" opacity=".65"/>
  <circle cx="648" cy="48" r="22" fill="url(#gold)" opacity=".55"/>
  <rect width="750" height="308" fill="url(#fade)"/>
</svg>
SVG,
    // Fanned cards with a chip stack.
    <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 750 308" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Playing card artwork">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0e5139"/><stop offset="1" stop-color="#062a1e"/></linearGradient>
    <radialGradient id="glow" cx="76%" cy="46%" r="48%"><stop offset="0" stop-color="#f6c744" stop-opacity=".32"/><stop offset="1" stop-color="#f6c744" stop-opacity="0"/></radialGradient>
    <linearGradient id="fade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#062a1e" stop-opacity=".94"/><stop offset=".58" stop-color="#062a1e" stop-opacity=".4"/><stop offset="1" stop-color="#062a1e" stop-opacity="0"/></linearGradient>
  </defs>
  <rect width="750" height="308" fill="url(#bg)"/>
  <circle cx="590" cy="140" r="170" fill="url(#glow)"/>
  <g transform="translate(596 152)">
    <rect x="-120" y="-96" width="132" height="188" rx="16" fill="#fdfcf6" transform="rotate(-17)" opacity=".93"/>
    <rect x="-84" y="-106" width="132" height="188" rx="16" fill="#fdfcf6" transform="rotate(-4)" opacity=".96"/>
    <rect x="-40" y="-108" width="132" height="188" rx="16" fill="#fdfcf6"/>
    <text x="26" y="30" text-anchor="middle" font-size="78" fill="#c8213c" font-family="'SF Pro Display',system-ui,sans-serif" font-weight="800">A</text>
    <text x="-58" y="52" text-anchor="middle" font-size="48" fill="#12241b" opacity=".7" font-family="'SF Pro Display',system-ui,sans-serif" font-weight="800">K</text>
  </g>
  <g transform="translate(452 244)">
    <ellipse cx="0" cy="18" rx="54" ry="18" fill="#031a12" opacity=".5"/>
    <rect x="-48" y="-8" width="96" height="22" rx="11" fill="#f6c744"/>
    <rect x="-48" y="-28" width="96" height="22" rx="11" fill="#e8ae1f"/>
    <rect x="-48" y="-48" width="96" height="22" rx="11" fill="#f6c744"/>
  </g>
  <rect width="750" height="308" fill="url(#fade)"/>
</svg>
SVG,
    // Reel band with sparkles.
    <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 750 308" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Slot reel artwork">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0b4633"/><stop offset="1" stop-color="#05261b"/></linearGradient>
    <radialGradient id="glow" cx="74%" cy="40%" r="50%"><stop offset="0" stop-color="#7ee0b8" stop-opacity=".26"/><stop offset="1" stop-color="#7ee0b8" stop-opacity="0"/></radialGradient>
    <linearGradient id="fade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#05261b" stop-opacity=".95"/><stop offset=".6" stop-color="#05261b" stop-opacity=".38"/><stop offset="1" stop-color="#05261b" stop-opacity="0"/></linearGradient>
  </defs>
  <rect width="750" height="308" fill="url(#bg)"/>
  <circle cx="600" cy="150" r="164" fill="url(#glow)"/>
  <g transform="translate(508 82)">
    <rect width="196" height="196" rx="26" fill="#041d15" stroke="#f6c744" stroke-opacity=".35" stroke-width="3"/>
    <g fill="none" stroke="#f6c744" stroke-opacity=".55" stroke-width="3">
      <rect x="20" y="20" width="42" height="42" rx="10"/>
      <rect x="77" y="20" width="42" height="42" rx="10"/>
      <rect x="134" y="20" width="42" height="42" rx="10"/>
      <rect x="20" y="77" width="42" height="42" rx="10"/>
      <rect x="77" y="77" width="42" height="42" rx="10"/>
      <rect x="134" y="77" width="42" height="42" rx="10"/>
      <rect x="20" y="134" width="42" height="42" rx="10"/>
      <rect x="77" y="134" width="42" height="42" rx="10"/>
      <rect x="134" y="134" width="42" height="42" rx="10"/>
    </g>
    <g fill="#f6c744" opacity=".9">
      <circle cx="41" cy="41" r="7"/><circle cx="98" cy="98" r="7"/><circle cx="155" cy="41" r="7"/>
      <circle cx="41" cy="155" r="7"/><circle cx="155" cy="155" r="7"/><circle cx="98" cy="41" r="7"/>
    </g>
  </g>
  <g fill="#ffe58f" opacity=".8">
    <path d="M470 54l4 11 11 4-11 4-4 11-4-11-11-4 11-4z"/>
    <path d="M690 236l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>
    <path d="M462 232l2.5 7 7 2.5-7 2.5-2.5 7-2.5-7-7-2.5 7-2.5z"/>
  </g>
  <rect width="750" height="308" fill="url(#fade)"/>
</svg>
SVG,
];

foreach ($banners as $index => $svg) {
    file_put_contents(sprintf('%s/banners/slide-%d.svg', $outRoot, $index + 1), $svg);
}

/* ------------------------------------------------------------------ games */

$written = 0;
foreach ($games as $entry) {
    $id = (string) ($entry['game']['id'] ?? '');
    if ($id === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $id) !== 1) {
        continue;
    }
    file_put_contents("{$outRoot}/games/{$id}.svg", gameTile($entry));
    $written++;
}

echo "wrote brand (4), banners (3), game tiles ({$written})\n";
