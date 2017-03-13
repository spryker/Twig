<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\Twig\Model\Loader;

use Twig_Error_Loader;
use Twig_ExistsLoaderInterface;
use Twig_LoaderInterface;

class FilesystemLoader implements Twig_LoaderInterface, Twig_ExistsLoaderInterface
{

    /**
     * @var array
     */
    protected $bundlePaths = [];

    /**
     * @var array
     */
    protected $paths;

    /**
     * @var array
     */
    protected $cache;

    /**
     * @param array $paths
     */
    public function __construct(array $paths = [])
    {
        $this->setPaths($paths);
    }

    /**
     * Sets the paths where templates are stored.
     *
     * @param array $paths A path or an array of paths where to look for templates
     *
     * @return void
     */
    public function setPaths(array $paths)
    {
        $this->paths = [];
        foreach ($paths as $path) {
            $this->addPath($path);
        }
    }

    /**
     * Adds a path where templates are stored.
     *
     * @param string $path A path where to look for templates
     *
     * @return void
     */
    public function addPath($path)
    {
        // invalidate the cache
        $this->cache = [];
        $this->paths[] = rtrim($path, '/\\');
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        $name = (string)$name;
        if (isset($this->cache[$name])) {
            return $this->cache[$name] !== false;
        }

        try {
            $this->findTemplate($name);

            return true;
        } catch (Twig_Error_Loader $exception) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) <= $time;
    }

    /**
     * @param string $bundle
     *
     * @return array
     */
    protected function getPathsForBundle($bundle)
    {
        $paths = [];
        foreach ($this->paths as $path) {
            $path = sprintf($path, $bundle);
            if (strpos($path, '*') !== false) {
                $path = glob($path);
                if (count($path) > 0) {
                    $paths[] = $path[0];
                }
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param string $name
     *
     * @throws \Twig_Error_Loader
     *
     * @return string
     */
    protected function findTemplate($name)
    {
        $name = (string)$name;

        if (isset($this->cache[$name])) {
            if ($this->cache[$name] !== false) {
                return $this->cache[$name];
            } else {
                throw new Twig_Error_Loader(sprintf('Unable to find template "%s" (cached).', $name));
            }
        }

        // normalize name
        $name = str_replace(['///', '//', '\\'], '/', $name);

        $this->validateName($name);

        if (isset($name[0]) && $name[0] === '@') {
            $pos = strpos($name, '/');
            if ($pos === false) {
                $this->cache[$name] = false;
                throw new Twig_Error_Loader(sprintf('Malformed bundle template name "%s" (expecting "@bundle/template_name").', $name));
            }
            $bundle = ucfirst(substr($name, 1, $pos - 1));
            $templateName = substr($name, $pos + 1);
        } else {
            $this->cache[$name] = false;
            throw new Twig_Error_Loader(sprintf('Missing bundle in template name "%s" (expecting "@bundle/template_name").', $name));
        }

        $paths = $this->getPathsForBundle($bundle);
        foreach ($paths as $path) {
            if (is_file($path . '/' . $templateName)) {
                return $this->cache[$name] = $path . '/' . $templateName;
            }
        }

        $this->cache[$name] = false;

        throw new Twig_Error_Loader(sprintf('Unable to find template "%s" (looked into: %s).', $templateName, implode(', ', $paths)));
    }

    /**
     * @param string $name
     *
     * @throws \Twig_Error_Loader
     *
     * @return void
     */
    protected function validateName($name)
    {
        if (strpos($name, "\0") !== false) {
            throw new Twig_Error_Loader('A template name cannot contain NUL bytes.');
        }

        $name = ltrim($name, '/');
        $parts = explode('/', $name);
        $level = 0;
        foreach ($parts as $part) {
            if ($part === '..') {
                --$level;
            } elseif ($part !== '.') {
                ++$level;
            }

            if ($level < 0) {
                throw new Twig_Error_Loader(sprintf('Looks like you try to load a template outside configured directories (%s).', $name));
            }
        }
    }

}