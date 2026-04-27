<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Exception;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ProfileNotFoundException extends DevToolsException
{
    public static function withId(string $id): self
    {
        return new self("Profile '{$id}' not found.");
    }
}
