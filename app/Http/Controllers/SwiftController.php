<?php

namespace App\Http\Controllers;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JetBrains\PhpStorm\NoReturn;
use OpenStack\ObjectStore\v1\Models\StorageObject;
use OpenStack\OpenStack;
use OpenStack\ObjectStore\v1\Models\Container;
use Exception;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SwiftController extends Controller
{
    public OpenStack $openStack;
    public function __construct() {
        $requestOptions = [
            'verify' => false,
            'headers' => ['User-Agent' => 'PHP-OPENCLOUD/SDKv1.0']
        ];
        $params = [
            'authUrl' => env('OS_AUTH_URL'),
            'region'  => 'RegionOne',
            'tenantId' => env('OS_TENANT'),
            'user'    =>
                array(
                    'name' => env('OS_USERNAME') ,
                    'password' => env('OS_PASSWORD'),
                    'domain'   =>
                        array(
                            'id' => 'default',
                        ),
                ),
            'requestOptions' => $requestOptions
        ];
        $this->openStack = new OpenStack($params);
    }

    public function objectStorageConnection(): array|string
    {
        set_time_limit(0);
        $containerName = 'SeptemDevContainer';
        try {
            return $this->fileVerification($containerName);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function createContainer($containerName): Container|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            return $openStackService->createContainer([
                'name' => $containerName
            ]);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getSingleContainer($containerName): Container|string {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            return $openStackService->getContainer($containerName);
        } catch(Exception $e) {
            return $e->getMessage();
        }
    }

    public function listAllContainers(): array|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $containerInfo = array();
            foreach ($openStackService->listContainers() as $container) {
                $containerInfo[] = $container->name;
            }
            return $containerInfo;
        } catch (Exception $e){
            return $e->getMessage();
        }
    }

    public function createObject($containerName, $objectName, $contentType, $content = null): StorageObject|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $stream = new Stream(fopen(storage_path('app\public\server/' . $objectName . '.' . $contentType), 'r'));
            $options = [
                'name'   => $objectName,
                'stream' => $stream,
            ];
            return $openStackService
                ->getContainer($containerName)
                ->createObject($options);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getObject($containerName, $objectName): StorageObject|string
    {
        set_time_limit(0);
        $openStackService = $this->openStack->objectStoreV1();
        try {
            return $openStackService->getContainer($containerName)->getObject($objectName);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function listAllObjects($containerName = null): array|string
    {
        $containerName = ($containerName == null) ? 'SeptemDevContainer' : $containerName;
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $objectInfo = array();
            $container = $openStackService->getContainer($containerName);
            foreach ($container->listObjects() as $object) {
                $objectInfo[] = $object;
            }
            return $objectInfo;
        } catch (Exception $e){
            return $e->getMessage();
        }
    }

    public function downloadObject($containerName, $objectName, $objectExtension): StorageObject|string|array
    {
        set_time_limit(0);
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $publicUri = $openStackService->getContainer($containerName)->getObject($objectName);
            $objectContents = $publicUri->download();
            return Storage::put('/public/server/' . $objectName . '.' .$objectExtension, $objectContents);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getAllFilesFromLocal(): int|array
    {
//        $allFiles = Storage::allFiles('public/server/');
//        $allFilesWithDate = array();
//        $i = 0;
//        foreach ($allFiles as $file) {
//            $fileDate = Storage::lastModified($file);
//            $lastModified = DateTime::createFromFormat("U", $fileDate);
//            $lastModified->setTimezone(new DateTimeZone('UTC'));
//            $allFilesWithDate[$i]['name'] = $file;
//            $allFilesWithDate[$i]['date'] = $lastModified;
//            $i++;
//        }
//        return $allFilesWithDate;
        return Storage::allFiles('public/server/');
    }

    public function getAllFilesFromServer($containerName): array|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try{
            $objectInfo = array();
            $container = $openStackService->getContainer($containerName);
            $i = 0;
            foreach ($container->listObjects() as $object) {
                $fileType = explode('/', $object->contentType);
                $objectInfo[$i]['name'] = $object->name;
                $objectInfo[$i]['nameWithType'] = $object->name . '.' . $fileType[1];
                $objectInfo[$i]['hash'] = $object->hash;
                $objectInfo[$i]['contentType'] = $object->contentType;
                $objectInfo[$i]['contentLength'] = $object->contentLength;
                $objectInfo[$i]['lastModified'] = $object->lastModified;
                $i++;
            }
            return $objectInfo;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function fileVerification($containerName): string|array
    {
        set_time_limit(0);
        try {
            $localFiles = $this->getAllFilesFromLocal();
            $serverFiles = $this->getAllFilesFromServer($containerName);
            $serverFileName = array();
            $localFileName = array();
            foreach ($serverFiles as $file) {
                $serverFileName[] = strtolower(trim($file['nameWithType']));
            }
            $i = 0;
            foreach ($localFiles as $file) {
                $explodeFileName = explode('/', $file);
                $localFileName[$i] = strtolower(trim($explodeFileName[2]));
                $i++;
            }

            $localObjects = array_diff($localFileName, $serverFileName);
            $serverObjects = array_diff($serverFileName, $localFileName);
            $differenceArray = array_merge($localObjects, $serverObjects);
            $response = array();
            $j = 0;
            foreach ($differenceArray as $objName) {
                $explodedObjectName = explode('.', $objName);
                if(!in_array($objName, $serverFileName)) {
                    $response[$j]['response'] = $this->createObject($containerName, $explodedObjectName[0], $explodedObjectName[1]);
                }
                if(!in_array($objName, $localFileName)) {
                    $response[$j]['response'] = $this->downloadObject($containerName, $explodedObjectName[0], $explodedObjectName[1]);
                }
                $j++;
            }
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function deleteObject($containerName, $objectName): ?string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $openStackService->getContainer($containerName)
                ->getObject($objectName)
                ->delete();
            return 'Deleted';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function mergeContainerMetaData($containerName): string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $metadata = [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
                'read' => '.r:*'
            ];
            $openStackService->getContainer($containerName)
                ->mergeMetadata($metadata);
            return 'Updated';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function mergeObjectMetaData($containerName, $objectName): string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $metadata = [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
                'read' => '.r:*'
            ];
            $openStackService->getContainer($containerName)->getObject($objectName)
                ->mergeMetadata($metadata);
            return 'Updated';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function videoStream($containerName = null, $objectName = null): StreamedResponse
    {
        $containerName = ($containerName == null) ? 'SeptemDevContainer' : $containerName;
        $objectName = ($objectName == null) ? 'video' : $objectName;
        $objectStore = $this->openStack->objectStoreV1();

        $object = $objectStore->getContainer($containerName)
            ->getObject($objectName);
//            ->download(['requestOptions' => ['stream' => true]]);

        $stream = $object->download();
        $response = new StreamedResponse();
        $response->setCallback(function () use ($stream) {
            echo $stream->getContents();
        });
        $response->headers->set('Content-Type', 'video/mp4');
        return $response;
    }
}
