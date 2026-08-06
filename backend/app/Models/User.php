<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'two_factor_secret',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_user_id');
    }

    public function ownedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'owner_user_id');
    }

    public function getRoleName(): string
    {
        return ($this->role instanceof UserRole ? $this->role : UserRole::from($this->role))->value;
    }

    public function permissions(): array
    {
        return UserRole::permissionMap()[$this->getRoleName()] ?? [];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function canAccessAllRecords(): bool
    {
        return in_array($this->getRoleName(), [
            UserRole::Owner->value,
            UserRole::Admin->value,
            UserRole::Manager->value,
        ], true);
    }

    /**
     * TASK-013 (RN-02) — lucro, despesas e avaliação financeira de estoque
     * são exclusivos de quem tem `dashboard.financial.view` (owner/admin).
     * Gerente perdeu essa permissão nesta task; vendedor/garantia nunca a
     * tiveram.
     */
    public function canViewFinancialReports(): bool
    {
        return $this->hasPermission('dashboard.financial.view');
    }

    /**
     * O custo do produto (catálogo) é informação operacional — quem
     * cadastra/edita catálogo precisa dele pra fazer o próprio trabalho,
     * independentemente de ter acesso ao relatório financeiro (RN-02 não se
     * aplica aqui; só vendedor/garantia, que só têm `products.view`, não
     * veem custo).
     */
    public function canViewCatalogCost(): bool
    {
        return $this->hasPermission('products.create')
            || $this->hasPermission('products.update')
            || $this->canViewFinancialReports();
    }
}
