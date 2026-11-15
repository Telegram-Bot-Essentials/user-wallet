<?php

declare(strict_types=1);

namespace TelegramBotEssentials\UserWallet\Tests;

use TelegramBotEssentials\Announcements\TbeAnnouncementsServiceProvider;
use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;
use TelegramBotEssentials\UserManagement\TbeUserManagementServiceProvider;
use TelegramBotEssentials\UserWallet\TbeUserWalletServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeAnnouncementsServiceProvider::class,
            TbeSettingsServiceProvider::class,
            TbeUserManagementServiceProvider::class,
            TbeBillingServiceProvider::class,
            TbeUserWalletServiceProvider::class,
        ]);
    }
}
