<?php

namespace Mix\Cli\Argument;

/**
 * Class Arguments
 * @package Mix\Cli\Argument
 */
class Arguments
{

    /**
     * @var string
     */
    protected static $command = '';

    /**
     * @param bool $singleton
     */
    public static function parse(bool $singleton = false): void
    {
        if ($singleton) {
            return;
        }
        $argv = $GLOBALS['argv'];
        $command = $argv[1] ?? '';
        $command = preg_match('/^[a-zA-Z0-9_\-:]+$/i', $command) ? $command : '';
        $command = substr($command, 0, 1) == '-' ? '' : $command;
        static::$command = trim($command);
    }

    /**
     * @return string
     */
    public static function script(): string
    {
        $argv = $GLOBALS['argv'];
        return $argv[0];
    }

    /**
     * @return string
     */
    public static function command(): string
    {
        return static::$command;
    }

}