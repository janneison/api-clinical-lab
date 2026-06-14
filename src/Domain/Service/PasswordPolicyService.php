<?php

declare(strict_types=1);

namespace ClinicalLab\Domain\Service;

use InvalidArgumentException;

/**
 * Política de contraseñas del sistema.
 *
 * Requisitos:
 *  - Mínimo 8 caracteres
 *  - Al menos una letra mayúscula (A-Z)
 *  - Al menos una letra minúscula (a-z)
 *  - Al menos un dígito (0-9)
 *  - Al menos un carácter especial del conjunto: ! @ # $ % & * + - _
 */
final class PasswordPolicyService
{
    /** Caracteres especiales permitidos */
    public const ALLOWED_SPECIAL = '!@#$%&*+-_';

    private const MIN_LENGTH = 8;

    /**
     * Valida la contraseña contra la política.
     *
     * @throws InvalidArgumentException con mensaje descriptivo si no cumple.
     */
    public function validate(string $password): void
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'debe tener al menos ' . self::MIN_LENGTH . ' caracteres';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'debe contener al menos una letra mayúscula';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'debe contener al menos una letra minúscula';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'debe contener al menos un número';
        }

        // Caracteres especiales permitidos: ! @ # $ % & * + - _
        if (!preg_match('/[!@#$%&*+\-_]/', $password)) {
            $errors[] = 'debe contener al menos un carácter especial (' . self::ALLOWED_SPECIAL . ')';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(
                'La contraseña no cumple la política de seguridad: ' . implode('; ', $errors) . '.'
            );
        }
    }

    /**
     * Devuelve la descripción de la política para mostrar al usuario.
     */
    public function description(): string
    {
        return sprintf(
            'Mínimo %d caracteres, al menos una mayúscula, una minúscula, un número y un carácter especial (%s).',
            self::MIN_LENGTH,
            self::ALLOWED_SPECIAL
        );
    }
}
