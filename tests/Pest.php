<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use TelegramBotEssentials\UserWallet\Tests\TestCase;
use TelegramBotEssentials\UserWallet\Tests\WebTestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(WebTestCase::class, RefreshDatabase::class)->in('Web');
