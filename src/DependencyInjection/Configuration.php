<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\DependencyInjection;

use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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

    protected const DESTINATION_DIR = '%kernel.project_dir%/generated_conf/';
    protected const NAME = 'name';

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
        $cronjobTree = (new TreeBuilder(static::CRONJOB))->getRootNode()
            ->addDefaultsIfNotSet();

        $configTree = $cronjobTree->children()->arrayNode(static::CONFIG)
            ->addDefaultsIfNotSet();

        $configTree->children()
            ->scalarNode(static::TEMPLATE_CLASS)->defaultValue(CrontabTemplate::class)->end()
            ->scalarNode(static::CONF_FILES_DIR)->defaultValue(static::DESTINATION_DIR . 'cron')->end()
            ->scalarNode(static::LOGS_DIR)->defaultValue('%kernel.logs_dir%/cron')->end();

        $configTree->children()->arrayNode(static::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode(static::LOG)->defaultTrue()->end()
            ->scalarNode(static::DESTINATION_FILE)->defaultValue('crontab')->end()
            ->booleanNode(static::HEARTBEAT)->defaultTrue()->end()
            ->scalarNode(static::USER)->defaultNull()->end();

        $commandsTreeDefinition = $cronjobTree->children()->arrayNode(static::COMMANDS)
            ->isRequired()
            ->useAttributeAsKey(static::NAME)
            ->prototype('array');

        /** @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue */
        \assert($commandsTreeDefinition instanceof ArrayNodeDefinition);

        $commandsTree = $commandsTreeDefinition->children();

        $commandsTree->scalarNode(static::NAME)->end()
            ->scalarNode(static::LOG_FILE_NAME)->defaultNull()->end()
            ->scalarNode(static::USER)->defaultNull()->end()
            ->scalarNode(static::DESTINATION_FILE)->defaultNull()->end();

        $commandsTree->arrayNode(static::COMMAND)
            ->isRequired()
            ->beforeNormalization()->ifString()->then(fn($commandValue) => [$commandValue])->end()
            ->scalarPrototype()->end();

        $commandsTree->arrayNode(static::SCHEDULE)
            ->children()
            ->scalarNode(static::MINUTE)->defaultValue('*')->end()
            ->scalarNode(static::HOUR)->defaultValue('*')->end()
            ->scalarNode(static::DAY_OF_MONTH)->defaultValue('*')->end()
            ->scalarNode(static::MONTH)->defaultValue('*')->end()
            ->scalarNode(static::DAY_OF_WEEK)->defaultValue('*')->end()
            ->end();

        $commandsTree->arrayNode(static::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode(static::LOG)->defaultNull()->end();

        return $cronjobTree;
    }

    protected function buildWorker(): NodeDefinition
    {
        $workerTree = (new TreeBuilder(static::WORKER))->getRootNode()
            ->addDefaultsIfNotSet();

        $configTree = $workerTree->children()->arrayNode(static::CONFIG)
            ->addDefaultsIfNotSet();

        $configTree->children()
            ->scalarNode(static::TEMPLATE_CLASS)->defaultValue(SupervisorTemplate::class)->end()
            ->scalarNode(static::CONF_FILES_DIR)->defaultValue(static::DESTINATION_DIR . 'worker')->end()
            ->scalarNode(static::LOGS_DIR)->defaultValue('%kernel.logs_dir%/worker')->end();

        $settingsTree = $configTree->children()->arrayNode(static::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet();
        $this->appendSupervisorConfig($settingsTree->children());

        $commandsTree = $workerTree->children()->arrayNode(static::COMMANDS)
            ->isRequired()
            ->useAttributeAsKey(static::NAME)
            ->prototype('array');

        /** @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue */
        \assert($commandsTree instanceof ArrayNodeDefinition);

        $commandsTree->children()
            ->scalarNode(static::NAME)->end()
            ->arrayNode(static::COMMAND)
            ->isRequired()
            ->beforeNormalization()->ifString()->then(fn($commandValue) => [$commandValue])->end()
            ->scalarPrototype()->end()
            ->end();

        $settingsTree = $commandsTree->children()->arrayNode(static::SETTINGS)
            ->ignoreExtraKeys(false)
            ->addDefaultsIfNotSet();
        $this->appendSupervisorConfig($settingsTree->children(), false);

        return $workerTree;
    }

    protected function appendSupervisorConfig(NodeBuilder $nodeBuilder, bool $withDefaults = true): void
    {
        $numberOfProcessesNode = $nodeBuilder->integerNode(static::NUMBER_OF_PROCESSES);
        $numberOfProcessesNode = true === $withDefaults ? $numberOfProcessesNode->defaultValue(1) : $numberOfProcessesNode->defaultNull();
        $numberOfProcessesNode->end();

        $autoStartNode = $nodeBuilder->booleanNode(static::AUTO_START);
        $autoStartNode = true === $withDefaults ? $autoStartNode->defaultTrue() : $autoStartNode->defaultNull();
        $autoStartNode->end();

        $autoRestartNode = $nodeBuilder->booleanNode(static::AUTO_RESTART);
        $autoRestartNode = true === $withDefaults ? $autoRestartNode->defaultTrue() : $autoRestartNode->defaultNull();
        $autoRestartNode->end();

        $nodeBuilder->scalarNode(static::PREFIX)->defaultNull()->end()
            ->scalarNode(static::USER)->defaultNull()->end()
            ->scalarNode(static::LOG_FILE)->defaultNull()->end();
    }
}
