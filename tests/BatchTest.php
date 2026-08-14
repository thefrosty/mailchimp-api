<?php

namespace DrewM\MailChimp\Tests;

use DrewM\MailChimp\MailChimp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BatchTest extends TestCase
{

    public function testNewBatch(): void
    {
        $MC_API_KEY = getenv('MC_API_KEY');

        $MailChimp = new MailChimp($MC_API_KEY);
        $Batch = $MailChimp->newBatch('1');
        $reflection = new ReflectionClass($Batch);

        $this->assertInstanceOf(MailChimp::class, $reflection->getProperty('MailChimp')->getValue($Batch));
        $this->assertSame([], $Batch->getOperations());
    }
}
