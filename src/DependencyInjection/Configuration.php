<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\DependencyInjection;

use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const COMMAND = 'command';
    public const SCHEDULE = 'schedule';
    public const LOG = 'log';
    public const LOG_FILE_NAME = 'log_file_name';
    public const LOG_FILE = 'log_file';
    public const TEMPLATE_CLASS = 'template_class';
    public const CONF_FILES_DIR = 'conf_files_dir';
    public const LOGS_DIR = 'logs_dir';
    public const HEARTBEAT = 'heartbeat';
    public const DESTINATION_FILE = 'destination_file';
    public const CONFIG = 'config';
    public const COMMANDS = 'commands';
    public const MINUTE = 'minute';
    public const HOUR = 'hour';
    public const DAY_OF_MONTH = 'day_of_month';
    public const MONTH = 'month';
    public const DAY_OF_WEEK = 'day_of_week';
    public const NUMBER_OF_PROCESSES = 'number_of_processes';
    public const AUTO_START = 'auto_start';
    public const AUTO_RESTART = 'auto_restart';
    public const PREFIX = 'prefix';
    public const USER = 'user';
    public const CRONJOB = 'cronjob';
    public const WORKER = 'worker';
    public const SETTINGS = 'settings';

    private const DESTINATION_DIR = '%kernel.project_dir%/generated_conf/';
    private const NAME = 'name';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('precision_soft_symfony_console');

        $treeBuilder->getRootNode()
            ->children()
            ->append($this->buildCronjob())
            ->append($this->buildWorker());

        return $treeBuilder;
    }

    protected function buildCronjob(): NodeDefinition
    {
        $cronjobTree = (new TreeBuilder(self::CRONJOB))->getRootNode()
            ->addDefaultsIfNotSet();

        $configTree = $cronjobTree->children()->arrayNode(self::CONFIG)
            ->addDefaultsIfNotSet();

        $configTree->children()
            ->scalarNode(self::TEMPLATE_CLASS)->defaultValue(CrontabTemplate::class)->end()
            ->scalarNode(self::CONF_FILES_DIR)->defaultValue(self::DESTINATION_DIR . 'cron')->end()
            ->scalarNode(self::LOGS_DIR)->defaultValue('%kernel.logs_dir%/cron')->end();

        $configTree->children()->arrayNode(self::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode(self::LOG)->defaultTrue()->end()
            ->scalarNode(self::DESTINATION_FILE)->defaultValue('crontab')->end()
            ->booleanNode(self::HEARTBEAT)->defaultTrue()->end()
            ->scalarNode(self::USER)->defaultNull()->end();

        /** @var NodeBuilder $commandsTree */
        $commandsTree = $cronjobTree->children()->arrayNode(self::COMMANDS)
            ->isRequired()
            ->useAttributeAsKey(self::NAME)
            ->prototype('array')
            ->children();

        $commandsTree->scalarNode(self::NAME)->end()
            ->scalarNode(self::LOG_FILE_NAME)->defaultNull()->end()
            ->scalarNode(self::USER)->defaultNull()->end()
            ->scalarNode(self::DESTINATION_FILE)->defaultNull()->end();

        $commandsTree->arrayNode(self::COMMAND)
            ->isRequired()
            ->beforeNormalization()->ifString()->then(fn($commandValue) => [$commandValue])->end()
            ->scalarPrototype()->end();

        $commandsTree->arrayNode(self::SCHEDULE)
            ->children()
            ->scalarNode(self::MINUTE)->defaultValue('*')->end()
            ->scalarNode(self::HOUR)->defaultValue('*')->end()
            ->scalarNode(self::DAY_OF_MONTH)->defaultValue('*')->end()
            ->scalarNode(self::MONTH)->defaultValue('*')->end()
            ->scalarNode(self::DAY_OF_WEEK)->defaultValue('*')->end()
            ->end();

        $commandsTree->arrayNode(self::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode(self::LOG)->defaultNull()->end();

        return $cronjobTree;
    }

    protected function buildWorker(): NodeDefinition
    {
        $workerTree = (new TreeBuilder(self::WORKER))->getRootNode()
            ->addDefaultsIfNotSet();

        $configTree = $workerTree->children()->arrayNode(self::CONFIG)
            ->addDefaultsIfNotSet();

        $configTree->children()
            ->scalarNode(self::TEMPLATE_CLASS)->defaultValue(SupervisorTemplate::class)->end()
            ->scalarNode(self::CONF_FILES_DIR)->defaultValue(self::DESTINATION_DIR . 'worker')->end()
            ->scalarNode(self::LOGS_DIR)->defaultValue('%kernel.logs_dir%/worker')->end();

        $settingsTree = $configTree->children()->arrayNode(self::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet();
        $this->appendSupervisorConfig($settingsTree->children());

        /** @var NodeBuilder $commandsTree */
        $commandsTree = $workerTree->children()->arrayNode(self::COMMANDS)
            ->isRequired()
            ->useAttributeAsKey(self::NAME)
            ->prototype('array');

        $commandsTree->children()
            ->scalarNode(self::NAME)->end()
            ->arrayNode(self::COMMAND)
            ->isRequired()
            ->beforeNormalization()->ifString()->then(fn($commandValue) => [$commandValue])->end()
            ->scalarPrototype()->end()
            ->end();

        $settingsTree = $commandsTree->children()->arrayNode(self::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet();
        $this->appendSupervisorConfig($settingsTree->children(), false);

        return $workerTree;
    }

    protected function appendSupervisorConfig(NodeBuilder $nodeBuilder, bool $withDefaults = true): void
    {
        $numberOfProcessesNode = $nodeBuilder->integerNode(self::NUMBER_OF_PROCESSES);
        $numberOfProcessesNode = true === $withDefaults ? $numberOfProcessesNode->defaultValue(1) : $numberOfProcessesNode->defaultNull();
        $numberOfProcessesNode->end();

        $autoStartNode = $nodeBuilder->booleanNode(self::AUTO_START);
        $autoStartNode = true === $withDefaults ? $autoStartNode->defaultTrue() : $autoStartNode->defaultNull();
        $autoStartNode->end();

        $autoRestartNode = $nodeBuilder->booleanNode(self::AUTO_RESTART);
        $autoRestartNode = true === $withDefaults ? $autoRestartNode->defaultTrue() : $autoRestartNode->defaultNull();
        $autoRestartNode->end();

        $nodeBuilder->scalarNode(self::PREFIX)->defaultNull()->end()
            ->scalarNode(self::USER)->defaultNull()->end()
            ->scalarNode(self::LOG_FILE)->defaultNull()->end();
    }
}
