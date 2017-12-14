<?php

namespace Vox\Webservice\Proxy;

use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Webservice\TransferManagerInterface;

interface ProxyFactoryInterface
{
    public function createProxy($class, TransferManagerInterface $transferManager): AccessInterceptorValueHolderInterface;
    
    public function registerProxyAutoloader();
}
