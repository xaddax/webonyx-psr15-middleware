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
            'post-install-cmd' => 'addGeneratorScripts',
            'post-update-cmd' => 'addGeneratorScripts',
        ];
    }

    public function addGeneratorScripts(Event $event): void
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

        $scriptsAdded = false;

        if (!isset($composerData['scripts']['generate-resolvers'])) {
            $composerData['scripts']['generate-resolvers'] = 'vendor/bin/generate-resolvers';
            $scriptsAdded = true;
            $event->getIO()->write('<info>Added "generate-resolvers" script to composer.json</info>');
        }

        if (!isset($composerData['scripts']['generate-requests'])) {
            $composerData['scripts']['generate-requests'] = 'vendor/bin/generate-requests';
            $scriptsAdded = true;
            $event->getIO()->write('<info>Added "generate-requests" script to composer.json</info>');
        }

        if ($scriptsAdded) {
            $result = file_put_contents(
                $composerJson,
                json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );

            if ($result === false) {
                throw new \RuntimeException('Failed to write to composer.json');
            }
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
