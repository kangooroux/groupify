<?php
declare(strict_types=1);
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    #[Route('/', name: 'app_group', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request): Response
    {
        $groups = [];
        $rawInput = '';

        if ($request->isMethod(Request::METHOD_POST)) {
            $rawInput = $request->request->getString('players');
            $players = array_values(array_filter(
                array_map('trim', explode("\n", $rawInput)),
                fn(string $name) => $name !== ''
            ));
            shuffle($players);
            $groups = array_chunk($players, 4);
        }

        return $this->render('group/index.html.twig', [
            'groups'   => $groups,
            'rawInput' => $rawInput,
        ]);
    }
}
