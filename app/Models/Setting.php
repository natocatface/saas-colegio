<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Services\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'school_name', 'logo', 'academic_year', 'active_period', 'currency',
        'address', 'phone', 'director', 'tuition_amount',
    ];

    /**
     * Configuración del colegio activo (cacheada por colegio).
     * Si no hay colegio activo (super-admin / páginas públicas) devuelve
     * la primera configuración existente o una instancia por defecto.
     */
    public static function current(): self
    {
        $tenancy = app(Tenancy::class);

        if (! $tenancy->check()) {
            // Páginas públicas o super-admin: marca genérica de la plataforma
            return new static([
                'school_name' => 'Colegio SaaS',
                'currency' => 'Bs',
                'academic_year' => date('Y'),
                'active_period' => '1er Trimestre',
            ]);
        }

        return Cache::rememberForever('app_settings_'.$tenancy->id(), function () {
            return static::first() ?? static::create([]);
        });
    }

    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget('app_settings_'.$setting->school_id);
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/'.$this->logo) : null;
    }

    /**
     * Logo como Data URI base64 (fiable para incrustar en PDFs con dompdf).
     */
    public function getLogoBase64Attribute(): ?string
    {
        if (! $this->logo || ! Storage::disk('public')->exists($this->logo)) {
            return null;
        }

        $ext = strtolower(pathinfo($this->logo, PATHINFO_EXTENSION));
        $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
        $data = base64_encode(Storage::disk('public')->get($this->logo));

        return "data:{$mime};base64,{$data}";
    }
}
