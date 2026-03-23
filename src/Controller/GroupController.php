<?php
declare(strict_types=1);
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    private const MAX_PLAYERS_PER_POD = 4;
    private const MAX_TOTAL_PLAYERS   = 200;
    private const MAX_HISTORY_BYTES   = 65536;

    #[Route('/', name: 'app_group', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request): Response
    {
        $groups         = [];
        $rawInput       = '';
        $customPods     = [];
        $errors         = [];
        $previousRounds = ['roundNumber' => 0, 'rounds' => []];

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$this->isCsrfTokenValid('randomize', $request->request->getString('_token'))) {
                $errors[] = 'Invalid CSRF token.';
            } else {
                $rawInput   = $request->request->getString('players');
                $rawHistory = $request->request->getString('previous_rounds');

                if ($rawHistory !== '' && strlen($rawHistory) <= self::MAX_HISTORY_BYTES) {
                    $decoded = json_decode($rawHistory, true);
                    if (is_array($decoded)
                        && isset($decoded['roundNumber'], $decoded['rounds'])
                        && is_int($decoded['roundNumber'])
                        && is_array($decoded['rounds'])
                    ) {
                        $previousRounds = $decoded;
                    }
                }

                $conflictMap = $this->buildConflictMap($previousRounds['rounds']);

                // Parse and validate custom pods
                foreach ($request->request->all('pod_players') as $rawPlayers) {
                    $assigned = array_values(array_filter(
                        array_map('trim', explode("\n", $rawPlayers)),
                        fn(string $p) => $p !== ''
                    ));
                    if (count($assigned) > self::MAX_PLAYERS_PER_POD) {
                        $errors[] = 'Each custom pod may have at most ' . self::MAX_PLAYERS_PER_POD . ' players.';
                        break;
                    }
                    if (!empty($assigned)) {
                        $customPods[] = ['players' => $assigned];
                    }
                }

                $remaining = array_values(array_filter(
                    array_map('trim', explode("\n", $rawInput)),
                    fn(string $p) => $p !== ''
                ));

                if (empty($errors)) {
                    $totalPlayers = count($remaining) + array_sum(array_map(fn($p) => count($p['players']), $customPods));
                    if ($totalPlayers > self::MAX_TOTAL_PLAYERS) {
                        $errors[] = 'Maximum ' . self::MAX_TOTAL_PLAYERS . ' players allowed.';
                    }
                }

                if (empty($errors)) {
                    shuffle($remaining);

                    // Phase 1: fill custom pods using conflict-aware greedy placement
                    $podPlayers = array_map(fn($pod) => $pod['players'], $customPods);
                    if (!empty($podPlayers)) {
                        foreach ($remaining as $player) {
                            $this->greedyPlace($player, $podPlayers, $conflictMap);
                        }
                        $remaining = [];
                    }

                    foreach ($podPlayers as $players) {
                        $groups[] = ['name' => 'Table ' . (count($groups) + 1), 'players' => $players];
                    }

                    // Phase 2: distribute remaining into new pods using conflict-aware greedy placement
                    if (!empty($remaining)) {
                        $newPodCount = (int) ceil(count($remaining) / self::MAX_PLAYERS_PER_POD);
                        $newPods     = array_fill(0, $newPodCount, []);
                        foreach ($remaining as $player) {
                            $this->greedyPlace($player, $newPods, $conflictMap);
                        }
                        foreach ($newPods as $players) {
                            $groups[] = ['name' => 'Table ' . (count($groups) + 1), 'players' => $players];
                        }
                    }

                    // Record this round into history
                    foreach ($groups as $group) {
                        if (!empty($group['players'])) {
                            $previousRounds['rounds'][] = $group['players'];
                        }
                    }
                    $previousRounds['roundNumber']++;
                }
            }
        }

        $playerList = [];
        foreach ($groups as $group) {
            foreach ($group['players'] as $player) {
                $playerList[] = ['name' => $player, 'table' => $group['name']];
            }
        }
        usort($playerList, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $this->render('group/index.html.twig', [
            'groups'          => $groups,
            'playerList'      => $playerList,
            'rawInput'        => $rawInput,
            'customPods'      => $customPods,
            'errors'          => $errors,
            'maxPlayersPerPod' => self::MAX_PLAYERS_PER_POD,
            'previousRounds'  => json_encode($previousRounds),
            'roundNumber'     => $previousRounds['roundNumber'],
        ]);
    }

    private function buildConflictMap(array $rounds): array
    {
        $map = [];
        foreach ($rounds as $tableGroup) {
            $n = count($tableGroup);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $tableGroup[$i];
                    $b = $tableGroup[$j];
                    $map[$a][$b] = ($map[$a][$b] ?? 0) + 1;
                    $map[$b][$a] = ($map[$b][$a] ?? 0) + 1;
                }
            }
        }
        return $map;
    }

    private function greedyPlace(string $player, array &$pods, array $conflictMap): void
    {
        $bestPod   = null;
        $bestScore = PHP_INT_MAX;
        $bestSize  = PHP_INT_MAX;

        foreach ($pods as $idx => $occupants) {
            if (count($occupants) >= self::MAX_PLAYERS_PER_POD) {
                continue;
            }
            $score = 0;
            foreach ($occupants as $existing) {
                $score += $conflictMap[$existing][$player] ?? 0;
            }
            $size = count($occupants);
            if ($score < $bestScore || ($score === $bestScore && $size < $bestSize)) {
                $bestScore = $score;
                $bestSize  = $size;
                $bestPod   = $idx;
            }
        }

        if ($bestPod !== null) {
            $pods[$bestPod][] = $player;
        }
    }
}
