<?php

namespace Vox\Webservice;

use Psr\Http\Message\RequestInterface;
use Vox\Webservice\Metadata\TransferMetadata;

interface CriteriaInterface
{
    const OPERATION_TYPE_ITEM       = 'item';
    const OPERATION_TYPE_COLLECTION = 'collection';

    public function withParams(array $params): CriteriaInterface;

    public function withQuery(array $query): CriteriaInterface;

    public function setParam($name, $value): CriteriaInterface;

    public function setQuery($name, $value): CriteriaInterface;

    public function withPath(string $path): CriteriaInterface;

    public function withOperationType(string $operationType): CriteriaInterface;

    public function createRequest(TransferMetadata $metadata): RequestInterface;

    public function getOperationType(): string;
}