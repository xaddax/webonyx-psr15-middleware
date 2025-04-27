<?php

namespace GraphQL\Middleware\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io) {}

    public static function getSubscribedEvents()
    {
        return [
            'post-install-cmd' => 'addGenerateResolversScript',
            'post-update-cmd' => 'addGenerateResolversScript',
        ];
    }

    public function addGenerateResolversScript(Event $event)
    {
        $composerJson = getcwd() . '/composer.json';
        $composerData = json_decode(file_get_contents($composerJson), true);

        if (!isset($composerData['scripts']['generate-resolvers'])) {
            $composerData['scripts']['generate-resolvers'] = 'vendor/bin/generate-resolvers';
            file_put_contents($composerJson, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $event->getIO()->write('<info>Added "generate-resolvers" script to composer.json</info>');
        }
    }

    public function deactivate(Composer $composer, IOInterface $io) {}
    public function uninstall(Composer $composer, IOInterface $io) {}
}