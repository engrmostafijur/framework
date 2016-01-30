<?php

namespace Kraken\Provider\Config;

use Kraken\Config\Config;
use Kraken\Config\ConfigFactory;
use Kraken\Config\ConfigInterface;
use Kraken\Core\CoreInterface;
use Kraken\Core\CoreInputContextInterface;
use Kraken\Core\Service\ServiceProvider;
use Kraken\Core\Service\ServiceProviderInterface;
use Kraken\Filesystem\Filesystem;
use Kraken\Filesystem\FilesystemAdapterFactory;
use Kraken\Support\ArraySupport;
use Kraken\Support\StringSupport;

class ConfigProvider extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var string[]
     */
    protected $requires = [
        'Kraken\Core\CoreInputContextInterface'
    ];

    /**
     * @var string[]
     */
    protected $provides = [
        'Kraken\Config\ConfigInterface'
    ];

    /**
     * @param CoreInterface $core
     */
    protected function register(CoreInterface $core)
    {
        $context = $core->make('Kraken\Core\CoreInputContextInterface');

        $global = $core->dataPath() . '/config-global/' . $this->getDir($core->unit());
        $local  = $core->dataPath() . '/config/' . $context->name();

        $config = new Config();
        $this->addConfigByPath($config, $global);
        $this->addConfigByPath($config, $local);
        $this->addConfig($config, new Config($core->config()));

        $vars = array_merge(
            $config->exists('vars') ? $config->get('vars') : [],
            $this->getDefaultVariables($core, $context)
        );

        $records = ArraySupport::flatten($config->all());
        foreach ($records as $key=>$value)
        {
            $new = StringSupport::parametrize($value, $vars);
            if (is_string($value) && $new != $value)
            {
                $config->set($key, $new);
            }
        }

        $core->instance(
            'Kraken\Config\ConfigInterface',
            $config
        );
    }

    /**
     * @param CoreInterface $core
     */
    protected function unregister(CoreInterface $core)
    {
        $core->remove(
            'Kraken\Config\ConfigInterface'
        );
    }

    /**
     * @param string $path
     * @return ConfigInterface
     */
    private function createConfig($path)
    {
        if (!is_dir($path))
        {
            return new Config();
        }

        $factory = new FilesystemAdapterFactory();

        return (new ConfigFactory(
            new Filesystem(
                $factory->create('Local', [ [ 'path' => $path ] ])
            )
        ))->create();
    }

    /**
     * @param string $runtimeUnit
     * @return string
     */
    private function getDir($runtimeUnit)
    {
        return $runtimeUnit;
    }

    /**
     * @param ConfigInterface $config
     * @param string $option
     * @return callable
     */
    private function getOverwriteHandler(ConfigInterface $config, $option)
    {
        switch ($option)
        {
            case 'isolate':     return [ $config, 'getOverwriteHandlerIsolater' ];
            case 'replace':     return [ $config, 'getOverwriteHandlerReplacer' ];
            case 'merge':       return [ $config, 'getOverwriteHandlerMerger' ];
            default:            return [ $config, 'getOverwriteHandlerMerger' ];
        }
    }

    /**
     * @param ConfigInterface $config
     * @param string $path
     */
    private function addConfigByPath(ConfigInterface $config, $path)
    {
        $this->addConfig($config, $this->createConfig($path));
    }

    /**
     * @param ConfigInterface $config
     * @param ConfigInterface $current
     */
    private function addConfig(ConfigInterface $config, ConfigInterface $current)
    {
        $dirs = (array) $current->get('config.dirs');
        foreach ($dirs as $dir)
        {
            $this->addConfigByPath($current, $dir);
        }

        if ($current->exists('config.mode'))
        {
            $config->setOverwriteHandler(
                $this->getOverwriteHandler($config, $current->get('config.mode'))
            );
        }

        $config->merge($current->all());
    }

    /**
     * @param CoreInterface $core
     * @param CoreInputContextInterface $context
     * @return string[]
     */
    private function getDefaultVariables(CoreInterface $core, CoreInputContextInterface $context)
    {
        return [
            'runtime'   => $context->type(),
            'parent'    => $context->parent(),
            'alias'     => $context->alias(),
            'name'      => $context->name(),
            'basepath'  => $core->basePath(),
            'datapath'  => $core->dataPath(),
            'host.main' => '127.0.0.1'
        ];
    }
}
