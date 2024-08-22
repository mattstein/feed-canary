<?php

/**
 * This file is part of the TwigBridge package.
 *
 * @copyright Robert Crowe <hello@vivalacrowe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Extensions;

use Illuminate\Foundation\Vite as IlluminateVite;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Access Laravel’s string class in your Twig templates.
 */
class TwigVite extends AbstractExtension
{
    protected string|object $callback = IlluminateVite::class;

    /**
     * Return the string object callback.
     */
    public function getCallback(): object|string
    {
        return $this->callback;
    }

    /**
     * Set a new string callback.
     */
    public function setCallback(object|string $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'TwigBridge_Extension_Laravel_Vite';
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'vite',
                function (...$arguments) {
                    // @phpstan-ignore-next-line
                    $arguments ??= '()';

                    $html = app(IlluminateVite::class)($arguments);

                    return $html->toHtml();
                }
            ),
        ];
    }
}
