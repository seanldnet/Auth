<?php
declare(strict_types=1);

namespace app\command;

use app\notification\ExpiryNotificationService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class NotifyExpiringLicenses extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:notify-expiring-licenses')
            ->setDescription('Create idempotent in-app reminders for licenses expiring in 7, 3, and 1 days.');
    }

    protected function execute(Input $input, Output $output): int
    {
        $count = (new ExpiryNotificationService())->dispatch();
        $output->writeln('Created ' . $count . ' license expiry notifications.');
        return 0;
    }
}
