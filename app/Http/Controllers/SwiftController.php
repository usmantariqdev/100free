<?php

namespace App\Http\Controllers;

use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenStack\ObjectStore\v1\Models\StorageObject;
use OpenStack\OpenStack;
use OpenStack\ObjectStore\v1\Models\Container;
use Exception;

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
        $containerName = 'DevContainer';
        dd($this->downloadObject($containerName, 'Test') );
//        $containerName = '100free';
        $objectStore = $this->openStack->objectStoreV1();
        $metadata = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
            'read' => '.r:*',
            'X-Container-Read' => '.r:*'
        ];

        try {
            return $this->fileVerification($containerName);
            dd($this->mergeMetaData($containerName));
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
//            dd($openStackService->getContainer($containerName)->getMetadata());
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
            $container = $openStackService->getContainer($containerName);
//            $metadata = [
//                'Access-Control-Allow-Origin' => '*',
//                'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
//                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
//            ];
//            $openStackService->getContainer($containerName)->getObject($objectName)->mergeMetadata($metadata);
            $object = $openStackService->getContainer($containerName)->getObject($objectName);
//            dd($object->getPublicUri());
//            dd($openStackService->getContainer($containerName)->getMetadata());
            $file = $openStackService->getContainer($containerName)->getObject($objectName)->download();
//            $response = $object->download(['saveAs' => storage_path('/app/public/server/file.mp4')]);
//            dd($file);
            dd(Storage::put('/public/server/video.mp4', $object->download()->getContents()));
            return 'success';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function listAllObjects($containerName): array|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $containerInfo = array();
            $container = $openStackService->getContainer($containerName);
            foreach ($container->listObjects() as $object) {
                $containerInfo[] = $object;
            }
            return $containerInfo;
        } catch (Exception $e){
            return $e->getMessage();
        }
    }

    public function downloadObject($containerName, $objectName): StorageObject|string|array
    {
        set_time_limit(0);
        ini_set('memory_limit', '100M');
        $openStackService = $this->openStack->objectStoreV1();
        try {
//            dd($openStackService->getContainer($containerName)->getObject($objectName)
//                ->download());
            $publicUri = $openStackService->getContainer($containerName)
                ->getObject($objectName)->download();
            dd($publicUri->getContents());
            dd(File::copy($publicUri->getContents(), public_path($objectName)));
            return File::copy($publicUri, public_path($objectName));
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getAllFilesFromLocal(): array
    {
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
            foreach ($differenceArray as $objName) {
                $explodedObjectName = explode('.', $objName);
                if(!in_array($objName, $serverFileName)) {
                    $response[] = $this->createObject($containerName, $explodedObjectName[0], $explodedObjectName[1]);
                }
                if(!in_array($objName, $localFileName)) {
                    $response[] = $this->downloadObject($containerName, $explodedObjectName[0]);
                }
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
}
