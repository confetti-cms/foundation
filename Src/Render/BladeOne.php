<?php

declare(strict_types=1);

namespace Confetti\Foundation\Render;

use Exception;
use Confetti\Foundation\User;
use Countable;
use IteratorAggregate;
use function array_pop;
use function array_reverse;
use function count;
use function implode;
use function in_array;
use function is_countable;
use function ob_get_clean;
use function ob_start;
use function substr;

class BladeOne extends \eftec\bladeone\BladeOne
{
    private User $currentUserObject;

    public function __construct($templatePath = null, $compiledPath = null, $mode = 0)
    {
        parent::__construct($templatePath, $compiledPath, $mode);

        $this->setCanFunction(function (
            $permission = null,
            /** @noinspection PhpUnusedParameterInspection */
            $subject = null
        ) {
            if (empty($this->currentPermission)) {
                $this->loadPermissions();
            }

            $permission = '/' . $_ENV['PROJECT_REPOSITORY_NAME'] . '/' . $permission;
            return in_array($permission, $this->currentPermission, true);
        });

        $this->setAnyFunction(function ($array = []) {
            if (empty($this->currentPermission)) {
                $this->loadPermissions();
            }

            foreach ($array as $permission) {
                $permission = '/' . $_ENV['PROJECT_REPOSITORY_NAME'] . '/' . $permission;
                if (in_array($permission, $this->currentPermission ?? [], true)) {
                    return true;
                }
            }
            return false;
        });
    }

    protected function loadPermissions(): void
    {
        if (empty($_COOKIE['access_token'])) {
            return;
        }
        $url = 'confetti-cms__auth:80/users/me/permissions';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: bearer ' . $_COOKIE['access_token'],
            ),
        ));

        $response = curl_exec($curl);
        if($response === false) {
            throw new Exception('Error: with url ' . $url . ': Message: ' .curl_error($curl));
        }
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($httpCode >= 401) {
            $this->currentUser = null;
            $this->currentPermission = [];

            // Unauthorized, let the user redirect to the login page
            return;
        }

        if ($httpCode >= 400) {
            throw new Exception('Error: ' . $response . ': Code: ' . $httpCode);
        }
        $body = json_decode($response, true);
        $this->currentUser = $body['name'];
        $this->currentUserObject = new User(
            $body['id'],
            $body['username'],
            $body['name'],
            $body['picture_url'],
        );
        $this->currentPermission = array_map(fn($permission) => $permission['id'], $body['permissions'] ?? []);
    }

    /**
     * Compile the Username statements into valid PHP.
     */
    public function compileUsername(): string
    {
        if (empty($this->currentUserObject)) {
            $this->loadPermissions();
        }
        return $this->currentUserObject->username;
    }

    /**
     * Compile the User picture statements into valid PHP.
     *
     * @return string
     */
    protected function compileUserPicture(): string
    {
        return $this->phpTagEcho . "'" . $this->currentUserObject->pictureUrl . "'; ?>";
    }

    /**
     * Compile the guest statements into valid PHP.
     *
     * @param null $expression
     * @return string
     */
    protected function compileGuest($expression = null): string
    {
        if ($expression === null) {
            return $this->phpTag . 'if(!request()->cookie("access_token")): ?>';
        }

        $permission = $this->stripParentheses($expression);
        if ($permission === '') {
            return $this->phpTag . 'if(!request()->cookie("access_token")): ?>';
        }

        return $this->phpTag . "if(!request()->cookie(\"access_token\") || \$this->currentRole!=$permission): ?>";
    }

    /**
     * Compile the auth statements into valid PHP.
     *
     * @param null $expression
     * @return string
     */
    protected function compileAuth($expression = null): string
    {
        if ($expression === null) {
            return $this->phpTag . 'if(request()->cookie("access_token")): ?>';
        }

        $permission = $this->stripParentheses($expression);
        if ($permission === '') {
            return $this->phpTag . 'if(request()->cookie("access_token")): ?>';
        }

        return $this->phpTag . "if(request()->cookie(\"access_token\") && \$this->currentRole==$permission): ?>";
    }

    /**
     * Get the string contents of a push section.
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    public function yieldPushContent($section, $default = ''): string
    {
        // If section has a * suffix, we will load all pushes for that section
        if (str_ends_with($section, '*')) {
            $section = substr($section, 0, -1);
            $result  = '';
            foreach ($this->pushes as $key => $value) {
                if (str_starts_with($key, $section)) {
                    $result .= ltrim(implode(array_reverse($value)));
                }
            }
            return $result;
        }
        // If the section doesn't exist, we'll just return the default content
        if (!isset($this->pushes[$section])) {
            return $default;
        }
        // Otherwise, we will implode the content of the section and return it
        return implode(array_reverse($this->pushes[$section]));
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    public function compilePushOnce($expression): string
    {
        $expression = $this->stripParentheses($expression);
        return $this->phpTag . "\$this->startPush($expression, '', true); ?>";
    }

    /**
     * Start injecting content into a push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startPush($section, $content = '', $once = false): void
    {
        if ($content === '') {
            if (ob_start()) {
                $this->pushStack[] = $section;
            }
        } else {
            $this->extendPush($section, $content, $once);
        }
    }

    /**
     * Compile the endpushonce statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpushOnce(): string
    {
        return $this->phpTag . '$this->stopPush(); ?>';
    }

    /**
     * Append content to a given push section.
     *
     * @param string $section
     * @param string $content
     * @param bool   $once
     * @return void
     */
    protected function extendPush($section, $content, bool $once = false): void
    {
        if (!isset($this->pushes[$section])) {
            $this->pushes[$section] = []; // start an empty section
        }
        if (!isset($this->pushes[$section][$this->renderCount])) {
            $this->pushes[$section][$this->renderCount] = $content;
        } elseif (!$once) {
            $this->pushes[$section][$this->renderCount] .= $content;
        }
    }

    /**
     * Stop injecting content into a push section.
     *
     * @return string
     */
    public function stopPush(): string
    {
        if (empty($this->pushStack)) {
            $this->showError('stopPush', 'Cannot end a section without first starting one', true);
        }
        $last = array_pop($this->pushStack);
        $this->extendPush($last, ob_get_clean(), true);
        return $last;
    }

    /**
     * Add new loop to the stack.
     *
     * @param array|Countable $data
     * @return void
     */
    public function addLoop($data): void
    {
        // `!$data instanceof IteratorAggregate` is an override to the original code
        // We don't want to trigger the `getIterator` method on the object
        $length = (is_countable($data) || $data instanceof Countable) && !$data instanceof IteratorAggregate ? count($data) : null;
        $parent = static::last($this->loopsStack);
        $this->loopsStack[] = [
            'index' => -1,
            'iteration' => 0,
            'remaining' => isset($length) ? $length + 1 : null,
            'count' => $length,
            'first' => true,
            'even' => true,
            'odd' => false,
            'last' => isset($length) ? $length == 1 : null,
            'depth' => count($this->loopsStack) + 1,
            'parent' => $parent ? (object)$parent : null,
        ];
    }
}
