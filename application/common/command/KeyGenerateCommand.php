<?php

namespace app\common\command;

use app\service\Encryption\Encrypter;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class KeyGenerateCommand extends Command
{
    protected function configure()
    {
        $this->setName('key:generate')
            ->setDescription('Set the application key');
    }

    protected function execute(Input $input, Output $output)
    {
        $key = $this->generateRandomKey();
        $publicKey = $this->generateRandomKey();
        $privateKey = $this->generateRandomKey();

        if (!$this->setKeyInEnvironmentFile($key, $publicKey, $privateKey)) {
            return;
        }

        $output->info("Application key [$key] set successfully.");
    }

    protected function generateRandomKey()
    {
        return 'base64:' . base64_encode(
                Encrypter::generateKey(config('app.cipher'))
            );
    }

    protected function setKeyInEnvironmentFile($key, $publicKey = '', $privateKey = '')
    {
        $currentKey = env('app.key');

        if (strlen($currentKey) !== 0) {
            $this->output->info('Old key exist. New key build process cancelled.');
            return false;
        }

        $this->writeNewEnvironmentFileWith($key, $publicKey, $privateKey);

        return true;
    }

    protected function writeNewEnvironmentFileWith($key, $publicKey = '', $privateKey = '')
    {
        file_put_contents(ROOT_PATH . DS . '.env', preg_replace(
            $this->keyReplacementPattern(),
            [
                "APP_KEY='{$key}'",
                "APP_PUBLIC_KEY='{$publicKey}'",
                "APP_PRIVATE_KEY='{$privateKey}'"
            ],
            file_get_contents(ROOT_PATH . DS . '.env')
        ));
    }

    protected function keyReplacementPattern()
    {
        $keyEscaped = preg_quote("=" . config('app.key'), '/');
        $publicEscaped = preg_quote("=" . config('app.public_key'), '/');
        $privateEscaped = preg_quote("=" . config('app.private_key'), '/');

        return ["/^APP_KEY{$keyEscaped}/m", "/^APP_PUBLIC_KEY{$publicEscaped}/m", "/^APP_PRIVATE_KEY{$privateEscaped}/m"];
    }
}