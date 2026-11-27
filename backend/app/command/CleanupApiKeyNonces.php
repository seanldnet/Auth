<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

final class CleanupApiKeyNonces extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:cleanup-api-key-nonces')
            ->setDescription('Delete expired API Key request nonces after the replay-protection window.');
    }

    protected function execute(Input $input, Output $output): int
    {
        $deleted = Db::execute('DELETE FROM auth_api_key_nonces WHERE expires_at < UTC_TIMESTAMP()');
        $output->writeln('Deleted ' . $deleted . ' expired API Key nonces.');
        return 0;
    }
}
