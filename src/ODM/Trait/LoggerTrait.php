<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Trait;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait LoggerTrait
{
    private readonly LoggerInterface $dynamodbOdmLogger;

    #[Required]
    public function setDynamodbOdmLogger(LoggerInterface $dynamodbOdmLogger): void
    {
        $this->dynamodbOdmLogger = $dynamodbOdmLogger;
    }

    private function logQuery(string $message, array $query): void
    {
        $this->dynamodbOdmLogger->error($message, $query);
    }
}
