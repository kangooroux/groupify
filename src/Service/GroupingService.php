<?php
declare(strict_types=1);
namespace App\Service;

use App\Exception\TooManyPlayersException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GroupingService
{
    private const MAX_TABLE_SIZE      = 4;
    private const MIN_TABLE_SIZE      = 3;
    private const MAX_ATTEMPTS        = 500;
    private const THREE_TABLE_PENALTY = 5;
    private const MAX_PLAYERS         = 200;

    /** @var array<string> */
    private array $warnings = [];

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function __construct(
        #[Autowire('%app.forced_pairs%')] private readonly array $forcedPairs,
        #[Autowire('%app.name_aliases%')] private readonly array $nameAliases,
    ) {}

    /** @return array<array<string>> */
    public function buildFirstRound(string $rawInput, array $rawFixedPods): array
    {
        $this->warnings = [];
        [$fixedPods, $fixedPlayers] = $this->parseFixedPods($rawFixedPods);
        $this->checkForcedPairConflicts($fixedPods, $fixedPlayers);

        $randomPlayers = $this->getRandomPlayers($rawInput, $fixedPlayers);
        $totalPlayers  = count($randomPlayers) + count($fixedPlayers);
        if ($totalPlayers > self::MAX_PLAYERS) {
            throw new TooManyPlayersException('Too many players (max ' . self::MAX_PLAYERS . ').');
        }
        if ($totalPlayers > 0 && $totalPlayers < self::MIN_TABLE_SIZE) {
            $this->warnings[] = 'Only ' . $totalPlayers . ' player(s) entered — tables work best with at least ' . self::MIN_TABLE_SIZE . '.';
        }
        shuffle($randomPlayers);

        return $this->assembleGroups($fixedPods, $randomPlayers, $this->forcedPairs);
    }

    /** @return array<array<string>> */
    public function generateNextRound(string $rawInput, array $rawFixedPods, array $lastRound): array
    {
        $this->warnings = [];
        [$fixedPods, $fixedPlayers] = $this->parseFixedPods($rawFixedPods);
        $this->checkForcedPairConflicts($fixedPods, $fixedPlayers);

        $remainingPlayers = $this->getRandomPlayers($rawInput, $fixedPlayers);
        $totalPlayers     = count($remainingPlayers) + count($fixedPlayers);
        if ($totalPlayers > self::MAX_PLAYERS) {
            throw new TooManyPlayersException('Too many players (max ' . self::MAX_PLAYERS . ').');
        }
        if ($totalPlayers > 0 && $totalPlayers < self::MIN_TABLE_SIZE) {
            $this->warnings[] = 'Only ' . $totalPlayers . ' player(s) entered — tables work best with at least ' . self::MIN_TABLE_SIZE . '.';
        }

        $conflicts         = [];
        $threeTablePlayers = [];

        foreach ($lastRound as $group) {
            if (count($group) === self::MIN_TABLE_SIZE) {
                foreach ($group as $player) {
                    $threeTablePlayers[$player] = true;
                }
            }
            $members = array_values($group);
            $count   = count($members);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $conflicts[$members[$i]][$members[$j]] = true;
                    $conflicts[$members[$j]][$members[$i]] = true;
                }
            }
        }

        $bestGroups = null;
        $bestScore  = PHP_INT_MAX;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $shuffled = $remainingPlayers;
            shuffle($shuffled);

            $groups = $this->assembleGroups($fixedPods, $shuffled, $this->forcedPairs);

            $score = 0;
            foreach ($groups as $group) {
                $members = array_values($group);
                $count   = count($members);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (isset($conflicts[$members[$i]][$members[$j]])) {
                            $score++;
                        }
                    }
                }
                if ($count === self::MIN_TABLE_SIZE) {
                    foreach ($group as $player) {
                        if (isset($threeTablePlayers[$player])) {
                            $score += self::THREE_TABLE_PENALTY;
                        }
                    }
                }
            }

            if ($score < $bestScore) {
                $bestScore  = $score;
                $bestGroups = $groups;
                if ($bestScore === 0) {
                    break;
                }
            }
        }

        return $bestGroups ?? [];
    }

    /** @return array<string, int> */
    public function buildPlayerTable(array $groups): array
    {
        $playerTable = [];
        foreach ($groups as $tableNumber => $players) {
            foreach ($players as $player) {
                $playerTable[$player] = $tableNumber + 1;
            }
        }
        ksort($playerTable);
        return $playerTable;
    }

    private function checkForcedPairConflicts(array $fixedPods, array $fixedPlayers): void
    {
        // Build a map of player → pod index so we can check if pair members share a pod
        $playerPodIndex = [];
        foreach ($fixedPods as $podIndex => $pod) {
            foreach ($pod as $player) {
                $playerPodIndex[$player] = $podIndex;
            }
        }

        $fixedIndex = array_flip($fixedPlayers);
        foreach ($this->forcedPairs as $pair) {
            $names   = array_values(array_filter($pair, fn(string $p) => $p !== ''));
            $blocked = array_filter($names, fn(string $p) => isset($fixedIndex[$p]));
            if (count($names) < 2) {
                continue;
            }
            if (count($blocked) > 0 && count($blocked) < count($names)) {
                $this->warnings[] = 'Forced pair (' . implode(' & ', $names) . ') could not be seated together because one or more members are in a fixed table.';
            } elseif (count($blocked) === count($names)) {
                // All members are in fixed pods — warn if they are not all in the same pod
                $pods = array_unique(array_map(fn(string $p) => $playerPodIndex[$p], $names));
                if (count($pods) > 1) {
                    $this->warnings[] = 'Forced pair (' . implode(' & ', $names) . ') could not be seated together because members are assigned to different fixed tables.';
                }
            }
        }
    }

    /** @return array<string> */
    private function getRandomPlayers(string $rawInput, array $fixedPlayers): array
    {
        $fixedIndex = array_flip($fixedPlayers);
        return array_values(array_filter(
            $this->parsePlayers($rawInput),
            fn(string $name) => !isset($fixedIndex[$name])
        ));
    }

    private function resolvePlayerName(string $name): string
    {
        return $this->nameAliases[strtolower($name)] ?? $name;
    }

    /** @return array<string> */
    private function parsePlayers(string $raw): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn(string $n) => $this->resolvePlayerName(trim($n)), explode("\n", $raw)),
            fn(string $n) => $n !== ''
        )));
    }

    /** @return array{array<array<string>>, array<string>} */
    private function parseFixedPods(array $rawFixedPods): array
    {
        $fixedPods   = [];
        $seenPlayers = [];

        foreach ($rawFixedPods as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $players = array_values(array_unique(array_filter(
                array_map(fn(string $n) => $this->resolvePlayerName(trim($n)), explode("\n", $entry)),
                fn($n) => $n !== ''
            )));

            // Drop players already assigned to an earlier pod, then cap to table size
            $players = array_slice(
                array_values(array_filter($players, fn(string $p) => !isset($seenPlayers[$p]))),
                0, self::MAX_TABLE_SIZE
            );
            foreach ($players as $p) {
                $seenPlayers[$p] = true;
            }

            if (!empty($players)) {
                $fixedPods[] = $players;
            }
        }

        $fixedPlayers = !empty($fixedPods) ? array_merge(...$fixedPods) : [];

        return [$fixedPods, $fixedPlayers];
    }

    /** @param array<string> $pool Already shuffled remaining players */
    private function assembleGroups(array $fixedPods, array $pool, array $forcedPairs = []): array
    {
        $customGroups = [];
        foreach ($fixedPods as $pod) {
            $fill           = array_splice($pool, 0, max(0, self::MAX_TABLE_SIZE - count($pod)));
            $customGroups[] = array_merge($pod, $fill);
        }

        $poolIndex    = array_flip($pool);
        $seededGroups = [];
        foreach ($forcedPairs as $pair) {
            $present = array_values(array_filter($pair, fn(string $p) => isset($poolIndex[$p])));
            if (count($present) >= 2) {
                foreach ($present as $p) {
                    unset($poolIndex[$p]);
                }
                $pool = array_values(array_keys($poolIndex));
                $fill = array_splice($pool, 0, max(0, self::MAX_TABLE_SIZE - count($present)));
                $poolIndex      = array_flip($pool);
                $seededGroups[] = array_merge($present, $fill);
            }
        }
        $pool = array_values(array_keys($poolIndex));

        $randomGroups = [];
        $n            = count($pool);
        if ($n > 0) {
            $offset = 0;
            foreach ($this->calculateTableSizes($n) as $size) {
                $randomGroups[] = array_slice($pool, $offset, $size);
                $offset        += $size;
            }
        }

        return array_merge($customGroups, $seededGroups, $randomGroups);
    }

    private function calculateTableSizes(int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $remainder = $n % self::MAX_TABLE_SIZE;
        if ($remainder === 1 && $n >= 9) {
            $k = intdiv($n, self::MAX_TABLE_SIZE);
            return array_merge(array_fill(0, $k - 2, self::MAX_TABLE_SIZE), [self::MIN_TABLE_SIZE, self::MIN_TABLE_SIZE, self::MIN_TABLE_SIZE]);
        }
        if ($remainder === 2 && $n >= 6) {
            $k = intdiv($n, self::MAX_TABLE_SIZE);
            return array_merge(array_fill(0, max(0, $k - 1), self::MAX_TABLE_SIZE), [self::MIN_TABLE_SIZE, self::MIN_TABLE_SIZE]);
        }
        if ($n === 5) {
            return [self::MIN_TABLE_SIZE, self::MIN_TABLE_SIZE - 1];
        }
        if (($remainder === 1 || $remainder === 2) && $n < 6) {
            return [$n];
        }
        $full = intdiv($n, self::MAX_TABLE_SIZE);
        return $remainder === 0
            ? array_fill(0, $full, self::MAX_TABLE_SIZE)
            : [...array_fill(0, $full, self::MAX_TABLE_SIZE), $remainder];
    }
}
