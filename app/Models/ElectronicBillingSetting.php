<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Services\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración de Facturación Electrónica (SUNAT · Perú) del colegio activo.
 * Las credenciales sensibles (Clave SOL, clave del certificado) se guardan
 * encriptadas en base de datos mediante los casts 'encrypted'.
 */
class ElectronicBillingSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'enabled', 'auto_emit', 'boletas_por_resumen', 'driver', 'environment',
        'ruc', 'razon_social', 'nombre_comercial', 'direccion_fiscal',
        'ubigeo', 'departamento', 'provincia', 'distrito', 'urbanizacion',
        'sol_user', 'sol_pass', 'certificate_path', 'certificate_password',
        'client_id', 'client_secret',
        'serie_factura', 'serie_boleta', 'serie_nc_factura', 'serie_nc_boleta',
        'igv_percent', 'moneda',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_emit' => 'boolean',
        'boletas_por_resumen' => 'boolean',
        'igv_percent' => 'decimal:2',
        'sol_user' => 'encrypted',
        'sol_pass' => 'encrypted',
        'certificate_password' => 'encrypted',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
    ];

    protected $attributes = [
        'driver' => 'none',
        'environment' => 'beta',
        'serie_factura' => 'F001',
        'serie_boleta' => 'B001',
        'serie_nc_factura' => 'FC01',
        'serie_nc_boleta' => 'BC01',
        'igv_percent' => 18.00,
        'moneda' => 'PEN',
    ];

    /**
     * Configuración del colegio activo (cacheada por colegio).
     * Si no existe, devuelve una instancia nueva sin persistir.
     */
    public static function current(): self
    {
        $tenancy = app(Tenancy::class);

        if (! $tenancy->check()) {
            return new static;
        }

        return Cache::rememberForever('billing_settings_'.$tenancy->id(), function () {
            return static::first() ?? new static;
        });
    }

    protected static function booted(): void
    {
        static::saved(function ($model) {
            if ($model->school_id) {
                Cache::forget('billing_settings_'.$model->school_id);
            }
        });
    }

    /** Endpoint del web service de SUNAT según el entorno configurado. */
    public function endpoint(): string
    {
        return $this->environment === 'produccion'
            ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService?wsdl'
            : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl';
    }

    /** ¿La emisión real está lista? (habilitada, con driver y datos mínimos). */
    public function isReady(): bool
    {
        return $this->enabled
            && $this->driver !== 'none'
            && filled($this->ruc)
            && filled($this->sol_user)
            && filled($this->sol_pass)
            && filled($this->certificate_path);
    }

    /** Nombre legible del entorno. */
    public function environmentLabel(): string
    {
        return $this->environment === 'produccion' ? 'Producción' : 'Beta (homologación)';
    }
}
