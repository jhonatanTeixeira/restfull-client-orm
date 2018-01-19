<?php


namespace Vox\Webservice;


use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectRepository;

interface TransferRepositoryInterface extends ObjectRepository
{
    public function findByCriteria(CriteriaInterface $criteria): Collection;

    public function findOneByCriteria(CriteriaInterface $criteria);
}