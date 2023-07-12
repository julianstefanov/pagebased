<?php

declare(strict_types=1);

namespace Zeroseven\Pagebased\ViewHelpers\Pagination\Link;

use Zeroseven\Pagebased\Pagination\Pagination;

final class NextViewHelper extends AbstractLinkViewHelper
{
    protected function getTargetStage(Pagination $pagination): ?int
    {
        return $pagination->getNextStage();
    }
}
