<?php

/**
 * @file
 * Contains \WP\Console\Core\Generator\Generator.
 */

namespace WP\Console\Core\Generator;

use WP\Console\Core\Utils\CountCodeLines;
use WP\Console\Core\Utils\TwigRenderer;
use WP\Console\Core\Utils\FileQueue;

/**
 * Class Generator
 *
 * @package WP\Console\Core\Generator
 */
abstract class Generator
{
    /**
     * @var TwigRenderer
     */
    protected $renderer;

    /**
     * @var FileQueue
     */
    protected $fileQueue;

    /**
     * @var CountCodeLines
     */
    protected $countCodeLines;

    /**
     * @param $renderer
     */
    public function setRenderer(TwigRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @param $fileQueue
     */
    public function setFileQueue(FileQueue $fileQueue)
    {
        $this->fileQueue = $fileQueue;
    }

    /**
     * @param $countCodeLines
     */
    public function setCountCodeLines(CountCodeLines $countCodeLines)
    {
        $this->countCodeLines = $countCodeLines;
    }

    /**
     * @param string $template
     * @param string $target
     * @param array  $parameters
     * @param null   $flag
     *
     * @return bool
     */
    protected function renderFile(
        $template,
        $target,
        $parameters = [],
        $flag = null
    ) {
        if (!is_dir(dirname($target))) {
            if(!mkdir(dirname($target), 0777, true)){
                throw new \InvalidArgumentException(
                    sprintf(
                        'Path "%s" is invalid. You need to provide a valid path.',
                        dirname($target)
                    )
                );
            }
        }

        //Count the code lines if the file exist
        $currentLine = 0;
        if (!empty($flag) && file_exists($target)) {
            $currentLine = count(file($target));
        }
        $content = $this->renderer->render($template, $parameters);

        if (file_put_contents($target, $content, $flag)) {
            $this->fileQueue->addFile($target);

            $newCodeLine = count(file($target));

            if ($currentLine > 0) {
                $newCodeLine = ($newCodeLine-$currentLine);
            }

            $this->countCodeLines->addCountCodeLines($newCodeLine);

            return true;
        }

        return false;
    }
}
