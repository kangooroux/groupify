<?php
declare(strict_types=1);
namespace App\Controller;

use App\Exception\TooManyPlayersException;
use App\Service\GroupingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    public function __construct(private readonly GroupingService $groupingService) {}

    #[Route('/print', name: 'app_group_print', methods: [Request::METHOD_GET])]
    public function printView(Request $request): Response
    {
        $session      = $request->getSession();
        $currentRound = (int) $session->get('round_number', 0);

        if ($currentRound === 0) {
            return $this->redirectToRoute('app_group');
        }

        $currentGroups = (array) $session->get('last_round', []);

        return $this->render('group/print.html.twig', [
            'groups'      => $currentGroups,
            'playerTable' => $this->groupingService->buildPlayerTable($currentGroups),
            'round'       => $currentRound,
        ]);
    }

    #[Route('/', name: 'app_group', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->isMethod(Request::METHOD_POST)) {
            $submittedToken = $request->request->getString('_token');
            $sessionToken   = (string) $session->get('csrf_token', '');
            if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $action = $request->request->getString('action');

            if ($action === 'reset') {
                $session->remove('last_round');
                $session->remove('round_number');
                $session->remove('raw_input');
                $session->remove('csrf_token');
                return $this->redirectToRoute('app_group');
            }

            $rawInput     = $request->request->getString('players');
            $rawFixedPods = array_filter(
                (array) ($request->request->all()['custom_pod'] ?? []),
                'is_string'
            );

            $lastRound   = (array) $session->get('last_round', []);
            $roundNumber = (int) $session->get('round_number', 0);

            try {
                if ($action === 'next_round' && $roundNumber > 0) {
                    $groups = $this->groupingService->generateNextRound($rawInput, $rawFixedPods, $lastRound);
                } else {
                    $groups = $this->groupingService->buildFirstRound($rawInput, $rawFixedPods);
                }
            } catch (TooManyPlayersException $e) {
                $this->addFlash('warning', $e->getMessage());
                return $this->redirectToRoute('app_group');
            }

            if (empty($groups)) {
                $this->addFlash('warning', 'No players were entered. Please add at least one player.');
                return $this->redirectToRoute('app_group');
            }

            foreach ($this->groupingService->getWarnings() as $warning) {
                $this->addFlash('warning', $warning);
            }

            $session->set('last_round', $groups);
            $session->set('round_number', $roundNumber + 1);
            $session->set('raw_input', $rawInput);

            return $this->redirectToRoute('app_group');
        }

        $currentGroups = (array) $session->get('last_round', []);
        $currentRound  = (int) $session->get('round_number', 0);

        $csrfToken = bin2hex(random_bytes(16));
        $session->set('csrf_token', $csrfToken);

        return $this->render('group/index.html.twig', [
            'groups'      => $currentGroups,
            'playerTable' => $this->groupingService->buildPlayerTable($currentGroups),
            'round'       => $currentRound,
            'rawInput'    => (string) $session->get('raw_input', ''),
            'csrfToken'   => $csrfToken,
        ]);
    }
}
