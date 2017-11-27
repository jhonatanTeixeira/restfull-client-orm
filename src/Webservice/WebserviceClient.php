<?php

namespace Vox\Webservice;

use Exception;
use GuzzleHttp\ClientInterface;
use Metadata\MetadataFactoryInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

class WebserviceClient implements WebserviceClientInterface
{
    /**
     * @var ClientRegistryInterface
     */
    private $clientRegistry;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    /**
     * @var DenormalizerInterface
     */
    private $denormalizer;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    public function __construct(
        ClientRegistryInterface $clientRegistry, 
        MetadataFactoryInterface $metadataFactory, 
        DenormalizerInterface $denormalizer, 
        SerializerInterface $serializer
    ) {
        $this->clientRegistry  = $clientRegistry;
        $this->metadataFactory = $metadataFactory;
        $this->denormalizer    = $denormalizer;
        $this->serializer      = $serializer;
    }
    
    public function cGet(string $transferName, array $filters = []): TransferCollection
    {
        $resource = $this->getResource($transferName);
        $client   = $this->getClient($transferName);
        $response = $client->request('GET', $resource->route);

        return new TransferCollection($transferName, $this->denormalizer, $response);
    }

    public function delete(string $transferName, $id)
    {
        
    }

    public function get(string $transferName, $id)
    {
        $resource = $this->getResource($transferName);
        $client   = $this->getClient($transferName);
        $route    = sprintf('%s/%s', $resource->route, $id);
        $response = $client->request('GET', $route);
        $contents = $response->getBody()->getContents();
        
        if ($contents) {
            return $this->denormalizer->denormalize(json_decode($contents, true), $transferName);
        }
    }

    public function post($transfer)
    {
        $data = $this->serializer->serialize($transfer, 'json');
        
        $resource = $this->getResource(get_class($transfer));
        $client   = $this->getClient(get_class($transfer));
        $response = $client->request('POST', $resource->route, ['json' => $data]);
        
        if ($response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
        
        $contents = $response->getBody()->getContents();
        $this->denormalizer->denormalize(json_decode($contents, true), $transfer);
    }

    public function put($transfer)
    {
        $data     = $this->serializer->serialize($transfer, 'json');
        $metadata = $this->getMetadata(get_class($transfer));
        $resource = $this->getResource(get_class($transfer));
        $client   = $this->getClient(get_class($transfer));
        
        if (empty($metadata->id)) {
            throw new RuntimeException('no id mapped for class ' . get_class($transfer));
        }
        
        $route    = sprintf('%s/%s', $resource->route, $metadata->id->getValue($transfer));
        $response = $client->request('PUT', $route, ['json' => $data]);
        
        if ($response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
    }
    
    private function getClient(string $transferName, Resource $resource = null): ClientInterface
    {
        if (null === $resource) {
            $resource = $this->getResource($transferName);
        }
        
        return  $this->clientRegistry->get($resource->client);
    }
    
    private function getResource(string $transferName): Resource
    {
        return $this->getMetadata($transferName)->getAnnotation(Resource::class, true);
    }
    
    private function getMetadata(string $transferName): TransferMetadata
    {
        return $this->metadataFactory->getMetadataForClass($transferName);
    }
}
