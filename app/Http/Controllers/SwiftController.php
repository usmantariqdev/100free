<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
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

    public function objectStorageConnection() {
        dd($this->downloadObject('100free', 'wallhaven-188424.jpg'));
        $service = $this->openStack->objectStoreV1();
        $container = $service->createContainer([
            'name' => 'DevContainer'
        ]);
        dd($container->getMetadata());
    }
    public function createDirectory() {
        $openstack = new OpenStack([
            'authUrl' => 'https://authenticate.ain.net/',
            'region' => 'RegionOne',
            'user' => [
                'id' => 'v2.0',
                'password' => '95ncjPdj4Mv#jd?dja(JdVc84A',
                'domain' => [
                    'name' => 'YOUR_DOMAIN_NAME'
                ]
            ],
//            'scope' => [
//                'project' => [
//                    'id' => 'YOUR_PROJECT_ID'
//                ]
//            ]
        ]);
        $container = $openstack->objectStoreV1()->getContainer('justfree');
        $directoryName = 'dev-septem/';
        $container->createObject(['name' => $directoryName]);
        $object = $container->getObject($directoryName);
        $contentType = $object->getContentType();

        if ($contentType === 'application/directory') {
            // directory exists
        } else {
            // directory does not exist
        }
    }
    public function uploadFile(): string {
        // Initialize the OpenStack client
        $openstack = new OpenStack([
            'authUrl' => 'https://your-auth-url/v3',
            'region' => 'your-region',
            'user' => [
                'id' => 'your-user-id',
                'password' => 'your-password',
                'domain' => [
                    'id' => 'your-domain-id',
                ],
            ],
            'scope' => [
                'project' => [
                    'id' => 'your-project-id',
                ],
            ],
        ]);

        // Create a new container in Swift
        $container = $openstack->objectStoreV1()->createContainer([
            'name' => 'your-container-name',
        ]);

        // Create a new object in the container
        $object = $container->createObject([
            'name' => 'your-file-name',
            'content' => fopen('your-file-path', 'r'),
        ]);

        // Return a success message
        return 'File uploaded successfully.';
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

    public function createObject($containerName, $objectName, $content = null): StorageObject|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $options = [
                'name'    => $objectName,
                'content' => $content
            ];
            return $openStackService->getContainer($containerName)->createObject($options);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    public function getObject($containerName, $objectName): StorageObject|string
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            return $openStackService->getContainer($containerName)->getObject($objectName);
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
                dd($object);
            }
            return $containerInfo;
        } catch (Exception $e){
            return $e->getMessage();
        }
    }

    public function downloadObject($containerName, $objectName): StorageObject|string|array
    {
        $openStackService = $this->openStack->objectStoreV1();
        try {
            $publicUri = $openStackService->getContainer($containerName)
                ->getObject($objectName)->getPublicUri();

//            Storage::copy( public_path(), storage_path() );
            File::copy($publicUri, public_path($objectName));
            return $openStackService->getContainer($containerName)
                ->getObject($objectName)->getPublicUri();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
