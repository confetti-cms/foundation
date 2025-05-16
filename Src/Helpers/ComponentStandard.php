<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Helpers;


use App\Components\ListComponent;
use App\Components\SelectFileComponent;
use Confetti\Parser\Components\Map;
use Exception;
use JsonException;
use RuntimeException;

abstract class ComponentStandard
{
    private const FORBIDDEN_PHP_KEYWORDS = [
        "abstract",
        "and",
        "array",
        "as",
        "break",
        "callable",
        "case",
        "catch",
        "class",
        "clone",
        "const",
        "continue",
        "declare",
        "default",
        "die",
        "do",
        "echo",
        "else",
        "elseif",
        "empty",
        "enddeclare",
        "endfor",
        "endforeach",
        "endif",
        "endswitch",
        "endwhile",
        "eval",
        "exit",
        "extends",
        "final",
        "finally",
        "for",
        "foreach",
        "function",
        "global",
        "goto",
        "if",
        "implements",
        "include",
        "include_once",
        "instanceof",
        "insteadof",
        "interface",
        "isset",
        "list",
        "namespace",
        "new",
        "or",
        "print",
        "private",
        "protected",
        "public",
        "require",
        "require_once",
        "return",
        "static",
        "switch",
        "throw",
        "trait",
        "try",
        "unset",
        "use",
        "var",
        "while",
        "xor",
        "yield",
    ];

    private array $decorations = [];

    public function __construct(
        protected ?string       $parentContentId = null,
        protected ?string       $relativeContentId = null,
        // We use the reference because we want to init the rest of the content store
        protected ?ContentStore $contentStore = null,
    )
    {
    }

    /**
     * This method will return the query class. When it is a list,
     * it will return the query class that extends ListComponent.
     */
    public static function query(): mixed
    {
        throw new RuntimeException('This method `query` should be overridden in the child class.');
    }

    public static function getComponentKey(): string
    {
        throw new RuntimeException('This method `getComponentKey` should be overridden in the child class.');
    }

    /**
     * @internal This method is not part of the public API and should not be used.
     */
    protected static function getParamsForNewQuery(string $id): array
    {
        // We need to know where this method is called from so that we can store
        // it as a very specific small part in the advanced caching mechanism.
        // This allows us to replace a specific component in a large caching content.
        $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $as       = $location['file'] . ':' . $location['line'];
        // Get relative and parent from the key.
        [$parent, $relative] = ComponentStandard::explodeKey($id);
        // We use $parent (not $key) to get the data in the join.
        // We do this because that is in line with how ListComponent handles the data.
        return [$parent, $relative, new ContentStore($parent, $as, false), $as, true];
    }

    /**
     * When using the abstract component (\App\Components\TextComponent) we use this method.
     * For example, as a child of the ContentComponent.
     * The specific component (\model\homepage\feature\title) will override this method.
     */
    public function getComponent(): ComponentEntity
    {
        $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $dir      = dirname($location['file']);
        $file     = basename($location['file']);
        return new ComponentEntity(
            self::componentKeyFromContentId(self::mergeIds($this->parentContentId, $this->relativeContentId)),
            self::class,
            $this->type(),
            $this->decorations,
            $this::getViewAdminPreview(),
            new SourceEntity(
                $dir,
                $file,
                $location['line'],
                0,
                0,
            ),
        );
    }

    public function getId(): string
    {
        return self::mergeIds($this->parentContentId, $this->relativeContentId);
    }

    public function getParentId(): string
    {
        return $this->parentContentId;
    }

    public function getRelativeId(): string
    {
        return $this->relativeContentId;
    }

    public function getChildren(): array
    {
        return [];
    }

    public static function componentKeyFromContentId(string $contentId): string
    {
        return preg_replace('/~[A-Z0-9_]+/', '~', $contentId);
    }

    /**
     * @param string[] $ids
     * @return string[]|Map[]|ComponentStandard[]|\ConfettiCms\Foundation\Helpers\DeveloperActionRequiredException
     */
    public static function componentClassNamesByIds(array $ids, ContentStore $store): array|DeveloperActionRequiredException
    {
        if (empty($ids)) {
            return [];
        }
        $query = new QueryBuilder();
        foreach ($ids as $id) {
            $query->appendSelect($id);
        }
        try {
            $values = $query->run()[0]['data'];
        } catch (JsonException $e) {
            return new DeveloperActionRequiredException('Error gj5o498h5: can\'t decode options: ' . $e->getMessage());
        }
        $result = [];
        foreach ($values as $id => $value) {
            $className = ComponentStandard::componentClassById($id, $store);
            if ($className instanceof DeveloperActionRequiredException) {
                throw $className;
            }
            $result[$id] = $className;
        }
        return $result;
    }

    /**
     * @return class-string|\Confetti\Parser\Components\Map|ComponentStandard
     * @noinspection PhpDocSignatureInspection
     */
    public static function componentClassById(string $id, ContentStore $store): string|DeveloperActionRequiredException
    {
        $pointerValues = self::getPointerValues($id, $store);

        $parts     = explode('/', ltrim($id, '/'));
        $pointerId = null;
        $result    = [];
        $idSoFar   = '';
        foreach ($parts as $part) {
            // If the parent is a pointer, the child needs a totally different class.
            if ($pointerId) {
                $className = '\\' . implode('\\', $result);
                $extended  = self::getExtendedModelKey($className, $idSoFar, $pointerValues);
                if ($extended instanceof DeveloperActionRequiredException) {
                    return $extended;
                }
                $result    = explode('\\', get_class($extended));
                $pointerId = null;
            }

            // Remove id banner/image~0123456789 -> banner/image~
            $classPart     = preg_replace('/~[A-Z0-9_]{10}/', '~', $part);
            // Remove model pointers banner/image~ -> banner/image_list
            if (str_ends_with($classPart, '~')) {
                $classPart = str_replace('~', '_list', $classPart);
            }
            // Remove file pointers banner/template- -> banner/template
            if (str_ends_with($classPart, '-')) {
                $pointerId = $classPart;
                $classPart = str_replace('-', '_pointer', $classPart);
            }
            // Rename forbidden class names
            if (in_array($classPart, self::FORBIDDEN_PHP_KEYWORDS)) {
                $classPart .= '_';
            }
            $result[] = $classPart;
            $idSoFar .= '/' . $part;
        }
        return '\\' . implode('\\', $result);
    }

    /**
     * The type is used to determine the method name.
     * E.g. `$header->selectFile('template')`. Where `selectFile` is the method name.
     */
    abstract public function type(): string;

    /**
     * Get should return the value that is most likely to be used.
     */
    abstract public function get(bool $useDefault = false): mixed;

    /**
     * The return value is a full path from the root to a blade file.
     * E.g. `/admin/components/color/input.blade.php`
     */
    abstract public function getViewAdminInput(): string;

    /**
     * The return value is a full path from the root to a preview file.
     *  E.g. `/admin/components/color/preview.mjs`
     */
    abstract public static function getViewAdminPreview(): string;

    /**
     * toString should return the value that is most likely to be used when converted to a string.
     */
    public function __toString(): string
    {
        if ($this->contentStore === null) {
            throw new RuntimeException("Component '{ComponentStandard::getComponent()->key}' is only used as a reference. Therefore, you can't convert `new {ComponentStandard::getComponent()->key}` to a string.");
        }
        $value = $this->get(useDefault: true);
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }


    public static function mergeIds(string $parent, string $relative): string
    {
        // If relative is already full, us it.
        if (str_starts_with($relative, '/')) {
            return $relative;
        }

        // If relative needs to look back, cut the parent.
        // `/model/page/block~22FCX8Q5VV/row-` with `../title`
        // should return `/model/page/block~22FCX8Q5VV/title`
        if (str_starts_with($relative, '../')) {
            $parts = explode('/', $parent);
            $parts = array_slice($parts, 0, count($parts) - substr_count($relative, '../'));
            $parent = implode('/', $parts);
            $relative = substr($relative, 3);
        }

        return $parent . '/' . $relative;
    }

    /**
     * @internal This method is not part of the public API and should not be used.
     */
    protected function getParamsForSelectedModel(): array
    {
        // We need to know where this method is called from so that we can store
        // it as a very specific small part in the advanced caching mechanism.
        // This allows us to replace a specific component in a large caching content.
        $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $as       = $location['file'] . ':' . $location['line'];

        // Parameters for the constructor of the child classes.
        return [$this->parentContentId, $this->relativeContentId, $this->contentStore, $as];
    }

    protected function setDecoration(string $key, mixed $value): void
    {
        $this->decorations[$key] = $value;
    }

    protected function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return 'Error 7o8h5edg4n5jk: can\'t decode options: ' . $json . ', Message ' . $e->getMessage();
        }
    }

    private static function getExtendedModelKey(string $pointerClassName, string $id, array $pointerValues): Map|Exception
    {
        $value = $pointerValues[$id] ?? null;

        /** @var SelectFileComponent $pointer */
        $params  = self::getParamsForNewQuery($id);
        $pointer = new $pointerClassName(...$params);
        // Get class and get the pointed file from the class
        return self::getExtendedModelByPointer($pointer, $value);
    }

    private static function getExtendedModelByPointer(SelectFileComponent $pointer, ?string $value): Map|Exception
    {
        $options = $pointer->getOptions();

        if ($value) {
            if (count($options) === 0) {
                return new DeveloperActionRequiredException("Selected value found to extend '{$pointer->getId()}'. But no options are set. Defined in '{$pointer->getComponent()->source}'");
            }
            if (!array_key_exists($value, $options)) {
                return new DeveloperActionRequiredException("Selected value found to extend '{$pointer->getId()}'. But file doesn't exist in the options list. Defined in '{$pointer->getComponent()->source}'");
            }
            return $options[$value];
        }
        // Get default value
        $file = $pointer->getComponent()->getDecoration('default');
        if ($file && array_key_exists($file, $options)) {
            return $options[$file];
        }
        // If no default value is set, use the first file in the list
        $file = $pointer->getComponent()->getDecoration('match', 'files')[0] ?? null;
        if ($file && array_key_exists($file, $options)) {
            return $options[$file];
        }
        return new DeveloperActionRequiredException("Can't found default value or first file in the list to extend '{$pointer->getId()}'. Make sure that there are options defined in '{$pointer->getComponent()->source}'");
    }

    /**
     * To get the target id of a pointer, we need to get the pointer values.
     */
    public static function getPointerValues(string $id, ContentStore $store): array
    {
        if (!str_contains($id, '-')) {
            return [];
        }
        $allAlreadySelected = true;
        $result = [];
        $parts = explode('/', ltrim($id, '/'));
        $idSoFar = '';
        $content = $store->getContent();
        foreach ($parts as $part) {
            $idSoFar .= '/' . $part;
            // We can only add a result here if the pointer is already selected
            // We only need to get the pointer values
            if (!empty($content['data']) && array_key_exists($idSoFar, $content['data']) && str_ends_with($part, '-')) {
                $result[$idSoFar] = $content['data'][$idSoFar];
                continue;
            }
            if (str_ends_with($part, '-')) {
                $allAlreadySelected = false;
                $store->selectInRoot($idSoFar);
            }
        }
        if ($allAlreadySelected) {
            return $result;
        }

        $init = $store->runInit();
        if (!$init) {
            $store->runCurrentQuery([
                'reason'                 => 'Get pointer values for ' . $id,
                'use_cache'               => true,
                'use_cache_from_root'     => true,
                'patch_cache_select'      => true,
            ]);
        }
        $content = $store->getContent();
        $idSoFar = '';
        foreach ($parts as $part) {
            $idSoFar .= '/' . $part;
            // We are only interested in the pointer values
            if (str_ends_with($part, '-') && array_key_exists($idSoFar, $content['data'] ?? [])) {
                $result[$idSoFar] = $content['data'][$idSoFar];
            }
        }

        // Store the pointer values in the content store
        // so we
        $store->appendPointerValues($result);

        return $result;
    }

    /**
     * @return string[]
     */
    public static function explodeKey(string $key): array
    {
        $found    = preg_match('/(?<parent>.*)\/(?<relative>[^\/]*)$/', $key, $matches);
        $parent   = $found === 0 ? $key : $matches['parent'];
        $relative = $found === 0 ? '' : $matches['relative'];
        return [$parent, $relative];
    }
}
