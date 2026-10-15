<?php

declare(strict_types=1);

namespace App\Support\Trait;

/**
 * Émission de toasts depuis un composant Livewire (TOAST_INSTRUCTION).
 *
 * Un seul événement navigateur porte les quatre types, ce qui évite quatre
 * écouteurs concurrents côté client. Les types sont exposés par des méthodes
 * nommées plutôt que par une chaîne libre : « succes » mal orthographié
 * quelque part produirait un toast sans style et sans erreur.
 */
trait InteractsWithToasts
{
    public function toastSuccess(string $message): void
    {
        $this->emettreToast('success', $message);
    }

    public function toastError(string $message): void
    {
        $this->emettreToast('error', $message);
    }

    public function toastWarning(string $message): void
    {
        $this->emettreToast('warning', $message);
    }

    public function toastInfo(string $message): void
    {
        $this->emettreToast('info', $message);
    }

    /**
     * Toast d'annulation : le Job de commit n'est dispatché qu'à l'expiration
     * de la fenêtre de 6 secondes.
     */
    public function toastUndo(string $message, string $evenement, int|string|null $id = null): void
    {
        $this->dispatch('toast', message: $message, type: 'info', undoEvent: $evenement, undoId: $id);
    }

    /**
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    private function emettreToast(string $type, string $message): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
