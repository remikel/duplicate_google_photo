<?php

namespace App\Service;

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;
use Google\Client;
use Google\Photos\Library\V1\FiltersBuilder;
use Google\Photos\Library\V1\MediaTypeFilter\MediaType;
use Google\Service\Libraryagent;
use Google\Type\Date;
use GPBMetadata\Google\Photos\Library\V1\PhotosLibrary;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class GooglePhotoService
{
    private $client;
    private $pagesize;

    function __construct($pagesize)
    {
        $this->pagesize = $pagesize;
        $this->client = new Client();
        // TODO mettre chemin relatif ou récupérer données autrement style via conf
        $this->client->setAuthConfig('../../duplicate_google_photo/oauth_google.json');
        $this->client->addScope('https://www.googleapis.com/auth/photoslibrary');
        $this->client->addScope('https://www.googleapis.com/auth/photoslibrary.appendonly');
        $this->client->addScope('https://www.googleapis.com/auth/photoslibrary.sharing');
        $this->client->addScope('https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata');
        $this->client->addScope('profile');
        $this->client->setRedirectUri('http://localhost:8000/auth/google/callback');
    }
    public function auth()
    {
        return $this->client->createAuthUrl();
    }

    public function callback($code)
    {
        $authToken = $this->client->fetchAccessTokenWithAuthCode($code);
        $refreshToken = $authToken['access_token'];
        $session = new Session();
        $code = $session->set('refresh_token', $refreshToken);
    }

    private function getPhotoLibraryClient()
    {
        $session = new Session();
        $refreshToken = $session->get('refresh_token');
        $jsonKey = json_decode(file_get_contents('../../duplicate_google_photo/oauth_google.json'), true)["web"];
        $jsonKey['refresh_token'] = $refreshToken;
        $authCredentials = new UserRefreshCredentials($this->client->getScopes(), $jsonKey);
        $photosLibraryClient = new PhotosLibraryClient(['credentials' => $authCredentials]);

        return $photosLibraryClient;
    }

    public function createAlbum($name = 'To delete album created by remikel.fr')
    {
        try {
            $photosLibraryClient = $this->getPhotoLibraryClient();
            $newAlbum = PhotosLibraryResourceFactory::album($name);
            $createdAlbum = $photosLibraryClient->createAlbum($newAlbum);
            $albumId = $createdAlbum->getId();
            $session = new Session();
            $session->set('album_id', $albumId);
            return $albumId;
        } catch (\Google\ApiCore\ApiException $exception) {
            dump($exception);
        } catch (\Google\ApiCore\ValidationException $e) {
            dump($e);
        }
    }

    private function getFilters($parameters)
    {
        $startDate = new Date();
        $startDay = $parameters['startDay'] ?: 1;
        $startDate->setDay($startDay);
        $startMonth = $parameters['startMonth'] ?: 1;
        $startDate->setMonth($startMonth);
        $startYear = $parameters['startYear'] ?: 1;
        $startDate->setYear($startYear);

        $endDate = new Date();
        $endDay = $parameters['endDay'] ?: 31;
        $endDate->setDay($endDay);
        $endMonth = $parameters['endMonth'] ?: 12;
        $endDate->setMonth($endMonth);
        $endYear = $parameters['endYear'] ?: 9999;
        $endDate->setYear($endYear);

        // Add the two dates as a date range to the filter
        // You can also set multiple date ranges here
        $filtersBuilder = new FiltersBuilder();
        $filtersBuilder->addDateRange($startDate, $endDate);

        if ($parameters['type'] == 'PHOTO') {
            $filtersBuilder->setMediaType(MediaType::PHOTO);
        } else {
            $filtersBuilder->setMediaType(MediaType::VIDEO);
        }

        return $filtersBuilder;
    }
    private function getPhotos($parameters)
    {
        $photosLibraryClient = $this->getPhotoLibraryClient();
        try {
            $pagesize = $parameters['photosToLoad'] * 2 < $this->pagesize ? $parameters['photosToLoad'] : $this->pagesize;
            $filtersBuilder = $this->getFilters($parameters);
            $response = $photosLibraryClient->searchMediaItems(
                [
                    'pageSize' => $pagesize,
                    'filters' => $filtersBuilder->build()
                ]
            );
            $i = 0;
            $photos = [];
            foreach ($response->iterateAllElements() as $item) {
                $i++;
                $photos[] = $item;
                // Get some properties of a media item
                if ($i > $parameters['photosToLoad']) {
                    break;
                }
            }

            return $photos;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function addToAlbum($mediaItemIds, $albumId)
    {
        try{
            // dump($mediaItemIds, $albumId);die;
            $photosLibraryClient = $this->getPhotoLibraryClient();
            $response = $photosLibraryClient->batchAddMediaItemsToAlbum(['AN2aG8W4cDEhD_7HNwN2Vy6BdwE7m9FDYBYP184MDk5D05LCxkCrODC4It8iub6gBI8Zh6q_nkmcwd2aHbTKXpNjDtD-DeQcZQ'], $albumId);
            dump($response);die;
        } catch(\Exception $e){
            dump($e);die;
        }

    }

    private function isSame($photo1, $photo2, $mediaData1, $mediaData2, $compares)
    {

        $same = true;
        if (in_array('mediaData.creationTime', $compares) && ($mediaData1->getCreationTime() != $mediaData2->getCreationTime())) {
            $same = false;
        } else if (in_array('filename', $compares) && ($photo1->getFilename() != $photo2->getFilename())) {
            $same = false;
        } else if (in_array('mediaData.photo.cameraModel', $compares) && ($mediaData1->getPhoto()->getCameraModel() != $mediaData2->getPhoto()->getCameraModel())) {
            $same = false;
        } else if (in_array('mediaData.height', $compares) && ($mediaData1->getHeight() != $mediaData2->getHeight())) {
            $same = false;
        } else if (in_array('mediaData.width', $compares) && ($mediaData1->getWidth() != $mediaData2->getWidth())) {
            $same = false;
        } 
        if ($photo1->getProductUrl() == $photo2->getProductUrl()) {
            $same = false;
        }
        return $same;
    }


    public function searchDuplicates($parameters)
    {
        $photos = $this->getPhotos($parameters);
        if (!is_array($photos)){
            return $photos;
        }
        $compares = $parameters['compares'];
        $links = [];
        $length = count($photos);
        for ($i = 0; $i < $length; $i++) {
            $photo1 = $photos[$i];
            for ($j = $i; $j < $length; $j++) {
                $photo2 = $photos[$j];
                $mediaData1 = $photo1->getMediaMetadata();
                $mediaData2 = $photo2->getMediaMetadata();
                $same = $this->isSame($photo1, $photo2, $mediaData1, $mediaData2, $compares);
                if ($same) {
                    if (true) {
                        if ($mediaData1->getWidth() > $mediaData2->getWidth()) {
                            $links[] = [$photo1, $photo2];
                        } else {
                            $links[] = [$photo2, $photo1];
                        }
                    }
                    // else if (compares.includes('pixelMatch') && await samePixel($photo1, $photo2) < 4) {
                    //   if ($photo1.mediaMetadata.width > $photo2.mediaMetadata.width) {
                    //     links[] = [ $photo1, $photo2 ];
                    //   }
                    //   else {
                    //     links[] = [ $photo2, $photo1 ];
                    //   }
                    // }
                }
            }
        }
        
        return $links;
    }
}
