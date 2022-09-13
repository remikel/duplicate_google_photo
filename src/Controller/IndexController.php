<?php

namespace App\Controller;

use App\Service\GooglePhotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class IndexController extends AbstractController
{
    private $photoService;

    function __construct(GooglePhotoService $photoService)
    {
        $this->photoService = $photoService;
    }
    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        return $this->render('index/index.html.twig');
    }

    #[Route('/privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('index/privacy.html.twig');
    }

    #[Route('/connect', name: 'connect')]
    public function connect(): RedirectResponse
    {
        $url = $this->photoService->auth();

        return new RedirectResponse($url);
    }

    #[Route('/auth/google/callback', name: 'callback')]
    public function callbackGoogle(Request $request)
    {
        $code = $request->query->get('code');
        $this->photoService->callback($code);

        return $this->redirectToRoute('duplicates');
    }

    #[Route('/search', name: 'duplicates')]
    public function searchDuplicates(Request $request)
    {
        if ($request->isMethod('POST')) {
            $photoPairs = $this->photoService->searchDuplicates($request->request->all());
            if (!is_array($photoPairs)) {
                if (get_class($photoPairs) == 'GuzzleHttp\Exception\ClientException'){
                    $this->addFlash(
                        'info',
                        'You have been disconnected, please reconnect'
                    );
                    return $this->redirectToRoute('app_index');
                }
                else if (get_class($photoPairs) == '\Google\ApiCore\ApiException') {
                    $this->addFlash(
                        'report',
                        $photoPairs->getBasicMessage()
                    );
                    return $this->render('index/duplicates.html.twig', array(
                        'photoPairs' => $photoPairs,
                        'searched' => false
                    ));
                } else {
                    dump($photoPairs);die;
                }
            }
            return $this->render('index/duplicates.html.twig', array(
                'photoPairs' => $photoPairs,
                'searched' => true
            ));
        }
        return $this->render('index/duplicates.html.twig', array(
            'searched' => false
        ));
    }

    #[Route('/createAlbum', name: 'createAlbum')]
    public function createAlbum(Request $request)
    {
        if ($request->isMethod('POST')) {
            $this->photoService->createAlbum();
        }
        return $this->render('index/duplicates.html.twig');
    }

    #[Route('/moveToDeleteAlbum', name: 'moveToDeleteAlbum')]
    public function moveToDeleteAlbum(Request $request)
    {
        if ($request->isMethod('POST')) {

            $session = new Session();
            $albumId = $session->get('album_id');
            if (!$albumId) {
                $albumId = $this->photoService->createAlbum();
            }
            $mediaIds = array_keys($request->request->all());
            $this->photoService->addToAlbum($mediaIds, $albumId);
        }

        return $this->render('index/duplicates.html.twig');
    }

    
}
