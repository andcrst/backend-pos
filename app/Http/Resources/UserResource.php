<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role;

        return [
            'id' => $this->id,
            // `nama` mengikuti nama kolom pada rancangan database PDF.
            'nama' => $this->name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $role instanceof UserRole ? $role->value : $role,
            'role_label' => $role instanceof UserRole ? $role->label() : null,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
