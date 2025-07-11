<?php /** @noinspection ALL */

use App\Components\RootComponent;
use ConfettiCms\Parser\Components\Map;
use ConfettiCms\Foundation\Helpers\Request;
use ConfettiCms\Foundation\Helpers\ComponentStandard;
use ConfettiCms\Foundation\Helpers\ContentStore;

/**
 * @template M
 * @param M $target
 * @return M|RootComponent
 */
function newRoot(RootComponent $target, int $trace = 0): RootComponent
{
    $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace + 1)[$trace];
    $as       = $location['file'] . ':' . $location['line'];

    $model = $target->newRoot(
        $target::getComponentKey(),
        $as,
    );

    return $model;
}

function modelById(string $contentId): Map|ComponentStandard
{
    $location  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $as        = $location['file'] . ':' . $location['line'];

    $contentStore = new ContentStore($contentId, $as, true);
    $className = \ConfettiCms\Foundation\Helpers\ComponentStandard::componentClassById($contentId, $contentStore);

    if (class_exists($className) === false) {
        throw new \RuntimeException('Error: o87huigr3. Model not found: ' . $className . ' for id: ' . $contentId);
    }

    return (new $className('', $contentId, $contentStore));
}

/**
 * You can use this in situations where you don't know what the parent classes are.
 */
function extendModel(\ConfettiCms\Parser\Components\Map|\ConfettiCms\Foundation\Contracts\SelectModelInterface &$component): Map
{
    if ($component instanceof \ConfettiCms\Foundation\Contracts\SelectModelInterface) {
        return $component->getSelected();
    }
    return $component;
}

/**
 * This array is loaded from the `config.json5` file.
 * You can access the configuration of the current environment and container.
 * The key `environments` is now `environment` and `containers` is now `container`.
 *
 * Examples:
 * You can get the name of the current environment with `config('environment.name')`.
 * You can get the option `dev_tools` of the current environment with `config('environment.options.dev_tools')`.
 * You can get the name of the current container with `config('environment.container.name')`.
 * You can get the hosts with `config('environment.container.hosts')`.
 *
 * Customize this function to suit your needs, but don't unset existing keys, as they might be used elsewhere.
 */
function config(string $key, mixed $default = null): mixed
{
    $config = json_decode($_ENV['APP_CONFIG']);

    /**
     * This part is where the magic happens! It fetches the value from the config array.
     * Feel free to marvel at its simplicity or tweak it to your needs.
     */
    $parts = explode('.', $key);
    foreach ($parts as $part) {
        if (!isset($config->$part)) {
            return $default;
        }
        $config = $config->$part;
    }

    return $config;
}

/**
 * @deprecated
 */
function hashId(string $id): string
{
    throw new \RuntimeException('This function is deprecated');
    return '_' . hash('crc32b', $id);
}

/**
 * We need to define this function here. On the remote server,
 * the blade files are compiled to the cache directory.
 * To handle other none blade files, you can use this
 * function to get the current repository directory.
 */
function repositoryPath(): string
{
    return __REPOSITORY_PATH__;
}

function request(): Request
{
    return new Request();
}

/**
 * @param array $variables example: ['currentContentId', 'The value']
 */
function variables(&$variables)
{
    return array_values($variables);
}

function titleByKey(string $key): string
{
    // Guess label from the last part of the relative content id
    $parts = explode('/', $key);
    $part  = end($parts);
    $part  = str_replace(['_', '~'], ' ', $part);
    return ucwords($part);
}

// This function is used to generate an id for a part of
// a content id. This id is always prefixed with a ~.
// Example: '/model/pages/page~' . newId()
function newId(): string
{
    $char               = '123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $encodingLength     = strlen($char);
    $desiredLengthTotal = 10;
    $desiredLengthTime  = 6;

    // Encode time
    // We use the time since a fixed point in the past.
    // This gives us a more space to use in the feature.
    $time = time() - 1684441872;
    $out  = '';
    while (strlen($out) < $desiredLengthTime) {
        $mod  = $time % $encodingLength;
        $out  = $char[$mod] . $out;
        $time = ($time - $mod) / $encodingLength;
    }

    // Encode random
    while (strlen($out) < $desiredLengthTotal) {
        $rand = random_int(0, $encodingLength - 1);
        $out  .= $char[$rand];
    }

    return $out;
}

function slugId($id) {
    $id = preg_replace("/([\W]+)/", "-", $id);
    return trim($id, '-');
}

function getServiceApi(): string
{
    return request()->scheme() . '://' . request()->host() . '/conf_api';
}
