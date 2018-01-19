<?php

namespace Vox\Webservice;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

class Criteria implements CriteriaInterface
{
    private $params = [];

    private $query = [];

    private $path;

    private $operationType = self::OPERATION_TYPE_COLLECTION;

    public function withParams(array $params): CriteriaInterface
    {
        $this->params = $params;

        return $this;
    }

    public function withQuery(array $query): CriteriaInterface
    {
        $this->query = $query;

        return $this;
    }

    public function setParam($name, $value): CriteriaInterface
    {
        $this->params[$name] = $value;

        return $this;
    }

    public function setQuery($name, $value): CriteriaInterface
    {
        $this->query[$name] = $value;

        return $this;
    }

    public function withPath(string $path): CriteriaInterface
    {
        $this->path = $path;

        return $this;
    }

    public function withOperationType(string $operationType): CriteriaInterface
    {
        if (!in_array($operationType, [self::OPERATION_TYPE_ITEM, self::OPERATION_TYPE_COLLECTION])) {
            throw new \InvalidArgumentException('invalid operation type');
        }

        $this->operationType = $operationType;

        return $this;
    }

    public function createRequest(TransferMetadata $metadata): RequestInterface
    {
        /* @var $resource Resource */
        $resource = $metadata->getAnnotation(Resource::class, true);

        $route = $resource->route . "/{$this->path}";

        foreach ($this->params as $name => $value) {
            $route .= "/$name/$value";
        }

        $route = preg_replace(".(/{2,})|(/+)$.", "/", $route);

        if (!empty($this->query)) {
            $route .= '?' . http_build_query($this->query);
        }

        return new Request('GET', $route, ['Content-Type' => 'application/json']);
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }
}