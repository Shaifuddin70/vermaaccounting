<?php

declare(strict_types=1);

/** @return list<array{type: string, message: string}> */
function admin_pull_toast_messages(): array
{
    $toasts = [];

    if (!empty($_SESSION['flash_success'])) {
        $toasts[] = ['type' => 'success', 'message' => (string) $_SESSION['flash_success']];
        unset($_SESSION['flash_success']);
    }

    if (!empty($_SESSION['flash_error'])) {
        $toasts[] = ['type' => 'error', 'message' => (string) $_SESSION['flash_error']];
        unset($_SESSION['flash_error']);
    }

    return $toasts;
}
