<?php

namespace GraphQL\Middleware\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'addGenerateResolversScript',
            'post-update-cmd' => 'addGenerateResolversScript',
        ];
    }

    public function addGenerateResolversScript(Event $event): void
    {
        $composerJson = getcwd() . '/composer.json';
        $composerJsonContent = file_get_contents($composerJson);

        if ($composerJsonContent === false) {
            throw new \RuntimeException('Failed to read composer.json');
        }

        $composerData = json_decode($composerJsonContent, true);
        if (!is_array($composerData)) {
            throw new \RuntimeException('Failed to decode composer.json');
        }

        if (!isset($composerData['scripts']['generate-resolvers'])) {
            $composerData['scripts']['generate-resolvers'] = 'vendor/bin/generate-resolvers';
            $result = file_put_contents(
                $composerJson,
                json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );

            if ($result === false) {
                throw new \RuntimeException('Failed to write to composer.json');
            }

            $event->getIO()->write('<info>Added "generate-resolvers" script to composer.json</info>');
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
