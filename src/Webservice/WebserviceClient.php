<?php

namespace Vox\Webservice;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Metadata\MetadataFactoryInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vox\Webservice\Exception\WebserviceResponseException;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * the webservice client does the actual work of consuming and publishing data to the external webservices
 *
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
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
     * @var NormalizerInterface
     */
    private $normalizer;

    public function __construct(
        ClientRegistryInterface $clientRegistry,
        MetadataFactoryInterface $metadataFactory,
        DenormalizerInterface $denormalizer,
        NormalizerInterface $normalizer
    ) {
        $this->clientRegistry  = $clientRegistry;
        $this->metadataFactory = $metadataFactory;
        $this->denormalizer    = $denormalizer;
        $this->normalizer      = $normalizer;
    }

    public function cGet(string $transferName, array $filters = []): TransferCollection
    {
        $resource = $this->getResource($transferName);
        $client   = $this->getClient($transferName);
        $options  = ['headers' => ['Content-Type' => 'application/json']];

        if (!empty($filters)) {
            $options['query'] = $filters;
        }

        $response = $client->request('GET', $resource->route, $options);

        if ($response->getStatusCode() >= 300) {
            throw new WebserviceResponseException($response, new Request('GET', $response->route, $options));
        }

        return new TransferCollection($transferName, $this->denormalizer, $response);
    }

    public function delete(string $transferName, $id)
    {
        $resource = $this->getResource($transferName);
        $client   = $this->getClient($transferName);
        $route    = sprintf('%s/%s', $resource->route, $id);
        $response = $client->request('DELETE', $route);

        if ($response->getStatusCode() >= 300) {
            throw new WebserviceResponseException($response, new Request('DELETE', $route));
        }
    }

    public function get(string $transferName, $id)
    {
        $resource = $this->getResource($transferName);
        $client   = $this->getClient($transferName);
        $route    = sprintf('%s/%s', $resource->route, $id);
        $response = $client->request('GET', $route, ['headers' => ['Content-Type' => 'application/json']]);

        if ($response->getStatusCode() >= 300) {
            throw new WebserviceResponseException($response, new Request('GET', $route));
        }

        $contents = $response->getBody()->getContents();
        
        if ($contents) {
            return $this->denormalizer->denormalize(json_decode($contents, true), $transferName);
        }
    }

    public function post($transfer)
    {
        $data = $this->normalizer->normalize($transfer, 'json');

        $resource = $this->getResource(get_class($transfer));
        $client   = $this->getClient(get_class($transfer));
        $response = $client->request('POST', $resource->route, ['json' => $data]);

        if ($response->getStatusCode() >= 300) {
            throw new WebserviceResponseException($response, new Request('POST', $resource->route, ['json' => $data]));
        }

        $contents = $response->getBody()->getContents();
        $this->denormalizer->denormalize(json_decode($contents, true), $transfer);
    }

    public function put($transfer)
    {
        $data     = $this->normalizer->normalize($transfer, 'json');
        $metadata = $this->getMetadata(get_class($transfer));
        $resource = $this->getResource(get_class($transfer));
        $client   = $this->getClient(get_class($transfer));

        if (empty($metadata->id)) {
            throw new RuntimeException('no id mapped for class ' . get_class($transfer));
        }

        $route    = sprintf('%s/%s', $resource->route, $metadata->id->getValue($transfer));
        $response = $client->request('PUT', $route, ['json' => $data]);

        if ($response->getStatusCode() >= 300) {
            throw new WebserviceResponseException($response, new Request('PUT', $route, ['json' => $data]));
        }

        $this->denormalizer->denormalize(json_decode($response->getBody()->getContents(), true), $transfer);
    }

    public function getByCriteria(CriteriaInterface $criteria, string $transferName)
    {
        $client   = $this->getClient($transferName);

        $request = $criteria->createRequest($this->getMetadata($transferName));

        $response = $client->send($request);

        if ($criteria->getOperationType() == CriteriaInterface::OPERATION_TYPE_ITEM) {
            $contents = $response->getBody()->getContents();
            return $this->denormalizer->denormalize(json_decode($contents, true), $transferName);
        }

        if ($criteria->getOperationType() == CriteriaInterface::OPERATION_TYPE_COLLECTION) {
            return new TransferCollection($transferName, $this->denormalizer, $response);
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
