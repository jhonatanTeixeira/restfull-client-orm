<?php

namespace Vox\Webservice\Exception;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WebserviceResponseException extends Exception
{
    /**
     * @var ResponseInterface
     */
    private $reponse;

    /**
     * @var RequestInterface
     */
    private $request;

    private $body;

    public function __construct(ResponseInterface $response, RequestInterface $request = null)
    {
        parent::__construct($response->getReasonPhrase(), $response->getStatusCode());

        $this->reponse = $response;
        $this->request = $request;
    }

    public function getBody()
    {
        return $this->body ?? $this->body = json_decode($this->reponse->getBody()->getContents(), true);
    }

    public function getReponse(): ResponseInterface
    {
        return $this->reponse;
    }

    public function getRequest()
    {
        return $this->request;
    }
}