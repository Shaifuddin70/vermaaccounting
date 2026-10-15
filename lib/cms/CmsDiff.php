<?php

declare(strict_types=1);

/**
 * Line diffs for revision comparisons (content bodies, stylesheets).
 */
final class CmsDiff
{
    private const MAX_CELLS = 1_000_000;

    /**
     * LCS line diff. Unchanged lines at both ends are matched first, so small edits to large files stay cheap.
     *
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{0: string, 1: string}> [op, line] with op ' ', '-' or '+'
     */
    public static function lines(array $a, array $b): array
    {
        $head = [];
        while ($a !== [] && $b !== [] && $a[0] === $b[0]) {
            $head[] = [' ', array_shift($a)];
            array_shift($b);
        }
        $tail = [];
        while ($a !== [] && $b !== [] && end($a) === end($b)) {
            array_unshift($tail, [' ', array_pop($a)]);
            array_pop($b);
        }
        return array_merge($head, self::lcs(array_values($a), array_values($b)), $tail);
    }

    private static function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        if ($n * $m > self::MAX_CELLS) {
            return array_merge(array_map(fn ($l) => ['-', $l], $a), array_map(fn ($l) => ['+', $l], $b));
        }
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $out = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = [' ', $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['-', $a[$i++]];
            } else {
                $out[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $out[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $out[] = ['+', $b[$j++]];
        }
        return $out;
    }

    /**
     * Side-by-side rows: removed and added runs are paired so changed lines sit next to each other.
     * With $context set, long unchanged runs collapse to that many lines around each change.
     *
     * @return list<array{0: ?string, 1: ?string, 2: string}> [left, right, kind] with kind same, change or gap
     */
    public static function sideBySide(array $diff, ?int $context = null): array
    {
        $rows = [];
        $dels = $adds = [];
        $flush = function () use (&$rows, &$dels, &$adds): void {
            for ($i = 0, $n = max(count($dels), count($adds)); $i < $n; $i++) {
                $rows[] = [$dels[$i] ?? null, $adds[$i] ?? null, 'change'];
            }
            $dels = $adds = [];
        };
        foreach ($diff as [$op, $line]) {
            if ($op === ' ') {
                $flush();
                $rows[] = [$line, $line, 'same'];
            } elseif ($op === '-') {
                $dels[] = $line;
            } else {
                $adds[] = $line;
            }
        }
        $flush();
        if ($context === null) {
            return $rows;
        }

        $keep = [];
        foreach ($rows as $i => $row) {
            if ($row[2] === 'change') {
                for ($k = max(0, $i - $context), $end = min(count($rows) - 1, $i + $context); $k <= $end; $k++) {
                    $keep[$k] = true;
                }
            }
        }
        $out = [];
        $skipped = 0;
        foreach ($rows as $i => $row) {
            if (isset($keep[$i])) {
                if ($skipped > 0) {
                    $out[] = [$skipped . ' unchanged line' . ($skipped === 1 ? '' : 's'), null, 'gap'];
                    $skipped = 0;
                }
                $out[] = $row;
            } else {
                $skipped++;
            }
        }
        if ($skipped > 0 && $out !== []) {
            $out[] = [$skipped . ' unchanged line' . ($skipped === 1 ? '' : 's'), null, 'gap'];
        }
        return $out;
    }
}
