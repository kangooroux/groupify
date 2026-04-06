<?php
declare(strict_types=1);
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    #[Route('/print', name: 'app_group_print', methods: [Request::METHOD_GET])]
    public function print(Request $request): Response
    {
        $session       = $request->getSession();
        $rounds        = $session->get('rounds', []);
        $currentGroups = !empty($rounds) ? end($rounds) : [];
        $currentRound  = count($rounds);

        $playerTable = [];
        foreach ($currentGroups as $tableNumber => $players) {
            foreach ($players as $player) {
                $playerTable[$player] = $tableNumber + 1;
            }
        }
        ksort($playerTable);

        return $this->render('group/print.html.twig', [
            'groups'      => $currentGroups,
            'playerTable' => $playerTable,
            'round'       => $currentRound,
        ]);
    }

    #[Route('/', name: 'app_group', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->isMethod(Request::METHOD_POST)) {
            $action = $request->request->getString('action');

            if ($action === 'reset') {
                $session->remove('rounds');
                $session->remove('all_players');
                $session->remove('raw_input');
                return $this->redirectToRoute('app_group');
            }

            $rawInput     = $request->request->getString('players');
            $rawFixedPods = $request->request->all('custom_pod');
            $rounds       = $session->get('rounds', []);

            if ($action === 'next_round' && !empty($rounds)) {
                $groups = $this->generateNextRound($rawInput, $rawFixedPods, $rounds);
            } else {
                $groups = $this->buildFirstRound($rawInput, $rawFixedPods);
            }

            $session->set('all_players', array_merge(...($groups ?: [[]])));
            $session->set('raw_input', $rawInput);

            $rounds[] = $groups;
            $session->set('rounds', $rounds);

            return $this->redirectToRoute('app_group');
        }

        $rounds        = $session->get('rounds', []);
        $currentGroups = !empty($rounds) ? end($rounds) : [];
        $currentRound  = count($rounds);

        $playerTable = [];
        foreach ($currentGroups as $tableNumber => $players) {
            foreach ($players as $player) {
                $playerTable[$player] = $tableNumber + 1;
            }
        }
        ksort($playerTable);

        return $this->render('group/index.html.twig', [
            'groups'         => $currentGroups,
            'playerTable'    => $playerTable,
            'round'          => $currentRound,
            'rawInput'    => $session->get('raw_input', ''),
        ]);
    }

    private const FORCED_PAIRS = [
        ['Solenne Pierre', 'Alexandre Bousquet'],
    ];

    private const NAME_ALIASES = [
        'bousquet alexandre' => 'Alexandre Bousquet',
        'pierre solenne'     => 'Solenne Pierre',
    ];

    private function buildFirstRound(string $rawInput, array $rawFixedPods): array
    {
        [$fixedPods, $fixedPlayers] = $this->parseFixedPods($rawFixedPods);

        $randomPlayers = array_values(array_filter(
            $this->parsePlayers($rawInput),
            fn(string $name) => !in_array($name, $fixedPlayers, true)
        ));
        shuffle($randomPlayers);

        return $this->assembleGroups($fixedPods, $randomPlayers, self::FORCED_PAIRS);
    }

    private function generateNextRound(string $rawInput, array $rawFixedPods, array $rounds): array
    {
        [$fixedPods, $fixedPlayers] = $this->parseFixedPods($rawFixedPods);

        $remainingPlayers = array_values(array_filter(
            $this->parsePlayers($rawInput),
            fn(string $name) => !in_array($name, $fixedPlayers, true)
        ));

        $lastRound         = end($rounds);
        $conflicts         = [];
        $threeTablePlayers = [];

        foreach ($lastRound as $group) {
            if (count($group) === 3) {
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

        for ($attempt = 0; $attempt < 500; $attempt++) {
            $shuffled = $remainingPlayers;
            shuffle($shuffled);

            $groups = $this->assembleGroups($fixedPods, $shuffled, self::FORCED_PAIRS);

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
                if ($count === 3) {
                    foreach ($group as $player) {
                        if (isset($threeTablePlayers[$player])) {
                            $score += 5;
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

    private function resolvePlayerName(string $name): string
    {
        $lower = strtolower($name);
        if (isset(self::NAME_ALIASES[$lower])) {
            return self::NAME_ALIASES[$lower];
        }
        foreach (self::FORCED_PAIRS as $pair) {
            foreach ($pair as $canonical) {
                if (strcasecmp($name, $canonical) === 0) {
                    return $canonical;
                }
            }
        }
        return $name;
    }

    /** @return array<string> */
    private function parsePlayers(string $raw): array
    {
        return array_values(array_filter(
            array_map(fn(string $n) => $this->resolvePlayerName(trim($n)), explode("\n", $raw)),
            fn(string $n) => $n !== ''
        ));
    }

    /** @return array{array<array<string>>, array<string>} */
    private function parseFixedPods(array $rawFixedPods): array
    {
        $fixedPods = [];
        foreach ($rawFixedPods as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $players = array_slice(
                array_values(array_filter(array_map(fn(string $n) => $this->resolvePlayerName(trim($n)), explode("\n", $entry)))),
                0, 4
            );
            if (!empty($players)) {
                $fixedPods[] = $players;
            }
        }
        $fixedPlayers = array_merge(...($fixedPods ?: [[]]));

        return [$fixedPods, $fixedPlayers];
    }

    /** @param array<string> $pool Already shuffled remaining players */
    private function assembleGroups(array $fixedPods, array $pool, array $forcedPairs = []): array
    {
        $customGroups = [];
        foreach ($fixedPods as $pod) {
            $fill           = array_splice($pool, 0, max(0, 4 - count($pod)));
            $customGroups[] = array_merge($pod, $fill);
        }

        // Seed random tables with forced pairs present in the remaining pool
        $poolIndex    = array_flip($pool);
        $seededGroups = [];
        foreach ($forcedPairs as $pair) {
            $present = array_values(array_filter($pair, fn(string $p) => isset($poolIndex[$p])));
            if (count($present) >= 2) {
                foreach ($present as $p) {
                    unset($poolIndex[$p]);
                }
                $pool = array_values(array_keys($poolIndex));
                $fill = array_splice($pool, 0, max(0, 4 - count($present)));
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
        $remainder = $n % 4;
        if ($remainder === 1 && $n >= 9) {
            $k = intdiv($n, 4);
            return array_merge(array_fill(0, $k - 2, 4), [3, 3, 3]);
        }
        if ($remainder === 2 && $n >= 6) {
            $k = intdiv($n, 4);
            return array_merge(array_fill(0, max(0, $k - 1), 4), [3, 3]);
        }
        if (($remainder === 1 || $remainder === 2) && $n < 6) {
            return [$n];
        }
        return array_map('count', array_chunk(range(0, $n - 1), 4));
    }
}
