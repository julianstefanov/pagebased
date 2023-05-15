<?php

namespace Zeroseven\Rampage\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Zeroseven\Rampage\Registration\Registration;

class TopicRepository extends AbstractRelationRepository
{
    protected $defaultOrderings = [
        'title' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING
    ];

    protected function getRelationPageIds(Registration $registration): array
    {
        return $registration->getObject()->getTopicPageIds();
    }
}
