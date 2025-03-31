<?php

declare(strict_types=1);

namespace Confetti\Foundation\Model;

use Confetti\Foundation\Helpers\ComponentEntity;
use Confetti\Foundation\Helpers\ComponentStandard;
use Confetti\Foundation\Helpers\ContentStore;
use Confetti\Foundation\Helpers\DeveloperActionRequiredException;
use RuntimeException;

abstract class BaseMap
{
    public function __construct(
        protected ?string       $parentContentId = null,
        protected ?string       $relativeContentId = null,
        protected ?ContentStore $contentStore = null,
    )
    {
        // In the admin, we can select another pointer value than the saved one.
        // In that case, we need data from the other pointer value.
        if ($relativeContentId && str_ends_with($relativeContentId, '-')) {
            $this->contentStore = clone $this->contentStore;
            $content = $this->contentStore->getContent();
            $content['data'][$this->getId()] = $this->getComponent()->source->getPath();
            $this->contentStore->setContent($content);
        }
    }

    public static function getComponentKey(): string
    {
        // This method should be overridden in the child class.
        // We can't make this method abstract since we need it in getParamsForNewQuery().
        throw new RuntimeException("The method getComponentKey() is not implemented in the child class.");
    }

    public function __get(string $name): void
    {
        throw new \RuntimeException("Model ->{$name}(...) not found. Create a model with `->...('{$name}')`");
    }

    abstract public function getComponent(): ComponentEntity;

    public static function getViewAdminPreview(): string
    {
        return 'not_implemented_since_we_thing_this_is_not_needed';
    }

    public function canFake(bool $canFake = true): self
    {
        $this->contentStore->setCanFake($canFake);
        return $this;
    }

    public function getId(): string
    {
        return ComponentStandard::mergeIds($this->parentContentId, $this->relativeContentId);
    }

    /**
     * The map itself can have a value. For example, for
     * a list item, we use this value to store the order of the list.
     * @return mixed
     */
    public function get(): mixed
    {
        return $this->contentStore->findOneData($this->parentContentId, '.');
    }


    /**
     * @deprecated check if this is really needed
     * @internal This method is not part of the public API and should not be used.
     */
    public function newRoot(string $contentId, string $as): self
    {
        $contentStore = new ContentStore($contentId, $as, true);
        return new static("", $contentId, $contentStore);
    }

    public function getLabel(): string
    {
        $component = $this->getComponent();
        $label     = $component->getDecoration('label');
        if ($label) {
            return $label;
        }
        return titleByKey($component->key);
    }

    /**
     * @return \Confetti\Parser\Components\Map[]|\App\Components\ListComponent[]
     */
    public function getChildren(): array
    {
        // Normally, this method should be overridden in the child class.
        // As situations may be:
        // 1. Create a file with ->list('blogs')->get()
        // 2. Use ->blogs()->get() in another file.
        // 3. Remove ->list('blogs')->get()
        throw new RuntimeException("No method list() found to get children of {$this->getId()}. Please define list with the method `list('{$this->parentContentId}')`.");
    }

    public function getParentId(): string
    {
        [$parent] = ComponentStandard::explodeKey($this->getId());
        return $parent;
    }

    /**
     * @internal This method is not part of the public API and should not be used.
     */
    protected static function getParamsForNewQuery(): array
    {
        // We need to know where this method is called from so that we can store
        // it as a very specific small part in the advanced caching mechanism.
        // This allows us to replace a specific part of the large cached query.
        $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $as       = $location['file'] . ':' . $location['line'];
        // Get relative and parent from the key.
        $key = static::getComponentKey();
        [$parent, $relative] = ComponentStandard::explodeKey($key);
        // 5'th: Return true to indicate that in this level, we want to use the full id in the query.
        //       Because it is not related to the parent
        return [$parent, $relative, new ContentStore($key, $as, false), $as, true];
    }

    /**
     * @internal This method is not part of the public API and should not be used.
     */
    protected function getParamsForProperty(string $key): array
    {
        $store = clone $this->contentStore;
        // Parameters for the constructor of the child classes.
        return [$this->getId(), $key, $store];
    }

    /**
     * @internal This method is not part of the public API and should not be used.
     */
    protected function getParamsForList(string $key): array
    {
        // We need to know where this method is called from so that we can store
        // it as a very specific small part in the advanced caching mechanism.
        // This allows us to replace a specific component in a large caching content.
        $location = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $as       = $location['file'] . ':' . $location['line'];

        // Parameters for the constructor of the child classes.
        return [$this->getId(), $key . '~', $this->contentStore, $as];
    }

    protected function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return 'Error 7o8h5edg4n5jk: can\'t decode options: ' . $json . ', Message ' . $e->getMessage();
        }
    }

    public function label(string $label): self
    {
        return $this;
    }

    protected function getComponentByRelativeId(string $relativeId): mixed
    {
        $className = ComponentStandard::componentClassById(
            $this->getId() . '/' . $relativeId,
            $this->contentStore
        );
        if ($className instanceof DeveloperActionRequiredException) {
            throw $className;
        }

        // Here we have to clone the content store because otherwise, the next
        // selector on this level will not be present on this leven but on one level deeper.
        $store = clone $this->contentStore;

        return new $className(
            $this->getId(),
            $relativeId,
            $store,
        );
    }
}
