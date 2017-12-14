<?php

namespace Vox\Webservice\Proxy;

use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Webservice\TransferManagerInterface;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface ProxyFactoryInterface
{
    public function createProxy($class, TransferManagerInterface $transferManager): AccessInterceptorValueHolderInterface;
    
    public function registerProxyAutoloader();
}
